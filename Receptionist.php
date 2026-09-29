<?php
// Receptionist.php - Receptionist Dashboard
require_once 'db.php';

// Check Receptionist role session authentication
if (!isset($_SESSION['staff_id']) || $_SESSION['role'] !== 'Receptionist') {
    header("Location: login.php");
    exit();
}

$receptionist_name = $_SESSION['name'];

// Fetch KPI statistics for the receptionist
// 1. Count Active Repairs (In Progress)
$active_repairs_query = "SELECT COUNT(*) AS total FROM job_cards j 
                         JOIN job_statuses s ON j.status_id = s.status_id 
                         WHERE s.status_name = 'In Progress'";
$res_active = $conn->query($active_repairs_query);
$active_repairs = ($res_active) ? $res_active->fetch_assoc()['total'] : 0;

// 2. Count Pending Tasks (Pending)
$pending_tasks_query = "SELECT COUNT(*) AS total FROM job_cards j 
                        JOIN job_statuses s ON j.status_id = s.status_id 
                        WHERE s.status_name = 'Pending'";
$res_pending = $conn->query($pending_tasks_query);
$pending_tasks = ($res_pending) ? $res_pending->fetch_assoc()['total'] : 0;

// 3. Count Completed Jobs (Ready for delivery)
$completed_query = "SELECT COUNT(*) AS total FROM job_cards j 
                      JOIN job_statuses s ON j.status_id = s.status_id 
                      WHERE s.status_name = 'Completed'";
$res_completed = $conn->query($completed_query);
$completed_repairs = ($res_completed) ? $res_completed->fetch_assoc()['total'] : 0;

// Fetch Recent Repair Jobs
$jobs_query = "SELECT j.job_id, v.reg_no, c.name AS customer_name, m.name AS mechanic_name, s.status_name 
               FROM job_cards j
               LEFT JOIN vehicles v ON j.vehicle_id = v.vehicle_id
               LEFT JOIN customers c ON j.customer_id = c.customer_id
               LEFT JOIN staff m ON j.mechanic_id = m.staff_id
               LEFT JOIN job_statuses s ON j.status_id = s.status_id
               ORDER BY j.id DESC LIMIT 5";
$jobs_result = $conn->query($jobs_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receptionist Dashboard | BikeFix</title>
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
                        <a href="Receptionist.php"><i data-lucide="layout-dashboard"></i> <span>Dashboard</span></a>
                    </li>
                    <li>
                        <a href="customer.php"><i data-lucide="users"></i> <span>Customer</span></a>
                    </li>
                    <li>
                        <a href="Invoices.php"><i data-lucide="receipt"></i> <span>Invoices</span></a>
                    </li>
                      <li>
                        <a href="Job_cad.php"><i data-lucide="file-text"></i> <span>Job Card</span></a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Admin" class="user-avatar-sm">
                    <div class="user-details">
                        <span class="user-name">
                            <?php echo htmlspecialchars($receptionist_name); ?>
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
                        <input type="text" placeholder="Search vehicles, jobs, customers...">
                    </div>
                </div>

                <div class="header-right">
                    <div class="notification-bell">
                        <i data-lucide="bell"></i>
                        <span class="badge">3</span>
                    </div>
                    <div class="user-profile">
                        <span class="welcome-text">Welcome, <strong>
                                <?php echo htmlspecialchars($receptionist_name); ?>
                            </strong></span>
                        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=Admin" alt="Profile"
                            class="user-avatar">
                    </div>
                </div>
            </header>

            <!-- Main Scrollable Content -->
            <main class="main-content">
                <div class="content-header">
                    <h2>Receptionist Dashboard</h2>
                    <p>Overview of active repair orders and customer queries</p>
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

                    <div class="stat-card green">
                        <div class="stat-icon"><i data-lucide="check-circle"></i></div>
                        <div class="stat-info">
                            <span class="stat-value">
                                <?php echo $completed_repairs; ?>
                            </span>
                            <span class="stat-label">Completed Jobs</span>
                        </div>
                        <div class="stat-mini-chart">
                            <div class="progress-line" style="width: 100%"></div>
                        </div>
                    </div>
                </div>

                <!-- Recent Repairs Table -->
                <div class="table-section">
                    <div class="table-header">
                        <h3>Recent Repair Order Status</h3>
                        <a href="Job_cad.php" style="text-decoration: none;"><button class="btn-primary">View All
                                Jobs</button></a>
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
                                                <?php echo htmlspecialchars($job['mechanic_name'] ?? 'Unassigned'); ?>
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
                                            <td><button class="table-action"><i data-lucide="more-horizontal"></i></button></td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" style="text-align: center; padding: 20px;">No recent repairs found.
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