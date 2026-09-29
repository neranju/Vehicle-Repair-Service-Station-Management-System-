<?php
// Job_cad.php - Job Cards Management Page
require_once 'db.php';

// Check Admin, Receptionist, or Mechanic role session authentication
if (!isset($_SESSION['staff_id']) || !in_array($_SESSION['role'], ['Admin', 'Receptionist', 'Mechanic'])) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'];
$session_role = $_SESSION['role'];

// Fetch lists needed for job creation form
$customers = [];
$customers_result = $conn->query("SELECT customer_id, name FROM customers ORDER BY name");
if ($customers_result) {
    while ($c = $customers_result->fetch_assoc()) {
        $customers[] = $c;
    }
}

$vehicles = [];
$vehicles_result = $conn->query("SELECT vehicle_id, reg_no FROM vehicles ORDER BY reg_no");
if ($vehicles_result) {
    while ($v = $vehicles_result->fetch_assoc()) {
        $vehicles[] = $v;
    }
}

$available_store_items = [];
$store_items_res = $conn->query("SELECT part_id, part_name, qty_in_stock, unit_price FROM parts ORDER BY part_name");
if ($store_items_res) {
    while ($item = $store_items_res->fetch_assoc()) {
        $available_store_items[] = $item;
    }
}

$mechanics = [];
$mech_res = $conn->query("SELECT s.staff_id, s.name FROM staff s JOIN roles r ON s.role_id = r.role_id WHERE r.role_name = 'Mechanic' ORDER BY s.name");
if ($mech_res) {
    while ($m = $mech_res->fetch_assoc()) {
        $mechanics[] = $m;
    }
}

$service_types = [];
$st_res = $conn->query("SELECT service_type_id, service_name FROM service_types ORDER BY service_name");
if ($st_res) {
    while ($s = $st_res->fetch_assoc()) {
        $service_types[] = $s;
    }
}

$statuses = [];
$status_res = $conn->query("SELECT status_id, status_name FROM job_statuses ORDER BY id");
if ($status_res) {
    while ($ss = $status_res->fetch_assoc()) {
        $statuses[] = $ss;
    }
}

