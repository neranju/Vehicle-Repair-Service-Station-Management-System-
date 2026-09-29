<?php
// Staff.php - Staff Registry Page
require_once 'db.php';

// Check Admin role session authentication
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'Admin') {
    header("Location: login.php");
    exit();
}

$admin_name = $_SESSION['name'];
$session_role = $_SESSION['role'];

$message = '';
$error_msg = '';

// Handle add staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = trim($_POST['role_id'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';

    if ($name === '' || $phone === '' || $role_id === '' || $password === '') {
        $error_msg = 'Please fill required fields (name, phone, role, password).';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error_msg = 'Phone number must be exactly 10 digits and contain numbers only.';
    } elseif (!preg_match('/^[A-Za-z\s.]+$/', $name)) {
        $error_msg = 'Staff member name must contain only alphabetic letters, spaces or dots.';
    } elseif ($password !== $password_confirm) {
        $error_msg = 'Passwords do not match.';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $hourly_rate_val = ($hourly_rate === '') ? null : (float) $hourly_rate;

        $insert_sql = "INSERT INTO staff (name, phone, email, role_id, password_hash, hourly_rate) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        if ($stmt) {
            $stmt->bind_param('sssssd', $name, $phone, $email, $role_id, $hashed, $hourly_rate_val);
            if ($stmt->execute()) {
                $new_id = $conn->insert_id;
                $staff_code = sprintf('STF%03d', $new_id);
                $upd = $conn->prepare("UPDATE staff SET staff_id = ? WHERE id = ?");
                if ($upd) {
                    $upd->bind_param('si', $staff_code, $new_id);
                    $upd->execute();
                    $upd->close();
                }
                header('Location: Staff.php?added=1');
                exit();
            } else {
                $error_msg = 'Failed to add staff: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare query: ' . $conn->error;
        }
    }
}

// Handle delete staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_staff'])) {
    $del_staff_id = trim($_POST['delete_staff_id'] ?? '');
    if ($del_staff_id === '') {
        $error_msg = 'Invalid staff selected for deletion.';
    } else {
        // Check references (e.g., job_cards.mechanic_id)
        $chk = $conn->prepare("SELECT COUNT(*) AS cnt FROM job_cards WHERE mechanic_id = ?");
        if ($chk) {
            $chk->bind_param('s', $del_staff_id);
            $chk->execute();
            $res = $chk->get_result()->fetch_assoc();
            $count = (int) $res['cnt'];
            $chk->close();
            if ($count > 0) {
                $error_msg = 'Cannot delete: This staff member has ' . $count . ' assigned job card(s). Reassign those jobs first.';
            } else {
                $del = $conn->prepare("DELETE FROM staff WHERE staff_id = ?");
                if ($del) {
                    $del->bind_param('s', $del_staff_id);
                    if ($del->execute()) {
                        header('Location: Staff.php?deleted=1');
                        exit();
                    } else {
                        $error_msg = 'Failed to delete staff: ' . $del->error;
                    }
                    $del->close();
                } else {
                    $error_msg = 'Failed to prepare delete: ' . $conn->error;
                }
            }
        }
    }
}

