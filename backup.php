<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$message = '';
$error = '';

// Create backups directory if it doesn't exist
$backup_dir = 'backups';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Handle backup creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_backup'])) {
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "disciplinary_branch_backup_{$timestamp}.sql";
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        // Create SQL dump
        $sql_dump = createDatabaseBackup();
        
        // Write to file
        if (file_put_contents($backup_path, $sql_dump)) {
            $message = "Backup created successfully: {$backup_filename}";
            
            // Log backup creation
            $log_entry = date('Y-m-d H:i:s') . " - Backup created by " . $user['name_with_initials'] . " (" . $user['rank'] . " " . $user['official_number'] . ")\n";
            file_put_contents($backup_dir . '/backup_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
        } else {
            $error = "Failed to create backup file";
        }
    } catch (Exception $e) {
        $error = "Backup failed: " . $e->getMessage();
    }
}

// Handle backup download
if (isset($_GET['download']) && !empty($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    } else {
        $error = "Backup file not found";
    }
}

// Handle backup restoration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restore_backup']) && isset($_FILES['backup_file'])) {
    try {
        $uploaded_file = $_FILES['backup_file'];
        
        if ($uploaded_file['error'] === UPLOAD_ERR_OK) {
            $file_content = file_get_contents($uploaded_file['tmp_name']);
            
            if (restoreDatabase($file_content)) {
                $message = "Database restored successfully from backup";
                
                // Log restoration
                $log_entry = date('Y-m-d H:i:s') . " - Database restored by " . $user['name_with_initials'] . " (" . $user['rank'] . " " . $user['official_number'] . ") from file: " . $uploaded_file['name'] . "\n";
                file_put_contents($backup_dir . '/backup_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
            } else {
                $error = "Failed to restore database";
            }
        } else {
            $error = "File upload failed";
        }
    } catch (Exception $e) {
        $error = "Restoration failed: " . $e->getMessage();
    }
}

// Handle backup deletion
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = $backup_dir . '/' . $filename;
    
    if (file_exists($filepath) && pathinfo($filename, PATHINFO_EXTENSION) === 'sql') {
        if (unlink($filepath)) {
            $message = "Backup file deleted: {$filename}";
        } else {
            $error = "Failed to delete backup file";
        }
    }
}

// Get list of existing backups
function getBackupFiles($backup_dir) {
    $backups = [];
    if (is_dir($backup_dir)) {
        $files = scandir($backup_dir);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
                $filepath = $backup_dir . '/' . $file;
                $backups[] = [
                    'filename' => $file,
                    'size' => formatBytes(filesize($filepath)),
                    'date' => date('Y-m-d H:i:s', filemtime($filepath))
                ];
            }
        }
        // Sort by date (newest first)
        usort($backups, function($a, $b) {
            return strcmp($b['date'], $a['date']);
        });
    }
    return $backups;
}

// Create database backup
function createDatabaseBackup() {
    global $pdo;
    
    $sql_dump = "-- Database Backup for Disciplinary Branch SBHQ\n";
    $sql_dump .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database: " . DB_NAME . "\n\n";
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=0;\n";
    $sql_dump .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $sql_dump .= "SET AUTOCOMMIT = 0;\n";
    $sql_dump .= "START TRANSACTION;\n\n";
    
    // Get all tables
    $tables_query = $pdo->query("SHOW TABLES");
    $tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        // Get table structure
        $create_table_query = $pdo->query("SHOW CREATE TABLE `$table`");
        $create_table = $create_table_query->fetch(PDO::FETCH_ASSOC);
        
        $sql_dump .= "-- Table structure for `$table`\n";
        $sql_dump .= "DROP TABLE IF EXISTS `$table`;\n";
        $sql_dump .= $create_table['Create Table'] . ";\n\n";
        
        // Get table data
        $data_query = $pdo->query("SELECT * FROM `$table`");
        $rows = $data_query->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            $sql_dump .= "-- Data for table `$table`\n";
            
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = 'NULL';
                    } else {
                        $values[] = "'" . addslashes($value) . "'";
                    }
                }
                $sql_dump .= "INSERT INTO `$table` VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql_dump .= "\n";
        }
    }
    
    $sql_dump .= "SET FOREIGN_KEY_CHECKS=1;\n";
    $sql_dump .= "COMMIT;\n";
    
    return $sql_dump;
}

