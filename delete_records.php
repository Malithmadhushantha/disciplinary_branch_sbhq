<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';
$search_results = [];
$search_performed = false;
$search_term = '';
$search_type = '';

// Handle deletion requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_record'])) {
    $record_id = (int)$_POST['record_id'];
    $record_type = sanitizeInput($_POST['record_type']);
    
    try {
        $pdo->beginTransaction();
        
        if ($record_type == 'preliminary_investigation') {
            // Delete related charge sheets and formal investigations first
            $stmt = $pdo->prepare("DELETE FROM charge_sheets WHERE pi_id = ?");
            $stmt->execute([$record_id]);
            
            $stmt = $pdo->prepare("DELETE FROM formal_investigations WHERE pi_id = ?");
            $stmt->execute([$record_id]);
            
            // Delete preliminary investigation
            $stmt = $pdo->prepare("DELETE FROM preliminary_investigations WHERE id = ?");
            $stmt->execute([$record_id]);
            
            $success = 'Preliminary investigation and all related records deleted successfully';
            
        } elseif ($record_type == 'charge_sheet') {
            $stmt = $pdo->prepare("DELETE FROM charge_sheets WHERE id = ?");
            $stmt->execute([$record_id]);
            
            $success = 'Charge sheet deleted successfully';
            
        } elseif ($record_type == 'formal_investigation') {
            $stmt = $pdo->prepare("DELETE FROM formal_investigations WHERE id = ?");
            $stmt->execute([$record_id]);
            
            $success = 'Formal investigation deleted successfully';
        }
        
        $pdo->commit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Error deleting record: ' . $e->getMessage();
    }
}