// Handle update staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_staff'])) {
    $orig_staff_id = trim($_POST['orig_staff_id'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role_id = trim($_POST['role_id'] ?? '');
    $hourly_rate = trim($_POST['hourly_rate'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($orig_staff_id === '' || $name === '' || $phone === '' || $role_id === '') {
        $error_msg = 'Please fill required fields (name, phone, role).';
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error_msg = 'Phone number must be exactly 10 digits and contain numbers only.';
    } elseif (!preg_match('/^[A-Za-z\s.]+$/', $name)) {
        $error_msg = 'Staff member name must contain only alphabetic letters, spaces or dots.';
    } else {
        $params = [];
        if ($hourly_rate === '') {
            $hourly_val = null;
        } else {
            $hourly_val = (float) $hourly_rate;
        }

        if ($password !== '') {
            $pw_hash = password_hash($password, PASSWORD_DEFAULT);
            if ($hourly_val === null) {
                $update_sql = "UPDATE staff SET name = ?, phone = ?, email = ?, role_id = ?, password_hash = ?, hourly_rate = NULL WHERE staff_id = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $stmt->bind_param('ssssss', $name, $phone, $email, $role_id, $pw_hash, $orig_staff_id);
                }
            } else {
                $update_sql = "UPDATE staff SET name = ?, phone = ?, email = ?, role_id = ?, password_hash = ?, hourly_rate = ? WHERE staff_id = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $stmt->bind_param('sssssds', $name, $phone, $email, $role_id, $pw_hash, $hourly_val, $orig_staff_id);
                }
            }
        } else {
            if ($hourly_val === null) {
                $update_sql = "UPDATE staff SET name = ?, phone = ?, email = ?, role_id = ?, hourly_rate = NULL WHERE staff_id = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $stmt->bind_param('sssss', $name, $phone, $email, $role_id, $orig_staff_id);
                }
            } else {
                $update_sql = "UPDATE staff SET name = ?, phone = ?, email = ?, role_id = ?, hourly_rate = ? WHERE staff_id = ?";
                $stmt = $conn->prepare($update_sql);
                if ($stmt) {
                    $stmt->bind_param('ssssds', $name, $phone, $email, $role_id, $hourly_val, $orig_staff_id);
                }
            }
        }

        if (isset($stmt)) {
            if ($stmt->execute()) {
                header('Location: Staff.php?updated=1');
                exit();
            } else {
                $error_msg = 'Failed to update staff: ' . $stmt->error;
            }
            $stmt->close();
        } else {
            $error_msg = 'Failed to prepare update: ' . $conn->error;
        }
    }
}

// Handle GET messages after redirect
if (isset($_GET['added']) && $_GET['added'] == 1) {
    $message = '✅ New staff member registered successfully.';
} elseif (isset($_GET['updated']) && $_GET['updated'] == 1) {
    $message = '✅ Staff record updated successfully.';
} elseif (isset($_GET['deleted']) && $_GET['deleted'] == 1) {
    $message = '✅ Staff member deleted successfully.';
}

// If editing, load staff data to prefill the form
$edit_staff = null;
if (isset($_GET['edit']) && $_GET['edit'] !== '') {
    $sid = $conn->real_escape_string($_GET['edit']);
    $res = $conn->query("SELECT * FROM staff WHERE staff_id = '" . $sid . "' LIMIT 1");
    if ($res && $res->num_rows === 1) {
        $edit_staff = $res->fetch_assoc();
    }
}

// Fetch roles for form
$roles_result = $conn->query("SELECT role_id, role_name FROM roles ORDER BY role_name");
$roles = [];
if ($roles_result) {
    while ($role = $roles_result->fetch_assoc()) {
        $roles[] = $role;
    }
}

// Fetch all staff members joined with their role details
$staff_query = "SELECT s.*, r.role_name FROM staff s 
                JOIN roles r ON s.role_id = r.role_id 
                ORDER BY s.id DESC";