// Restore database from backup
function restoreDatabase($sql_content) {
    global $pdo;
    
    try {
        // Split SQL content into individual statements
        $statements = explode(';', $sql_content);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Database restore error: " . $e->getMessage());
        return false;
    }
}

// Format file size
function formatBytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

$backup_files = getBackupFiles($backup_dir);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup & Restore - Disciplinary Branch SBHQ</title>
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
        
        /* Cards */
        .backup-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .card-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
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
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #e74c3c;
            box-shadow: 0 0 0 0.2rem rgba(231, 76, 60, 0.25);
        }
        
        .btn-backup {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-backup:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
            color: white;
        }
        
        .btn-restore {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-restore:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(243, 156, 18, 0.3);
            color: white;
        }
        
        .btn-danger-custom {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border: none;
            border-radius: 8px;
            padding: 8px 15px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-danger-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
            color: white;
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
        
        /* Status indicators */
        .status-success {
            color: #27ae60;
            font-weight: 600;
        }
        
        .status-warning {
            color: #f39c12;
            font-weight: 600;
        }
        
        .status-danger {
            color: #e74c3c;
            font-weight: 600;
        }
        
        /* Info boxes */
        .info-box {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
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
                        <h1 class="page-title">Database Backup & Restore</h1>
                        <div class="header-subtitle">Manage system data backups</div>
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
            <!-- Status Messages -->
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Important Information -->
            <div class="warning-box">
                <h6><i class="fas fa-exclamation-triangle me-2"></i>Important Notice</h6>
                <p class="mb-2">Database backup and restore operations are critical system functions. Please ensure:</p>
                <ul class="mb-0">
                    <li>Create regular backups before making any major changes</li>
                    <li>Store backup files in a secure location</li>
                    <li>Test restore procedures periodically</li>
                    <li>Only authorized personnel should perform these operations</li>
                </ul>
            </div>
            
            <!-- Create Backup -->
            <div class="backup-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-download me-2"></i>
                        Create Database Backup
                    </h5>
                </div>
                
                <p class="mb-4">Create a complete backup of all database tables and data. This will generate an SQL file containing all the data that can be used to restore the database later.</p>
                
                <form method="POST" action="">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <p class="mb-0"><strong>Backup will include:</strong></p>
                            <small class="text-muted">Users, Investigations, Charge Sheets, Formal Investigations, Actions</small>
                        </div>
                        <button type="submit" name="create_backup" class="btn btn-backup" onclick="return confirm('Create a new database backup?')">
                            <i class="fas fa-download me-2"></i>
                            Create Backup Now
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Restore Database -->
            <div class="backup-card">
                <div class="card-header" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                    <h5 class="card-title">
                        <i class="fas fa-upload me-2"></i>
                        Restore Database
                    </h5>
                </div>
                
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Warning:</strong> Restoring a backup will replace ALL current data in the database. This action cannot be undone.
                </div>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="backup_file" class="form-label">
                            <i class="fas fa-file me-2"></i>Select Backup File (.sql)
                        </label>
                        <input type="file" class="form-control" id="backup_file" name="backup_file" accept=".sql" required>
                        <small class="text-muted">Only SQL backup files are accepted</small>
                    </div>
                    
                    <button type="submit" name="restore_backup" class="btn btn-restore" onclick="return confirm('Are you sure you want to restore the database? This will replace ALL current data!')">
                        <i class="fas fa-upload me-2"></i>
                        Restore Database
                    </button>
                </form>
            </div>
            
            <!-- Existing Backups -->
            <div class="backup-card">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-list me-2"></i>
                        Existing Backup Files
                    </h5>
                </div>
                
                <?php if (!empty($backup_files)): ?>
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>Backup File</th>
                                    <th>Created Date</th>
                                    <th>File Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($backup_files as $backup): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-archive me-2"></i>
                                        <strong><?php echo $backup['filename']; ?></strong>
                                    </td>
                                    <td><?php echo $backup['date']; ?></td>
                                    <td><?php echo $backup['size']; ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="?download=<?php echo urlencode($backup['filename']); ?>" 
                                               class="btn btn-outline-primary btn-sm" title="Download">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?delete=<?php echo urlencode($backup['filename']); ?>" 
                                               class="btn btn-danger-custom btn-sm" title="Delete"
                                               onclick="return confirm('Delete this backup file?')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-folder-open fa-2x text-muted mb-2"></i><br>
                        <span class="text-muted">No backup files found</span><br>
                        <small class="text-muted">Create your first backup using the form above</small>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Automated Backup Instructions -->
            <div class="info-box">
                <h6><i class="fas fa-clock me-2"></i>Setting Up Automated Daily Backups</h6>
                <p class="mb-2">To set up automated daily backups, you can use a cron job (Linux/Mac) or Task Scheduler (Windows):</p>
                
                <div class="row">
                    <div class="col-md-6">
                        <strong>Linux/Mac (Cron Job):</strong>
                        <div class="bg-dark text-white p-2 rounded mt-2">
                            <code>0 2 * * * /usr/bin/php <?php echo realpath(__FILE__); ?> --backup</code>
                        </div>
                        <small>Runs daily at 2:00 AM</small>
                    </div>
                    
                    <div class="col-md-6">
                        <strong>Windows (Task Scheduler):</strong>
                        <ol class="small mt-2">
                            <li>Open Task Scheduler</li>
                            <li>Create Basic Task</li>
                            <li>Set trigger to "Daily"</li>
                            <li>Action: Start Program</li>
                            <li>Program: php.exe</li>
                            <li>Arguments: <?php echo realpath(__FILE__); ?> --backup</li>
                        </ol>
                    </div>
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
        
        // File upload validation
        document.getElementById('backup_file').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileName = file.name.toLowerCase();
                if (!fileName.endsWith('.sql')) {
                    alert('Please select a valid SQL backup file.');
                    e.target.value = '';
                }
            }
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>

<?php
// Handle command line backup creation for automated backups
if (php_sapi_name() === 'cli' && isset($argv[1]) && $argv[1] === '--backup') {
    try {
        $timestamp = date('Y-m-d_H-i-s');
        $backup_filename = "auto_backup_{$timestamp}.sql";
        $backup_path = $backup_dir . '/' . $backup_filename;
        
        $sql_dump = createDatabaseBackup();
        
        if (file_put_contents($backup_path, $sql_dump)) {
            echo "Automated backup created successfully: {$backup_filename}\n";
            
            // Log backup creation
            $log_entry = date('Y-m-d H:i:s') . " - Automated backup created: {$backup_filename}\n";
            file_put_contents($backup_dir . '/backup_log.txt', $log_entry, FILE_APPEND | LOCK_EX);
            
            // Clean up old backups (keep only last 30 days)
            $files = glob($backup_dir . '/auto_backup_*.sql');
            $cutoff_time = time() - (30 * 24 * 60 * 60); // 30 days ago
            
            foreach ($files as $file) {
                if (filemtime($file) < $cutoff_time) {
                    unlink($file);
                    echo "Deleted old backup: " . basename($file) . "\n";
                }
            }
        } else {
            echo "Failed to create automated backup\n";
        }
    } catch (Exception $e) {
        echo "Automated backup failed: " . $e->getMessage() . "\n";
    }
}
?>