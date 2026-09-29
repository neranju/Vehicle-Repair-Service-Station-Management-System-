<?php
// customer.php - Customer List Page
require_once 'db.php';

// Check Admin or Receptionist role session authentication
if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Receptionist')) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'];
$role = $_SESSION['role'];
$message = '';
$error_msg = '';

function resolve_brand_id($conn, $brand_text)
{
    $brand_text = trim($brand_text);
    if ($brand_text === '') {
        return null;
    }

    $stmt = $conn->prepare("SELECT brand_id FROM vehicle_brands WHERE brand_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $brand_text);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['brand_id'];
        }
        $stmt->close();
    }

    $ins = $conn->prepare("INSERT INTO vehicle_brands (brand_name) VALUES (?)");
    if ($ins) {
        $ins->bind_param('s', $brand_text);
        if ($ins->execute()) {
            $last_inserted_id = $conn->insert_id;
            $new_id = sprintf('BR%03d', $last_inserted_id);
            $upd = $conn->prepare("UPDATE vehicle_brands SET brand_id = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param('si', $new_id, $last_inserted_id);
                $upd->execute();
                $upd->close();
            }
            $ins->close();
            return $new_id;
        }
        $ins->close();
    }

    return null;
}

function resolve_model_id($conn, $model_text, $brand_id = null)
{
    $model_text = trim($model_text);
    if ($model_text === '') {
        return null;
    }

    if ($brand_id !== null) {
        $stmt = $conn->prepare("SELECT model_id FROM vehicle_models WHERE model_name = ? AND brand_id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('ss', $model_text, $brand_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $stmt->close();
                return $row['model_id'];
            }
            $stmt->close();
        }
    }

    $stmt = $conn->prepare("SELECT model_id FROM vehicle_models WHERE model_name = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param('s', $model_text);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $stmt->close();
            return $row['model_id'];
        }
        $stmt->close();
    }

    if ($brand_id === null) {
        return null;
    }

    $ins = $conn->prepare("INSERT INTO vehicle_models (brand_id, model_name) VALUES (?, ?)");
    if ($ins) {
        $ins->bind_param('ss', $brand_id, $model_text);
        if ($ins->execute()) {
            $last_inserted_id = $conn->insert_id;
            $new_id = sprintf('MD%03d', $last_inserted_id);
            $upd = $conn->prepare("UPDATE vehicle_models SET model_id = ? WHERE id = ?");
            if ($upd) {
                $upd->bind_param('si', $new_id, $last_inserted_id);
                $upd->execute();
                $upd->close();
            }
            $ins->close();
            return $new_id;
        }
        $ins->close();
    }

    return null;
}