$staff_result = $conn->query($staff_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Registry | BikeFix</title>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700&display=swap" rel="stylesheet">

    <script src="https://unpkg.com/lucide@latest"></script>
    <link rel="stylesheet" href="CSS/dashboard.css">
    <style>
        .store-form-card {
            background: #ffffff;
            border: 1px solid #e6edf3;
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
            color: #0f172a;
        }

        .store-form-card h3 {
            margin: 0 0 10px 0;
            color: #0f172a;
        }

        .store-form-card label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-size: 0.95rem;
        }

        .store-form-card input,
        .store-form-card select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e6e9ee;
            border-radius: 10px;
            background: #fbfdfe;
            color: #0f172a;
            box-sizing: border-box;
        }

        .store-form-card .form-grid {
            gap: 12px;
        }

        .store-form-card .btn-primary {
            background: #0f172a;
            color: #fff;
            border-radius: 10px;
            padding: 10px 16px;
            border: none;
            cursor: pointer;
        }

        .staff-actions form {
            display: inline;
        }

        .table-responsive .table-action {
            background: transparent;
            border: none;
            cursor: pointer;
            color: #ef4444;
        }

        @media (max-width: 720px) {
            .store-form-card .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <div class="dashboard-layout">

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
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin' || $session_role === 'Receptionist'): ?>
                        <li class="">
                            <a href="customer.php"><i data-lucide="users"></i> <span>Customer</span></a>
                        </li>
                    <?php endif; ?>

                    <?php if ($session_role === 'Admin'): ?>
                        <li class="active">
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
                            <?php echo htmlspecialchars($session_role); ?>
                        </span>
                    </div>
                </div>
                <a href="logout.php" class="logout-btn" title="Logout"
                    style="color: inherit; display: flex; align-items: center;"><i data-lucide="log-out"></i></a>
            </div>
        </aside>


        <div class="content-wrapper">

            <header class="top-header">
                <div class="header-left">
                    <button id="sidebarToggle" class="mobile-toggle">
                        <i data-lucide="menu"></i>
                    </button>
                    <div class="search-bar">
                        <i data-lucide="search"></i>
                        <input type="text" placeholder="Search staff members...">
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

            <main class="main-content">
                <div class="content-header">
                    <h2>Staff Directory</h2>
                    <p>Manage workshop mechanics, receptionists, and administrators</p>
                </div>

                <div class="table-section">
                    <div class="table-header">
                        <h3>Employee Roster</h3>
                    </div>
                    <?php if (!empty($message)): ?>
                        <div class="notification success"
                            style="margin:12px 0; padding:10px; background:#f0fdf4; color:#064e3b; border-radius:8px;">
                            <?php echo htmlspecialchars($message); ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($error_msg)): ?>
                        <div class="notification error"
                            style="margin:12px 0; padding:10px; background:#fff5f5; color:#7f1d1d; border-radius:8px;">
                            <?php echo htmlspecialchars($error_msg); ?>
                        </div>
                    <?php endif; ?>

                    <div class="store-form-card" style="margin-bottom:18px; padding:18px;">
                        <?php if (isset($edit_staff) && $edit_staff): ?>
                            <h3 style="margin-bottom:10px;">Edit Staff -
                                <?php echo htmlspecialchars($edit_staff['staff_id']); ?>
                            </h3>
                            <form method="post" action="Staff.php">
                                <input type="hidden" name="orig_staff_id"
                                    value="<?php echo htmlspecialchars($edit_staff['staff_id']); ?>">
                                <div class="form-grid"
                                    style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px;">
                                    <div>
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" pattern="[A-Za-z\s.]+"
                                            oninput="this.value = this.value.replace(/[^A-Za-z\s.]/g, '');"
                                            value="<?php echo htmlspecialchars($edit_staff['name']); ?>" required>
                                    </div>
                                    <div>
                                        <label for="phone">Phone</label>
                                        <input type="text" id="phone" name="phone" pattern="[0-9]{10}" maxlength="10"
                                            minlength="10"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);"
                                            value="<?php echo htmlspecialchars($edit_staff['phone']); ?>" required>
                                    </div>
                                    <div>
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email"
                                            value="<?php echo htmlspecialchars($edit_staff['email']); ?>">
                                    </div>
                                    <div>
                                        <label for="role_id">Role</label>
                                        <select id="role_id" name="role_id" required>
                                            <option value="" disabled>Select role</option>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?php echo htmlspecialchars($r['role_id']); ?>" <?php echo ($r['role_id'] === $edit_staff['role_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($r['role_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="hourly_rate">Hourly Rate</label>
                                        <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0"
                                            value="<?php echo htmlspecialchars($edit_staff['hourly_rate']); ?>">
                                    </div>
                                    <div>
                                        <label for="password">Password (leave blank to keep existing)</label>
                                        <input type="password" id="password" name="password">
                                    </div>
                                    <div>
                                        <label for="password_confirm">Confirm Password</label>
                                        <input type="password" id="password_confirm" name="password_confirm">
                                    </div>
                                </div>
                                <div style="margin-top:12px; text-align:right;">
                                    <button type="submit" name="update_staff" class="btn-primary">Update Staff</button>
                                    <a href="Staff.php" style="margin-left:10px; color:#374151;">Cancel</a>
                                </div>
                            </form>
                        <?php else: ?>
                            <h3 style="margin-bottom:10px;">Register New Staff</h3>
                            <form method="post" action="Staff.php">
                                <div class="form-grid"
                                    style="display:grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap:12px;">
                                    <div>
                                        <label for="name">Full Name</label>
                                        <input type="text" id="name" name="name" pattern="[A-Za-z\s.]+"
                                            oninput="this.value = this.value.replace(/[^A-Za-z\s.]/g, '');" required>
                                    </div>
                                    <div>
                                        <label for="phone">Phone</label>
                                        <input type="text" id="phone" name="phone" pattern="[0-9]{10}" maxlength="10"
                                            minlength="10"
                                            oninput="this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);"
                                            required>
                                    </div>
                                    <div>
                                        <label for="email">Email</label>
                                        <input type="email" id="email" name="email">
                                    </div>
                                    <div>
                                        <label for="role_id">Role</label>
                                        <select id="role_id" name="role_id" required>
                                            <option value="" disabled selected>Select role</option>
                                            <?php foreach ($roles as $r): ?>
                                                <option value="<?php echo htmlspecialchars($r['role_id']); ?>">
                                                    <?php echo htmlspecialchars($r['role_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="hourly_rate">Hourly Rate</label>
                                        <input type="number" id="hourly_rate" name="hourly_rate" step="0.01" min="0">
                                    </div>
                                    <div>
                                        <label for="password">Password</label>
                                        <input type="password" id="password" name="password" required>
                                    </div>
                                    <div>
                                        <label for="password_confirm">Confirm Password</label>
                                        <input type="password" id="password_confirm" name="password_confirm" required>
                                    </div>
                                </div>
                                <div style="margin-top:12px; text-align:right;">
                                    <button type="submit" name="add_staff" class="btn-primary">Create Staff</button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Staff ID</th>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>System Role</th>
                                    <th>Hourly Rate</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($staff_result && $staff_result->num_rows > 0): ?>
                                    <?php while ($employee = $staff_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td><strong>
                                                    <?php echo htmlspecialchars($employee['staff_id']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($employee['name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($employee['phone']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($employee['email'] ?? 'N/A'); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge_role = $employee['role_name'];
                                                $badge_class = "pending";
                                                if ($badge_role === 'Admin')
                                                    $badge_class = "completed";
                                                elseif ($badge_role === 'Mechanic')
                                                    $badge_class = "progress";
                                                ?>
                                                <span class="status-badge <?php echo $badge_class; ?>">
                                                    <?php echo htmlspecialchars($badge_role); ?>
                                                </span>
                                            </td>
                                            <td>LKR
                                                <?php echo number_format($employee['hourly_rate'] ?? 0.00, 2); ?>
                                            </td>
                                            <td>
                                                <a href="Staff.php?edit=<?php echo urlencode($employee['staff_id']); ?>"
                                                    class="table-action" title="Edit"><i data-lucide="edit-2"></i></a>
                                                <form method="post" action="Staff.php"
                                                    onsubmit="return confirm('Delete this staff member?');"
                                                    style="display:inline">
                                                    <input type="hidden" name="delete_staff_id"
                                                        value="<?php echo htmlspecialchars($employee['staff_id']); ?>">
                                                    <button type="submit" name="delete_staff" class="table-action"
                                                        title="Delete"><i data-lucide="trash-2"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 20px;">No staff records found.
                                        </td>
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