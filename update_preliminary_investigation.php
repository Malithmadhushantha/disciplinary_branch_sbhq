<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';
$investigation = null;

// Get available actions
$stmt = $pdo->query("SELECT * FROM actions_taken ORDER BY action_name");
$actions = $stmt->fetchAll();

// Handle investigation selection
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['id'])) {
    $investigation_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM preliminary_investigations WHERE id = ?");
    $stmt->execute([$investigation_id]);
    $investigation = $stmt->fetch();
    
    if (!$investigation) {
        $error = 'Investigation not found';
    }
}

// Handle form submission for updating investigation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_investigation'])) {
    $investigation_id = (int)$_POST['investigation_id'];
    $file_number = sanitizeInput($_POST['file_number']);
    $officer_rank = sanitizeInput($_POST['officer_rank']);
    $official_number = sanitizeInput($_POST['official_number']);
    $first_name = sanitizeInput($_POST['first_name']);
    $last_name = sanitizeInput($_POST['last_name']);
    $nic_number = sanitizeInput($_POST['nic_number']);
    $police_id_number = sanitizeInput($_POST['police_id_number']);
    $main_branch_name = sanitizeInput($_POST['main_branch_name']);
    $sub_branch_name = sanitizeInput($_POST['sub_branch_name']);
    $offense_description = sanitizeInput($_POST['offense_description']);
    $offense_date = $_POST['offense_date'];
    $investigating_officer = sanitizeInput($_POST['investigating_officer']);
    $action_taken_id = (int)$_POST['action_taken_id'];
    $status = sanitizeInput($_POST['status']);
    
    // Validation
    if (empty($file_number) || empty($officer_rank) || empty($first_name) || empty($last_name) || 
        empty($nic_number) || empty($police_id_number) || empty($main_branch_name) || 
        empty($offense_description) || empty($offense_date) || empty($investigating_officer) || 
        empty($action_taken_id) || empty($status)) {
        $error = 'Please fill in all required fields (*)';
    } else {
        // Check if file number already exists for other investigations
        $stmt = $pdo->prepare("SELECT id FROM preliminary_investigations WHERE file_number = ? AND id != ?");
        $stmt->execute([$file_number, $investigation_id]);
        if ($stmt->fetch()) {
            $error = 'This file number is already in use by another investigation';
        } else {
            // Update preliminary investigation
            $stmt = $pdo->prepare("
                UPDATE preliminary_investigations SET
                file_number = ?, officer_rank = ?, official_number = ?, first_name = ?, 
                last_name = ?, nic_number = ?, police_id_number = ?, main_branch_name = ?, 
                sub_branch_name = ?, offense_description = ?, offense_date = ?, 
                investigating_officer = ?, action_taken_id = ?, status = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($stmt->execute([$file_number, $officer_rank, $official_number, $first_name, $last_name, 
                               $nic_number, $police_id_number, $main_branch_name, $sub_branch_name, 
                               $offense_description, $offense_date, $investigating_officer, $action_taken_id, 
                               $status, $investigation_id])) {
                $success = 'Preliminary investigation updated successfully';
                
                // Refresh investigation data
                $stmt = $pdo->prepare("SELECT * FROM preliminary_investigations WHERE id = ?");
                $stmt->execute([$investigation_id]);
                $investigation = $stmt->fetch();
            } else {
                $error = 'Error updating preliminary investigation';
            }
        }
    }
}