// No model select: customers will type brand and model instead

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($name === '' || $phone === '' || $address_line1 === '' || $city === '') {
        $error_msg = 'Please fill in all required fields before submitting.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error_msg = 'Phone number must be exactly 10 digits and contain numbers only.';
    } elseif (!preg_match('/^[A-Za-z\s.]+$/', $name)) {
        $error_msg = 'Customer name must contain only alphabetic letters, spaces or dots.';
    } else {
        $insert_customer_sql = "INSERT INTO customers (name, phone, email, reg_date) VALUES (?, ?, ?, NOW())";
        $stmt_customer = $conn->prepare($insert_customer_sql);
        if ($stmt_customer) {
            $stmt_customer->bind_param('sss', $name, $phone, $email);
            if ($stmt_customer->execute()) {
                $new_customer_id = $conn->insert_id;
                $customer_id = sprintf('CUS%03d', $new_customer_id);

                $update_customer_id_sql = "UPDATE customers SET customer_id = ? WHERE id = ?";
                $stmt_update = $conn->prepare($update_customer_id_sql);
                if ($stmt_update) {
                    $stmt_update->bind_param('si', $customer_id, $new_customer_id);
                    $stmt_update->execute();
                    $stmt_update->close();
                }

                $insert_address_sql = "INSERT INTO customer_addresses (customer_id, address_line1, address_line2, city, is_primary) VALUES (?, ?, ?, ?, 1)";
                $stmt_address = $conn->prepare($insert_address_sql);
                if ($stmt_address) {
                    $stmt_address->bind_param('ssss', $customer_id, $address_line1, $address_line2, $city);
                    if ($stmt_address->execute()) {
                        // If a registration number was provided, save vehicle record
                        $reg_no = trim($_POST['reg_no'] ?? '');
                        $year = trim($_POST['year'] ?? '');

                        if ($reg_no !== '') {
                            $brand_text = trim($_POST['brand_text'] ?? '');
                            $model_text = trim($_POST['model_text'] ?? '');
                            if ($model_text === '') {
                                $error_msg = 'Please enter bike model when registration number is provided.';
                            } else {
                                $brand_id = resolve_brand_id($conn, $brand_text);
                                $model_id = resolve_model_id($conn, $model_text, $brand_id);
                                if (empty($model_id)) {
                                    $error_msg = 'Unable to resolve vehicle model. Please enter both brand and model.';
                                } else {
                                    $insert_vehicle_sql = "INSERT INTO vehicles (customer_id, model_id, reg_no, year) VALUES (?, ?, ?, ?)";
                                    $stmt_vehicle = $conn->prepare($insert_vehicle_sql);
                                    if ($stmt_vehicle) {
                                        $stmt_vehicle->bind_param('ssss', $customer_id, $model_id, $reg_no, $year);
                                        if ($stmt_vehicle->execute()) {
                                            $new_vid = $conn->insert_id;
                                            $vehicle_id = sprintf('VEH%03d', $new_vid);
                                            $stmt_update_vid = $conn->prepare("UPDATE vehicles SET vehicle_id = ? WHERE id = ?");
                                            if ($stmt_update_vid) {
                                                $stmt_update_vid->bind_param('si', $vehicle_id, $new_vid);
                                                $stmt_update_vid->execute();
                                                $stmt_update_vid->close();
                                            }
                                        }
                                        $stmt_vehicle->close();
                                    }
                                }
                            }
                        }

                        header('Location: customer.php?added=1');
                        exit();
                    } else {
                        $error_msg = 'Customer was added but address save failed: ' . $stmt_address->error;
                    }
                    $stmt_address->close();
                } else {
                    $error_msg = 'Failed to prepare address query: ' . $conn->error;
                }
            } else {
                $error_msg = 'Failed to add customer: ' . $stmt_customer->error;
            }
            $stmt_customer->close();
        } else {
            $error_msg = 'Failed to prepare customer query: ' . $conn->error;
        }
    }
}

// Handle delete customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_customer'])) {
    $del_customer_id = trim($_POST['delete_customer_id'] ?? '');
    if ($del_customer_id === '') {
        $error_msg = 'Invalid customer specified for deletion.';
    } else {
        // Prevent deletion if there are job cards referencing this customer
        $chk = $conn->prepare("SELECT COUNT(*) as cnt FROM job_cards WHERE customer_id = ?");
        if ($chk) {
            $chk->bind_param('s', $del_customer_id);
            $chk->execute();
            $res = $chk->get_result();
            $row = $res->fetch_assoc();
            $count = (int) $row['cnt'];
            $chk->close();

            if ($count > 0) {
                $error_msg = 'Cannot delete customer: ' . $count . ' related job card(s) exist. Remove those first.';
            } else {
                // Safe to delete: remove vehicles, addresses, then customer in a transaction
                $conn->begin_transaction();
                $del_v = $conn->prepare("DELETE FROM vehicles WHERE customer_id = ?");
                if ($del_v) {
                    $del_v->bind_param('s', $del_customer_id);
                    $del_v->execute();
                    $del_v->close();
                }
                $del_a = $conn->prepare("DELETE FROM customer_addresses WHERE customer_id = ?");
                if ($del_a) {
                    $del_a->bind_param('s', $del_customer_id);
                    $del_a->execute();
                    $del_a->close();
                }
                $del_c = $conn->prepare("DELETE FROM customers WHERE customer_id = ?");
                if ($del_c) {
                    $del_c->bind_param('s', $del_customer_id);
                    if ($del_c->execute()) {
                        $conn->commit();
                        header('Location: customer.php?deleted=1');
                        exit();
                    } else {
                        $conn->rollback();
                        $error_msg = 'Failed to delete customer: ' . $del_c->error;
                    }
                    $del_c->close();
                }
            }
        } else {
            $error_msg = 'Failed to check related records: ' . $conn->error;
        }
    }
}