// Handle add job card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_job'])) {
    $vehicle_id = trim($_POST['vehicle_id'] ?? '');
    $customer_id = trim($_POST['customer_id'] ?? '');
    $mechanic_id = trim($_POST['mechanic_id'] ?? '');
    $service_type_text = trim($_POST['service_type'] ?? '');
    $status_id = trim($_POST['status_id'] ?? '');
    $problem_description = trim($_POST['problem_description'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    // support multiple optional parts on job creation
    $part_ids = $_POST['part_id'] ?? [];
    $part_quantities = $_POST['part_quantity'] ?? [];
    $part_prices = $_POST['part_price'] ?? [];
    $parts_to_add_on_create = [];
    foreach ($part_ids as $idx => $raw_pid) {
        $pid = trim($raw_pid);
        if ($pid === '')
            continue;
        $pq = (int) trim($part_quantities[$idx] ?? 0);
        $pp = (float) trim($part_prices[$idx] ?? 0);
        if ($pq <= 0) {
            $error_msg = 'Please enter valid quantities for selected parts.';
            break;
        }
        $parts_to_add_on_create[] = ['part_id' => $pid, 'quantity' => $pq, 'price' => $pp];
    }

    if (isset($error_msg) && $error_msg !== '') {
        // already set above in parts loop — skip
    } elseif ($vehicle_id === '' || $customer_id === '' || $mechanic_id === '' || $service_type_text === '') {
        $error_msg = 'Please fill required fields (vehicle, customer, mechanic, service).';
    } else {
        // Resolve or create service_type_id from free-text
        $service_type_text = $conn->real_escape_string($service_type_text);
        $service_type_id = null;
        $sel = $conn->prepare("SELECT service_type_id FROM service_types WHERE service_name = ? LIMIT 1");
        if ($sel) {
            $sel->bind_param('s', $service_type_text);
            $sel->execute();
            $sel->bind_result($found_st_id);
            if ($sel->fetch()) {
                $service_type_id = $found_st_id;
            }
            $sel->close();
        }
        if ($service_type_id === null) {
            // create new service type id
            $id_row = $conn->query("SELECT MAX(id) AS max_id FROM service_types");
            $next_id = 1;
            if ($id_row && $r = $id_row->fetch_assoc()) {
                $next_id = ((int) $r['max_id']) + 1;
            }
            $new_st_code = sprintf('SV%03d', $next_id);
            $ins = $conn->prepare("INSERT INTO service_types (service_type_id, service_name, default_price) VALUES (?, ?, 0.00)");
            if ($ins) {
                $ins->bind_param('ss', $new_st_code, $service_type_text);
                if ($ins->execute()) {
                    $service_type_id = $new_st_code;
                }
                $ins->close();
            }
        }
        // generate next job id
        $id_row = $conn->query("SELECT MAX(id) AS max_id FROM job_cards");
        $next_id = 1;
        if ($id_row && $r = $id_row->fetch_assoc()) {
            $next_id = ((int) $r['max_id']) + 1;
        }
        $job_code = sprintf('JC%03d', $next_id);
        $date_in = date('Y-m-d H:i:s');

        $stmt = null;
        $check = null;
        $ins = null;
        $upd = null;
        $conn->begin_transaction();
        try {
            // Insert job without labour_charge (uses default 0.00)
            $insert_sql = "INSERT INTO job_cards (job_id, vehicle_id, customer_id, mechanic_id, service_type_id, status_id, date_in, problem_description, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insert_sql);
            if (!$stmt) {
                throw new Exception('Failed to prepare job insert: ' . $conn->error);
            }
            $stmt->bind_param('sssssssss', $job_code, $vehicle_id, $customer_id, $mechanic_id, $service_type_id, $status_id, $date_in, $problem_description, $remarks);
            if (!$stmt->execute()) {
                throw new Exception('Failed to add job card: ' . $stmt->error);
            }
            $stmt->close();
            $stmt = null;

            if (!empty($parts_to_add_on_create)) {
                $check = $conn->prepare("SELECT qty_in_stock, unit_price FROM parts WHERE part_id = ? FOR UPDATE");
                if (!$check) {
                    throw new Exception('Failed to prepare stock lookup: ' . $conn->error);
                }
                $ins = $conn->prepare("INSERT INTO jobcard_parts (job_part_id, job_id, part_id, quantity_used, price_at_time) VALUES (?, ?, ?, ?, ?)");
                if (!$ins) {
                    throw new Exception('Failed to prepare part insert: ' . $conn->error);
                }
                $upd = $conn->prepare("UPDATE parts SET qty_in_stock = qty_in_stock - ? WHERE part_id = ?");
                if (!$upd) {
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

                foreach ($parts_to_add_on_create as $pitem) {
                    $pid = $pitem['part_id'];
                    $pqty = (int) $pitem['quantity'];
                    $pprice = (float) $pitem['price'];

                    $check->bind_param('s', $pid);
                    $check->execute();
                    $check->bind_result($stock_qty, $unit_price);
                    if (!$check->fetch()) {
                        throw new Exception('Selected store item not found: ' . $pid);
                    }
                    if ($stock_qty < $pqty) {
                        throw new Exception('Not enough stock available for ' . $pid . '. Available: ' . $stock_qty);
                    }
                    if ($pprice <= 0) {
                        $pprice = $unit_price;
                    }
                    $check->free_result();

                    $job_part_code = sprintf('JP%03d', $nextjp);
                    $nextjp++;

                    $ins->bind_param('sssid', $job_part_code, $job_code, $pid, $pqty, $pprice);
                    if (!$ins->execute()) {
                        throw new Exception('Failed to add part to job card: ' . $ins->error);
                    }

                    $upd->bind_param('is', $pqty, $pid);
                    if (!$upd->execute()) {
                        throw new Exception('Failed to decrement stock: ' . $upd->error);
                    }
                }
                $check->close();
                $check = null;
                $ins->close();
                $ins = null;
                $upd->close();
                $upd = null;
            }

            $conn->commit();
            header('Location: Job_cad.php?added=1');
            exit();
        } catch (Exception $e) {
            if ($check !== null) {
                @$check->free_result();
                $check->close();
            }
            if ($ins !== null) {
                $ins->close();
            }
            if ($upd !== null) {
                $upd->close();
            }
            if ($stmt !== null) {
                $stmt->close();
            }
            $conn->rollback();
            $error_msg = $e->getMessage();
        }
    }
}

// Handle adding parts to a job card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_part'])) {
    $job_id_post = trim($_POST['job_id'] ?? '');
    $part_ids = $_POST['part_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($job_id_post === '' || !is_array($part_ids) || empty($part_ids)) {
        $part_error = 'Please select at least one part to add.';
    } else {
        $parts_to_add = [];
        foreach ($part_ids as $index => $raw_part_id) {
            $part_id = trim($raw_part_id);
            if ($part_id === '') {
                continue;
            }
            $quantity = (int) trim($quantities[$index] ?? 0);
            if ($quantity <= 0) {
                $part_error = 'Please enter a valid quantity for each selected part.';
                break;
            }
            $parts_to_add[] = ['part_id' => $part_id, 'quantity' => $quantity];
        }

        if (empty($parts_to_add) && !$part_error) {
            $part_error = 'Please select at least one part to add.';
        }
    }

    if (empty($part_error)) {
        $check_stmt = null;
        $insert_stmt = null;
        $update_stmt = null;
        $conn->begin_transaction();
        try {
            $check_stmt = $conn->prepare("SELECT qty_in_stock, unit_price FROM parts WHERE part_id = ? FOR UPDATE");
            if (!$check_stmt) {
                throw new Exception('Failed to prepare stock lookup: ' . $conn->error);
            }
            $insert_stmt = $conn->prepare("INSERT INTO jobcard_parts (job_part_id, job_id, part_id, quantity_used, price_at_time) VALUES (?, ?, ?, ?, ?)");
            if (!$insert_stmt) {
                throw new Exception('Failed to prepare job part insert: ' . $conn->error);
            }
            $update_stmt = $conn->prepare("UPDATE parts SET qty_in_stock = qty_in_stock - ? WHERE part_id = ?");
            if (!$update_stmt) {
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

            foreach ($parts_to_add as $item) {
                $part_id = $item['part_id'];
                $quantity = $item['quantity'];

                $check_stmt->bind_param('s', $part_id);
                $check_stmt->execute();
                $check_stmt->bind_result($qty_in_stock, $unit_price);
                if (!$check_stmt->fetch()) {
                    throw new Exception('Selected part not found: ' . htmlspecialchars($part_id));
                }
                $check_stmt->free_result();

                if ($qty_in_stock < $quantity) {
                    throw new Exception('Not enough stock available for ' . $part_id . '. Available: ' . $qty_in_stock);
                }

                $job_part_code = sprintf('JP%03d', $nextjp);
                $nextjp++;

                $price_at_time = (float) $unit_price;
                $insert_stmt->bind_param('sssid', $job_part_code, $job_id_post, $part_id, $quantity, $price_at_time);
                if (!$insert_stmt->execute()) {
                    throw new Exception('Failed to add part to job card: ' . $insert_stmt->error);
                }

                $update_stmt->bind_param('is', $quantity, $part_id);
                if (!$update_stmt->execute()) {
                    throw new Exception('Failed to decrement stock: ' . $update_stmt->error);
                }
            }

            $check_stmt->close();
            $insert_stmt->close();
            $update_stmt->close();
            $conn->commit();
            header('Location: Job_cad.php?view_job=' . urlencode($job_id_post) . '&added_part=1');
            exit();
        } catch (Exception $e) {
            if ($check_stmt !== null) {
                @$check_stmt->free_result();
                $check_stmt->close();
            }
            if ($insert_stmt !== null) {
                $insert_stmt->close();
            }
            if ($update_stmt !== null) {
                $update_stmt->close();
            }
            $conn->rollback();
            $part_error = 'Failed to add parts: ' . $e->getMessage();
        }
    }
}

// Handle delete job card
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_job'])) {
    $del_job_id = trim($_POST['del_job_id'] ?? '');
    if ($del_job_id === '') {
        $error_msg = 'Invalid job selected for deletion.';
    } else {
        $chk_inv = $conn->prepare("SELECT COUNT(*) AS cnt FROM invoices WHERE job_id = ?");
        $inv_count = 0;
        if ($chk_inv) {
            $chk_inv->bind_param('s', $del_job_id);
            $chk_inv->execute();
            $inv_count = (int) $chk_inv->get_result()->fetch_assoc()['cnt'];
            $chk_inv->close();
        }
        if ($inv_count > 0) {
            $error_msg = 'Cannot delete: An invoice exists for this job. Delete the invoice first.';
        } else {
            $conn->begin_transaction();
            try {
                $pu_res2 = $conn->prepare("SELECT part_id, quantity_used FROM jobcard_parts WHERE job_id = ?");
                if ($pu_res2) {
                    $pu_res2->bind_param('s', $del_job_id);
                    $pu_res2->execute();
                    $pu_data = $pu_res2->get_result();
                    $restore = $conn->prepare("UPDATE parts SET qty_in_stock = qty_in_stock + ? WHERE part_id = ?");
                    while ($pu = $pu_data->fetch_assoc()) {
                        if ($restore) {
                            $restore->bind_param('is', $pu['quantity_used'], $pu['part_id']);
                            $restore->execute();
                        }
                    }
                    if ($restore)
                        $restore->close();
                    $pu_res2->close();
                }
                $dp = $conn->prepare("DELETE FROM jobcard_parts WHERE job_id = ?");
                if ($dp) {
                    $dp->bind_param('s', $del_job_id);
                    $dp->execute();
                    $dp->close();
                }
                $dj = $conn->prepare("DELETE FROM job_cards WHERE job_id = ?");
                if (!$dj)
                    throw new Exception('Prepare failed: ' . $conn->error);
                $dj->bind_param('s', $del_job_id);
                if (!$dj->execute())
                    throw new Exception('Delete failed: ' . $dj->error);
                $dj->close();
                $conn->commit();
                header('Location: Job_cad.php?deleted=1');
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error_msg = 'Delete failed: ' . $e->getMessage();
            }
        }
    }
}

// Handle update (edit) job card — saves original to history then updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_job'])) {
    $upd_job_id = trim($_POST['upd_job_id'] ?? '');
    $upd_vehicle_id = trim($_POST['upd_vehicle_id'] ?? '');
    $upd_customer_id = trim($_POST['upd_customer_id'] ?? '');
    $upd_mechanic_id = trim($_POST['upd_mechanic_id'] ?? '');
    $upd_service = trim($_POST['upd_service_type'] ?? '');
    $upd_status_id = trim($_POST['upd_status_id'] ?? '');
    $upd_problem = trim($_POST['upd_problem_description'] ?? '');
    $upd_remarks = trim($_POST['upd_remarks'] ?? '');

    if ($upd_job_id === '' || $upd_vehicle_id === '' || $upd_customer_id === '' || $upd_mechanic_id === '' || $upd_service === '') {
        $error_msg = 'Please fill all required fields for job update.';
    } else {
        // Ensure history table exists
        $conn->query("CREATE TABLE IF NOT EXISTS job_card_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            job_id VARCHAR(20),
            vehicle_id VARCHAR(20),
            customer_id VARCHAR(20),
            mechanic_id VARCHAR(20),
            service_type_id VARCHAR(20),
            status_id VARCHAR(20),
            problem_description TEXT,
            remarks TEXT,
            edited_at DATETIME
        )");
        // Save original record to history
        $orig_st = $conn->prepare("SELECT * FROM job_cards WHERE job_id = ? LIMIT 1");
        if ($orig_st) {
            $orig_st->bind_param('s', $upd_job_id);
            $orig_st->execute();
            $orig_row = $orig_st->get_result()->fetch_assoc();
            $orig_st->close();
            if ($orig_row) {
                $hist = $conn->prepare("INSERT INTO job_card_history (job_id,vehicle_id,customer_id,mechanic_id,service_type_id,status_id,problem_description,remarks,edited_at) VALUES (?,?,?,?,?,?,?,?,NOW())");
                if ($hist) {
                    $hist->bind_param('ssssssss', $orig_row['job_id'], $orig_row['vehicle_id'], $orig_row['customer_id'], $orig_row['mechanic_id'], $orig_row['service_type_id'], $orig_row['status_id'], $orig_row['problem_description'], $orig_row['remarks']);
                    $hist->execute();
                    $hist->close();
                }
            }
        }
        // Resolve or create service_type_id
        $upd_stid = null;
        $sel_st = $conn->prepare("SELECT service_type_id FROM service_types WHERE service_name = ? LIMIT 1");
        if ($sel_st) {
            $sel_st->bind_param('s', $upd_service);
            $sel_st->execute();
            $sel_st->bind_result($found_stid2);
            if ($sel_st->fetch())
                $upd_stid = $found_stid2;
            $sel_st->close();
        }
        if ($upd_stid === null) {
            $max_st2 = $conn->query("SELECT MAX(id) AS m FROM service_types");
            $nxt_st2 = 1;
            if ($max_st2 && $r2 = $max_st2->fetch_assoc())
                $nxt_st2 = ((int) $r2['m']) + 1;
            $new_stc = sprintf('SV%03d', $nxt_st2);
            $ins_st2 = $conn->prepare("INSERT INTO service_types (service_type_id, service_name, default_price) VALUES (?,?,0.00)");
            if ($ins_st2) {
                $ins_st2->bind_param('ss', $new_stc, $upd_service);
                $ins_st2->execute();
                $ins_st2->close();
                $upd_stid = $new_stc;
            }
        }
        // Update the job card
        $upd_st = $conn->prepare("UPDATE job_cards SET vehicle_id=?, customer_id=?, mechanic_id=?, service_type_id=?, status_id=?, problem_description=?, remarks=? WHERE job_id=?");
        if ($upd_st) {
            $upd_st->bind_param('ssssssss', $upd_vehicle_id, $upd_customer_id, $upd_mechanic_id, $upd_stid, $upd_status_id, $upd_problem, $upd_remarks, $upd_job_id);
            if ($upd_st->execute()) {
                header('Location: Job_cad.php?updated=1');
                exit();
            } else {
                $error_msg = 'Update failed: ' . $upd_st->error;
            }
            $upd_st->close();
        } else {
            $error_msg = 'Prepare failed: ' . $conn->error;
        }
    }
}

