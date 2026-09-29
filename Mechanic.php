<?php
// Mechanic.php - Mechanic Dashboard View
require_once 'db.php';

// Check Mechanic role session authentication
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'Mechanic') {
    header("Location: login.php");
    exit();
}

$mechanic_id = $_SESSION['staff_id'];
$mechanic_name = $_SESSION['name'];

// Fetch jobs assigned specifically to this mechanic
$jobs_query = "SELECT j.*, v.reg_no, c.name AS customer_name, s.status_name 
               FROM job_cards j
               LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
               LEFT JOIN customers c ON j.customer_id = c.customer_id
               LEFT JOIN job_statuses s ON j.status_id = s.status_id
               WHERE j.mechanic_id = ?
               ORDER BY j.id DESC";

$stmt = $conn->prepare($jobs_query);
$stmt->bind_param("s", $mechanic_id);
$stmt->execute();
$jobs_result = $stmt->get_result();

// Count their Active Repairs (In Progress)
$active_repairs_query = "SELECT COUNT(*) AS total FROM job_cards j 
                         JOIN job_statuses s ON j.status_id = s.status_id 
                         WHERE j.mechanic_id = ? AND s.status_name = 'In Progress'";
$stmt_active = $conn->prepare($active_repairs_query);
$stmt_active->bind_param("s", $mechanic_id);
$stmt_active->execute();
$active_repairs = $stmt_active->get_result()->fetch_assoc()['total'];

// Count their Pending Tasks (Pending)
$pending_tasks_query = "SELECT COUNT(*) AS total FROM job_cards j 
                        JOIN job_statuses s ON j.status_id = s.status_id 
                        WHERE j.mechanic_id = ? AND s.status_name = 'Pending'";
$stmt_pending = $conn->prepare($pending_tasks_query);
$stmt_pending->bind_param("s", $mechanic_id);
$stmt_pending->execute();
$pending_tasks = $stmt_pending->get_result()->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mechanic Dashboard | BikeFix</title>
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
                    <li class="active">
                        <a href="Mechanic.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                    </li>
                    <li class="">
                        <a href="Store.php"><i data-lucide="package"></i> <span>Store</span></a>
                    </li>
                    <li class="">
                        <a href="Job_cad.php"><i data-lucide="file-text"></i> <span>Job Card</span></a>
                    </li>

                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Mechanic"
                        class="user-avatar-sm">
                    <div class="user-details">
                        <span class="user-name">
                            <?php echo htmlspecialchars($mechanic_name); ?>
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
                        <input type="text" placeholder="Search assigned repairs...">
                    </div>
                </div>

                <div class="header-right">
                    <div class="notification-bell">
                        <i data-lucide="bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <span class="welcome-text">Welcome, <strong>
                                <?php echo htmlspecialchars($mechanic_name); ?>
                            </strong></span>
                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Profile"
                            class="user-avatar">
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="main-content">
                <div class="content-header">
                    <h2>Mechanic Dashboard</h2>
                    <p>Track your assigned repair jobs and tasks</p>
                </div>

                <div class="stats-grid">
                    <div class="stat-card blue">
                        <div class="stat-icon"><i data-lucide="activity"></i></div>
                        <div class="stat-info">
                            <span class="stat-value">
                                <?php echo $active_repairs; ?>
                            </span>
                            <span class="stat-label">Active Repairs</span>
                        </div>
                        <div class="stat-mini-chart">
                            <div class="progress-line" style="width: 70%"></div>
                        </div>
                    </div>

                    <div class="stat-card orange">
                        <div class="stat-icon"><i data-lucide="clock"></i></div>
                        <div class="stat-info">
                            <span class="stat-value">
                                <?php echo $pending_tasks; ?>
                            </span>
                            <span class="stat-label">Pending Tasks</span>
                        </div>
                        <div class="stat-mini-chart">
                            <div class="progress-line" style="width: 40%"></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Repairs Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>Assigned Repairs</h3>
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
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($jobs_result && $jobs_result->num_rows > 0): ?>
                                    <?php while ($job = $jobs_result->fetch_assoc()): ?>
                                        <tr class="interactive-row">
                                            <td>
                                                <?php echo htmlspecialchars($job['job_id']); ?>
                                            </td>
                                            <td><strong>
                                                    <?php echo htmlspecialchars($job['reg_no']); ?>
                                                </strong></td>
                                            <td>
                                                <?php echo htmlspecialchars($job['customer_name']); ?>
                                            </td>
                                            <td>
                                                <?php echo htmlspecialchars($mechanic_name); ?>
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
                                            <td><a class="table-action"
                                                    href="Job_cad.php?view_job=<?php echo urlencode($job['job_id']); ?>"
                                                    title="View Job Card"><i data-lucide="file-text"></i></a></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">No jobs currently
                                            assigned to you.</td>
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