// Handle search requests
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['search_records'])) {
    $search_term = sanitizeInput($_POST['search_term']);
    $search_type = sanitizeInput($_POST['search_type']);
    
    if (!empty($search_term) && !empty($search_type)) {
        $search_performed = true;
        
        if ($search_type == 'preliminary_investigation') {
            $stmt = $pdo->prepare("
                SELECT pi.*, at.action_name,
                       (SELECT COUNT(*) FROM charge_sheets WHERE pi_id = pi.id) as charge_sheets_count,
                       (SELECT COUNT(*) FROM formal_investigations WHERE pi_id = pi.id) as formal_investigations_count
                FROM preliminary_investigations pi
                JOIN actions_taken at ON pi.action_taken_id = at.id
                WHERE pi.file_number LIKE ? 
                   OR pi.first_name LIKE ? 
                   OR pi.last_name LIKE ? 
                   OR pi.official_number LIKE ?
                   OR pi.nic_number LIKE ?
                   OR CONCAT(pi.first_name, ' ', pi.last_name) LIKE ?
                ORDER BY pi.created_at DESC
            ");
            
            $search_pattern = '%' . $search_term . '%';
            $stmt->execute([$search_pattern, $search_pattern, $search_pattern, 
                           $search_pattern, $search_pattern, $search_pattern]);
            $search_results = $stmt->fetchAll();
            
        } elseif ($search_type == 'charge_sheet') {
            $stmt = $pdo->prepare("
                SELECT cs.*, pi.file_number, pi.officer_rank, pi.official_number,
                       pi.first_name, pi.last_name, pi.nic_number
                FROM charge_sheets cs
                JOIN preliminary_investigations pi ON cs.pi_id = pi.id
                WHERE cs.charge_sheet_number LIKE ?
                   OR pi.file_number LIKE ?
                   OR pi.first_name LIKE ?
                   OR pi.last_name LIKE ?
                   OR pi.official_number LIKE ?
                ORDER BY cs.created_at DESC
            ");
            
            $search_pattern = '%' . $search_term . '%';
            $stmt->execute([$search_pattern, $search_pattern, $search_pattern, 
                           $search_pattern, $search_pattern]);
            $search_results = $stmt->fetchAll();
            
        } elseif ($search_type == 'formal_investigation') {
            $stmt = $pdo->prepare("
                SELECT fi.*, pi.file_number, pi.officer_rank, pi.official_number,
                       pi.first_name, pi.last_name, pi.nic_number
                FROM formal_investigations fi
                JOIN preliminary_investigations pi ON fi.pi_id = pi.id
                WHERE fi.investigation_number LIKE ?
                   OR pi.file_number LIKE ?
                   OR pi.first_name LIKE ?
                   OR pi.last_name LIKE ?
                   OR pi.official_number LIKE ?
                ORDER BY fi.created_at DESC
            ");
            
            $search_pattern = '%' . $search_term . '%';
            $stmt->execute([$search_pattern, $search_pattern, $search_pattern, 
                           $search_pattern, $search_pattern]);
            $search_results = $stmt->fetchAll();
        }
    } else {
        $error = 'Please enter search term and select record type';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Records - Disciplinary Branch SBHQ</title>
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
        }
        
        .sidebar.active {
            left: 0;
        }
        
        .sidebar-header {
            padding: 2rem 1.5rem;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.2), rgba(0, 0, 0, 0.1));
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
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
        
        .sidebar-menu {
            list-style: none;
            padding: 1rem 0;
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
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
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
        
        /* Warning Card */
        .warning-card {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
        }
        
        /* Search Card */
        .search-card {
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
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .btn-search {
            background: linear-gradient(135deg, #17a2b8, #138496);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-search:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
            color: white;
        }
        
        /* Results Table */
        .results-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .table-header {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1.5rem 2rem;
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
        
        .btn-delete {
            background: linear-gradient(135deg, #dc3545, #c82333);
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            color: white;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .record-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid #dc3545;
        }
        
        .related-records {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 0.8rem;
            margin-top: 0.5rem;
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
            <p>Sri Lanka Police Headquarters</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="index.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="search_officer_status.php"><i class="fas fa-search"></i> Search Officer Status</a></li>
            <li><a href="preliminary_investigation.php"><i class="fas fa-file-alt"></i> Preliminary Investigations</a></li>
            <li><a href="charge_sheets.php"><i class="fas fa-file-contract"></i> Charge Sheets</a></li>
            <li><a href="formal_disciplinary_investigation.php"><i class="fas fa-gavel"></i> Formal Investigations</a></li>
            <li><a href="summary.php"><i class="fas fa-chart-bar"></i> Summary & Reports</a></li>
            <li><a href="delete_records.php" class="active"><i class="fas fa-trash-alt"></i> Delete Records</a></li>
            <li><a href="backup.php"><i class="fas fa-database"></i> Database Backup</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
        
        <!-- Developer Credit -->
        <div class="developer-credit">
            developed by<br>
            pc 93037 smm madhushantha
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
                        <h1 class="page-title">Delete Records</h1>
                        <div class="header-subtitle">⚠️ Permanently remove records from database</div>
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
            <!-- Warning Notice -->
            <div class="warning-card">
                <div class="row align-items-center">
                    <div class="col-md-2 text-center">
                        <i class="fas fa-exclamation-triangle fa-3x"></i>
                    </div>
                    <div class="col-md-10">
                        <h4><strong>⚠️ DANGER ZONE - DATA DELETION</strong></h4>
                        <p class="mb-2">
                            <strong>WARNING:</strong> This action permanently deletes records from the database and cannot be undone.
                        </p>
                        <ul class="mb-0">
                            <li>Deleting a preliminary investigation will also delete all related charge sheets and formal investigations</li>
                            <li>Deleted records cannot be recovered</li>
                            <li>Only authorized personnel should perform delete operations</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <!-- Search Form -->
            <div class="search-card">
                <h5 class="mb-4">
                    <i class="fas fa-search me-2"></i>
                    Search Records for Deletion
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
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="search_type" class="form-label">
                                <i class="fas fa-layer-group me-2"></i>Record Type
                            </label>
                            <select class="form-select" id="search_type" name="search_type" required>
                                <option value="">Select Record Type</option>
                                <option value="preliminary_investigation" <?php echo ($search_type == 'preliminary_investigation') ? 'selected' : ''; ?>>
                                    Preliminary Investigations
                                </option>
                                <option value="charge_sheet" <?php echo ($search_type == 'charge_sheet') ? 'selected' : ''; ?>>
                                    Charge Sheets
                                </option>
                                <option value="formal_investigation" <?php echo ($search_type == 'formal_investigation') ? 'selected' : ''; ?>>
                                    Formal Investigations
                                </option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="search_term" class="form-label">
                                <i class="fas fa-search me-2"></i>Search Term
                            </label>
                            <input type="text" class="form-control" id="search_term" name="search_term" 
                                   value="<?php echo htmlspecialchars($search_term); ?>"
                                   placeholder="Enter file number, officer name, NIC, etc..." required>
                        </div>
                        
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" name="search_records" class="btn btn-search w-100">
                                <i class="fas fa-search me-2"></i>Search
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Search Results -->
            <?php if ($search_performed): ?>
                <div class="results-table">
                    <div class="table-header">
                        <h5 class="table-title">
                            <i class="fas fa-list me-2"></i>
                            Search Results - <?php echo ucfirst(str_replace('_', ' ', $search_type)); ?>
                            <?php if (!empty($search_results)): ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($search_results); ?> Found</span>
                            <?php endif; ?>
                        </h5>
                    </div>
                    
                    <div class="p-4">
                        <?php if (!empty($search_results)): ?>
                            <?php foreach ($search_results as $result): ?>
                                <div class="record-card">
                                    <?php if ($search_type == 'preliminary_investigation'): ?>
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6><strong>File: <?php echo $result['file_number']; ?></strong></h6>
                                                <p class="mb-1">
                                                    <strong>Officer:</strong> <?php echo $result['officer_rank'] . ' ' . $result['official_number']; ?> - 
                                                    <?php echo $result['first_name'] . ' ' . $result['last_name']; ?>
                                                </p>
                                                <p class="mb-1"><strong>NIC:</strong> <?php echo $result['nic_number']; ?></p>
                                                <p class="mb-1"><strong>Status:</strong> <?php echo $result['status']; ?></p>
                                                <p class="mb-0"><strong>Action:</strong> <?php echo $result['action_name']; ?></p>
                                                
                                                <?php if ($result['charge_sheets_count'] > 0 || $result['formal_investigations_count'] > 0): ?>
                                                    <div class="related-records mt-2">
                                                        <small>
                                                            <i class="fas fa-warning me-1"></i>
                                                            <strong>Related Records:</strong>
                                                            <?php if ($result['charge_sheets_count'] > 0): ?>
                                                                <?php echo $result['charge_sheets_count']; ?> Charge Sheet(s)
                                                            <?php endif; ?>
                                                            <?php if ($result['formal_investigations_count'] > 0): ?>
                                                                <?php echo $result['formal_investigations_count']; ?> Formal Investigation(s)
                                                            <?php endif; ?>
                                                            <br><em>These will also be deleted</em>
                                                        </small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="button" class="btn btn-delete" 
                                                        onclick="confirmDelete(<?php echo $result['id']; ?>, 'preliminary_investigation', 'PI: <?php echo htmlspecialchars($result['file_number']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Delete Investigation
                                                </button>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($search_type == 'charge_sheet'): ?>
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6><strong>Charge Sheet: <?php echo $result['charge_sheet_number']; ?></strong></h6>
                                                <p class="mb-1"><strong>File:</strong> <?php echo $result['file_number']; ?></p>
                                                <p class="mb-1">
                                                    <strong>Officer:</strong> <?php echo $result['officer_rank'] . ' ' . $result['official_number']; ?> - 
                                                    <?php echo $result['first_name'] . ' ' . $result['last_name']; ?>
                                                </p>
                                                <p class="mb-0"><strong>Issued:</strong> <?php echo formatDate($result['issued_date']); ?></p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="button" class="btn btn-delete" 
                                                        onclick="confirmDelete(<?php echo $result['id']; ?>, 'charge_sheet', 'Charge Sheet: <?php echo htmlspecialchars($result['charge_sheet_number']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Delete Charge Sheet
                                                </button>
                                            </div>
                                        </div>
                                        
                                    <?php elseif ($search_type == 'formal_investigation'): ?>
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h6><strong>Investigation: <?php echo $result['investigation_number']; ?></strong></h6>
                                                <p class="mb-1"><strong>File:</strong> <?php echo $result['file_number']; ?></p>
                                                <p class="mb-1">
                                                    <strong>Officer:</strong> <?php echo $result['officer_rank'] . ' ' . $result['official_number']; ?> - 
                                                    <?php echo $result['first_name'] . ' ' . $result['last_name']; ?>
                                                </p>
                                                <p class="mb-1"><strong>Conducting Officer:</strong> <?php echo $result['conducting_officer']; ?></p>
                                                <p class="mb-0"><strong>Date:</strong> <?php echo formatDate($result['investigation_date']); ?></p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="button" class="btn btn-delete" 
                                                        onclick="confirmDelete(<?php echo $result['id']; ?>, 'formal_investigation', 'Formal Investigation: <?php echo htmlspecialchars($result['investigation_number']); ?>')">
                                                    <i class="fas fa-trash me-2"></i>Delete Investigation
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Records Found</h5>
                                <p class="text-muted">
                                    No <?php echo str_replace('_', ' ', $search_type); ?> records found for "<?php echo htmlspecialchars($search_term); ?>".
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                        <h5>Are you sure you want to delete this record?</h5>
                        <p class="text-muted mb-3" id="deleteRecordInfo"></p>
                        <div class="alert alert-warning">
                            <strong>Warning:</strong> This action cannot be undone. The record and all related data will be permanently deleted from the database.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" id="deleteRecordId" name="record_id" value="">
                        <input type="hidden" id="deleteRecordType" name="record_type" value="">
                        <button type="submit" name="delete_record" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Yes, Delete Permanently
                        </button>
                    </form>
                </div>
            </div>
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
        
        // Delete confirmation
        function confirmDelete(recordId, recordType, recordInfo) {
            document.getElementById('deleteRecordId').value = recordId;
            document.getElementById('deleteRecordType').value = recordType;
            document.getElementById('deleteRecordInfo').textContent = recordInfo;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }
        
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('search_term');
            if (searchInput) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>