// Load job data for editing
$edit_job = null;
if (isset($_GET['edit_job']) && $_GET['edit_job'] !== '') {
    $ej_id = $conn->real_escape_string($_GET['edit_job']);
    $ej_res = $conn->query("SELECT j.*, st.service_name FROM job_cards j LEFT JOIN service_types st ON j.service_type_id = st.service_type_id WHERE j.job_id = '" . $ej_id . "' LIMIT 1");
    if ($ej_res && $ej_res->num_rows === 1) {
        $edit_job = $ej_res->fetch_assoc();
    }
}

// If viewing a single job, fetch its used parts and available parts
$view_job = trim($_GET['view_job'] ?? '');
$job_parts = [];
$available_parts = [];
if ($view_job !== '') {
    $jp_stmt = $conn->prepare("SELECT jp.*, p.part_name FROM jobcard_parts jp JOIN parts p ON jp.part_id = p.part_id WHERE jp.job_id = ? ORDER BY jp.id");
    if ($jp_stmt) {
        $jp_stmt->bind_param('s', $view_job);
        $jp_stmt->execute();
        $res = $jp_stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $job_parts[] = $row;
        }
        $jp_stmt->close();
    }

    $parts_res = $conn->query("SELECT part_id, part_name, qty_in_stock, unit_price FROM parts ORDER BY part_name");
    if ($parts_res) {
        while ($p = $parts_res->fetch_assoc()) {
            $available_parts[] = $p;
        }
    }

    $invoice_exists = false;
    $invoice_id = '';
    $inv_stmt = $conn->prepare("SELECT invoice_id FROM invoices WHERE job_id = ? LIMIT 1");
    if ($inv_stmt) {
        $inv_stmt->bind_param('s', $view_job);
        $inv_stmt->execute();
        $inv_stmt->bind_result($invoice_id);
        if ($inv_stmt->fetch()) {
            $invoice_exists = true;
        }
        $inv_stmt->close();
    }
}

