<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$investigation = null;
$error = '';

// Get investigation ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: preliminary_investigation.php');
    exit();
}

$investigation_id = (int)$_GET['id'];

// Get preliminary investigation with related data
$stmt = $pdo->prepare("
    SELECT pi.*, at.action_name, cs.charge_sheet_number, cs.issued_date as charge_sheet_date,
           fi.investigation_number as formal_inv_number, fi.investigation_date as formal_inv_date
    FROM preliminary_investigations pi
    LEFT JOIN actions_taken at ON pi.action_taken_id = at.id
    LEFT JOIN charge_sheets cs ON pi.id = cs.pi_id
    LEFT JOIN formal_investigations fi ON pi.id = fi.pi_id
    WHERE pi.id = ?
");
$stmt->execute([$investigation_id]);
$investigation = $stmt->fetch();

if (!$investigation) {
    $error = 'Preliminary investigation not found';
}

// Check if this is a print request
$is_print = isset($_GET['print']) && $_GET['print'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $is_print ? 'Print - ' : ''; ?>Preliminary Investigation Details - Disciplinary Branch SBHQ</title>
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
                margin-bottom: 1rem !important;
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
            
            .card-header {
                background: #f8f9fa !important;
                color: #000 !important;
                border-bottom: 1px solid #000 !important;
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
            background: linear-gradient(135deg, #3498db, #2980b9);
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
        
        .related-actions {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }
        
        .related-title {
            font-weight: 600;
            color: #495057;
            margin-bottom: 1rem;
        }
        
        .related-item {
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .related-item:last-child {
            margin-bottom: 0;
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
        <h4>Preliminary Investigation Report</h4>
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
                    <li><a href="preliminary_investigation.php" class="active"><i class="fas fa-file-alt"></i> Preliminary Investigations</a></li>
                    <li><a href="charge_sheets.php"><i class="fas fa-file-contract"></i> Charge Sheets</a></li>
                    <li><a href="formal_disciplinary_investigation.php"><i class="fas fa-gavel"></i> Formal Investigations</a></li>
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
                        <h1 class="page-title">Investigation Details</h1>
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
                    <a href="preliminary_investigation.php" class="btn btn-primary">
                        <i class="fas fa-arrow-left me-2"></i>Back to Investigations
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Action Buttons -->
                <?php if (!$is_print): ?>
                <div class="action-buttons no-print">
                    <a href="preliminary_investigation.php" class="btn btn-action btn-back">
                        <i class="fas fa-arrow-left"></i>
                        Back to List
                    </a>
                    <a href="?id=<?php echo $investigation_id; ?>&print=1" class="btn btn-action btn-print" target="_blank">
                        <i class="fas fa-print"></i>
                        Print Report
                    </a>
                    <a href="update_preliminary_investigation.php?id=<?php echo $investigation_id; ?>" class="btn btn-action btn-edit">
                        <i class="fas fa-edit"></i>
                        Edit Investigation
                    </a>
                </div>
                <?php endif; ?>
                
                <!-- Investigation Summary -->
                <div class="detail-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-file-alt me-2"></i>
                            Preliminary Investigation Summary
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-folder me-2"></i>File Number:
                        </div>
                        <div class="info-value">
                            <strong><?php echo $investigation['file_number']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar me-2"></i>Offense Date:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($investigation['offense_date']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-search me-2"></i>Investigating Officer:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $investigation['investigating_officer']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-cogs me-2"></i>Action Taken:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $investigation['action_name']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-info-circle me-2"></i>Status:
                        </div>
                        <div class="info-value">
                            <span class="status-badge <?php 
                                echo $investigation['status'] == 'විමර්ෂණයේ පවතී' ? 'status-active' : 
                                    ($investigation['status'] == 'අවසන් කර ඇත' ? 'status-completed' : 'status-transferred'); 
                            ?>">
                                <?php 
                                $status_map = [
                                    'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                    'අවසන් කර ඇත' => 'Completed',
                                    'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                                ];
                                echo $status_map[$investigation['status']] ?? $investigation['status'];
                                ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-clock me-2"></i>Record Created:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($investigation['created_at']); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Officer Details -->
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #e67e22, #d35400);">
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
                            <strong><?php echo $investigation['officer_rank'] . ' ' . ($investigation['official_number'] ?: 'N/A'); ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-user me-2"></i>Full Name:
                        </div>
                        <div class="info-value sinhala">
                            <strong><?php echo $investigation['first_name'] . ' ' . $investigation['last_name']; ?></strong>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-id-card me-2"></i>National Identity Card:
                        </div>
                        <div class="info-value">
                            <?php echo $investigation['nic_number']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-shield-alt me-2"></i>Police Identity Card:
                        </div>
                        <div class="info-value">
                            <?php echo $investigation['police_id_number']; ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-building me-2"></i>Main Branch:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $investigation['main_branch_name']; ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($investigation['sub_branch_name'])): ?>
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-sitemap me-2"></i>Sub Branch:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo $investigation['sub_branch_name']; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Offense Details -->
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #e74c3c, #c0392b);">
                        <h5 class="card-title">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Offense Details
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar me-2"></i>Date of Offense:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($investigation['offense_date']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-file-text me-2"></i>Description:
                        </div>
                        <div class="info-value sinhala">
                            <?php echo nl2br(htmlspecialchars($investigation['offense_description'])); ?>
                        </div>
                    </div>
                </div>
                
                <!-- Related Actions -->
                <?php if (!empty($investigation['charge_sheet_number']) || !empty($investigation['formal_inv_number'])): ?>
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #27ae60, #229954);">
                        <h5 class="card-title">
                            <i class="fas fa-link me-2"></i>
                            Related Actions
                        </h5>
                    </div>
                    
                    <div class="related-actions">
                        <?php if (!empty($investigation['charge_sheet_number'])): ?>
                        <div class="related-item">
                            <div class="related-title">
                                <i class="fas fa-file-contract me-2 text-warning"></i>
                                Charge Sheet Issued
                            </div>
                            <div class="info-row mb-0">
                                <div class="info-label">Charge Sheet Number:</div>
                                <div class="info-value sinhala"><strong><?php echo $investigation['charge_sheet_number']; ?></strong></div>
                            </div>
                            <div class="info-row mb-0">
                                <div class="info-label">Issue Date:</div>
                                <div class="info-value"><?php echo formatDate($investigation['charge_sheet_date']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($investigation['formal_inv_number'])): ?>
                        <div class="related-item">
                            <div class="related-title">
                                <i class="fas fa-gavel me-2 text-info"></i>
                                Formal Disciplinary Investigation
                            </div>
                            <div class="info-row mb-0">
                                <div class="info-label">Investigation Number:</div>
                                <div class="info-value sinhala"><strong><?php echo $investigation['formal_inv_number']; ?></strong></div>
                            </div>
                            <div class="info-row mb-0">
                                <div class="info-label">Investigation Date:</div>
                                <div class="info-value"><?php echo formatDate($investigation['formal_inv_date']); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Investigation Timeline -->
                <div class="detail-card">
                    <div class="card-header" style="background: linear-gradient(135deg, #9b59b6, #8e44ad);">
                        <h5 class="card-title">
                            <i class="fas fa-clock me-2"></i>
                            Investigation Timeline
                        </h5>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar-plus me-2"></i>Investigation Started:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($investigation['created_at']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-calendar-check me-2"></i>Last Updated:
                        </div>
                        <div class="info-value">
                            <?php echo formatDate($investigation['updated_at']); ?>
                        </div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">
                            <i class="fas fa-hourglass-half me-2"></i>Investigation Duration:
                        </div>
                        <div class="info-value">
                            <?php 
                            $start = new DateTime($investigation['created_at']);
                            $end = new DateTime();
                            $interval = $start->diff($end);
                            
                            if ($interval->days > 0) {
                                echo $interval->days . ' days';
                            } else {
                                echo 'Started today';
                            }
                            ?>
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