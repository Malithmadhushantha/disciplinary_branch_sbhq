<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$error = '';
$success = '';

// Handle form submission for adding formal investigation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_formal_investigation'])) {
    $pi_id = (int)$_POST['pi_id'];
    $investigation_number = sanitizeInput($_POST['investigation_number']);
    $investigation_date = $_POST['investigation_date'];
    $conducting_officer = sanitizeInput($_POST['conducting_officer']);
    $complaint_officer = sanitizeInput($_POST['complaint_officer']);
    $defense_officer = sanitizeInput($_POST['defense_officer']);
    $notes = sanitizeInput($_POST['notes']);
    
    if (!empty($pi_id) && !empty($investigation_number) && !empty($investigation_date) && 
        !empty($conducting_officer) && !empty($complaint_officer) && !empty($defense_officer)) {
        
        // Check if formal investigation already exists for this preliminary investigation
        $stmt = $pdo->prepare("SELECT id FROM formal_investigations WHERE pi_id = ?");
        $stmt->execute([$pi_id]);
        if ($stmt->fetch()) {
            $error = 'A formal disciplinary investigation already exists for this preliminary investigation';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO formal_investigations 
                (pi_id, investigation_number, investigation_date, conducting_officer, 
                 complaint_officer, defense_officer, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$pi_id, $investigation_number, $investigation_date, $conducting_officer, 
                            $complaint_officer, $defense_officer, $notes])) {
                header('Location: formal_disciplinary_investigation.php?success=1');
                exit();
            } else {
                $error = 'Error adding formal disciplinary investigation';
            }
        }
    } else {
        $error = 'Please fill in required fields';
    }
}