// Fetch all jobs from database
$jobs_query = "SELECT j.*, v.reg_no, c.name AS customer_name, m.name AS mechanic_name, s.status_name 
               FROM job_cards j
               LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
               LEFT JOIN customers c ON j.customer_id = c.customer_id
               LEFT JOIN staff m ON j.mechanic_id = m.staff_id
               LEFT JOIN job_statuses s ON j.status_id = s.status_id
               ORDER BY j.id DESC";
$jobs_result = $conn->query($jobs_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Cards | BikeFix</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="CSS/dashboard.css">
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
                    <?php if ($session_role === 'Admin'): ?>
                        <li class="">
                            <a href="dashboard.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php elseif ($session_role === 'Mechanic'): ?>
                        <li class="">
                            <a href="Mechanic.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php elseif ($session_role === 'Receptionist'): ?>
                        <li class="">
                            <a href="Receptionist.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin' || $session_role === 'Receptionist'): ?>
                        <li class="">
                            <a href="customer.php"><i data-lucide="users"></i> <span>Customer</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin'): ?>
                        <li class="">
                            <a href="Staff.php"><i data-lucide="user-check"></i> <span>Staff</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin' || $session_role === 'Mechanic'): ?>
                        <li class="">
                            <a href="Store.php"><i data-lucide="package"></i> <span>Store</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin' || $session_role === 'Receptionist'): ?>
                        <li class="">
                            <a href="Invoices.php"><i data-lucide="receipt"></i> <span>Invoices</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin' || $session_role === 'Receptionist' || $session_role === 'Mechanic'): ?>
                        <li class="active">
                            <a href="Job_cad.php"><i data-lucide="file-text"></i> <span>Job Card</span></a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Admin" class="user-avatar-sm">
                    <div class="user-details">
                        <span class="user-name">
                            <?php echo htmlspecialchars($user_name); ?>
                        </span>
                        <span class="user-role">
                            <?php echo htmlspecialchars($session_role); ?>
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
                        <input type="text" placeholder="Search job records...">
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
                    <h2>Job Card Registry</h2>
                    <p>Track workshop repairs, diagnostics, and completions</p>
                </div>

                <!-- Job Cards Table -->
                <div class="table-section">
                    <div class="table-header"
                        style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
                        <h3 style="margin:0;">Active and Historical Job Cards</h3>
                        <button id="showJobForm" class="btn-primary">Add New Job Card</button>
                    </div>

                    <div id="newJobCard"
                        style="display:none; margin:18px 0; padding:18px; border:1px solid #e6edf3; border-radius:12px; background:#fff; color:#0f172a;">
                        <h3 style="margin-top:0;">Create Job Card</h3>
                        <form method="post" action="Job_cad.php">
                            <div class="form-grid"
                                style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px;">
                                <div>
                                    <label for="customer_id">Customer</label>
                                    <select id="customer_id" name="customer_id" required>
                                        <option value="" disabled selected>Select customer</option>
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['customer_id']); ?>">
                                                <?php echo htmlspecialchars($c['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="vehicle_id">Vehicle</label>
                                    <select id="vehicle_id" name="vehicle_id" required>
                                        <option value="" disabled selected>Select vehicle</option>
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?php echo htmlspecialchars($v['vehicle_id']); ?>">
                                                <?php echo htmlspecialchars($v['reg_no']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="mechanic_id">Assign Mechanic</label>
                                    <select id="mechanic_id" name="mechanic_id" required>
                                        <option value="" disabled selected>Select mechanic</option>
                                        <?php foreach ($mechanics as $m): ?>
                                            <option value="<?php echo htmlspecialchars($m['staff_id']); ?>">
                                                <?php echo htmlspecialchars($m['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label for="service_type">Service Type</label>
                                    <input type="text" id="service_type" name="service_type" required
                                        placeholder="e.g. Full Service">
                                </div>
                                <div style="grid-column: span 2;">
                                    <label>Store Items</label>
                                    <div style="margin:8px 0 12px;">
                                        <button type="button" id="addJobCreatePartRow" class="btn-primary"
                                            style="padding:8px 12px;">Add Item</button>
                                    </div>
                                    <div style="overflow-x:auto;">
                                        <table class="modern-table" style="width:100%; min-width:720px;">
                                            <thead>
                                                <tr>
                                                    <th>Part</th>
                                                    <th>Qty</th>
                                                    <th>Price</th>
                                                    <th>Action</th>
                                                </tr>
                                            </thead>
                                            <tbody id="jobCreatePartRows">
                                                <tr class="job-create-part-row">
                                                    <td>
                                                        <select name="part_id[]">
                                                            <option value="" selected>Select item</option>
                                                            <?php foreach ($available_store_items as $item): ?>
                                                                <option
                                                                    value="<?php echo htmlspecialchars($item['part_id']); ?>"
                                                                    data-price="<?php echo htmlspecialchars($item['unit_price']); ?>">
                                                                    <?php echo htmlspecialchars($item['part_name'] . ' (Stock: ' . $item['qty_in_stock'] . ')'); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                    <td><input type="number" name="part_quantity[]" min="1" value="1">
                                                    </td>
                                                    <td><input type="number" name="part_price[]" step="0.01" min="0"
                                                            value="0" placeholder="Auto"></td>
                                                    <td><button type="button" class="remove-job-create-row"
                                                            style="background:#ef4444; border:none; border-radius:8px; color:#fff; padding:8px 10px; cursor:pointer;">Remove</button>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                <div>
                                    <label for="status_id">Status</label>
                                    <select id="status_id" name="status_id" required>
                                        <?php foreach ($statuses as $s): ?>
                                            <option value="<?php echo htmlspecialchars($s['status_id']); ?>">
                                                <?php echo htmlspecialchars($s['status_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Labour charge removed as requested -->
                                <div style="grid-column: span 2;">
                                    <label for="problem_description">Problem Description</label>
                                    <textarea id="problem_description" name="problem_description" rows="3"
                                        style="width:100%;"></textarea>
                                </div>
                                <div style="grid-column: span 2;">
                                    <label for="remarks">Remarks</label>
                                    <input type="text" id="remarks" name="remarks" style="width:100%;">
                                </div>
                            </div>
                            <div
                                style="margin-top:12px; text-align:right; display:flex; gap:10px; justify-content:flex-end;">
                                <button type="button" id="cancelJobForm"
                                    style="background:#fff; border:1px solid #d1d5db; padding:8px 12px; border-radius:8px;">Cancel</button>
                                <button type="submit" name="add_job" class="btn-primary">Save Job Card</button>
                            </div>
                        </form>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Job ID</th>
                                    <th>Vehicle No</th>
                                    <th>Customer Name</th>
                                    <th>Assigned Mechanic</th>
                                    <th>Status</th>
                                    <th>Date In</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($jobs_result && $jobs_result->num_rows > 0): ?>
                                    <?php while ($job = $jobs_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td><strong>
                                                    <?php echo htmlspecialchars($job['job_id']); ?>
                                                </strong></td>
                                            <td><strong>
                                                    <?php echo htmlspecialchars($job['reg_no']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($job['customer_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($job['mechanic_name']); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $status = $job['status_name'];
                                                $badge_class = "pending";
                                                if ($status === 'In Progress')
                                                    $badge_class = "progress";
                                                elseif ($status === 'Completed' || $status === 'Delivered')
                                                    $badge_class = "completed";
                                                elseif ($status === 'Cancelled')
                                                    $badge_class = "cancelled";
                                                ?>
                                                <span class="status-badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($status); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($job['date_in']))); ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <a class="table-action"
                                                    href="Job_cad.php?view_job=<?php echo urlencode($job['job_id']); ?>"
                                                    title="View / Add Parts"
                                                    style="color:var(--primary-color); margin-right:4px;"><i
                                                        data-lucide="package"></i></a>
                                                <a class="table-action"
                                                    href="Job_cad.php?edit_job=<?php echo urlencode($job['job_id']); ?>"
                                                    title="Edit Job" style="color:#f59e0b; margin-right:4px;"><i
                                                        data-lucide="edit-2"></i></a>
                                                <form method="post" action="Job_cad.php" style="display:inline;"
                                                    onsubmit="return confirm('Delete job <?php echo htmlspecialchars(addslashes($job['job_id'])); ?>? Parts stock will be restored.');">
                                                    <input type="hidden" name="del_job_id"
                                                        value="<?php echo htmlspecialchars($job['job_id']); ?>">
                                                    <button type="submit" name="delete_job" class="table-action" title="Delete"
                                                        style="color:#ef4444; background:none; border:none; cursor:pointer;"><i
                                                            data-lucide="trash-2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px;">No jobs found in
                                            database.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <?php if (!empty($error_msg)): ?>
                    <div
                        style="margin:14px 0; padding:12px 16px; background:rgba(239,68,68,0.12); color:#f87171; border-radius:10px; border:1px solid rgba(239,68,68,0.25);">
                        ⚠️ <?php echo htmlspecialchars($error_msg); ?></div>
                <?php endif; ?>
                <?php if (isset($_GET['added'])): ?>
                    <div
                        style="margin:14px 0; padding:12px 16px; background:rgba(16,185,129,0.12); color:#34d399; border-radius:10px; border:1px solid rgba(16,185,129,0.25);">
                        ✅ New job card created successfully!</div>
                <?php endif; ?>
                <?php if (isset($_GET['deleted'])): ?>
                    <div
                        style="margin:14px 0; padding:12px 16px; background:rgba(16,185,129,0.12); color:#34d399; border-radius:10px; border:1px solid rgba(16,185,129,0.25);">
                        ✅ Job card deleted &amp; parts stock restored.</div>
                <?php endif; ?>
                <?php if (isset($_GET['updated'])): ?>
                    <div
                        style="margin:14px 0; padding:12px 16px; background:rgba(16,185,129,0.12); color:#34d399; border-radius:10px; border:1px solid rgba(16,185,129,0.25);">
                        ✅ Job card updated successfully. Original record saved to history.</div>
                <?php endif; ?>

                <?php if ($edit_job): ?>
                    <div class="table-section" style="margin-top:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:18px;">
                            <h3 style="margin:0;">Edit Job Card &mdash; <span
                                    style="color:var(--primary-color);"><?php echo htmlspecialchars($edit_job['job_id']); ?></span>
                            </h3>
                            <a href="Job_cad.php" style="color:var(--text-muted); font-size:0.9rem; text-decoration:none;">✕
                                Cancel</a>
                        </div>
                        <form method="post" action="Job_cad.php">
                            <input type="hidden" name="upd_job_id"
                                value="<?php echo htmlspecialchars($edit_job['job_id']); ?>">
                            <div style="display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:14px;">
                                <div>
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Customer</label>
                                    <select name="upd_customer_id" required
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main);">
                                        <?php foreach ($customers as $c): ?>
                                            <option value="<?php echo htmlspecialchars($c['customer_id']); ?>" <?php echo ($c['customer_id'] === $edit_job['customer_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($c['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Vehicle</label>
                                    <select name="upd_vehicle_id" required
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main);">
                                        <?php foreach ($vehicles as $v): ?>
                                            <option value="<?php echo htmlspecialchars($v['vehicle_id']); ?>" <?php echo ($v['vehicle_id'] === $edit_job['vehicle_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($v['reg_no']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Mechanic</label>
                                    <select name="upd_mechanic_id" required
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main);">
                                        <?php foreach ($mechanics as $m): ?>
                                            <option value="<?php echo htmlspecialchars($m['staff_id']); ?>" <?php echo ($m['staff_id'] === $edit_job['mechanic_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($m['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Service
                                        Type</label>
                                    <input type="text" name="upd_service_type" required
                                        value="<?php echo htmlspecialchars($edit_job['service_name'] ?? ''); ?>"
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main); box-sizing:border-box;">
                                </div>
                                <div>
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Status</label>
                                    <select name="upd_status_id" required
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main);">
                                        <?php foreach ($statuses as $ss): ?>
                                            <option value="<?php echo htmlspecialchars($ss['status_id']); ?>" <?php echo ($ss['status_id'] === $edit_job['status_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($ss['status_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div style="grid-column:span 2;">
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Problem
                                        Description</label>
                                    <textarea name="upd_problem_description" rows="3"
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main); box-sizing:border-box;"><?php echo htmlspecialchars($edit_job['problem_description'] ?? ''); ?></textarea>
                                </div>
                                <div style="grid-column:span 2;">
                                    <label
                                        style="display:block; margin-bottom:6px; color:var(--text-muted); font-size:0.88rem;">Remarks</label>
                                    <input type="text" name="upd_remarks"
                                        value="<?php echo htmlspecialchars($edit_job['remarks'] ?? ''); ?>"
                                        style="width:100%; padding:10px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--bg-dark); color:var(--text-main); box-sizing:border-box;">
                                </div>
                            </div>
                            <div
                                style="margin-top:18px; display:flex; gap:10px; justify-content:flex-end; align-items:center;">
                                <p style="margin:0; font-size:0.78rem; color:var(--text-muted); flex:1;">&#9432; Original
                                    record saved to history on update.</p>
                                <a href="Job_cad.php"
                                    style="padding:10px 18px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); color:var(--text-muted); text-decoration:none;">Cancel</a>
                                <button type="submit" name="update_job" class="btn-primary"
                                    style="padding:10px 22px;">&#128190; Save Changes</button>
                            </div>
                        </form>
                    </div>
                <?php endif; ?>

                <?php if ($view_job !== ''): ?>
                    <div class="table-section" style="margin-top:24px;">
                        <div
                            style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
                            <h3 style="margin:0;">Job Details: <span
                                    style="color:var(--primary-color);"><?php echo htmlspecialchars($view_job); ?></span>
                            </h3>
                            <div style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
                                <a href="Job_cad.php" class="btn-primary" style="padding:8px 14px; background:#374151;">←
                                    Back to List</a>
                                <?php if ($invoice_exists): ?>
                                    <a href="Invoices.php?view_invoice=<?php echo urlencode($invoice_id); ?>"
                                        class="btn-primary" style="padding:8px 14px;">View Invoice</a>
                                <?php else: ?>
                                    <a href="Invoices.php?selected_job=<?php echo urlencode($view_job); ?>&preview=1"
                                        class="btn-primary" style="padding:8px 14px;">Create Invoice</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (!empty($part_error)): ?>
                            <div
                                style="color:#ef4444; margin-bottom:12px; padding:10px; background:rgba(239,68,68,0.1); border-radius:8px;">
                                <?php echo htmlspecialchars($part_error); ?></div>
                        <?php endif; ?>

                        <h4 style="margin-bottom:12px;">Add Parts to Job</h4>
                        <form method="post" action="Job_cad.php?view_job=<?php echo urlencode($view_job); ?>">
                            <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($view_job); ?>">
                            <div style="margin-bottom:12px;">
                                <button type="button" id="addJobPartRow" class="btn-primary" style="padding:8px 12px;">+ Add
                                    Part Row</button>
                            </div>
                            <div style="overflow-x:auto;">
                                <table class="modern-table" style="width:100%; min-width:520px;">
                                    <thead>
                                        <tr>
                                            <th>Part</th>
                                            <th>Quantity</th>
                                            <th style="width:100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="jobPartRows">
                                        <tr class="job-part-row">
                                            <td>
                                                <select name="part_id[]" required
                                                    style="width:100%; padding:8px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--card-bg); color:var(--text-main);">
                                                    <option value="" disabled selected>Select part</option>
                                                    <?php foreach ($available_parts as $ap): ?>
                                                        <option value="<?php echo htmlspecialchars($ap['part_id']); ?>">
                                                            <?php echo htmlspecialchars($ap['part_name'] . ' (Available: ' . $ap['qty_in_stock'] . ')'); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <input type="number" name="quantity[]" min="1" value="1" required
                                                    style="width:80px; padding:8px; border-radius:8px; border:1px solid rgba(255,255,255,0.1); background:var(--card-bg); color:var(--text-main);">
                                            </td>
                                            <td>
                                                <button type="button" class="remove-job-part-row"
                                                    style="background:#ef4444; border:none; border-radius:8px; color:#fff; padding:8px 10px; cursor:pointer;">Remove</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div style="margin-top:12px; text-align:right;">
                                <button type="submit" name="add_part" class="btn-primary">Add Parts to Job</button>
                            </div>
                        </form>

                        <h4 style="margin-top:28px; margin-bottom:12px;">Parts Used So Far</h4>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Part</th>
                                        <th>Qty</th>
                                        <th>Unit Price (LKR)</th>
                                        <th>Subtotal (LKR)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($job_parts)): ?>
                                        <?php foreach ($job_parts as $jp): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($jp['part_name']); ?></td>
                                                <td><?php echo htmlspecialchars($jp['quantity_used']); ?></td>
                                                <td><?php echo number_format((float) $jp['price_at_time'], 2); ?></td>
                                                <td><?php echo number_format((float) $jp['price_at_time'] * (int) $jp['quantity_used'], 2); ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" style="text-align:center; padding:16px;">No parts attached to this
                                                job yet.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

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
            if (typeof lucide !== 'undefined') { try { lucide.createIcons(); } catch (e) { /* ignore */ } }
            const showJobBtn = document.getElementById('showJobForm');
            const newJobCard = document.getElementById('newJobCard');
            const cancelJobBtn = document.getElementById('cancelJobForm');
            if (showJobBtn && newJobCard) {
                showJobBtn.addEventListener('click', function (e) {
                    e.preventDefault();
                    newJobCard.style.display = 'block';
                    newJobCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
            if (cancelJobBtn && newJobCard) {
                cancelJobBtn.addEventListener('click', function (e) { e.preventDefault(); newJobCard.style.display = 'none'; });
            }
            // Dynamic rows for parts on job creation
            const addJobCreatePartRow = document.getElementById('addJobCreatePartRow');
            const jobCreatePartRows = document.getElementById('jobCreatePartRows');
            if (addJobCreatePartRow && jobCreatePartRows) {
                addJobCreatePartRow.addEventListener('click', function (e) {
                    e.preventDefault();
                    const template = jobCreatePartRows.querySelector('.job-create-part-row');
                    if (!template) return;
                    const clone = template.cloneNode(true);
                    clone.querySelectorAll('select').forEach(function (s) { s.selectedIndex = 0; });
                    clone.querySelectorAll('input').forEach(function (i) { if (i.type === 'number') i.value = i.name === 'part_quantity[]' ? '1' : '0'; else i.value = ''; });
                    jobCreatePartRows.appendChild(clone);
                });
            }
            if (jobCreatePartRows) {
                jobCreatePartRows.addEventListener('click', function (e) {
                    if (!e.target.classList.contains('remove-job-create-row')) return;
                    const row = e.target.closest('.job-create-part-row');
                    if (!row) return;
                    if (jobCreatePartRows.querySelectorAll('.job-create-part-row').length > 1) row.remove();
                });
                jobCreatePartRows.addEventListener('change', function (e) {
                    const tgt = e.target; if (!tgt || tgt.tagName.toLowerCase() !== 'select') return;
                    const sel = tgt; const row = sel.closest('.job-create-part-row'); if (!row) return;
                    const priceInput = row.querySelector('input[name="part_price[]"]');
                    const opt = sel.options[sel.selectedIndex]; const price = opt ? opt.dataset.price : '';
                    if (priceInput && price !== undefined && price !== '') {
                        const cur = parseFloat(priceInput.value || '0'); if (!cur || cur <= 0) priceInput.value = parseFloat(price).toFixed(2);
                    }
                });
            }
        });
    </script>
</body>

</html>