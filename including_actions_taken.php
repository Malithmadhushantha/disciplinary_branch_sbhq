<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle form submission for adding new action
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_action'])) {
    $action_name = sanitizeInput($_POST['action_name']);
    $action_code = sanitizeInput($_POST['action_code']);
    
    if (!empty($action_name) && !empty($action_code)) {
        // Check if action already exists
        $stmt = $pdo->prepare("SELECT id FROM actions_taken WHERE action_name = ? OR action_code = ?");
        $stmt->execute([$action_name, $action_code]);
        if ($stmt->fetch()) {
            $error = 'This action already exists';
        } else {
            $stmt = $pdo->prepare("INSERT INTO actions_taken (action_name, action_code) VALUES (?, ?)");
            if ($stmt->execute([$action_name, $action_code])) {
                $success = 'Action added successfully';
            } else {
                $error = 'Error adding action';
            }
        }
    } else {
        $error = 'Please fill in all fields';
    }
}

// Handle deletion
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    
    // Check if action is being used in investigations
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM preliminary_investigations WHERE action_taken_id = ?");
    $stmt->execute([$delete_id]);
    $count = $stmt->fetch()['count'];
    
    if ($count > 0) {
        $error = 'This action cannot be deleted as it is being used in investigations';
    } else {
        $stmt = $pdo->prepare("DELETE FROM actions_taken WHERE id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = 'Action deleted successfully';
        } else {
            $error = 'Error deleting action';
        }
    }
}

// Get all actions
$stmt = $pdo->query("SELECT * FROM actions_taken ORDER BY action_name");
$actions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Actions Taken - Disciplinary Branch SBHQ</title>
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
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .btn-add {
            background: linear-gradient(135deg, #27ae60, #229954);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(39, 174, 96, 0.3);
            color: white;
        }
        
        /* Actions Table */
        .actions-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
        }
        
        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            background: #e74c3c;
            border: none;
            border-radius: 6px;
            padding: 0.4rem 0.8rem;
            color: white;
            font-size: 0.8rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            background: #c0392b;
            color: white;
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
     <!-- Main Content -->    
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
                        <h1 class="page-title">Manage Actions Taken</h1>
                        <div class="header-subtitle">Configure disciplinary actions</div>
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
            <!-- Add New Action Form -->
            <div class="form-card">
                <h5 class="mb-4">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add New Action
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
                        <div class="col-md-6 mb-3">
                            <label for="action_name" class="form-label">
                                <i class="fas fa-tag me-2"></i>Action Name (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="action_name" name="action_name" 
                                   required placeholder="e.g., චෝදනා පත්‍ර නිකුත් කිරීම">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label for="action_code" class="form-label">
                                <i class="fas fa-code me-2"></i>Action Code (English)
                            </label>
                            <input type="text" class="form-control" id="action_code" name="action_code" 
                                   required placeholder="e.g., charge_sheet">
                        </div>
                        
                        <div class="col-md-2 mb-3 d-flex align-items-end">
                            <button type="submit" name="add_action" class="btn btn-add w-100">
                                <i class="fas fa-plus me-2"></i>Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Actions List -->
            <div class="actions-table">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-list me-2"></i>
                        Actions List
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Action Name</th>
                                <th>Action Code</th>
                                <th>Date Added</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($actions as $action): ?>
                            <tr>
                                <td><strong><?php echo $action['id']; ?></strong></td>
                                <td><?php echo $action['action_name']; ?></td>
                                <td><code><?php echo $action['action_code']; ?></code></td>
                                <td><?php echo formatDate($action['created_at']); ?></td>
                                <td>
                                    <button type="button" class="btn btn-delete" 
                                            onclick="confirmDelete(<?php echo $action['id']; ?>)" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($actions)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2"></i><br>
                                    <span class="text-muted">No actions added yet</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Pre-defined Actions Help -->
            <div class="form-card">
                <h6 class="mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Pre-defined Actions
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Charge Sheet Issuance</li>
                            <li><i class="fas fa-check text-success me-2"></i>Formal Disciplinary Investigation</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Minor Disciplinary Investigation</li>
                            <li><i class="fas fa-check text-success me-2"></i>Case Closure</li>
                        </ul>
                    </div>
                </div>
                <small class="text-muted">
                    <i class="fas fa-lightbulb me-1"></i>
                    Use the above list as reference when adding new actions
                </small>
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
        
        // Confirm delete function
        function confirmDelete(actionId) {
            if (confirm('Are you sure you want to delete this action?\n\nDeleting this action may affect investigations that use it.')) {
                window.location.href = '?delete_id=' + actionId;
            }
        }
        
        // Auto-generate action code based on action name
        document.getElementById('action_name').addEventListener('input', function() {
            const actionName = this.value;
            const actionCode = actionName
                .toLowerCase()
                .replace(/\s+/g, '_')
                .replace(/[^\w\s]/gi, '')
                .replace(/ා/g, 'a')
                .replace(/ි/g, 'i')
                .replace(/ී/g, 'i')
                .replace(/ු/g, 'u')
                .replace(/ූ/g, 'u')
                .replace(/ෙ/g, 'e')
                .replace(/ේ/g, 'e')
                .replace(/ො/g, 'o')
                .replace(/ෝ/g, 'o');
            
            // Simple transliteration for common words
            const transliterations = {
                'චෝදනා': 'charge',
                'පත්ර': 'sheet',
                'විධිමත්': 'formal',
                'විනය': 'disciplinary',
                'පරීක්ෂණ': 'investigation',
                'නිකුත්': 'issue',
                'කිරීම': 'ing',
                'අවසන්': 'close',
                'මාරු': 'transfer'
            };
            
            let generatedCode = actionCode;
            for (const [sinhala, english] of Object.entries(transliterations)) {
                generatedCode = generatedCode.replace(new RegExp(sinhala, 'g'), english);
            }
            
            document.getElementById('action_code').value = generatedCode;
        });
    </script>
</body>
</html>