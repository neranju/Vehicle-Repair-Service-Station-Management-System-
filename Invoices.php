<?php
// Invoices.php - Invoices Dashboard Page
require_once 'db.php';

// Check Admin, Receptionist or Mechanic role session authentication
if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Receptionist' && $_SESSION['role'] !== 'Mechanic')) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'];
$role = $_SESSION['role'];

// Fetch invoices from database joined with customer and vehicle details
$invoices_query = "SELECT i.*, j.vehicle_id, v.reg_no, c.name AS customer_name, m.name AS mechanic_name 
                   FROM invoices i
                   LEFT JOIN job_cards j ON i.job_id = j.job_id
                   LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
                   LEFT JOIN customers c ON j.customer_id = c.customer_id
                   LEFT JOIN staff m ON j.mechanic_id = m.staff_id
                   ORDER BY i.id DESC";
$invoices_result = $conn->query($invoices_query);

// Invoice detail view (printable)
$view_invoice = trim($_GET['view_invoice'] ?? '');
$auto_print = isset($_GET['autoprint']) && $_GET['autoprint'] === '1';
$invoice_error = '';
$invoice_message = '';
if (isset($_GET['updated'])) {
    $invoice_message = 'Invoice updated successfully.';
}
if (isset($_GET['deleted'])) {
    $invoice_message = 'Invoice deleted successfully.';
}
$invoice_detail = null;
$invoice_items = [];
if ($view_invoice !== '') {
    $s = $conn->prepare("SELECT i.*, j.vehicle_id, v.reg_no, c.name AS customer_name, m.name AS mechanic_name, j.job_id AS job_ref, j.problem_description, j.date_in, j.date_out, st.service_name AS service_type, pm.method_name AS payment_method FROM invoices i LEFT JOIN job_cards j ON i.job_id = j.job_id LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id LEFT JOIN customers c ON j.customer_id = c.customer_id LEFT JOIN staff m ON j.mechanic_id = m.staff_id LEFT JOIN service_types st ON j.service_type_id = st.service_type_id LEFT JOIN payment_methods pm ON i.method_id = pm.method_id WHERE i.invoice_id = ? LIMIT 1");
    if ($s) {
        $s->bind_param('s', $view_invoice);
        $s->execute();
        $res = $s->get_result();
        $invoice_detail = $res->fetch_assoc();
        $s->close();
    }

    if ($invoice_detail) {
        // fetch parts used for the related job
        $job_id = $invoice_detail['job_id'];
        $pi = $conn->prepare("SELECT jp.quantity_used, jp.price_at_time, p.part_name FROM jobcard_parts jp JOIN parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
        if ($pi) {
            $pi->bind_param('s', $job_id);
            $pi->execute();
            $r = $pi->get_result();
            while ($row = $r->fetch_assoc()) {
                $invoice_items[] = $row;
            }
            $pi->close();
        }
    }
}

$selected_job = trim($_GET['selected_job'] ?? '');
$preview_open = !empty($selected_job) || isset($_GET['preview']);
$job_preview = null;
$preview_items = [];
$preview_parts_total = 0.00;
$preview_labour = 0.00;
$preview_sub_total = 0.00;
if ($selected_job !== '') {
    $job_stmt = $conn->prepare("SELECT j.job_id, j.problem_description, j.date_in, j.date_out, j.labour_charge, v.reg_no, c.name AS customer_name, m.name AS mechanic_name, st.service_name AS service_type FROM job_cards j LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id LEFT JOIN customers c ON j.customer_id = c.customer_id LEFT JOIN staff m ON j.mechanic_id = m.staff_id LEFT JOIN service_types st ON j.service_type_id = st.service_type_id WHERE j.job_id = ? LIMIT 1");
    if ($job_stmt) {
        $job_stmt->bind_param('s', $selected_job);
        $job_stmt->execute();
        $result = $job_stmt->get_result();
        $job_preview = $result->fetch_assoc();
        $job_stmt->close();
    }
    if ($job_preview) {
        $pi = $conn->prepare("SELECT jp.quantity_used, jp.price_at_time, p.part_name FROM jobcard_parts jp JOIN parts p ON jp.part_id = p.part_id WHERE jp.job_id = ?");
        if ($pi) {
            $pi->bind_param('s', $selected_job);
            $pi->execute();
            $res = $pi->get_result();
            while ($row = $res->fetch_assoc()) {
                $preview_items[] = $row;
                $preview_parts_total += $row['quantity_used'] * $row['price_at_time'];
            }
            $pi->close();
        }
        $preview_labour = (float) $job_preview['labour_charge'];
        $preview_sub_total = $preview_parts_total + $preview_labour;
    }
}

// Fetch jobs that can be invoiced (jobs without an invoice yet)
$jobs_for_invoice = [];
$jres = $conn->query("SELECT j.job_id, v.reg_no, c.name AS customer_name FROM job_cards j LEFT JOIN vehicles v ON j.vehicle_id=v.vehicle_id LEFT JOIN customers c ON j.customer_id=c.customer_id WHERE NOT EXISTS (SELECT 1 FROM invoices i WHERE i.job_id = j.job_id) ORDER BY j.date_in DESC");
if ($jres) {
    while ($r = $jres->fetch_assoc()) {
        $jobs_for_invoice[] = $r;
    }
}

// registration lookup removed; job selection handled via dropdown or preview links

// Fetch payment methods
$payment_methods = [];
$pmr = $conn->query("SELECT method_id, method_name FROM payment_methods ORDER BY method_name");
if ($pmr) {
    while ($p = $pmr->fetch_assoc()) {
        $payment_methods[] = $p;
    }
}

// Fetch available parts for invoice line items
$available_parts = [];
$parts_res = $conn->query("SELECT part_id, part_name, qty_in_stock, unit_price FROM parts ORDER BY part_name");
if ($parts_res) {
    while ($p = $parts_res->fetch_assoc()) {
        $available_parts[] = $p;
    }
}

// Handle invoice creation
// Create invoice directly from selected job (no new parts) and auto-print
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_from_job'])) {
    $job_id = trim($_POST['job_id'] ?? '');
    $method_id = trim($_POST['method_id'] ?? '');
    if ($method_id === '') {
        $method_id = null;
    }
    $discount = 0.00;
    $tax = 0.00;
    if ($job_id === '') {
        $invoice_error = 'Job id required to create invoice.';
    } else {
        // compute parts subtotal
        $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity_used * price_at_time),0) AS parts_total FROM jobcard_parts WHERE job_id = ?");
        $parts_total = 0.00;
        if ($stmt) {
            $stmt->bind_param('s', $job_id);
            $stmt->execute();
            $stmt->bind_result($parts_total);
            $stmt->fetch();
            $stmt->close();
        }
        // labour
        $labour = 0.00;
        $lr = $conn->prepare("SELECT labour_charge FROM job_cards WHERE job_id = ? LIMIT 1");
        if ($lr) {
            $lr->bind_param('s', $job_id);
            $lr->execute();
            $lr->bind_result($labour);
            $lr->fetch();
            $lr->close();
        }

        $sub_total = (float) $parts_total + (float) $labour;
        $total_amount = $sub_total - $discount + $tax;

        $idr = $conn->query("SELECT MAX(id) AS max_id FROM invoices");
        $next = 1;
        if ($idr && $rr = $idr->fetch_assoc()) {
            $next = ((int) $rr['max_id']) + 1;
        }
        $invoice_code = sprintf('INV%03d', $next);

        $ins = $conn->prepare("INSERT INTO invoices (id, invoice_id, job_id, sub_total, discount, tax, total_amount, paid_status, method_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?)");
        if ($ins) {
            $ins->bind_param('issdddds', $next, $invoice_code, $job_id, $sub_total, $discount, $tax, $total_amount, $method_id);
            if ($ins->execute()) {
                header('Location: Invoices.php?view_invoice=' . urlencode($invoice_code) . '&autoprint=1');
                exit();
            } else {
                $invoice_error = 'Failed to create invoice: ' . $ins->error;
            }
            $ins->close();
        } else {
            $invoice_error = 'Failed preparing invoice insert: ' . $conn->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_invoice'])) {
    $job_id = trim($_POST['job_id'] ?? '');
    // Discount and tax removed — keep zeroed for totals
    $discount = 0.00;
    $tax = 0.00;
    $method_id = trim($_POST['method_id'] ?? '');
    if ($method_id === '') {
        $method_id = null;
    }
    $invoice_part_ids = $_POST['part_id'] ?? [];
    $invoice_part_qtys = $_POST['part_quantity'] ?? [];
    $invoice_part_prices = $_POST['part_price'] ?? [];
    $invoice_parts = [];
    foreach ($invoice_part_ids as $index => $raw_part_id) {
        $part_id = trim($raw_part_id);
        if ($part_id === '') {
            continue;
        }
        $qty = (int) trim($invoice_part_qtys[$index] ?? 0);
        $price = (float) trim($invoice_part_prices[$index] ?? 0);
        if ($qty <= 0) {
            $invoice_error = 'Please enter a valid qty for each selected part.';
            break;
        }
        $invoice_parts[] = [
            'part_id' => $part_id,
            'quantity' => $qty,
            'price' => $price,
        ];
    }
    $add_invoice_part = !empty($invoice_parts);

    if ($job_id === '') {
        $invoice_error = 'Please select a job to invoice.';
    } else {
        // add invoice parts to the job if requested
        if ($add_invoice_part) {
            $check = null;
            $ins_part = null;
            $upd_stock = null;
            $conn->begin_transaction();
            try {
                $check = $conn->prepare("SELECT qty_in_stock, unit_price FROM parts WHERE part_id = ? LIMIT 1");
                if (!$check) {
                    throw new Exception('Failed to prepare stock lookup: ' . $conn->error);
                }
                $ins_part = $conn->prepare("INSERT INTO jobcard_parts (job_part_id, job_id, part_id, quantity_used, price_at_time) VALUES (?, ?, ?, ?, ?)");
                if (!$ins_part) {
                    throw new Exception('Failed to prepare part insert: ' . $conn->error);
                }
                $upd_stock = $conn->prepare("UPDATE parts SET qty_in_stock = qty_in_stock - ? WHERE part_id = ?");
                if (!$upd_stock) {
                    throw new Exception('Failed to prepare stock update: ' . $conn->error);
                }
                $r = $conn->query("SELECT MAX(id) AS max_id FROM jobcard_parts");
                $nextjp = 1;
                if ($r && $rr = $r->fetch_assoc()) {
                    $nextjp = ((int) $rr['max_id']) + 1;
                }
                if ($r) {
                    $r->close();
                }
                foreach ($invoice_parts as $invoice_part) {
                    $part_id = $invoice_part['part_id'];
                    $invoice_part_qty = $invoice_part['quantity'];
                    $invoice_part_price = $invoice_part['price'];
                    $check->bind_param('s', $part_id);
                    $check->execute();
                    $check->bind_result($stock_qty, $unit_price);
                    if (!$check->fetch()) {
                        throw new Exception('Selected part not found: ' . $part_id);
                    }
                    if ($stock_qty < $invoice_part_qty) {
                        throw new Exception('Not enough stock for selected part ' . $part_id . '. Available: ' . $stock_qty);
                    }
                    if ($invoice_part_price <= 0) {
                        $invoice_part_price = $unit_price;
                    }
                    $check->free_result();
                    $job_part_code = sprintf('JP%03d', $nextjp);
                    $nextjp++;
                    $ins_part->bind_param('sssid', $job_part_code, $job_id, $part_id, $invoice_part_qty, $invoice_part_price);
                    if (!$ins_part->execute()) {
                        throw new Exception('Failed to add invoice part: ' . $ins_part->error);
                    }
                    $upd_stock->bind_param('is', $invoice_part_qty, $part_id);
                    if (!$upd_stock->execute()) {
                        throw new Exception('Stock update failed: ' . $upd_stock->error);
                    }
                }
                $check->close();
                $check = null;
                $ins_part->close();
                $ins_part = null;
                $upd_stock->close();
                $upd_stock = null;
            } catch (Exception $e) {
                if ($check !== null) {
                    @$check->free_result();
                    $check->close();
                }
                if ($ins_part !== null) {
                    $ins_part->close();
                }
                if ($upd_stock !== null) {
                    $upd_stock->close();
                }
                $conn->rollback();
                $invoice_error = 'Failed to add invoice parts: ' . $e->getMessage();
            }
        }

        // compute parts subtotal (including any newly added invoice part)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(quantity_used * price_at_time),0) AS parts_total FROM jobcard_parts WHERE job_id = ?");
        $parts_total = 0.00;
        if ($stmt) {
            $stmt->bind_param('s', $job_id);
            $stmt->execute();
            $stmt->bind_result($parts_total);
            $stmt->fetch();
            $stmt->close();
        }

        // get labour_charge from job_cards (if present)
        $labour = 0.00;
        $lr = $conn->prepare("SELECT labour_charge FROM job_cards WHERE job_id = ? LIMIT 1");
        if ($lr) {
            $lr->bind_param('s', $job_id);
            $lr->execute();
            $lr->bind_result($labour);
            $lr->fetch();
            $lr->close();
        }

        $sub_total = (float) $parts_total + (float) $labour;
        $total_amount = $sub_total - $discount + $tax;

        // generate invoice id
        $idr = $conn->query("SELECT MAX(id) AS max_id FROM invoices");
        $next = 1;
        if ($idr && $rr = $idr->fetch_assoc()) {
            $next = ((int) $rr['max_id']) + 1;
        }
        $invoice_code = sprintf('INV%03d', $next);

        if (empty($invoice_error)) {
            $ins = $conn->prepare("INSERT INTO invoices (id, invoice_id, job_id, sub_total, discount, tax, total_amount, paid_status, method_id) VALUES (?, ?, ?, ?, ?, ?, ?, 'Unpaid', ?)");
            if ($ins) {
                $ins->bind_param('issdddds', $next, $invoice_code, $job_id, $sub_total, $discount, $tax, $total_amount, $method_id);
                if ($ins->execute()) {
                    if ($add_invoice_part) {
                        $conn->commit();
                    }
                    header('Location: Invoices.php?view_invoice=' . urlencode($invoice_code));
                    exit();
                } else {
                    if ($add_invoice_part) {
                        $conn->rollback();
                    }
                    $invoice_error = 'Failed to create invoice: ' . $ins->error;
                }
                $ins->close();
            } else {
                if ($add_invoice_part) {
                    $conn->rollback();
                }
                $invoice_error = 'Failed preparing invoice insert: ' . $conn->error;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_invoice'])) {
    $invoice_id = trim($_POST['invoice_id'] ?? '');
    // Discount and tax are no longer editable — keep zeroed
    $discount = 0.00;
    $tax = 0.00;
    $paid_status = trim($_POST['paid_status'] ?? 'Unpaid');
    $method_id = trim($_POST['method_id'] ?? '');
    if ($method_id === '') {
        $method_id = null;
    }

    if ($invoice_id === '') {
        $invoice_error = 'Invoice ID is required to update.';
    } else {
        $sr = $conn->prepare("SELECT sub_total FROM invoices WHERE invoice_id = ? LIMIT 1");
        if ($sr) {
            $sr->bind_param('s', $invoice_id);
            $sr->execute();
            $sr->bind_result($sub_total);
            if ($sr->fetch()) {
                $sr->close();
                $total_amount = (float) $sub_total - $discount + $tax;
                $up = $conn->prepare("UPDATE invoices SET discount = ?, tax = ?, total_amount = ?, paid_status = ?, method_id = ? WHERE invoice_id = ?");
                if ($up) {
                    $up->bind_param('dddsss', $discount, $tax, $total_amount, $paid_status, $method_id, $invoice_id);
                    if ($up->execute()) {
                        header('Location: Invoices.php?view_invoice=' . urlencode($invoice_id) . '&updated=1');
                        exit();
                    } else {
                        $invoice_error = 'Failed to update invoice: ' . $up->error;
                    }
                    $up->close();
                } else {
                    $invoice_error = 'Failed preparing invoice update: ' . $conn->error;
                }
            } else {
                $sr->close();
                $invoice_error = 'Invoice not found for update.';
            }
        } else {
            $invoice_error = 'Failed preparing invoice lookup: ' . $conn->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    $invoice_id = trim($_POST['invoice_id'] ?? '');
    if ($invoice_id === '') {
        $invoice_error = 'Invoice ID is required to delete.';
    } else {
        $del = $conn->prepare("DELETE FROM invoices WHERE invoice_id = ?");
        if ($del) {
            $del->bind_param('s', $invoice_id);
            if ($del->execute()) {
                header('Location: Invoices.php?deleted=1');
                exit();
            } else {
                $invoice_error = 'Failed to delete invoice: ' . $del->error;
            }
            $del->close();
        } else {
            $invoice_error = 'Failed preparing invoice delete: ' . $conn->error;
        }
    }
}

// Print preview stand-alone view
if (isset($_GET['print_preview']) && $invoice_detail) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Print Invoice - <?php echo htmlspecialchars($invoice_detail['invoice_id']); ?></title>
        <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
        <script src="https://unpkg.com/lucide@latest"></script>
        <style>
            body {
                background: #f1f5f9;
                color: #0f172a;
                font-family: 'Outfit', sans-serif;
                margin: 0;
                padding: 40px 20px;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .no-print-actions {
                width: 100%;
                max-width: 800px;
                display: flex;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .btn-action {
                padding: 10px 20px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 0.95rem;
                transition: all 0.2s ease;
            }

            .btn-back {
                background: #ffffff;
                border: 1px solid #cbd5e1;
                color: #334155;
            }

            .btn-back:hover {
                background: #f8fafc;
            }

            .btn-print {
                background: #0f172a;
                color: #ffffff;
                border: none;
            }

            .btn-print:hover {
                background: #1e293b;
            }

            .print-preview-container {
                width: 100%;
                max-width: 800px;
                background: #ffffff;
                padding: 40px;
                border-radius: 16px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
                border: 1px solid #e2e8f0;
                box-sizing: border-box;
            }

            .invoice-header {
                display: flex;
                justify-content: space-between;
                border-bottom: 2px solid #f1f5f9;
                padding-bottom: 20px;
                margin-bottom: 20px;
            }

            .invoice-header h1 {
                margin: 0 0 10px 0;
                font-size: 2.2rem;
                font-weight: 700;
            }

            .invoice-header p {
                margin: 0;
                color: #475569;
                line-height: 1.5;
            }

            .invoice-title {
                text-align: right;
            }

            .invoice-title h2 {
                margin: 0 0 10px 0;
                font-size: 1.8rem;
                color: #0f172a;
            }

            .details-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 40px;
                margin-bottom: 30px;
            }

            .details-block h4 {
                margin: 0 0 10px 0;
                font-size: 1.1rem;
                color: #0f172a;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 5px;
            }

            .details-block p {
                margin: 5px 0;
                color: #475569;
                line-height: 1.4;
            }

            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 30px;
            }

            .items-table th {
                background: #f8fafc;
                text-align: left;
                padding: 12px 16px;
                font-weight: 600;
                color: #334155;
                border-bottom: 2px solid #e2e8f0;
            }

            .items-table td {
                padding: 14px 16px;
                border-bottom: 1px solid #f1f5f9;
                color: #475569;
            }

            .summary-section {
                display: flex;
                justify-content: flex-end;
                margin-bottom: 30px;
            }

            .summary-table {
                width: 300px;
                border-collapse: collapse;
            }

            .summary-table td {
                padding: 10px 12px;
                color: #475569;
            }

            .summary-table tr.total-row td {
                font-weight: 700;
                color: #0f172a;
                font-size: 1.2rem;
                border-top: 2px solid #e2e8f0;
                padding-top: 15px;
            }

            .invoice-footer {
                display: flex;
                justify-content: space-between;
                border-top: 2px solid #f1f5f9;
                padding-top: 25px;
                margin-top: 20px;
                font-size: 0.9rem;
            }

            .invoice-footer p {
                margin: 0;
                color: #64748b;
            }

            @media print {
                body {
                    background: #ffffff;
                    padding: 0;
                    margin: 0;
                }

                .print-preview-container {
                    border: none;
                    box-shadow: none;
                    padding: 0;
                    max-width: 100%;
                }

                .no-print-actions {
                    display: none !important;
                }

                .items-table th {
                    background: #f8fafc !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
            }
        </style>
    </head>

    <body>
        <div class="no-print-actions">
            <a href="Invoices.php?view_invoice=<?php echo urlencode($invoice_detail['invoice_id']); ?>"
                class="btn-action btn-back">
                <i data-lucide="arrow-left"></i> Back to Invoice Details
            </a>
            <button onclick="window.print();" class="btn-action btn-print">
                <i data-lucide="printer"></i> Print Invoice
            </button>
        </div>
        <div class="print-preview-container">
            <div class="invoice-header">
                <div>
                    <h1>BikeFix Garage</h1>
                    <p>No. 12, Main Road,Aluthgama<br>Phone: 034-7812564<br>Email:bikefix@gmail.com</p>
                </div>
                <div class="invoice-title">
                    <p
                        style="margin:0 0 5px 0; text-transform:uppercase; font-size:0.85rem; font-weight:600; letter-spacing:1px; color:#64748b;">
                        Invoice</p>
                    <h2><?php echo htmlspecialchars($invoice_detail['invoice_id']); ?></h2>
                    <p>Date: <?php echo htmlspecialchars(date('Y-m-d', strtotime($invoice_detail['invoice_date']))); ?></p>
                </div>
            </div>

            <div class="details-grid">
                <div class="details-block">
                    <h4>Billed To</h4>
                    <p><strong><?php echo htmlspecialchars($invoice_detail['customer_name']); ?></strong></p>
                    <p>Vehicle Reg No: <?php echo htmlspecialchars($invoice_detail['reg_no']); ?></p>
                    <p>Job Card ID: <?php echo htmlspecialchars($invoice_detail['job_ref']); ?></p>
                </div>
                <div class="details-block">
                    <h4>Job Details</h4>
                    <p>Service: <?php echo htmlspecialchars($invoice_detail['service_type'] ?: 'N/A'); ?></p>
                    <p>Mechanic: <?php echo htmlspecialchars($invoice_detail['mechanic_name']); ?></p>
                    <p>Status: <?php echo htmlspecialchars($invoice_detail['paid_status']); ?></p>
                </div>
            </div>

            <table class="items-table">
                <thead>
                    <tr>
                        <th>Part / Service</th>
                        <th style="text-align: center; width: 80px;">Qty</th>
                        <th style="text-align: right; width: 120px;">Unit Price</th>
                        <th style="text-align: right; width: 120px;">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoice_items as $it): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($it['part_name']); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($it['quantity_used']); ?></td>
                            <td style="text-align: right;">LKR
                                <?php echo htmlspecialchars(number_format((float) $it['price_at_time'], 2)); ?>
                            </td>
                            <td style="text-align: right;">LKR
                                <?php echo htmlspecialchars(number_format((float) $it['price_at_time'] * (int) $it['quantity_used'], 2)); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php
                    $labour_line = 0.00;
                    if (!empty($invoice_detail['sub_total'])) {
                        $lj = $conn->prepare("SELECT labour_charge FROM job_cards WHERE job_id = ? LIMIT 1");
                        if ($lj) {
                            $lj->bind_param('s', $invoice_detail['job_id']);
                            $lj->execute();
                            $lj->bind_result($labour_line);
                            $lj->fetch();
                            $lj->close();
                        }
                    }
                    ?>
                    <?php if ($labour_line > 0): ?>
                        <tr>
                            <td>Labour / Service Charge</td>
                            <td style="text-align: center;">1</td>
                            <td style="text-align: right;">LKR
                                <?php echo htmlspecialchars(number_format((float) $labour_line, 2)); ?>
                            </td>
                            <td style="text-align: right;">LKR
                                <?php echo htmlspecialchars(number_format((float) $labour_line, 2)); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="summary-section">
                <table class="summary-table">
                    <tr>
                        <td>Sub Total</td>
                        <td style="text-align: right;">LKR <?php echo number_format($invoice_detail['sub_total'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Discount</td>
                        <td style="text-align: right;">- LKR <?php echo number_format($invoice_detail['discount'], 2); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Tax</td>
                        <td style="text-align: right;">+ LKR <?php echo number_format($invoice_detail['tax'], 2); ?></td>
                    </tr>
                    <tr class="total-row">
                        <td>Total Amount</td>
                        <td style="text-align: right;">LKR <?php echo number_format($invoice_detail['total_amount'], 2); ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div class="invoice-footer">
                <div>
                    <p style="font-weight: 600; margin-bottom: 5px; color: #475569;">Payment Method</p>
                    <p><?php echo htmlspecialchars($invoice_detail['payment_method'] ?: 'Cash'); ?></p>
                </div>
                <div style="text-align: right;">
                    <p style="font-weight: 600; margin-bottom: 5px; color: #475569;">Prepared By</p>
                    <p><?php echo htmlspecialchars($invoice_detail['mechanic_name'] ?: 'Authorized Personnel'); ?></p>
                </div>
            </div>
        </div>
        <script>
            lucide.createIcons();
            // Automatically trigger browser print dialog
            window.onload = function () {
                setTimeout(function () {
                    window.print();
                }, 300);
            };
        </script>
    </body>

    </html>
    <?php
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices | BikeFix</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="CSS/dashboard.css">
    <style>
        @media print {
            body {
                background: #fff;
                color: #000;
            }

            .dashboard-layout,
            .sidebar,
            .top-header,
            .search-bar,
            .notification-bell,
            .user-profile,
            .table-header,
            #newInvoice,
            .dashboard-footer,
            .logout-btn,
            .table-action,
            button.btn-primary,
            a.btn-primary {
                display: none !important;
            }

            .content-wrapper {
                margin-left: 0 !important;
                width: 100% !important;
            }

            .main-content,
            .content-header,
            .table-section {
                padding: 0 !important;
                margin: 0 !important;
                background: transparent !important;
            }

            .invoice-card {
                border: none !important;
                box-shadow: none !important;
                background: transparent !important;
            }

            .invoice-card h1,
            .invoice-card h2,
            .invoice-card h4,
            .invoice-card p,
            .invoice-card td,
            .invoice-card th {
                color: #000 !important;
            }

            .invoice-card table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            .invoice-card table,
            .invoice-card th,
            .invoice-card td {
                border: 1px solid #000 !important;
            }

            .invoice-card th,
            .invoice-card td {
                padding: 10px !important;
            }

            .table-responsive {
                overflow: visible !important;
            }
        }
    </style>
</head>

<body>

    <div class="dashboard-layout">
        <!-- Fixed Left Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <img src="assets/logo.png" alt="Logo" class="sidebar-logo">
                <span class="sidebar-brand">BikeFix <span class="accent"></span></span>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <?php if ($role === 'Admin'): ?>
                        <li class="">
                            <a href="dashboard.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php elseif ($role === 'Mechanic'): ?>
                        <li class="">
                            <a href="Mechanic.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php else: ?>
                        <li class="">
                            <a href="Receptionist.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Receptionist'): ?>
                        <li class="">
                            <a href="customer.php"><i data-lucide="users"></i> <span>Customer</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin'): ?>
                        <li class="">
                            <a href="Staff.php"><i data-lucide="user-check"></i> <span>Staff</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Mechanic'): ?>
                        <li class="">
                            <a href="Store.php"><i data-lucide="package"></i> <span>Store</span></a>
                        </li>
                    <?php endif; ?>

                    <li class="active">
                        <a href="Invoices.php"><i data-lucide="receipt"></i> <span>Invoices</span></a>
                    </li>
                    
                    <?php if ($role === 'Admin' || $role === 'Mechanic'|| $role === 'Receptionist'): ?>
                    <li class="">
                        <a href="Job_cad.php"><i data-lucide="file-text"></i> <span>Job Card</span></a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin"<?php echo htmlspecialchars($role); ?>"
                        alt="<?php echo htmlspecialchars($role); ?>" class="user-avatar-sm">
                    <div class="user-details">
                        <span class="user-name">
                            <?php echo htmlspecialchars($user_name); ?>
                        </span>
                        <span class="user-role">
                            <?php echo htmlspecialchars($role); ?>
                        </span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"
                    style="color: inherit; display: flex; align-items: center;"><i data-lucide="log-out"></i></a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <div class="content-wrapper">
            <!-- Top Header -->
            <header class="top-header">
                <div class="header-left">
                    <button id="sidebarToggle" class="mobile-toggle">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="search-bar">
                        <i data-lucide="search"></i>
                        <input type="text" placeholder="Search invoices...">
                    </div>
                </div>

                <div class="header-right">
                    <div class="notification-bell">
                        <i data-lucide="bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <span class="welcome-text">Welcome, <strong>
                                <?php echo htmlspecialchars($user_name); ?>
                            </strong></span>
                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Profile"
                            class="user-avatar">
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="main-content">
                <div class="content-header">
                    <h2>Invoices Dashboard</h2>
                    <p>Audit and check billing transaction balances</p>
                </div>

                <?php if ($invoice_detail): ?>
                    <div class="table-section invoice-card"
                        style="margin-bottom:18px; padding:24px; background:#fff; border-radius:16px; color:#0f172a;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:start; gap:20px; flex-wrap:wrap;">
                            <div>
                                <h1 style="margin:0; font-size:1.8rem;">BikeFix Garage</h1>
                                <p style="margin:6px 0 0; color:#475569;">No. 12, Main Road, Colombo<br>Phone:
                                    011-1234567<br>Email: info@bikefix.lk</p>
                            </div>
                            <div style="text-align:right;">
                                <p style="margin:0; font-size:0.9rem; color:#475569;">Invoice</p>
                                <h2 style="margin:4px 0 0;"><?php echo htmlspecialchars($invoice_detail['invoice_id']); ?>
                                </h2>
                                <p style="margin:6px 0 0; color:#475569;">Date:
                                    <?php echo htmlspecialchars(date('Y-m-d', strtotime($invoice_detail['invoice_date']))); ?>
                                </p>
                                <a href="Invoices.php?view_invoice=<?php echo urlencode($invoice_detail['invoice_id']); ?>&print_preview=1"
                                    class="btn-primary"
                                    style="margin-top:10px; display:inline-block; text-decoration:none; text-align:center;">Print
                                    Preview</a>
                            </div>
                        </div>
                        <?php if (!empty($invoice_message)): ?>
                            <div
                                style="margin-top:16px; padding:12px; border:1px solid #d1fae5; background:#ecfdf5; color:#166534; border-radius:10px;">
                                <?php echo htmlspecialchars($invoice_message); ?>
                            </div><?php endif; ?>
                        <?php if (!empty($invoice_error)): ?>
                            <div
                                style="margin-top:16px; padding:12px; border:1px solid #fee2e2; background:#fef2f2; color:#991b1b; border-radius:10px;">
                                <?php echo htmlspecialchars($invoice_error); ?>
                            </div><?php endif; ?>
                        <div
                            style="margin-top:18px; padding:18px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#0f172a;">
                            <form method="post"
                                action="Invoices.php?view_invoice=<?php echo urlencode($invoice_detail['invoice_id']); ?>">
                                <input type="hidden" name="invoice_id"
                                    value="<?php echo htmlspecialchars($invoice_detail['invoice_id']); ?>">
                                <div style="display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:12px;">
                                    <!-- Discount and Tax removed per settings -->
                                    <div>
                                        <label for="paid_status">Status</label>
                                        <select id="paid_status" name="paid_status">
                                            <option value="Unpaid" <?php echo ($invoice_detail['paid_status'] === 'Unpaid') ? 'selected' : ''; ?>>Unpaid</option>
                                            <option value="Paid" <?php echo ($invoice_detail['paid_status'] === 'Paid') ? 'selected' : ''; ?>>Paid</option>
                                            <option value="Partial" <?php echo ($invoice_detail['paid_status'] === 'Partial') ? 'selected' : ''; ?>>Partial</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="method_id">Payment Method</label>
                                        <select id="method_id" name="method_id">
                                            <option value="" <?php echo empty($invoice_detail['method_id']) ? 'selected' : ''; ?>>None</option>
                                            <?php foreach ($payment_methods as $pm): ?>
                                                <option value="<?php echo htmlspecialchars($pm['method_id']); ?>" <?php echo ($invoice_detail['method_id'] === $pm['method_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($pm['method_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div
                                    style="margin-top:16px; display:flex; gap:10px; flex-wrap:wrap; justify-content:flex-end;">
                                    <button type="submit" name="update_invoice" class="btn-primary">Update Invoice</button>
                                    <button type="submit" name="delete_invoice" class="btn-danger"
                                        onclick="return confirm('Delete this invoice? This cannot be undone.');">Delete
                                        Invoice</button>
                                </div>
                            </form>
                        </div>
                        <div
                            style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:20px; margin-top:22px;">
                            <div>
                                <h4 style="margin-bottom:8px;">Billed To</h4>
                                <p style="margin:0; font-weight:600;">
                                    <?php echo htmlspecialchars($invoice_detail['customer_name']); ?>
                                </p>
                                <p style="margin:3px 0 0; color:#475569;">Vehicle:
                                    <?php echo htmlspecialchars($invoice_detail['reg_no']); ?>
                                </p>
                                <p style="margin:3px 0 0; color:#475569;">Job Card:
                                    <?php echo htmlspecialchars($invoice_detail['job_ref']); ?>
                                </p>
                            </div>
                            <div>
                                <h4 style="margin-bottom:8px;">Job Details</h4>
                                <p style="margin:3px 0 0; color:#475569;">Service:
                                    <?php echo htmlspecialchars($invoice_detail['service_type'] ?: 'N/A'); ?>
                                </p>
                                <p style="margin:3px 0 0; color:#475569;">Mechanic:
                                    <?php echo htmlspecialchars($invoice_detail['mechanic_name']); ?>
                                </p>
                                <p style="margin:3px 0 0; color:#475569;">Status:
                                    <?php echo htmlspecialchars($invoice_detail['paid_status']); ?>
                                </p>
                            </div>
                        </div>

                        <div style="margin-top:24px;">
                            <div class="table-responsive">
                                <table class="modern-table invoice-items">
                                    <thead>
                                        <tr>
                                            <th>Part / Service</th>
                                            <th>Qty</th>
                                            <th>Unit Price</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($invoice_items as $it): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($it['part_name']); ?></td>
                                                <td><?php echo htmlspecialchars($it['quantity_used']); ?></td>
                                                <td><?php echo htmlspecialchars(number_format((float) $it['price_at_time'], 2)); ?>
                                                </td>
                                                <td><?php echo htmlspecialchars(number_format((float) $it['price_at_time'] * (int) $it['quantity_used'], 2)); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <?php
                                        $labour_line = 0.00;
                                        if (!empty($invoice_detail['sub_total'])) {
                                            $lj = $conn->prepare("SELECT labour_charge FROM job_cards WHERE job_id = ? LIMIT 1");
                                            if ($lj) {
                                                $lj->bind_param('s', $invoice_detail['job_id']);
                                                $lj->execute();
                                                $lj->bind_result($labour_line);
                                                $lj->fetch();
                                                $lj->close();
                                            }
                                        }
                                        ?>
                                        <?php if ($labour_line > 0): ?>
                                            <tr>
                                                <td>Labour</td>
                                                <td>1</td>
                                                <td><?php echo htmlspecialchars(number_format((float) $labour_line, 2)); ?></td>
                                                <td><?php echo htmlspecialchars(number_format((float) $labour_line, 2)); ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align:right;">Sub Total</td>
                                            <td>LKR <?php echo number_format($invoice_detail['sub_total'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="text-align:right;">Discount</td>
                                            <td>- LKR <?php echo number_format($invoice_detail['discount'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="text-align:right;">Tax</td>
                                            <td>+ LKR <?php echo number_format($invoice_detail['tax'], 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="text-align:right; font-weight:700;">Total</td>
                                            <td style="font-weight:700;">LKR
                                                <?php echo number_format($invoice_detail['total_amount'], 2); ?>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td colspan="3" style="text-align:right;">Payment Method</td>
                                            <td><?php echo htmlspecialchars($invoice_detail['payment_method'] ?: 'Cash'); ?>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div
                            style="margin-top:22px; display:flex; justify-content:space-between; flex-wrap:wrap; gap:16px;">
                            <div style="max-width:60%; color:#475569;">
                                <p style="margin:0 0 4px; font-weight:600;">Note</p>
                                <p style="margin:0;">Thank you for your business. Please pay within 7 days.</p>
                            </div>
                            <div style="text-align:right;">
                                <p style="margin:0; color:#475569;">Prepared by</p>
                                <p style="margin:6px 0 0; font-weight:600;"><?php echo htmlspecialchars($user_name); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Invoices Table -->
                <div class="table-section">
                    <div class="table-header"
                        style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <h3>Transaction Invoices</h3>
                        <button id="showInvoiceForm" class="btn-primary">Create Invoice</button>
                    </div>

                    <div id="newInvoice"
                        style="display:<?php echo $preview_open ? 'block' : 'none'; ?>; margin:18px 0; padding:18px; border:1px solid #e6edf3; border-radius:12px; background:#fff; color:#0f172a;">
                        <h3 style="margin-top:0;">Create Invoice from Job</h3>
                        <?php if (!empty($invoice_error)): ?>
                            <div style="color:#9b1c1c; margin-bottom:8px;"><?php echo htmlspecialchars($invoice_error); ?>
                            </div><?php endif; ?>
                        <form method="post" action="Invoices.php">
                            <div style="display:grid; grid-template-columns: repeat(4, minmax(0,1fr)); gap:12px;">
                                <div>
                                    <label for="job_id">Job</label>
                                    <select id="job_id" name="job_id" required>
                                        <option value="" disabled selected>Select job</option>
                                        <?php foreach ($jobs_for_invoice as $j): ?>
                                            <option value="<?php echo htmlspecialchars($j['job_id']); ?>" <?php echo ($selected_job === $j['job_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($j['job_id'] . ' — ' . $j['reg_no'] . ' / ' . $j['customer_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Bike reg lookup removed per request -->
                                <div style="grid-column: span 4;">
                                    <button type="button" id="addInvoicePartRow" class="btn-primary"
                                        style="padding:8px 12px;">Add Invoice Part</button>
                                </div>
                                <div style="grid-column: span 4; overflow-x:auto;">
                                    <table class="modern-table" style="width:100%; min-width:760px; margin-top:10px;">
                                        <thead>
                                            <tr>
                                                <th>Part</th>
                                                <th>Qty</th>
                                                <th>Price</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="invoicePartRows">
                                            <tr class="invoice-part-row">
                                                <td>
                                                    <select name="part_id[]">
                                                        <option value="" selected>No extra part</option>
                                                        <?php foreach ($available_parts as $part): ?>
                                                            <option
                                                                value="<?php echo htmlspecialchars($part['part_id']); ?>"
                                                                data-price="<?php echo htmlspecialchars($part['unit_price']); ?>">
                                                                <?php echo htmlspecialchars($part['part_name'] . ' (Stock: ' . $part['qty_in_stock'] . ')'); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                </td>
                                                <td><input type="number" name="part_quantity[]" min="1" value="1"></td>
                                                <td><input type="number" name="part_price[]" step="0.01" min="0"
                                                        value="0" placeholder="Auto"></td>
                                                <td><button type="button" class="remove-invoice-part-row"
                                                        style="background:#ef4444; border:none; border-radius:8px; color:#fff; padding:8px 10px; cursor:pointer;">Remove</button>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- Discount and Tax removed per settings -->
                                <div>
                                    <label for="method_id">Payment Method</label>
                                    <select id="method_id" name="method_id">
                                        <option value="" selected>None</option>
                                        <?php foreach ($payment_methods as $pm): ?>
                                            <option value="<?php echo htmlspecialchars($pm['method_id']); ?>">
                                                <?php echo htmlspecialchars($pm['method_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div
                                style="margin-top:12px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                                <button type="button" id="previewInvoiceJob" class="btn-primary"
                                    style="background:#0f172a;">Preview Job</button>
                                <button type="button" id="cancelInvoiceForm"
                                    style="background:#fff; border:1px solid #d1d5db; padding:8px 12px; border-radius:8px;">Cancel</button>
                                <button type="submit" name="create_invoice" class="btn-primary">Create Invoice</button>
                            </div>
                        </form>

                        <?php if ($selected_job && $job_preview): ?>
                            <div
                                style="margin-top:22px; padding:18px; border:1px solid #e2e8f0; border-radius:12px; background:#f8fafc; color:#0f172a;">
                                <h4 style="margin:0 0 12px;">Job Preview</h4>
                                <p style="margin:0 0 6px;"><strong>Job:</strong>
                                    <?php echo htmlspecialchars($job_preview['job_id']); ?></p>
                                <p style="margin:0 0 6px;"><strong>Customer:</strong>
                                    <?php echo htmlspecialchars($job_preview['customer_name']); ?></p>
                                <p style="margin:0 0 6px;"><strong>Vehicle:</strong>
                                    <?php echo htmlspecialchars($job_preview['reg_no']); ?></p>
                                <p style="margin:0 0 6px;"><strong>Service:</strong>
                                    <?php echo htmlspecialchars($job_preview['service_type'] ?: 'N/A'); ?></p>
                                <p style="margin:0 0 6px;"><strong>Problem:</strong>
                                    <?php echo nl2br(htmlspecialchars($job_preview['problem_description'])); ?></p>
                                <div class="table-responsive" style="margin-top:14px;">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th>Part</th>
                                                <th>Qty</th>
                                                <th>Unit Price</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($preview_items)): ?>
                                                <?php foreach ($preview_items as $item): ?>
                                                    <tr>
                                                        <td><?php echo htmlspecialchars($item['part_name']); ?></td>
                                                        <td><?php echo htmlspecialchars($item['quantity_used']); ?></td>
                                                        <td><?php echo htmlspecialchars(number_format($item['price_at_time'], 2)); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars(number_format($item['quantity_used'] * $item['price_at_time'], 2)); ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" style="text-align:center;">No parts added to this job yet.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" style="text-align:right;">Parts Total</td>
                                                <td>LKR <?php echo number_format($preview_parts_total, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" style="text-align:right;">Labour</td>
                                                <td>LKR <?php echo number_format($preview_labour, 2); ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="3" style="text-align:right; font-weight:700;">Subtotal</td>
                                                <td style="font-weight:700;">LKR
                                                    <?php echo number_format($preview_sub_total, 2); ?>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                                <div
                                    style="margin-top:12px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                                    <form method="post" action="Invoices.php"
                                        style="display:flex; gap:8px; align-items:center;">
                                        <input type="hidden" name="job_id"
                                            value="<?php echo htmlspecialchars($job_preview['job_id']); ?>">
                                        <label for="method_id_preview" style="margin:0 6px 0 0;">Payment</label>
                                        <select id="method_id_preview" name="method_id" style="margin-right:6px;">
                                            <option value="" selected>None</option>
                                            <?php foreach ($payment_methods as $pm): ?>
                                                <option value="<?php echo htmlspecialchars($pm['method_id']); ?>">
                                                    <?php echo htmlspecialchars($pm['method_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="create_from_job" class="btn-primary">Create & Print
                                            Invoice</button>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Invoice ID</th>
                                    <th>Job ID</th>
                                    <th>Vehicle No</th>
                                    <th>Customer Name</th>
                                    <th>Total Amount</th>
                                    <th>Payment Status</th>
                                    <th>Invoice Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($invoices_result && $invoices_result->num_rows > 0): ?>
                                    <?php while ($invoice = $invoices_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td><strong>
                                                    <?php echo htmlspecialchars($invoice['invoice_id']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($invoice['job_id']); ?>
                                            </td>
                                            <td><strong>
                                                    <?php echo htmlspecialchars($invoice['reg_no']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($invoice['customer_name']); ?>
                                            </td>
                                            <td>LKR
                                                <?php echo number_format($invoice['total_amount'], 2); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $invoice['paid_status'];
                                                $badge_class = "pending";
                                                if ($status === 'Paid')
                                                    $badge_class = "completed";
                                                elseif ($status === 'Partial')
                                                    $badge_class = "progress";
                                                ?>
                                                <span class="status-badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($invoice['invoice_date']))); ?>
                                            </td>
                                            <td>
                                                <a class="table-action"
                                                    href="Invoices.php?view_invoice=<?php echo urlencode($invoice['invoice_id']); ?>"
                                                    title="Edit Invoice"><i data-lucide="edit-2"></i></a>
                                                <form method="post" action="Invoices.php" style="display:inline; margin:0;">
                                                    <input type="hidden" name="invoice_id"
                                                        value="<?php echo htmlspecialchars($invoice['invoice_id']); ?>">
                                                    <input type="hidden" name="delete_invoice" value="1">
                                                    <button type="submit" class="table-action" title="Delete Invoice"
                                                        onclick="return confirm('Delete invoice <?php echo htmlspecialchars($invoice['invoice_id']); ?>?');"><i
                                                            data-lucide="trash-2"></i></button>
                                                </form>
                                                <a class="table-action"
                                                    href="Invoices.php?view_invoice=<?php echo urlencode($invoice['invoice_id']); ?>&print_preview=1"
                                                    title="Print Invoice"><i data-lucide="printer"></i></a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">No invoices found in
                                            database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>

            <!-- Sticky Footer -->
            <footer class="dashboard-footer">
                <div class="footer-left">BikeFix</div>
                <div class="footer-right">
                    <span class="server-status"><span class="dot green"></span> Connected to Secure Server</span>
                </div>
            </footer>
        </div>
    </div>

    <script src="JS/dashboard.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof lucide !== 'undefined') { try { lucide.createIcons(); } catch (e) { } }
            const showInv = document.getElementById('showInvoiceForm');
            const newInv = document.getElementById('newInvoice');
            const cancelInv = document.getElementById('cancelInvoiceForm');
            const previewBtn = document.getElementById('previewInvoiceJob');
            const jobSelect = document.getElementById('job_id');
            const addInvoicePartRow = document.getElementById('addInvoicePartRow');
            const invoicePartRows = document.getElementById('invoicePartRows');
            if (showInv && newInv) showInv.addEventListener('click', function (e) { e.preventDefault(); newInv.style.display = 'block'; newInv.scrollIntoView({ behavior: 'smooth', block: 'center' }); });
            if (cancelInv && newInv) cancelInv.addEventListener('click', function (e) { e.preventDefault(); newInv.style.display = 'none'; });
            if (addInvoicePartRow && invoicePartRows) {
                addInvoicePartRow.addEventListener('click', function (e) {
                    e.preventDefault();
                    const template = invoicePartRows.querySelector('.invoice-part-row');
                    if (!template) return;
                    const clone = template.cloneNode(true);
                    clone.querySelectorAll('select').forEach(function (select) { select.selectedIndex = 0; });
                    clone.querySelectorAll('input').forEach(function (input) { if (input.type === 'number') { input.value = input.name === 'part_quantity[]' ? '1' : '0'; } });
                    invoicePartRows.appendChild(clone);
                });
            }
            // bike reg lookup handler removed
            if (invoicePartRows) {
                invoicePartRows.addEventListener('click', function (e) {
                    if (!e.target.classList.contains('remove-invoice-part-row')) return;
                    const row = e.target.closest('.invoice-part-row');
                    if (!row) return;
                    if (invoicePartRows.querySelectorAll('.invoice-part-row').length > 1) {
                        row.remove();
                    }
                });
                // Auto-fill price when part selection changes
                invoicePartRows.addEventListener('change', function (e) {
                    const tgt = e.target;
                    if (!tgt || tgt.tagName.toLowerCase() !== 'select') return;
                    const sel = tgt;
                    const row = sel.closest('.invoice-part-row');
                    if (!row) return;
                    const priceInput = row.querySelector('input[name="part_price[]"]');
                    const opt = sel.options[sel.selectedIndex];
                    const price = opt ? opt.dataset.price : '';
                    if (priceInput && price !== undefined && price !== '') {
                        // Only auto-fill when the current value is empty or zero
                        const cur = parseFloat(priceInput.value || '0');
                        if (!cur || cur <= 0) {
                            priceInput.value = parseFloat(price).toFixed(2);
                        }
                    }
                });
            }
            if (previewBtn && jobSelect) {
                previewBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    const selected = jobSelect.value;
                    if (!selected) return alert('Select a job first.');
                    window.location.href = 'Invoices.php?selected_job=' + encodeURIComponent(selected) + '&preview=1';
                });
            }
            <?php if ($auto_print && $invoice_detail): ?>
                window.print();
            <?php endif; ?>
        });
    </script>
</body>

</html>