<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();

// Get summary statistics
$summary_data = [];

// Total investigations by rank
$stmt = $pdo->query("
    SELECT officer_rank, COUNT(*) as count 
    FROM preliminary_investigations 
    GROUP BY officer_rank 
    ORDER BY count DESC
");
$summary_data['by_rank'] = $stmt->fetchAll();

// Total investigations by action taken
$stmt = $pdo->query("
    SELECT at.action_name, COUNT(*) as count 
    FROM preliminary_investigations pi
    JOIN actions_taken at ON pi.action_taken_id = at.id
    GROUP BY at.action_name 
    ORDER BY count DESC
");
$summary_data['by_action'] = $stmt->fetchAll();

// Total investigations by status
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM preliminary_investigations 
    GROUP BY status 
    ORDER BY count DESC
");
$summary_data['by_status'] = $stmt->fetchAll();

// Monthly statistics for current year
$current_year = date('Y');
$stmt = $pdo->prepare("
    SELECT MONTH(created_at) as month, COUNT(*) as count 
    FROM preliminary_investigations 
    WHERE YEAR(created_at) = ?
    GROUP BY MONTH(created_at) 
    ORDER BY month
");
$stmt->execute([$current_year]);
$monthly_data = $stmt->fetchAll();

// Create full year array
$monthly_stats = array_fill(1, 12, 0);
foreach ($monthly_data as $month) {
    $monthly_stats[$month['month']] = $month['count'];
}

// Recent activity (last 30 days)
$stmt = $pdo->query("
    SELECT COUNT(*) as count 
    FROM preliminary_investigations 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$recent_activity = $stmt->fetch()['count'];

// Handle filter requests
$filtered_results = [];
$filter_applied = false;

if ($_GET && (isset($_GET['rank']) || isset($_GET['action']) || isset($_GET['status']))) {
    $filter_applied = true;
    $where_conditions = [];
    $params = [];
    
    if (!empty($_GET['rank'])) {
        $where_conditions[] = "pi.officer_rank = ?";
        $params[] = $_GET['rank'];
    }
    
    if (!empty($_GET['action'])) {
        $where_conditions[] = "at.action_name = ?";
        $params[] = $_GET['action'];
    }
    
    if (!empty($_GET['status'])) {
        $where_conditions[] = "pi.status = ?";
        $params[] = $_GET['status'];
    }
    
    $where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";
    
    $stmt = $pdo->prepare("
        SELECT pi.*, at.action_name
        FROM preliminary_investigations pi
        JOIN actions_taken at ON pi.action_taken_id = at.id
        $where_clause
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute($params);
    $filtered_results = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary & Reports - Disciplinary Branch SBHQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="image/favicon.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            overflow-x: hidden;
        }
        
        /* Enhanced Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            transition: all 0.3s ease;
            z-index: 1000;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            flex-shrink: 0;
        }
        
        .sidebar-header h4 {
            color: #fff;
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .sidebar-header p {
            color: #bdc3c7;
            margin: 0;
            font-size: 0.85rem;
        }
        
        .sidebar-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }
        
        .sidebar-menu-wrapper {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        .sidebar-menu-wrapper::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar-menu-wrapper::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-menu-wrapper::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 2px;
        }
        
        .sidebar-menu-wrapper::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.5);
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
            margin: 0;
        }
        
        .sidebar-menu li {
            margin: 0;
        }
        
        .sidebar-menu a {
            display: block;
            padding: 1rem 1.5rem;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
            position: relative;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: linear-gradient(90deg, rgba(52, 152, 219, 0.3), rgba(52, 152, 219, 0.1));
            color: #3498db;
            border-left-color: #3498db;
        }
        
        .sidebar-menu i {
            width: 20px;
            margin-right: 10px;
        }
     
      
        /* Developer Credit */
        .developer-credit {
            position: absolute;
            bottom: 1rem;
            left: 0;
            right: 0;
            text-align: center;
            padding: 0 1rem;
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.7rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 1rem;
        }
        
        /* Enhanced Header Styles */
        .main-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem 2rem;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .menu-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            font-size: 1.2rem;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 8px 12px;
        }
        
        .menu-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: scale(1.05);
        }
        
        .page-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .header-subtitle {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
            margin-top: 0.2rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-profile {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.7rem 1.2rem;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 25px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
        }
        
        .user-details {
            color: white;
        }
        
        .user-name {
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .user-position {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 0;
            transition: margin-left 0.3s ease;
            min-height: 100vh;
        }
        
        .content-wrapper {
            padding: 2rem;
        }
        
        /* Summary Cards */
        .summary-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .filter-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        
        .filter-form .form-control,
        .filter-form .form-select {
            border: 2px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border-radius: 10px;
        }
        
        .filter-form .form-control:focus,
        .filter-form .form-select:focus {
            border-color: rgba(255, 255, 255, 0.5);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            box-shadow: none;
        }
        
        .filter-form .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .filter-form option {
            color: #333;
        }
        
        .btn-filter {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            border-radius: 10px;
            padding: 0.7rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-filter:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-2px);
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            border-radius: 10px;
            background: #f8f9fa;
            margin-bottom: 1rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        /* Table */
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px 15px 0 0;
        }
        
        .table-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .table th {
            background: #f8f9fa;
            border: none;
            font-weight: 600;
            color: #2c3e50;
            padding: 1rem;
        }
        
        .table td {
            border: none;
            padding: 1rem;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Overlay */
        .sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }
        
        .sidebar-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-header {
                padding: 1rem;
            }
            
            .content-wrapper {
                padding: 1rem;
            }
            
            .user-details {
                display: none;
            }
            
            .page-title {
                font-size: 1.4rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h4>Disciplinary Branch</h4>
            <p>POLICE SPECIAL BRACNH</p>
        </div>
        
        <div class="sidebar-content">
            <div class="sidebar-menu-wrapper">
                <ul class="sidebar-menu">
                    <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="search_officer_status.php"><i class="fas fa-search"></i> Search Officer Status</a></li>
                    <li><a href="preliminary_investigation.php"><i class="fas fa-file-alt"></i> Preliminary Investigations</a></li>
                    <li><a href="charge_sheets.php"><i class="fas fa-file-contract"></i> Charge Sheets</a></li>
                    <li><a href="formal_disciplinary_investigation.php"><i class="fas fa-gavel"></i> Formal Investigations</a></li>
                    <li><a href="summary.php" class="active"><i class="fas fa-chart-bar"></i> Summary & Reports</a></li>
                    <li><a href="delete_records.php"><i class="fas fa-trash-alt"></i> Delete Records</a></li>
                    <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </div>
            
            <!-- Developer Credit -->
            <div class="developer-credit">
                Developed by<br>
                PC 93037 SMM MADHUSHANTHA
            </div>
        </div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
     
    <!-- Main Content -->
    <div class="main-content">
        <!-- Enhanced Header -->
        <header class="main-header">
            <div class="header-content">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-3">
                        <h1 class="page-title">Summary & Reports</h1>
                        <div class="header-subtitle">Analytics and filtered reports</div>
                    </div>
                </div>
                
                <div class="user-info">
                    <div class="user-profile">
                        <div class="user-avatar">
                            <?php echo substr($user['name_with_initials'], 0, 1); ?>
                        </div>
                        <div class="user-details">
                            <div class="user-name">
                                <?php echo $user['rank'] . ' ' . $user['official_number']; ?>
                            </div>
                            <div class="user-position">
                                <?php echo $user['name_with_initials']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Content -->
        <div class="content-wrapper">
            <!-- Quick Stats -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo array_sum(array_column($summary_data['by_rank'], 'count')); ?></div>
                        <div class="stat-label">Total Investigations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $recent_activity; ?></div>
                        <div class="stat-label">Last 30 Days</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($summary_data['by_rank']); ?></div>
                        <div class="stat-label">Different Ranks</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo count($summary_data['by_action']); ?></div>
                        <div class="stat-label">Action Types</div>
                    </div>
                </div>
            </div>
            
            <!-- Charts -->
            <div class="row">
                <div class="col-md-6">
                    <div class="summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-star me-2"></i>
                            Investigations by Rank
                        </h5>
                        <div class="chart-container">
                            <canvas id="rankChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-cogs me-2"></i>
                            Investigations by Action
                        </h5>
                        <div class="chart-container">
                            <canvas id="actionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-8">
                    <div class="summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-calendar me-2"></i>
                            Monthly Statistics for <?php echo $current_year; ?>
                        </h5>
                        <div class="chart-container">
                            <canvas id="monthlyChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="summary-card">
                        <h5 class="mb-4">
                            <i class="fas fa-info-circle me-2"></i>
                            Status Overview
                        </h5>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-card">
                <h5 class="mb-4">
                    <i class="fas fa-filter me-2"></i>
                    Filters and Special Reports
                </h5>
                
                <form method="GET" action="" class="filter-form">
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Rank</label>
                            <select name="rank" class="form-select">
                                <option value="">All Ranks</option>
                                <?php foreach ($summary_data['by_rank'] as $rank_data): ?>
                                    <option value="<?php echo $rank_data['officer_rank']; ?>" 
                                            <?php echo (isset($_GET['rank']) && $_GET['rank'] == $rank_data['officer_rank']) ? 'selected' : ''; ?>>
                                        <?php echo $rank_data['officer_rank']; ?> (<?php echo $rank_data['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <?php foreach ($summary_data['by_action'] as $action_data): ?>
                                    <option value="<?php echo $action_data['action_name']; ?>"
                                            <?php echo (isset($_GET['action']) && $_GET['action'] == $action_data['action_name']) ? 'selected' : ''; ?>>
                                        <?php echo $action_data['action_name']; ?> (<?php echo $action_data['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="">All Status</option>
                                <?php foreach ($summary_data['by_status'] as $status_data): ?>
                                    <option value="<?php echo $status_data['status']; ?>"
                                            <?php echo (isset($_GET['status']) && $_GET['status'] == $status_data['status']) ? 'selected' : ''; ?>>
                                        <?php 
                                        $status_map = [
                                            'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                            'අවසන් කර ඇත' => 'Completed',
                                            'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                                        ];
                                        echo $status_map[$status_data['status']] ?? $status_data['status'];
                                        ?> (<?php echo $status_data['count']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3 mb-3 d-flex align-items-end">
                            <button type="submit" class="btn btn-filter w-100">
                                <i class="fas fa-search me-2"></i>
                                Apply Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Filtered Results -->
            <?php if ($filter_applied): ?>
                <div class="summary-card">
                    <div class="table-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="table-title">
                                <i class="fas fa-list me-2"></i>
                                Filtered Results
                                <span class="badge bg-light text-dark ms-2"><?php echo count($filtered_results); ?> Results</span>
                            </h5>
                            <a href="summary.php" class="btn" style="background: rgba(255,255,255,0.2); color: white; text-decoration: none;">
                                <i class="fas fa-times me-1"></i>
                                Clear Filter
                            </a>
                        </div>
                    </div>
                    
                    <?php if (!empty($filtered_results)): ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>File Number</th>
                                        <th>Officer</th>
                                        <th>Offense Date</th>
                                        <th>Action Taken</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filtered_results as $result): ?>
                                        <tr>
                                            <td><strong><?php echo $result['file_number']; ?></strong></td>
                                            <td>
                                                <?php echo $result['officer_rank'] . ' ' . $result['official_number']; ?><br>
                                                <small class="text-muted"><?php echo $result['first_name'] . ' ' . $result['last_name']; ?></small>
                                            </td>
                                            <td><?php echo formatDate($result['offense_date']); ?></td>
                                            <td><?php echo $result['action_name']; ?></td>
                                            <td>
                                                <span class="badge <?php 
                                                    echo $result['status'] == 'විමර්ෂණයේ පවතී' ? 'bg-warning' : 
                                                        ($result['status'] == 'අවසන් කර ඇත' ? 'bg-success' : 'bg-info'); 
                                                ?>">
                                                    <?php 
                                                    $status_map = [
                                                        'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                                        'අවසන් කර ඇත' => 'Completed',
                                                        'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                                                    ];
                                                    echo $status_map[$result['status']] ?? $result['status'];
                                                    ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-search fa-2x text-muted mb-2"></i><br>
                            <span class="text-muted">No results found for selected filters</span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle functionality
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        function toggleSidebar() {
            sidebar.classList.toggle('active');
            sidebarOverlay.classList.toggle('active');
        }
        
        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);
        
        // Close sidebar on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                toggleSidebar();
            }
        });
        
        // Chart configurations
        const chartColors = [
            '#3498db', '#e74c3c', '#f39c12', '#27ae60', '#9b59b6',
            '#1abc9c', '#34495e', '#e67e22', '#95a5a6', '#2c3e50'
        ];
        
        // Rank Chart
        const rankData = <?php echo json_encode($summary_data['by_rank']); ?>;
        new Chart(document.getElementById('rankChart'), {
            type: 'doughnut',
            data: {
                labels: rankData.map(item => item.officer_rank),
                datasets: [{
                    data: rankData.map(item => item.count),
                    backgroundColor: chartColors.slice(0, rankData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Action Chart
        const actionData = <?php echo json_encode($summary_data['by_action']); ?>;
        new Chart(document.getElementById('actionChart'), {
            type: 'pie',
            data: {
                labels: actionData.map(item => item.action_name),
                datasets: [{
                    data: actionData.map(item => item.count),
                    backgroundColor: chartColors.slice(0, actionData.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Monthly Chart
        const monthlyData = <?php echo json_encode(array_values($monthly_stats)); ?>;
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        
        new Chart(document.getElementById('monthlyChart'), {
            type: 'line',
            data: {
                labels: months,
                datasets: [{
                    label: 'Number of Investigations',
                    data: monthlyData,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Status Chart
        const statusData = <?php echo json_encode($summary_data['by_status']); ?>;
        new Chart(document.getElementById('statusChart'), {
            type: 'bar',
            data: {
                labels: statusData.map(item => {
                    const statusMap = {
                        'විමර්ෂණයේ පවතී': 'Under Investigation',
                        'අවසන් කර ඇත': 'Completed',
                        'වෙනත් ස්ථානයකට මාරු කර ඇත': 'Transferred'
                    };
                    return statusMap[item.status] || item.status;
                }),
                datasets: [{
                    data: statusData.map(item => item.count),
                    backgroundColor: ['#f39c12', '#27ae60', '#e74c3c'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html>