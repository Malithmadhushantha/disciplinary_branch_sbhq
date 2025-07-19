<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$search_results = [];
$search_performed = false;
$search_term = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' || isset($_GET['search'])) {
    $search_term = isset($_POST['search_term']) ? sanitizeInput($_POST['search_term']) : sanitizeInput($_GET['search']);
    
    if (!empty($search_term)) {
        $search_performed = true;
        
        // Search in preliminary investigations
        $stmt = $pdo->prepare("
            SELECT DISTINCT pi.*, at.action_name,
                   cs.charge_sheet_number, cs.issued_date as charge_sheet_date,
                   fi.investigation_number as formal_inv_number, fi.investigation_date as formal_inv_date
            FROM preliminary_investigations pi
            LEFT JOIN actions_taken at ON pi.action_taken_id = at.id
            LEFT JOIN charge_sheets cs ON pi.id = cs.pi_id
            LEFT JOIN formal_investigations fi ON pi.id = fi.pi_id
            WHERE pi.first_name LIKE ? 
               OR pi.last_name LIKE ? 
               OR pi.official_number LIKE ? 
               OR pi.nic_number LIKE ?
               OR pi.police_id_number LIKE ?
               OR pi.file_number LIKE ?
               OR CONCAT(pi.first_name, ' ', pi.last_name) LIKE ?
            ORDER BY pi.created_at DESC
        ");
        
        $search_pattern = '%' . $search_term . '%';
        $stmt->execute([
            $search_pattern, $search_pattern, $search_pattern, 
            $search_pattern, $search_pattern, $search_pattern, $search_pattern
        ]);
        $search_results = $stmt->fetchAll();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search Officer Status - Disciplinary Branch SBHQ</title>
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
        
        /* Search Card */
        .search-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-input {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 15px 60px 15px 20px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            border-color: #17a2b8;
            box-shadow: 0 0 0 0.2rem rgba(23, 162, 184, 0.25);
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: linear-gradient(135deg, #17a2b8, #138496);
            border: none;
            border-radius: 12px;
            padding: 10px 20px;
            color: white;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 5px 15px rgba(23, 162, 184, 0.3);
            color: white;
        }
        
        /* Results */
        .results-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .results-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem 2rem;
        }
        
        .results-title {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .officer-profile {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            padding: 2rem;
            margin-bottom: 1rem;
            border-radius: 15px;
        }
        
        .officer-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            border-left: 4px solid #17a2b8;
        }
        
        .investigation-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
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
        
        .print-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            border: none;
            border-radius: 10px;
            padding: 8px 15px;
            color: white;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
            color: white;
        }
        
        /* Search suggestions */
        .search-suggestions {
            margin-top: 1rem;
        }
        
        .suggestion-tag {
            display: inline-block;
            background: #e9ecef;
            color: #495057;
            padding: 0.3rem 0.8rem;
            border-radius: 15px;
            font-size: 0.8rem;
            margin: 0.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .suggestion-tag:hover {
            background: #17a2b8;
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
                    <li><a href="search_officer_status.php" class="active"> <i class="fas fa-search"></i> Search Officer Status</a></li>
                    <li><a href="preliminary_investigation.php"><i class="fas fa-file-alt"></i> Preliminary Investigations</a></li>
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
                        <h1 class="page-title">Search Officer Status</h1>
                        <div class="header-subtitle">Find and view officer disciplinary records</div>
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
            <!-- Search Form -->
            <div class="search-card">
                <h5 class="mb-4">
                    <i class="fas fa-search me-2"></i>
                    Search Officer
                </h5>
                
                <form method="POST" action="">
                    <div class="search-box">
                        <input type="text" class="form-control search-input" name="search_term" 
                               value="<?php echo htmlspecialchars($search_term); ?>"
                               placeholder="Search by name, official number, NIC number, police ID number, or file number..."
                               required>
                        <button type="submit" class="btn search-btn">
                            <i class="fas fa-search me-2"></i>Search
                        </button>
                    </div>
                </form>
                
                <div class="search-suggestions">
                    <small class="text-muted">
                        <i class="fas fa-lightbulb me-1"></i>
                        Search Examples:
                    </small><br>
                    <span class="suggestion-tag" onclick="searchSuggestion('Silva')">Silva</span>
                    <span class="suggestion-tag" onclick="searchSuggestion('12345')">12345</span>
                    <span class="suggestion-tag" onclick="searchSuggestion('199912345678')">199912345678</span>
                    <span class="suggestion-tag" onclick="searchSuggestion('PI/2024/001')">PI/2024/001</span>
                </div>
            </div>
            
            <!-- Search Results -->
            <?php if ($search_performed): ?>
                <div class="results-card">
                    <div class="results-header">
                        <h5 class="results-title">
                            <i class="fas fa-list-ul me-2"></i>
                            Search Results
                            <?php if (!empty($search_results)): ?>
                                <span class="badge bg-light text-dark ms-2"><?php echo count($search_results); ?> Results</span>
                            <?php endif; ?>
                        </h5>
                        <small>Search: "<?php echo htmlspecialchars($search_term); ?>"</small>
                    </div>
                    
                    <div class="p-4">
                        <?php if (!empty($search_results)): ?>
                            <?php foreach ($search_results as $result): ?>
                                <div class="officer-card">
                                    <!-- Officer Profile -->
                                    <div class="officer-profile">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <h4>
                                                    <i class="fas fa-user me-2"></i>
                                                    <?php echo $result['officer_rank'] . ' ' . $result['official_number']; ?>
                                                </h4>
                                                <h5><?php echo $result['first_name'] . ' ' . $result['last_name']; ?></h5>
                                                <p class="mb-1">
                                                    <i class="fas fa-id-card me-2"></i>
                                                    NIC: <?php echo $result['nic_number']; ?>
                                                </p>
                                                <p class="mb-0">
                                                    <i class="fas fa-shield-alt me-2"></i>
                                                    Police ID: <?php echo $result['police_id_number']; ?>
                                                </p>
                                                <p class="mb-0 mt-2">
                                                    <i class="fas fa-building me-2"></i>
                                                    Branch: <?php echo $result['main_branch_name']; ?>
                                                    <?php if (!empty($result['sub_branch_name'])): ?>
                                                        / <?php echo $result['sub_branch_name']; ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button onclick="printProfile('<?php echo $result['id']; ?>')" class="btn print-btn">
                                                    <i class="fas fa-print me-2"></i>
                                                    Print Profile
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Investigation Details -->
                                    <div class="investigation-detail">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <strong>File Number:</strong><br>
                                                <?php echo $result['file_number']; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Offense Date:</strong><br>
                                                <?php echo formatDate($result['offense_date']); ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Action Taken:</strong><br>
                                                <?php echo $result['action_name']; ?>
                                            </div>
                                            <div class="col-md-3">
                                                <strong>Status:</strong><br>
                                                <span class="status-badge <?php 
                                                    echo $result['status'] == 'විමර්ෂණයේ පවතී' ? 'status-active' : 
                                                        ($result['status'] == 'අවසන් කර ඇත' ? 'status-completed' : 'status-transferred'); 
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
                                            </div>
                                        </div>
                                        
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <strong>Offense Description:</strong><br>
                                                <p class="mb-2"><?php echo $result['offense_description']; ?></p>
                                            </div>
                                        </div>
                                        
                                        <!-- Additional Information -->
                                        <?php if (!empty($result['charge_sheet_number'])): ?>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <div class="alert alert-warning mb-2">
                                                        <i class="fas fa-file-contract me-2"></i>
                                                        <strong>Charge Sheet:</strong> <?php echo $result['charge_sheet_number']; ?><br>
                                                        <small>Issued Date: <?php echo formatDate($result['charge_sheet_date']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($result['formal_inv_number'])): ?>
                                            <div class="row mt-3">
                                                <div class="col-md-6">
                                                    <div class="alert alert-info mb-2">
                                                        <i class="fas fa-gavel me-2"></i>
                                                        <strong>Formal Investigation:</strong> <?php echo $result['formal_inv_number']; ?><br>
                                                        <small>Investigation Date: <?php echo formatDate($result['formal_inv_date']); ?></small>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="row mt-3">
                                            <div class="col-12">
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>
                                                    Record Created: <?php echo formatDate($result['created_at']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-search fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Search Results Found</h5>
                                <p class="text-muted">
                                    No results found for "<?php echo htmlspecialchars($search_term); ?>".<br>
                                    Try using a different search term.
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
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
        
        // Search suggestion functionality
        function searchSuggestion(term) {
            document.querySelector('input[name="search_term"]').value = term;
            document.querySelector('form').submit();
        }
        
        // Print profile functionality
        function printProfile(officerId) {
            window.open('print_officer_profile.php?id=' + officerId, '_blank');
        }
        
        // Auto-focus search input
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="search_term"]').focus();
        });
    </script>
</body>
</html>