// Handle update customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_customer'])) {
    $orig_customer_id = trim($_POST['orig_customer_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address_line1 = trim($_POST['address_line1'] ?? '');
    $address_line2 = trim($_POST['address_line2'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if ($orig_customer_id === '' || $name === '' || $phone === '' || $address_line1 === '' || $city === '') {
        $error_msg = 'Please fill required fields before updating.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error_msg = 'Phone number must be exactly 10 digits and contain numbers only.';
    } elseif (!preg_match('/^[A-Za-z\s.]+$/', $name)) {
        $error_msg = 'Customer name must contain only alphabetic letters, spaces or dots.';
    } else {
        $update_c = $conn->prepare("UPDATE customers SET name = ?, phone = ?, email = ? WHERE customer_id = ?");
        if ($update_c) {
            $update_c->bind_param('ssss', $name, $phone, $email, $orig_customer_id);
            if ($update_c->execute()) {
                $update_c->close();
                // Update or insert primary address
                $chk_addr = $conn->prepare("SELECT id FROM customer_addresses WHERE customer_id = ? AND is_primary = 1 LIMIT 1");
                if ($chk_addr) {
                    $chk_addr->bind_param('s', $orig_customer_id);
                    $chk_addr->execute();
                    $res = $chk_addr->get_result();
                    if ($res && $res->num_rows === 1) {
                        $addr = $res->fetch_assoc();
                        $addr_up = $conn->prepare("UPDATE customer_addresses SET address_line1 = ?, address_line2 = ?, city = ? WHERE id = ?");
                        if ($addr_up) {
                            $addr_up->bind_param('sssi', $address_line1, $address_line2, $city, $addr['id']);
                            $addr_up->execute();
                            $addr_up->close();
                        }
                    } else {
                        $addr_ins = $conn->prepare("INSERT INTO customer_addresses (customer_id, address_line1, address_line2, city, is_primary) VALUES (?, ?, ?, ?, 1)");
                        if ($addr_ins) {
                            $addr_ins->bind_param('ssss', $orig_customer_id, $address_line1, $address_line2, $city);
                            $addr_ins->execute();
                            $addr_ins->close();
                        }
                    }
                    $chk_addr->close();
                }

                // Optionally add a new vehicle if reg_no provided
                $reg_no = trim($_POST['reg_no'] ?? '');
                $year = trim($_POST['year'] ?? '');
                if ($reg_no !== '') {
                    $brand_text = trim($_POST['brand_text'] ?? '');
                    $model_text = trim($_POST['model_text'] ?? '');
                    if ($model_text === '') {
                        $error_msg = 'Please enter bike model when registration number is provided.';
                    } else {
                        $brand_id = resolve_brand_id($conn, $brand_text);
                        $model_id = resolve_model_id($conn, $model_text, $brand_id);
                        if (empty($model_id)) {
                            $error_msg = 'Unable to resolve vehicle model. Please enter both brand and model.';
                        } else {
                            $ins_v = $conn->prepare("INSERT INTO vehicles (customer_id, model_id, reg_no, year) VALUES (?, ?, ?, ?)");
                            if ($ins_v) {
                                $ins_v->bind_param('ssss', $orig_customer_id, $model_id, $reg_no, $year);
                                if ($ins_v->execute()) {
                                    $new_vid = $conn->insert_id;
                                    $vehicle_id = sprintf('VEH%03d', $new_vid);
                                    $stmt_update_vid = $conn->prepare("UPDATE vehicles SET vehicle_id = ? WHERE id = ?");
                                    if ($stmt_update_vid) {
                                        $stmt_update_vid->bind_param('si', $vehicle_id, $new_vid);
                                        $stmt_update_vid->execute();
                                        $stmt_update_vid->close();
                                    }
                                }
                                $ins_v->close();
                            }
                        }
                    }
                }

                if (empty($error_msg)) {
                    header('Location: customer.php?updated=1');
                    exit();
                }
            } else {
                $error_msg = 'Failed to update customer: ' . $update_c->error;
            }
        } else {
            $error_msg = 'Failed to prepare update: ' . $conn->error;
        }
    }
}

// If editing, load customer data to prefill form
$edit_customer = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $cid = $conn->real_escape_string($_GET['edit']);
    $res = $conn->query("SELECT c.*, ca.address_line1, ca.address_line2, ca.city FROM customers c LEFT JOIN customer_addresses ca ON c.customer_id = ca.customer_id AND ca.is_primary = 1 WHERE c.customer_id = '" . $cid . "' LIMIT 1");
    if ($res && $res->num_rows === 1) {
        $edit_customer = $res->fetch_assoc();
    }
}

// If editing, also fetch the customer's most recent vehicle to prefill vehicle fields
if ($edit_customer) {
    $stmt_v = $conn->prepare("SELECT v.reg_no, v.year, vm.model_name, b.brand_name, vm.model_id FROM vehicles v LEFT JOIN vehicle_models vm ON v.model_id = vm.model_id LEFT JOIN vehicle_brands b ON vm.brand_id = b.brand_id WHERE v.customer_id = ? ORDER BY v.id DESC LIMIT 1");
    if ($stmt_v) {
        $stmt_v->bind_param('s', $edit_customer['customer_id']);
        $stmt_v->execute();
        $rv = $stmt_v->get_result();
        if ($rv && $rv->num_rows === 1) {
            $vrow = $rv->fetch_assoc();
            $edit_customer['reg_no'] = $vrow['reg_no'];
            $edit_customer['year'] = $vrow['year'];
            $edit_customer['brand_name'] = $vrow['brand_name'];
            $edit_customer['model_text'] = $vrow['model_name'];
            $edit_customer['model_id'] = $vrow['model_id'];
        }
        $stmt_v->close();
    }
}

// Handle GET messages after redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '✅ New customer registered successfully.';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = '✅ Customer record updated successfully.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '✅ Customer deleted successfully.';
}

// Fetch all customers linked with primary address and their vehicles if available
$customers_query = "SELECT c.*, ca.address_line1, ca.address_line2, ca.city, 
                    GROUP_CONCAT(CONCAT(v.reg_no, ' (', COALESCE(vm.model_name, ''), ')') SEPARATOR '; ') AS vehicles
                    FROM customers c
                    LEFT JOIN customer_addresses ca ON c.customer_id = ca.customer_id AND ca.is_primary = 1
                    LEFT JOIN vehicles v ON v.customer_id = c.customer_id
                    LEFT JOIN vehicle_models vm ON v.model_id = vm.model_id
                    GROUP BY c.id
                    ORDER BY c.id DESC";
$customers_result = $conn->query($customers_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Registry | BikeFix</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="CSS/dashboard.css">
    <style>
        .store-form-card {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.06);
            color: #111827;
        }

        .store-form-card h3 {
            margin: 0 0 12px 0;
            color: #0f172a;
        }

        .store-form-card label {
            display: block;
            margin-bottom: 8px;
            color: #374151;
        }

        .store-form-card input,
        .store-form-card select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #e6e9ee;
            border-radius: 10px;
            background: #fbfdfe;
            color: #0f172a;
            box-sizing: border-box;
        }

        .store-form-card .form-grid {
            gap: 16px;
        }

        .store-form-card .btn-primary {
            background: #0f172a;
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            border: none;
        }

        .notification {
            border-radius: 10px;
            padding: 12px 14px;
        }

        .notification.success {
            background: #f0fdf4;
            color: #064e3b;
        }

        .notification.error {
            background: #fff5f5;
            color: #7f1d1d;
        }

        /* Compact table styling for customer dashboard */
        .compact-table {
            font-size: 13px;
            border-collapse: collapse;
        }

        .compact-table th,
        .compact-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #eef2f7;
            vertical-align: top;
        }

        .compact-table thead th {
            background: #fbfdfe;
            color: #0f172a;
            font-weight: 600;
        }

        .customer-address {
            font-size: 12px;
            color: #ffffff;
        }

        .customer-vehicles {
            font-size: 12px;
            color: #ffffff;
            white-space: normal;
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
                    <?php elseif ($role === 'Receptionist'): ?>
                        <li class="">
                            <a href="Receptionist.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Receptionist'): ?>
                        <li class="active">
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

                    <?php if ($role === 'Admin' || $role === 'Receptionist'): ?>
                        <li class="">
                            <a href="Invoices.php"><i data-lucide="receipt"></i> <span>Invoices</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Receptionist'): ?>
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
                        <input type="text" placeholder="Search customer records...">
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
                    <h2>Customer Database</h2>
                    <p>Manage and view registered workshop patrons</p>
                </div>

                <!-- Customers Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>Patron Records</h3>
                    </div>
                    <?php if ($message): ?>
                        <div class="notification success"
                            style="margin-bottom: 20px; padding: 15px; background: #e6ffed; color: #1f7a2d; border-radius: 8px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($error_msg): ?>
                        <div class="notification error"
                            style="margin-bottom: 20px; padding: 15px; background: #ffe6e6; color: #a92b2b; border-radius: 8px;">
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>
                    <div class="store-form-card" style="margin-bottom: 30px; padding: 28px;">
                        <?php if (isset($edit_customer) && $edit_customer): ?>
                            <h3>Edit Customer - <?php echo htmlspecialchars($edit_customer['customer_id']); ?></h3>
                            <form method="post" action="customer.php">
                                <input type="hidden" name="orig_customer_id"
                                    value="<?php echo htmlspecialchars($edit_customer['customer_id']); ?>">
                            <?php else: ?>
                                <h3>Add New Customer</h3>
                                <form method="post" action="customer.php">
                                <?php endif; ?>
                                <div class="form-grid"
                                    style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px;">
                                    <div class="input-group">
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" placeholder="Kamal Perera" required
                                            pattern="[A-Za-z\s.]+"
                                            oninput="this.value = this.value.replace(/[^A-Za-z\s.]/g, '');"
                                            value="<?php echo isset($edit_customer['name']) ? htmlspecialchars($edit_customer['name']) : ''; ?>">
                                    </div>
                                    <div class="input-group">
                                        <label for="phone">Phone Number</label>
                                        <input type="text" id="phone" name="phone" placeholder="0771234567" required
                                            pattern="[0-9]{10}" maxlength="10" minlength="10"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);"
                                            value="<?php echo isset($edit_customer['phone']) ? htmlspecialchars($edit_customer['phone']) : ''; ?>">
                                    </div>
                                    <div class="input-group" style="grid-column: span 2;">
                                        <label for="email">Email Address</label>
                                        <input type="email" id="email" name="email" placeholder="kamal@gmail.com"
                                            value="<?php echo isset($edit_customer['email']) ? htmlspecialchars($edit_customer['email']) : ''; ?>">
                                    </div>
                                    <div class="input-group" style="grid-column: span 2;">
                                        <label for="address_line1">Address Line 1</label>
                                        <input type="text" id="address_line1" name="address_line1"
                                            placeholder="No. 123, Galle Road" required
                                            value="<?php echo isset($edit_customer['address_line1']) ? htmlspecialchars($edit_customer['address_line1']) : ''; ?>">
                                    </div>
                                    <div class="input-group" style="grid-column: span 2;">
                                        <label for="address_line2">Address Line 2</label>
                                        <input type="text" id="address_line2" name="address_line2"
                                            placeholder="Colombo 03"
                                            value="<?php echo isset($edit_customer['address_line2']) ? htmlspecialchars($edit_customer['address_line2']) : ''; ?>">
                                    </div>
                                    <div class="input-group">
                                        <label for="city">City</label>
                                        <input type="text" id="city" name="city" placeholder="Colombo" required
                                            value="<?php echo isset($edit_customer['city']) ? htmlspecialchars($edit_customer['city']) : ''; ?>">
                                    </div>
                                    <div class="input-group" style="grid-column: span 2; margin-top: 6px;">
                                        <h4 style="margin:8px 0 12px 0; color:#0f172a;">Vehicle (Optional)</h4>
                                    </div>
                                    <div class="input-group">
                                        <label for="reg_no">Bike Reg. No</label>
                                        <input type="text" id="reg_no" name="reg_no" placeholder="WP-BCA-1234">
                                    </div>
                                    <div class="input-group">
                                        <label for="brand_text">Brand</label>
                                        <input type="text" id="brand_text" name="brand_text" placeholder="e.g. Honda"
                                            value="<?php echo isset($edit_customer['brand_name']) ? htmlspecialchars($edit_customer['brand_name']) : ''; ?>">
                                    </div>
                                    <div class="input-group">
                                        <label for="model_text">Model</label>
                                        <input type="text" id="model_text" name="model_text" placeholder="e.g. CG125"
                                            value="<?php echo isset($edit_customer['model_text']) ? htmlspecialchars($edit_customer['model_text']) : ''; ?>">
                                    </div>
                                    <div class="input-group">
                                        <label for="year">Year</label>
                                        <input type="number" id="year" name="year" placeholder="2018" min="1900"
                                            max="2100">
                                    </div>
                                </div>
                                <div style="margin-top: 22px; text-align: right;">
                                    <?php if (isset($edit_customer) && $edit_customer): ?>
                                        <button type="submit" name="update_customer" class="btn-primary">Update
                                            Customer</button>
                                        <a href="customer.php" style="margin-left:10px; color:#374151;">Cancel</a>
                                    <?php else: ?>
                                        <button type="submit" name="add_customer" class="btn-primary">Save Customer</button>
                                    <?php endif; ?>
                                </div>
                            </form>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table compact-table">
                            <thead>
                                <tr>
                                    <th>Customer ID</th>
                                    <th>Customer Name</th>
                                    <th>Phone Number</th>
                                    <th>Email Address</th>
                                    <th>Vehicles</th>
                                    <th>Location / Address</th>
                                    <th>Registration Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($customers_result && $customers_result->num_rows > 0): ?>
                                    <?php while ($customer = $customers_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td><strong>
                                                    <?php echo htmlspecialchars($customer['customer_id']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($customer['name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($customer['phone']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($customer['email'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="customer-vehicles">
                                                <?php echo htmlspecialchars($customer['vehicles'] ?? 'N/A'); ?>
                                            </td>
                                            <td class="customer-address">
                                                <?php if (!empty($customer['address_line1'])): ?>
                                                    <?php echo htmlspecialchars($customer['address_line1']); ?>
                                                    <?php if (!empty($customer['address_line2'])): ?>,
                                                        <?php echo htmlspecialchars($customer['address_line2']); ?>             <?php endif; ?>,
                                                    <?php echo htmlspecialchars($customer['city']); ?>
                                                <?php else: ?>
                                                    N/A
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars(date('Y-m-d H:i', strtotime($customer['reg_date']))); ?>
                                            </td>
                                            <td>
                                                <a href="customer.php?edit=<?php echo urlencode($customer['customer_id']); ?>"
                                                    class="table-action" title="Edit"><i data-lucide="edit-2"></i></a>
                                                <form method="post" action="customer.php"
                                                    onsubmit="return confirm('Delete this customer and related vehicles?');"
                                                    style="display:inline">
                                                    <input type="hidden" name="delete_customer_id"
                                                        value="<?php echo htmlspecialchars($customer['customer_id']); ?>">
                                                    <button type="submit" name="delete_customer" class="table-action"
                                                        title="Delete"><i data-lucide="trash-2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px;">No customers found in
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
        // Initialize Lucide icons
        lucide.createIcons();
    </script>
</body>

</html>