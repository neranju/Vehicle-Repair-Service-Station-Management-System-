<?php
// Store.php - Store Inventory Page
require_once 'db.php';

// Check Admin or Mechanic role session authentication
if (!isset($_SESSION['staff_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Mechanic')) {
    header("Location: login.php");
    exit();
}

$user_name = $_SESSION['name'];
$role = $_SESSION['role'];
$admin_name = $user_name;
$message = '';
$error_msg = '';

// Fetch categories and suppliers for add item form
$categories = [];
$categories_result = $conn->query("SELECT category_id, category_name FROM part_categories ORDER BY category_name");
if ($categories_result) {
    while ($row = $categories_result->fetch_assoc()) {
        $categories[] = $row;
    }
}

$suppliers = [];
$suppliers_result = $conn->query("SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name");
if ($suppliers_result) {
    while ($row = $suppliers_result->fetch_assoc()) {
        $suppliers[] = $row;
    }
}

$edit_part = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $edit_id = $conn->real_escape_string($_GET['edit']);
    $edit_query = "SELECT * FROM parts WHERE part_id = '" . $edit_id . "' LIMIT 1";
    $edit_result = $conn->query($edit_query);
    if ($edit_result && $edit_result->num_rows === 1) {
        $edit_part = $edit_result->fetch_assoc();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_part'])) {
    $part_id = trim($_POST['part_id'] ?? '');
    $part_name = trim($_POST['part_name'] ?? '');
    $part_code = trim($_POST['part_code'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $qty_in_stock = trim($_POST['qty_in_stock'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $reorder_level = trim($_POST['reorder_level'] ?? '');
    $supplier_id = trim($_POST['supplier_id'] ?? '');

    if ($part_id === '' || $part_name === '' || $part_code === '' || $category_id === '' || $supplier_id === '' || $qty_in_stock === '' || $unit_price === '' || $reorder_level === '') {
        $error_msg = 'Please complete all fields before adding the item.';
    } elseif (!ctype_digit($qty_in_stock) || !ctype_digit($reorder_level) || !is_numeric($unit_price)) {
        $error_msg = 'Quantity, reorder level and unit price must be valid numbers.';
    } else {
        $qty_in_stock = (int) $qty_in_stock;
        $unit_price = (float) $unit_price;
        $reorder_level = (int) $reorder_level;

        $insert_sql = "INSERT INTO parts (part_id, part_name, part_code, category_id, qty_in_stock, unit_price, reorder_level, supplier_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('ssssidis', $part_id, $part_name, $part_code, $category_id, $qty_in_stock, $unit_price, $reorder_level, $supplier_id);
            if ($stmt->execute()) {
                header('Location: Store.php?added=1');
                exit();
            } else {
                $error_msg = 'Failed to add item: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare query: ' . $conn->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $supplier_name   = trim($_POST['supplier_name'] ?? '');
    $contact_person  = trim($_POST['contact_person'] ?? '');
    $phone           = trim($_POST['supplier_phone'] ?? '');
    $email           = trim($_POST['supplier_email'] ?? '');
    $address         = trim($_POST['supplier_address'] ?? '');

    if ($supplier_name === '') {
        $error_msg = 'Supplier name is required.';
    } elseif (!preg_match('/^[A-Za-z\s.&\-]+$/', $supplier_name)) {
        $error_msg = 'Supplier name must contain letters only (no numbers).';
    } elseif ($contact_person !== '' && !preg_match('/^[A-Za-z\s.]+$/', $contact_person)) {
        $error_msg = 'Contact person name must contain letters only (no numbers).';
    } elseif ($phone === '') {
        $error_msg = 'Phone number is required.';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error_msg = 'Phone number must be exactly 10 digits (numbers only, no spaces or dashes).';
    } elseif ($email === '') {
        $error_msg = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || strpos($email, '@') === false) {
        $error_msg = 'Please enter a valid email address containing @.';
    } else {
        // Generate next supplier ID
        $id_result = $conn->query("SELECT MAX(id) AS max_id FROM suppliers");
        $next_id = 1;
        if ($id_result && $row = $id_result->fetch_assoc()) {
            $next_id = ((int) $row['max_id']) + 1;
        }
        $supplier_code = sprintf('SUP%03d', $next_id);

        $insert_sql = "INSERT INTO suppliers (supplier_id, supplier_name, contact_person, phone, email, address) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('ssssss', $supplier_code, $supplier_name, $contact_person, $phone, $email, $address);
            if ($stmt->execute()) {
                header('Location: Store.php?supplier_added=1');
                exit();
            } else {
                $error_msg = 'Failed to add supplier: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare supplier query: ' . $conn->error;
        }
    }
}

// Handle delete supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_supplier'])) {
    $del_sup_id = trim($_POST['del_supplier_id'] ?? '');
    if ($del_sup_id === '') {
        $error_msg = 'Invalid supplier selected.';
    } else {
        // Check if any parts use this supplier
        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM parts WHERE supplier_id = ?");
        $parts_count = 0;
        if ($chk) {
            $chk->bind_param('s', $del_sup_id);
            $chk->execute();
            $parts_count = (int) $chk->get_result()->fetch_assoc()['cnt'];
            $chk->close();
        }
        if ($parts_count > 0) {
            $error_msg = 'Cannot delete: ' . $parts_count . ' part(s) are linked to this supplier. Reassign or delete those parts first.';
        } else {
            $ds = $conn->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
            if ($ds) {
                $ds->bind_param('s', $del_sup_id);
                if ($ds->execute()) {
                    header('Location: Store.php?supplier_deleted=1');
                    exit();
                } else {
                    $error_msg = 'Failed to delete supplier: ' . $ds->error;
                }
                $ds->close();
            } else {
                $error_msg = 'Prepare failed: ' . $conn->error;
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_part'])) {
    $orig_part_id = trim($_POST['orig_part_id'] ?? '');
    $part_id = trim($_POST['part_id'] ?? '');
    $part_name = trim($_POST['part_name'] ?? '');
    $part_code = trim($_POST['part_code'] ?? '');
    $category_id = trim($_POST['category_id'] ?? '');
    $qty_in_stock = trim($_POST['qty_in_stock'] ?? '');
    $unit_price = trim($_POST['unit_price'] ?? '');
    $reorder_level = trim($_POST['reorder_level'] ?? '');
    $supplier_id = trim($_POST['supplier_id'] ?? '');

    if ($orig_part_id === '' || $part_id === '' || $part_name === '' || $part_code === '' || $category_id === '' || $supplier_id === '' || $qty_in_stock === '' || $unit_price === '' || $reorder_level === '') {
        $error_msg = 'Please complete all fields before updating the item.';
    } elseif (!ctype_digit($qty_in_stock) || !ctype_digit($reorder_level) || !is_numeric($unit_price)) {
        $error_msg = 'Quantity, reorder level and unit price must be valid numbers.';
    } else {
        $qty_in_stock = (int) $qty_in_stock;
        $unit_price = (float) $unit_price;
        $reorder_level = (int) $reorder_level;

        $update_sql = "UPDATE parts SET part_name = ?, part_code = ?, category_id = ?, qty_in_stock = ?, unit_price = ?, reorder_level = ?, supplier_id = ? WHERE part_id = ?";
        $stmt = $conn->prepare($update_sql);
        if ($stmt) {
            $stmt->bind_param('sssidiss', $part_name, $part_code, $category_id, $qty_in_stock, $unit_price, $reorder_level, $supplier_id, $orig_part_id);
            if ($stmt->execute()) {
                header('Location: Store.php?updated=1');
                exit();
            } else {
                $error_msg = 'Failed to update item: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare update query: ' . $conn->error;
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_part'])) {
    $delete_part_id = trim($_POST['delete_part_id'] ?? '');
    if ($delete_part_id === '') {
        $error_msg = 'Invalid part selected for deletion.';
    } else {
        $delete_sql = "DELETE FROM parts WHERE part_id = ?";
        $stmt = $conn->prepare($delete_sql);
        if ($stmt) {
            $stmt->bind_param('s', $delete_part_id);
            if ($stmt->execute()) {
                header('Location: Store.php?deleted=1');
                exit();
            } else {
                $error_msg = 'Failed to delete item: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare delete query: ' . $conn->error;
        }
    }
}

if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '✅ New store item added successfully.';
} elseif (isset($_GET['supplier_added']) && $_GET['supplier_added'] == 1) {
    $message = '✅ New supplier added successfully.';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = '✅ Store item updated successfully.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '✅ Store item deleted successfully.';
} elseif (isset($_GET['supplier_deleted']) && $_GET['supplier_deleted'] == 1) {
    $message = '✅ Supplier deleted successfully.';
}

// Fetch full supplier list for management table
$all_suppliers = [];
$sup_res = $conn->query("SELECT s.supplier_id, s.supplier_name, s.contact_person, s.phone, s.email, COUNT(p.part_id) AS part_count FROM suppliers s LEFT JOIN parts p ON s.supplier_id = p.supplier_id GROUP BY s.supplier_id ORDER BY s.supplier_name");
if ($sup_res) {
    while ($sr = $sup_res->fetch_assoc()) {
        $all_suppliers[] = $sr;
    }
}

// Fetch parts from database
$parts_query = "SELECT p.*, pc.category_name, s.supplier_name FROM parts p 
                LEFT JOIN part_categories pc ON p.category_id = pc.category_id 
                LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id 
                ORDER BY p.id DESC";
$parts_result = $conn->query($parts_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Inventory | BikeFix</title>
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
            box-shadow: 0 18px 50px rgba(15, 23, 42, 0.08);
            color: #111827;
        }

        .store-form-card h3 {
            margin-bottom: 18px;
            color: #111827;
        }

        .store-form-card label {
            display: block;
            margin-bottom: 8px;
            font-size: 0.95rem;
            color: #374151;
        }

        .store-form-card input,
        .store-form-card select {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #f9fafb;
            color: #111827;
        }

        .store-form-card .btn-primary {
            background: #1f2937;
            border-color: #1f2937;
            color: #ffffff;
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
                        <li class="active">
                            <a href="Store.php"><i data-lucide="package"></i> <span>Store</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Receptionist'): ?>
                        <li class="">
                            <a href="Invoices.php"><i data-lucide="receipt"></i> <span>Invoices</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($role === 'Admin' || $role === 'Mechanic' || $role === 'Receptionist'): ?>
                        <li class="">
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
                            <?php echo htmlspecialchars($admin_name); ?>
                        </span>
                        <span class="user-role">
                            <?php echo htmlspecialchars($_SESSION['role']); ?>
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
                        <input type="text" placeholder="Search store items...">
                    </div>
                </div>

                <div class="header-right">
                    <div class="notification-bell">
                        <i data-lucide="bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <span class="welcome-text">Welcome, <strong>
                                <?php echo htmlspecialchars($admin_name); ?>
                            </strong></span>
                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Profile"
                            class="user-avatar">
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="main-content">
                <div class="content-header">
                    <h2>Store Dashboard</h2>
                    <p>Track workshop spare parts, stock levels, and pricing</p>
                </div>

                <!-- Recent Store Items Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>Inventory Stock</h3>
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
                        <h3>Add New Store Item</h3>
                        <form method="post" action="Store.php">
                            <div class="form-grid"
                                style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px; margin-top: 15px;">
                                <div class="input-group">
                                    <label for="part_id">Part ID</label>
                                    <input type="text" id="part_id" name="part_id" placeholder="PRT003"
                                        value="<?php echo htmlspecialchars($edit_part['part_id'] ?? ''); ?>" <?php echo $edit_part ? 'readonly' : ''; ?> required>
                                    <?php if ($edit_part): ?>
                                        <input type="hidden" name="orig_part_id"
                                            value="<?php echo htmlspecialchars($edit_part['part_id']); ?>">
                                    <?php endif; ?>
                                </div>
                                <div class="input-group">
                                    <label for="part_code">Part Code</label>
                                    <input type="text" id="part_code" name="part_code" placeholder="BP-HON-002"
                                        value="<?php echo htmlspecialchars($edit_part['part_code'] ?? ''); ?>" required>
                                </div>
                                <div class="input-group" style="grid-column: span 2;">
                                    <label for="part_name">Part Name</label>
                                    <input type="text" id="part_name" name="part_name" placeholder="Brake Rotor"
                                        value="<?php echo htmlspecialchars($edit_part['part_name'] ?? ''); ?>" required>
                                </div>
                                <div class="input-group">
                                    <label for="category_id">Category</label>
                                    <select id="category_id" name="category_id" required>
                                        <option value="" disabled selected>Select category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category['category_id']); ?>">
                                                <?php echo htmlspecialchars($category['category_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="input-group">
                                    <div style="display:flex; align-items:flex-end; gap:10px;">
                                        <div style="flex:1;">
                                            <label for="supplier_id">Supplier</label>
                                            <select id="supplier_id" name="supplier_id" required>
                                                <option value="" disabled<?php echo !$edit_part ? ' selected' : ''; ?>>
                                                    Select supplier</option>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <option
                                                        value="<?php echo htmlspecialchars($supplier['supplier_id']); ?>"
                                                        <?php echo ($edit_part && $edit_part['supplier_id'] === $supplier['supplier_id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($supplier['supplier_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <button type="button" id="showSupplierForm" class="btn-primary"
                                            style="padding: 12px 16px; min-width: 140px;">Add Supplier</button>
                                    </div>
                                </div>
                                <div class="input-group">
                                    <label for="qty_in_stock">Quantity in Stock</label>
                                    <input type="number" id="qty_in_stock" name="qty_in_stock"
                                        value="<?php echo htmlspecialchars($edit_part['qty_in_stock'] ?? '0'); ?>"
                                        min="0" required>
                                </div>
                                <div class="input-group">
                                    <label for="unit_price">Unit Price</label>
                                    <input type="number" id="unit_price" name="unit_price" step="0.01" min="0"
                                        placeholder="1200.00"
                                        value="<?php echo htmlspecialchars($edit_part['unit_price'] ?? ''); ?>"
                                        required>
                                </div>
                                <div class="input-group">
                                    <label for="reorder_level">Reorder Level</label>
                                    <input type="number" id="reorder_level" name="reorder_level"
                                        value="<?php echo htmlspecialchars($edit_part['reorder_level'] ?? '0'); ?>"
                                        min="0" required>
                                </div>
                            </div>
                            <div style="margin-top: 20px; text-align: right;">
                                <?php if ($edit_part): ?>
                                    <button type="submit" name="update_part" class="btn-primary">Update Item</button>
                                    <a href="Store.php" class="btn-primary"
                                        style="background:#9ca3af; border-color:#9ca3af; margin-left: 10px;">Cancel Edit</a>
                                <?php else: ?>
                                    <button type="submit" name="add_part" class="btn-primary">Add Item</button>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div id="newSupplierCard"
                            style="display: none; margin-top: 24px; padding: 18px; border: 1px solid #d1d5db; border-radius: 16px; background: #f8fafb;">
                            <h3 style="margin-bottom: 16px;">Add New Supplier</h3>
                            <form method="post" action="Store.php" id="supplierForm" novalidate>
                                <div class="form-grid"
                                    style="display:grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 15px;">
                                    <div>
                                        <label for="supplier_name">Supplier Name <span style="color:#ef4444;">*</span></label>
                                        <input type="text" id="supplier_name" name="supplier_name"
                                            placeholder="Superior Parts" required
                                            title="Letters only, no numbers"
                                            oninput="this.value=this.value.replace(/[0-9]/g,'')">
                                        <small style="color:#6b7280;">Letters only — no numbers allowed</small>
                                    </div>
                                    <div>
                                        <label for="supplier_phone">Phone <span style="color:#ef4444;">*</span></label>
                                        <input type="text" id="supplier_phone" name="supplier_phone"
                                            placeholder="0711234567" required maxlength="10"
                                            title="Exactly 10 digits"
                                            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                                        <small style="color:#6b7280;">10 digits only, no spaces or dashes</small>
                                    </div>
                                    <div>
                                        <label for="contact_person">Contact Person</label>
                                        <input type="text" id="contact_person" name="contact_person"
                                            placeholder="Mr. Perera"
                                            oninput="this.value=this.value.replace(/[0-9]/g,'')">
                                        <small style="color:#6b7280;">Letters only</small>
                                    </div>
                                    <div>
                                        <label for="supplier_email">Email <span style="color:#ef4444;">*</span></label>
                                        <input type="text" id="supplier_email" name="supplier_email"
                                            placeholder="supplier@example.com" required>
                                        <small style="color:#6b7280;">Must contain @ symbol</small>
                                    </div>
                                    <div style="grid-column: span 2;">
                                        <label for="supplier_address">Address</label>
                                        <input type="text" id="supplier_address" name="supplier_address"
                                            placeholder="123 Galle Road, Colombo">
                                    </div>
                                </div>
                                <div id="supplierFormError" style="display:none; margin-top:12px; padding:10px 14px; background:#ffe6e6; color:#a92b2b; border-radius:8px; font-size:0.9rem;"></div>
                                <div style="margin-top: 18px; display: flex; justify-content: flex-end; gap: 10px;">
                                    <button type="button" id="cancelAddSupplier"
                                        style="padding: 10px 16px; border-radius: 12px; border: 1px solid #cbd5e1; background: #ffffff; color: #374151;">Cancel</button>
                                    <button type="submit" name="add_supplier" class="btn-primary">Save Supplier</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Part ID</th>
                                    <th>Part Code</th>
                                    <th>Part Name</th>
                                    <th>Unit Price</th>
                                    <th>Qty in Stock</th>
                                    <th>Supplier</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($parts_result && $parts_result->num_rows > 0): ?>
                                    <?php while ($part = $parts_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td><strong>
                                                    <?php echo htmlspecialchars($part['part_id']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($part['part_code']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($part['part_name']); ?>
                                            </td>
                                            <td>LKR
                                                <?php echo number_format($part['unit_price'], 2); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($part['qty_in_stock']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($part['supplier_name'] ?? $part['supplier_id']); ?>
                                            </td>
                                            <td>
                                                <?php if ($part['qty_in_stock'] == 0): ?>
                                                    <span class="status-badge out-of-stock">Out of Stock</span>
                                                <?php elseif ($part['qty_in_stock'] < 5): ?>
                                                    <span class="status-badge pending">Low Stock</span>
                                                <?php else: ?>
                                                    <span class="status-badge completed">In Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space: nowrap; display:flex; gap:6px; justify-content:flex-end;">
                                                <a href="Store.php?edit=<?php echo urlencode($part['part_id']); ?>"
                                                    class="table-action" title="Edit"><i data-lucide="edit-2"></i></a>
                                                <form method="post" action="Store.php"
                                                    onsubmit="return confirm('Delete this item?');" style="display:inline;">
                                                    <input type="hidden" name="delete_part_id"
                                                        value="<?php echo htmlspecialchars($part['part_id']); ?>">
                                                    <button type="submit" name="delete_part" class="table-action" title="Delete"
                                                        style="background:none; border:none; padding:0; color:#ef4444; cursor:pointer;"><i
                                                            data-lucide="trash-2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" style="text-align: center; padding: 20px;">No parts found in
                                            inventory.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Suppliers Management Table -->
                <div class="table-section" style="margin-top:30px;">
                    <div class="table-header" style="display:flex; align-items:center; justify-content:space-between;">
                        <h3>Suppliers</h3>
                        <span style="font-size:0.85rem; color:#6b7280;"><?php echo count($all_suppliers); ?> supplier(s) registered</span>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Supplier ID</th>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Parts Linked</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($all_suppliers)): ?>
                                    <?php foreach ($all_suppliers as $sup): ?>
                                        <tr class="interactive-row">
                                            <td><strong><?php echo htmlspecialchars($sup['supplier_id']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($sup['supplier_name']); ?></td>
                                            <td><?php echo htmlspecialchars($sup['contact_person'] ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($sup['phone'] ?: '—'); ?></td>
                                            <td><?php echo htmlspecialchars($sup['email'] ?: '—'); ?></td>
                                            <td>
                                                <?php if ((int)$sup['part_count'] > 0): ?>
                                                    <span class="status-badge completed"><?php echo (int)$sup['part_count']; ?> parts</span>
                                                <?php else: ?>
                                                    <span class="status-badge pending">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="white-space:nowrap;">
                                                <?php if ((int)$sup['part_count'] === 0): ?>
                                                    <form method="post" action="Store.php" style="display:inline;"
                                                        onsubmit="return confirm('Delete supplier &quot;<?php echo htmlspecialchars(addslashes($sup['supplier_name'])); ?>&quot;? This cannot be undone.');">
                                                        <input type="hidden" name="del_supplier_id" value="<?php echo htmlspecialchars($sup['supplier_id']); ?>">
                                                        <button type="submit" name="delete_supplier" class="table-action" title="Delete Supplier"
                                                            style="background:none; border:none; color:#ef4444; cursor:pointer; padding:0;"><i data-lucide="trash-2"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <span title="Cannot delete — parts linked" style="color:#9ca3af; cursor:not-allowed;"><i data-lucide="lock"></i></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="7" style="text-align:center; padding:20px;">No suppliers registered yet.</td></tr>
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
            if (typeof lucide !== 'undefined') { try { lucide.createIcons(); } catch(e) {} }

            const showSupplierBtn  = document.getElementById('showSupplierForm');
            const newSupplierCard  = document.getElementById('newSupplierCard');
            const cancelSupplierBtn= document.getElementById('cancelAddSupplier');
            const supplierForm     = document.getElementById('supplierForm');
            const supplierFormError= document.getElementById('supplierFormError');

            if (showSupplierBtn && newSupplierCard) {
                showSupplierBtn.addEventListener('click', function () {
                    newSupplierCard.style.display = 'block';
                    newSupplierCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                });
            }
            if (cancelSupplierBtn && newSupplierCard) {
                cancelSupplierBtn.addEventListener('click', function () {
                    newSupplierCard.style.display = 'none';
                });
            }

            // Client-side validation before submit
            if (supplierForm) {
                supplierForm.addEventListener('submit', function (e) {
                    const name    = document.getElementById('supplier_name').value.trim();
                    const phone   = document.getElementById('supplier_phone').value.trim();
                    const email   = document.getElementById('supplier_email').value.trim();
                    const contact = document.getElementById('contact_person').value.trim();
                    let errMsg = '';

                    if (name === '') {
                        errMsg = 'Supplier name is required.';
                    } else if (/[0-9]/.test(name)) {
                        errMsg = 'Supplier name must contain letters only — no numbers allowed.';
                    } else if (contact !== '' && /[0-9]/.test(contact)) {
                        errMsg = 'Contact person name must contain letters only — no numbers allowed.';
                    } else if (phone === '') {
                        errMsg = 'Phone number is required.';
                    } else if (!/^[0-9]{10}$/.test(phone)) {
                        errMsg = 'Phone number must be exactly 10 digits (numbers only, no spaces or dashes).';
                    } else if (email === '') {
                        errMsg = 'Email address is required.';
                    } else if (email.indexOf('@') === -1) {
                        errMsg = 'Email address must contain the @ symbol.';
                    } else if (!/^[^@]+@[^@]+\.[^@]+$/.test(email)) {
                        errMsg = 'Please enter a valid email address (e.g. name@domain.com).';
                    }

                    if (errMsg) {
                        e.preventDefault();
                        supplierFormError.textContent = '⚠️ ' + errMsg;
                        supplierFormError.style.display = 'block';
                        supplierFormError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        supplierFormError.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>

</html>