// Get all investigations for selection if no specific ID provided
if (!$investigation) {
    $stmt = $pdo->prepare("
        SELECT pi.*, at.action_name 
        FROM preliminary_investigations pi 
        JOIN actions_taken at ON pi.action_taken_id = at.id 
        ORDER BY pi.created_at DESC
    ");
    $stmt->execute();
    $all_investigations = $stmt->fetchAll();
}

$ranks = [
    'ප්‍ර.පො.ප.' => 'ප්‍ර.පො.ප.',
    'කා.ප්‍ර.පො.ප.' => 'කා.ප්‍ර.පො.ප.',
    'පො.ප.' => 'පො.ප.',
    'කා.පො.ප.' => 'කා.පො.ප.',
    'උ.පො.ප.' => 'උ.පො.ප.',
    'කා.උ.පො.ප.' => 'කා.උ.පො.ප.',
    'පො.සැ.' => 'පො.සැ.',
    'කා.පො.සැ.' => 'කා.පො.සැ.',
    'පො.කො.' => 'පො.කො.',
    'කා.පො.කො.' => 'කා.පො.කො.',
    'පො.සැ.රි.' => 'පො.සැ.රි.',
    'පො.කො.රි.' => 'පො.කො.රි.'
];

$statuses = [
    'විමර්ෂණයේ පවතී' => 'Under Investigation',
    'අවසන් කර ඇත' => 'Completed',
    'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred to Another Location'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Preliminary Investigation - Disciplinary Branch SBHQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
        
        /* Form Styles */
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
            color: white;
        }
        
        .btn-cancel {
            background: #6c757d;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            background: #5a6268;
            color: white;
            text-decoration: none;
        }
        
        /* Investigation Selection Cards */
        .investigation-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .investigation-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }
        
        .search-box {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .search-box input {
            padding-left: 2.5rem;
            border-radius: 10px;
            border: 2px solid #e9ecef;
        }
        
        .search-box .search-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
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
                    <li><a href="index.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
                    <li><a href="search_officer_status.php"><i class="fas fa-search"></i> Search Officer Status</a></li>
                    <li><a href="preliminary_investigation.php"><i class="fas fa-file-alt"></i> Preliminary Investigations</a></li>
                    <li><a href="charge_sheets.php"><i class="fas fa-file-contract"></i> Charge Sheets</a></li>
                    <li><a href="formal_disciplinary_investigation.php"><i class="fas fa-gavel"></i> Formal Investigations</a></li>
                    <li><a href="summary.php"><i class="fas fa-chart-bar"></i> Summary & Reports</a></li>
                    <li><a href="backup.php" class="active"><i class="fas fa-database"></i> Database Backup</a></li>
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
                        <h1 class="page-title">Update Preliminary Investigation</h1>
                        <div class="header-subtitle">Modify existing investigation records</div>
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
            <?php if (!$investigation): ?>
                <!-- Investigation Selection -->
                <div class="form-card">
                    <h5 class="mb-4">
                        <i class="fas fa-search me-2"></i>
                        Select Investigation to Update
                    </h5>
                    
                    <div class="search-box">
                        <input type="text" class="form-control" id="searchInput" 
                               placeholder="Search by file number, officer, or offense...">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                    
                    <div id="investigationsList">
                        <?php if (isset($all_investigations)): ?>
                            <?php foreach ($all_investigations as $inv): ?>
                                <div class="investigation-card" onclick="selectInvestigation(<?php echo $inv['id']; ?>)">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>File Number:</strong><br>
                                            <?php echo $inv['file_number']; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Officer:</strong><br>
                                            <?php echo $inv['officer_rank'] . ' ' . $inv['official_number']; ?><br>
                                            <small><?php echo $inv['first_name'] . ' ' . $inv['last_name']; ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>Action:</strong><br>
                                            <?php echo $inv['action_name']; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <strong>Status:</strong><br>
                                            <small><?php 
                                            $status_map = [
                                                'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                                'අවසන් කර ඇත' => 'Completed',
                                                'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                                            ];
                                            echo $status_map[$inv['status']] ?? $inv['status'];
                                            ?></small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <!-- Update Form -->
                <div class="form-card">
                    <h5 class="mb-4">
                        <i class="fas fa-edit me-2"></i>
                        Update Preliminary Investigation
                        <span class="badge bg-primary ms-2"><?php echo $investigation['file_number']; ?></span>
                    </h5>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <?php echo $error; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success" role="alert">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $success; ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <input type="hidden" name="investigation_id" value="<?php echo $investigation['id']; ?>">
                        
                        <div class="row">
                            <!-- File Number -->
                            <div class="col-md-6 mb-3">
                                <label for="file_number" class="form-label">
                                    <i class="fas fa-folder me-2"></i>Preliminary Investigation Number (File Number) <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="file_number" name="file_number" 
                                       value="<?php echo $investigation['file_number']; ?>" required>
                            </div>
                            
                            <!-- Officer Rank -->
                            <div class="col-md-6 mb-3">
                                <label for="officer_rank" class="form-label">
                                    <i class="fas fa-star me-2"></i>Officer's Rank <span class="required">*</span>
                                </label>
                                <select class="form-select" id="officer_rank" name="officer_rank" required>
                                    <option value="">Select Rank</option>
                                    <?php foreach ($ranks as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($investigation['officer_rank'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $key; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Official Number -->
                            <div class="col-md-6 mb-3">
                                <label for="official_number" class="form-label">
                                    <i class="fas fa-id-badge me-2"></i>Official Number
                                </label>
                                <input type="text" class="form-control" id="official_number" name="official_number" 
                                       value="<?php echo $investigation['official_number']; ?>" 
                                       placeholder="Some ranks may have this">
                            </div>
                            
                            <!-- First Name -->
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>First Name <span class="required">*</span> (Sinhala)
                                </label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?php echo $investigation['first_name']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Last Name -->
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">
                                    <i class="fas fa-user me-2"></i>Last Name <span class="required">*</span> (Sinhala)
                                </label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?php echo $investigation['last_name']; ?>" required>
                            </div>
                            
                            <!-- NIC Number -->
                            <div class="col-md-6 mb-3">
                                <label for="nic_number" class="form-label">
                                    <i class="fas fa-id-card me-2"></i>National Identity Card Number <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="nic_number" name="nic_number" 
                                       value="<?php echo $investigation['nic_number']; ?>" 
                                       required placeholder="123456789V or 200012345678">
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Police ID Number -->
                            <div class="col-md-6 mb-3">
                                <label for="police_id_number" class="form-label">
                                    <i class="fas fa-shield-alt me-2"></i>Police Identity Card Number <span class="required">*</span>
                                </label>
                                <input type="text" class="form-control" id="police_id_number" name="police_id_number" 
                                       value="<?php echo $investigation['police_id_number']; ?>" required>
                            </div>
                            
                            <!-- Offense Date -->
                            <div class="col-md-6 mb-3">
                                <label for="offense_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Offense Date <span class="required">*</span>
                                </label>
                                <input type="date" class="form-control" id="offense_date" name="offense_date" 
                                       value="<?php echo $investigation['offense_date']; ?>" required>
                            </div>
                        </div>
                        
                        <div class="row">
                            <!-- Officer Attached Main Branch Name -->
                            <div class="col-md-6 mb-3">
                                <label for="main_branch_name" class="form-label">
                                    <i class="fas fa-building me-2"></i>Officer Attached Main Branch Name <span class="required">*</span> (Sinhala)
                                </label>
                                <input type="text" class="form-control" id="main_branch_name" name="main_branch_name" 
                                       value="<?php echo $investigation['main_branch_name']; ?>" required>
                            </div>
                            
                            <!-- Officer Attached Sub Branch Name -->
                            <div class="col-md-6 mb-3">
                                <label for="sub_branch_name" class="form-label">
                                    <i class="fas fa-sitemap me-2"></i>Officer Attached Sub Branch Name (Sinhala)
                                </label>
                                <input type="text" class="form-control" id="sub_branch_name" name="sub_branch_name" 
                                       value="<?php echo $investigation['sub_branch_name']; ?>">
                            </div>
                        </div>
                        
                        <!-- Offense Description -->
                        <div class="mb-3">
                            <label for="offense_description" class="form-label">
                                <i class="fas fa-exclamation-triangle me-2"></i>Offense Description <span class="required">*</span> (Sinhala)
                            </label>
                            <textarea class="form-control" id="offense_description" name="offense_description" rows="4" 
                                      required placeholder="Enter offense description in Sinhala"><?php echo $investigation['offense_description']; ?></textarea>
                        </div>
                        
                        <!-- Investigating Officer -->
                        <div class="mb-3">
                            <label for="investigating_officer" class="form-label">
                                <i class="fas fa-search me-2"></i>Investigating Officer <span class="required">*</span> (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="investigating_officer" name="investigating_officer" 
                                   value="<?php echo $investigation['investigating_officer']; ?>" 
                                   required placeholder="Enter investigating officer name in Sinhala">
                        </div>
                        
                        <div class="row">
                            <!-- Action Taken -->
                            <div class="col-md-6 mb-3">
                                <label for="action_taken_id" class="form-label">
                                    <i class="fas fa-cogs me-2"></i>Action Taken <span class="required">*</span>
                                </label>
                                <select class="form-select" id="action_taken_id" name="action_taken_id" required>
                                    <option value="">Select Action</option>
                                    <?php foreach ($actions as $action): ?>
                                        <option value="<?php echo $action['id']; ?>" <?php echo ($investigation['action_taken_id'] == $action['id']) ? 'selected' : ''; ?>>
                                            <?php echo $action['action_name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Status -->
                            <div class="col-md-6 mb-3">
                                <label for="status" class="form-label">
                                    <i class="fas fa-info-circle me-2"></i>Status <span class="required">*</span>
                                </label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="">Select Status</option>
                                    <?php foreach ($statuses as $key => $value): ?>
                                        <option value="<?php echo $key; ?>" <?php echo ($investigation['status'] == $key) ? 'selected' : ''; ?>>
                                            <?php echo $value; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between mt-4">
                            <a href="preliminary_investigation.php" class="btn btn-cancel">
                                <i class="fas fa-arrow-left me-2"></i>
                                Cancel
                            </a>
                            
                            <button type="submit" name="update_investigation" class="btn btn-submit">
                                <i class="fas fa-save me-2"></i>
                                Update Investigation
                            </button>
                        </div>
                    </form>
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
        
        // Investigation selection
        function selectInvestigation(investigationId) {
            window.location.href = '?id=' + investigationId;
        }
        
        // Search functionality
        <?php if (!$investigation): ?>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const cards = document.querySelectorAll('.investigation-card');
            
            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        <?php endif; ?>
        
        // Form validation
        <?php if ($investigation): ?>
        document.querySelector('form').addEventListener('submit', function(e) {
            const requiredFields = document.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields (*)');
            }
        });
        
        // NIC validation
        document.getElementById('nic_number').addEventListener('input', function() {
            const nic = this.value.trim();
            const oldNicPattern = /^[0-9]{9}[VvXx]$/;
            const newNicPattern = /^[0-9]{12}$/;
            
            if (nic && !oldNicPattern.test(nic) && !newNicPattern.test(nic)) {
                this.classList.add('is-invalid');
            } else {
                this.classList.remove('is-invalid');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>