// Get investigations eligible for formal investigation (where action taken is formal investigation)
$stmt = $pdo->prepare("
    SELECT pi.*, at.action_name 
    FROM preliminary_investigations pi 
    JOIN actions_taken at ON pi.action_taken_id = at.id 
    WHERE at.action_code = 'formal_investigation' AND pi.id NOT IN (SELECT pi_id FROM formal_investigations)
    ORDER BY pi.created_at DESC
");
$stmt->execute();
$eligible_investigations = $stmt->fetchAll();

// Get all formal investigations with investigation details
$stmt = $pdo->prepare("
    SELECT fi.*, pi.file_number, pi.officer_rank, pi.official_number, 
           pi.first_name, pi.last_name, pi.nic_number, pi.offense_description
    FROM formal_investigations fi
    JOIN preliminary_investigations pi ON fi.pi_id = pi.id
    ORDER BY fi.created_at DESC
");
$stmt->execute();
$formal_investigations = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Formal Disciplinary Investigation - Disciplinary Branch SBHQ</title>
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
        
        /* Cards */
        .form-card, .table-card {
            background: white;
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            margin-bottom: 2rem;
        }
        
        .form-control, .form-select, .form-control:focus, .form-select:focus {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #8e44ad;
            box-shadow: 0 0 0 0.2rem rgba(142, 68, 173, 0.25);
        }
        
        .form-label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 0.5rem;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(142, 68, 173, 0.3);
            color: white;
        }
        
        /* Investigation Cards */
        .investigation-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .investigation-card:hover {
            border-color: #8e44ad;
            box-shadow: 0 5px 15px rgba(142, 68, 173, 0.1);
        }
        
        .investigation-card.selected {
            border-color: #27ae60;
            background: #d4edda;
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
        
        /* Status Badge */
        .status-formal {
            background: #f3e5f5;
            color: #6a1b9a;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
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
                        <h1 class="page-title">Formal Disciplinary Investigation</h1>
                        <div class="header-subtitle">Manage formal investigation proceedings</div>
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
            <!-- Add Formal Investigation Form -->
            <div class="form-card">
                <h5 class="mb-4">
                    <i class="fas fa-gavel me-2"></i>
                    Add New Formal Disciplinary Investigation
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
                
                <?php if (!empty($eligible_investigations)): ?>
                    <div class="mb-4">
                        <h6>Investigations Eligible for Formal Disciplinary Investigation:</h6>
                        <div id="investigationsList">
                            <?php foreach ($eligible_investigations as $investigation): ?>
                                <div class="investigation-card" onclick="selectInvestigation(<?php echo $investigation['id']; ?>)">
                                    <div class="row">
                                        <div class="col-md-3">
                                            <strong>File Number:</strong> <?php echo $investigation['file_number']; ?>
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Officer:</strong> <?php echo $investigation['officer_rank'] . ' ' . $investigation['official_number']; ?><br>
                                            <small><?php echo $investigation['first_name'] . ' ' . $investigation['last_name']; ?></small>
                                        </div>
                                        <div class="col-md-3">
                                            <strong>NIC:</strong> <?php echo $investigation['nic_number']; ?>
                                        </div>
                                        <div class="col-md-2">
                                            <small class="text-muted">Click to select</small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <form method="POST" action="" id="formalInvestigationForm" style="display: none;">
                        <input type="hidden" id="selectedPiId" name="pi_id" value="">
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="investigation_number" class="form-label">
                                    <i class="fas fa-hashtag me-2"></i>Formal Investigation Number * (Sinhala)
                                </label>
                                <input type="text" class="form-control" id="investigation_number" name="investigation_number" required>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="investigation_date" class="form-label">
                                    <i class="fas fa-calendar me-2"></i>Investigation Date *
                                </label>
                                <input type="date" class="form-control" id="investigation_date" name="investigation_date" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="conducting_officer" class="form-label">
                                <i class="fas fa-user-tie me-2"></i>Conducting Officer Name * (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="conducting_officer" name="conducting_officer" 
                                   required placeholder="Enter conducting officer name in Sinhala">
                        </div>
                        
                        <div class="mb-3">
                            <label for="complaint_officer" class="form-label">
                                <i class="fas fa-user-check me-2"></i>Complaint Officer Name * (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="complaint_officer" name="complaint_officer" 
                                   required placeholder="Enter complaint officer name in Sinhala">
                        </div>
                        
                        <div class="mb-3">
                            <label for="defense_officer" class="form-label">
                                <i class="fas fa-user-shield me-2"></i>Defense Officer Name * (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="defense_officer" name="defense_officer" 
                                   required placeholder="Enter defense officer name in Sinhala">
                        </div>
                        
                        <div class="mb-4">
                            <label for="notes" class="form-label">
                                <i class="fas fa-sticky-note me-2"></i>Notes (Sinhala)
                            </label>
                            <textarea class="form-control" id="notes" name="notes" rows="4" 
                                      placeholder="Enter additional notes or details in Sinhala"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" name="add_formal_investigation" class="btn btn-submit">
                                <i class="fas fa-save me-2"></i>
                                Save Formal Investigation
                            </button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No investigations eligible for formal disciplinary investigation. First complete a preliminary investigation with "Formal Disciplinary Investigation" action.
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Existing Formal Investigations -->
            <div class="table-card">
                <div class="table-header">
                    <h5 class="table-title">
                        <i class="fas fa-list me-2"></i>
                        Completed Formal Disciplinary Investigations
                    </h5>
                </div>
                
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>Investigation Number</th>
                                <th>Officer</th>
                                <th>File Number</th>
                                <th>Investigation Date</th>
                                <th>Conducting Officer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($formal_investigations as $investigation): ?>
                            <tr>
                                <td><strong><?php echo $investigation['investigation_number']; ?></strong></td>
                                <td>
                                    <?php echo $investigation['officer_rank'] . ' ' . $investigation['official_number']; ?><br>
                                    <small class="text-muted"><?php echo $investigation['first_name'] . ' ' . $investigation['last_name']; ?></small>
                                </td>
                                <td><?php echo $investigation['file_number']; ?></td>
                                <td><?php echo formatDate($investigation['investigation_date']); ?></td>
                                <td><?php echo $investigation['conducting_officer']; ?></td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="view_formal_investigation.php?id=<?php echo $investigation['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="edit_formal_investigation.php?id=<?php echo $investigation['id']; ?>" 
                                           class="btn btn-outline-success btn-sm" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="print_formal_investigation.php?id=<?php echo $investigation['id']; ?>" 
                                           class="btn btn-outline-info btn-sm" title="Print" target="_blank">
                                            <i class="fas fa-print"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($formal_investigations)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <i class="fas fa-folder-open fa-2x text-muted mb-2"></i><br>
                                    <span class="text-muted">No formal disciplinary investigations conducted yet</span>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
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
        
        // Investigation selection
        function selectInvestigation(piId) {
            // Remove previous selections
            document.querySelectorAll('.investigation-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select current card
            event.target.closest('.investigation-card').classList.add('selected');
            
            // Set the ID and show the form
            document.getElementById('selectedPiId').value = piId;
            document.getElementById('formalInvestigationForm').style.display = 'block';
            
            // Scroll to form
            document.getElementById('formalInvestigationForm').scrollIntoView({
                behavior: 'smooth'
            });
        }
    </script>
</body>
</html>