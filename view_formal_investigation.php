<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$formal_investigation = null;
$preliminary_investigation = null;
$error = '';

// Get formal investigation ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: formal_disciplinary_investigation.php');
    exit();
}

$investigation_id = (int)$_GET['id'];

// Get formal investigation with related preliminary investigation data
$stmt = $pdo->prepare("
    SELECT fi.*, pi.file_number, pi.officer_rank, pi.official_number, 
           pi.first_name, pi.last_name, pi.nic_number, pi.police_id_number,
           pi.main_branch_name, pi.sub_branch_name, pi.offense_description, 
           pi.offense_date, pi.investigating_officer, pi.status as pi_status,
           at.action_name
    FROM formal_investigations fi
    JOIN preliminary_investigations pi ON fi.pi_id = pi.id
    JOIN actions_taken at ON pi.action_taken_id = at.id
    WHERE fi.id = ?
");
$stmt->execute([$investigation_id]);
$investigation_data = $stmt->fetch();

if (!$investigation_data) {
    $error = 'Formal investigation not found';
} else {
    $formal_investigation = $investigation_data;
}

// Check if this is a print request
$is_print = isset($_GET['print']) && $_GET['print'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_print ? 'Print - ' : ''; ?>Formal Investigation Details - Disciplinary Branch SBHQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="image/favicon.png">
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
        
        /* Print Styles */
        @media print {
            body {
                background: white !important;
                font-size: 12px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0 !important;
            }
            
            .content-wrapper {
                padding: 0 !important;
            }
            
            .detail-card {
                box-shadow: none !important;
                border: 1px solid #dee2e6 !important;
                page-break-inside: avoid;
            }
            
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 2rem;
                border-bottom: 2px solid #000;
                padding-bottom: 1rem;
            }
            
            .print-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 10px;
                border-top: 1px solid #000;
                padding-top: 0.5rem;
            }
        }
        
        .print-header {
            display: none;
        }
        
        .print-footer {
            display: none;
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
        
        /* Detail Cards */
        .detail-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px 15px 0 0;
            margin: -2rem -2rem 2rem -2rem;
        }
        
        .card-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .info-row {
            display: flex;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
            align-items: flex-start;
        }
        
        .info-label {
            font-weight: 600;
            color: #2c3e50;
            min-width: 200px;
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            flex: 1;
            color: #34495e;
            word-wrap: break-word;
        }
        
        .info-value.sinhala {
            font-family: 'Noto Sans Sinhala', sans-serif;
            font-size: 1.1rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-active {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-transferred {
            background: #f8d7da;
            color: #721c24;
        }
        
        .action-buttons {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .btn-action {
            padding: 0.8rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-right: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            border: none;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border: none;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
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
            
            .info-row {
                flex-direction: column;
            }
            
            .info-label {
                min-width: auto;
                margin-bottom: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <!-- Print Header -->
    <div class="print-header">
        <h2>Sri Lanka Police Headquarters</h2>
        <h3>Disciplinary Branch</h3>
        <h4>Formal Disciplinary Investigation Report</h4>
        <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>

    <?php if (!$is_print): ?>
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
                    <li><a href="formal_disciplinary_investigation.php" class="active"><i class="fas fa-gavel"></i> Formal Investigations</a></li>
                    <li><a href="summary.php"><i class="fas fa-chart-bar"></i> Summary & Reports</a></li>
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
 
    <?php endif; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (!$is_print): ?>
        <!-- Enhanced Header -->
        <header class="main-header no-print">
            <div class="header-content">
                <div class="d-flex align-items-center">
                    <button class="menu-toggle" id="menuToggle">
                        <i class="fas fa-bars"></i>
                    </button>
                    <div class="ms-3">
                        <h1 class="page-title">Formal Investigation Details</h1>
                        <div class="header-subtitle">View complete investigation record</div>
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
        <?php endif; ?>
        
        <!-- Content -->
        <div class="content-wrapper">
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                </div>
                <div class="text-center">
                    <a href="formal_disciplinary_investigation.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Formal Investigations
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Action Buttons -->
                <?php if (!$is_print): ?>
                <div class="action-buttons no-print">
                    <a href="formal_disciplinary_investigation.php" class="btn btn-action btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                    <a href="?id=<?php echo $investigation_id; ?>&print=1" class="btn btn-action btn-print" target="_blank">
                        <i class="fas fa-print"></i>
                        Print Report
                    </a>
                    <a href="edit_formal_investigation.php?id=<?php echo $investigation_id; ?>" class="btn btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit Investigation
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Formal Investigation Details -->
                <div class="detail-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-gavel me-2"></i>
                            Formal Disciplinary Investigation Details
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-hashtag me-2"></i>Investigation Number:
                        </div>
                        <div class="info-value sinhala">
                            <strong><?php echo $formal_investigation['investigation_number']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar me-2"></i>Investigation Date:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($formal_investigation['investigation_date']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user-tie me-2"></i>Conducting Officer:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['conducting_officer']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user-check me-2"></i>Complaint Officer:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['complaint_officer']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user-shield me-2"></i>Defense Officer:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['defense_officer']; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($formal_investigation['notes'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-sticky-note me-2"></i>Notes:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo nl2br(htmlspecialchars($formal_investigation['notes'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-clock me-2"></i>Record Created:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($formal_investigation['created_at']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Officer Details -->
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #3498db, #2980b9);">
                        <h5 class="card-title">
                            <i class="fas fa-user me-2"></i>
                            Officer Details
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-star me-2"></i>Rank & Number:
                        </div>
                        <div class="info-value">
                            <strong><?php echo $formal_investigation['officer_rank'] . ' ' . $formal_investigation['official_number']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user me-2"></i>Full Name:
                        </div>
                        <div class="info-value sinhala">
                            <strong><?php echo $formal_investigation['first_name'] . ' ' . $formal_investigation['last_name']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-id-card me-2"></i>National Identity Card:
                        </div>
                        <div class="info-value">
                            <?php echo $formal_investigation['nic_number']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-shield-alt me-2"></i>Police Identity Card:
                        </div>
                        <div class="info-value">
                            <?php echo $formal_investigation['police_id_number']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-building me-2"></i>Main Branch:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['main_branch_name']; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($formal_investigation['sub_branch_name'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-sitemap me-2"></i>Sub Branch:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['sub_branch_name']; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Preliminary Investigation Details -->
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #e67e22, #d35400);">
                        <h5 class="card-title">
                            <i class="fas fa-file-alt me-2"></i>
                            Related Preliminary Investigation
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-folder me-2"></i>File Number:
                        </div>
                        <div class="info-value">
                            <strong><?php echo $formal_investigation['file_number']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-exclamation-triangle me-2"></i>Offense Description:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo nl2br(htmlspecialchars($formal_investigation['offense_description'])); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar me-2"></i>Offense Date:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($formal_investigation['offense_date']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-search me-2"></i>Investigating Officer:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['investigating_officer']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-cogs me-2"></i>Action Taken:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $formal_investigation['action_name']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-info-circle me-2"></i>Status:
                        </div>
                        <div class="info-value">
                            <span class="status-badge <?php 
                                echo $formal_investigation['pi_status'] == 'විමර්ෂණයේ පවතී' ? 'status-active' : 
                                    ($formal_investigation['pi_status'] == 'අවසන් කර ඇත' ? 'status-completed' : 'status-transferred'); 
                            ?>">
                                <?php 
                                $status_map = [
                                    'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                    'අවසන් කර ඇත' => 'Completed',
                                    'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                                ];
                                echo $status_map[$formal_investigation['pi_status']] ?? $formal_investigation['pi_status'];
                                ?>
                            </span>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Print Footer -->
    <div class="print-footer">
        <p>This is an official document generated from the Disciplinary Branch Management System</p>
        <p>Printed by: <?php echo $user['rank'] . ' ' . $user['official_number'] . ' - ' . $user['name_with_initials']; ?></p>
    </div>
    
    <?php if (!$is_print): ?>
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
    </script>
    <?php else: ?>
    <script>
        // Auto print when page loads in print mode
        window.onload = function() {
            window.print();
        };
    </script>
    <?php endif; ?>
</body>
</html>