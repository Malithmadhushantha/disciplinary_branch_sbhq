<?php
require_once 'config.php';
requireLogin();

$user = getCurrentUser();
$formal_investigation = null;
$preliminary_investigation = null;
$error = '';
$success = '';

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
    header('Location: formal_disciplinary_investigation.php');
    exit();
} else {
    $formal_investigation = $investigation_data;
}

// Handle form submission for updating formal investigation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_investigation'])) {
    $investigation_number = sanitizeInput($_POST['investigation_number']);
    $investigation_date = $_POST['investigation_date'];
    $conducting_officer = sanitizeInput($_POST['conducting_officer']);
    $complaint_officer = sanitizeInput($_POST['complaint_officer']);
    $defense_officer = sanitizeInput($_POST['defense_officer']);
    $notes = sanitizeInput($_POST['notes']);
    
    // Validation
    if (empty($investigation_number) || empty($investigation_date) || 
        empty($conducting_officer) || empty($complaint_officer) || empty($defense_officer)) {
        $error = 'Please fill in all required fields (*)';
    } else {
        // Check if investigation number already exists for other investigations
        $stmt = $pdo->prepare("SELECT id FROM formal_investigations WHERE investigation_number = ? AND id != ?");
        $stmt->execute([$investigation_number, $investigation_id]);
        if ($stmt->fetch()) {
            $error = 'This investigation number is already in use by another formal investigation';
        } else {
            // Update formal investigation
            $stmt = $pdo->prepare("
                UPDATE formal_investigations SET
                investigation_number = ?, investigation_date = ?, conducting_officer = ?, 
                complaint_officer = ?, defense_officer = ?, notes = ?,
                updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            
            if ($stmt->execute([$investigation_number, $investigation_date, $conducting_officer, 
                               $complaint_officer, $defense_officer, $notes, $investigation_id])) {
                $success = 'Formal investigation updated successfully';
                
                // Refresh investigation data
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
                $formal_investigation = $stmt->fetch();
            } else {
                $error = 'Error updating formal investigation';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Formal Investigation - Disciplinary Branch SBHQ</title>
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
        
        .form-control, .form-select {
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
        
        .required {
            color: #e74c3c;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #8e44ad, #9b59b6);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(142, 68, 173, 0.3);
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
        
        /* Officer Info Card */
        .officer-info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .officer-info h6 {
            margin-bottom: 1rem;
            font-weight: 600;
        }
        
        .officer-info .info-item {
            margin-bottom: 0.5rem;
        }
        
        .officer-info .info-item:last-child {
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
                        <h1 class="page-title">Edit Formal Investigation</h1>
                        <div class="header-subtitle">Update formal investigation details</div>
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
            <!-- Officer Information -->
            <div class="officer-info">
                <h6>
                    <i class="fas fa-user me-2"></i>
                    Officer Under Investigation
                </h6>
                <div class="row">
                    <div class="col-md-4">
                        <div class="info-item">
                            <strong>Rank & Number:</strong> <?php echo $formal_investigation['officer_rank'] . ' ' . $formal_investigation['official_number']; ?>
                        </div>
                        <div class="info-item">
                            <strong>Name:</strong> <?php echo $formal_investigation['first_name'] . ' ' . $formal_investigation['last_name']; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item">
                            <strong>File Number:</strong> <?php echo $formal_investigation['file_number']; ?>
                        </div>
                        <div class="info-item">
                            <strong>NIC:</strong> <?php echo $formal_investigation['nic_number']; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="info-item">
                            <strong>Branch:</strong> <?php echo $formal_investigation['main_branch_name']; ?>
                        </div>
                        <?php if (!empty($formal_investigation['sub_branch_name'])): ?>
                        <div class="info-item">
                            <strong>Sub Branch:</strong> <?php echo $formal_investigation['sub_branch_name']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Edit Form -->
            <div class="form-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-edit me-2"></i>
                        Edit Formal Investigation Details
                    </h5>
                </div>
                
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
                            <label for="investigation_number" class="form-label">
                                <i class="fas fa-hashtag me-2"></i>Formal Investigation Number <span class="required">*</span> (Sinhala)
                            </label>
                            <input type="text" class="form-control" id="investigation_number" name="investigation_number" 
                                   value="<?php echo htmlspecialchars($formal_investigation['investigation_number']); ?>" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="investigation_date" class="form-label">
                                <i class="fas fa-calendar me-2"></i>Investigation Date <span class="required">*</span>
                            </label>
                            <input type="date" class="form-control" id="investigation_date" name="investigation_date" 
                                   value="<?php echo $formal_investigation['investigation_date']; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="conducting_officer" class="form-label">
                            <i class="fas fa-user-tie me-2"></i>Conducting Officer Name <span class="required">*</span> (Sinhala)
                        </label>
                        <input type="text" class="form-control" id="conducting_officer" name="conducting_officer" 
                               value="<?php echo htmlspecialchars($formal_investigation['conducting_officer']); ?>"
                               required placeholder="Enter conducting officer name in Sinhala">
                    </div>
                    
                    <div class="mb-3">
                        <label for="complaint_officer" class="form-label">
                            <i class="fas fa-user-check me-2"></i>Complaint Officer Name <span class="required">*</span> (Sinhala)
                        </label>
                        <input type="text" class="form-control" id="complaint_officer" name="complaint_officer" 
                               value="<?php echo htmlspecialchars($formal_investigation['complaint_officer']); ?>"
                               required placeholder="Enter complaint officer name in Sinhala">
                    </div>
                    
                    <div class="mb-3">
                        <label for="defense_officer" class="form-label">
                            <i class="fas fa-user-shield me-2"></i>Defense Officer Name <span class="required">*</span> (Sinhala)
                        </label>
                        <input type="text" class="form-control" id="defense_officer" name="defense_officer" 
                               value="<?php echo htmlspecialchars($formal_investigation['defense_officer']); ?>"
                               required placeholder="Enter defense officer name in Sinhala">
                    </div>
                    
                    <div class="mb-4">
                        <label for="notes" class="form-label">
                            <i class="fas fa-sticky-note me-2"></i>Notes (Sinhala)
                        </label>
                        <textarea class="form-control" id="notes" name="notes" rows="4" 
                                  placeholder="Enter additional notes or details in Sinhala"><?php echo htmlspecialchars($formal_investigation['notes'] ?? ''); ?></textarea>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex justify-content-between mt-4">
                        <div>
                            <a href="view_formal_investigation.php?id=<?php echo $investigation_id; ?>" class="btn btn-cancel me-2">
                                <i class="fas fa-eye me-2"></i>
                                View Details
                            </a>
                            <a href="formal_disciplinary_investigation.php" class="btn btn-cancel">
                                <i class="fas fa-arrow-left me-2"></i>
                                Back to List
                            </a>
                        </div>
                        
                        <button type="submit" name="update_investigation" class="btn btn-submit">
                            <i class="fas fa-save me-2"></i>
                            Update Investigation
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Related Information -->
            <div class="form-card">
                <h6 class="mb-3">
                    <i class="fas fa-info-circle me-2"></i>
                    Related Preliminary Investigation Details
                </h6>
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Offense Date:</strong> <?php echo formatDate($formal_investigation['offense_date']); ?></p>
                        <p><strong>Investigating Officer:</strong> <?php echo $formal_investigation['investigating_officer']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Action Taken:</strong> <?php echo $formal_investigation['action_name']; ?></p>
                        <p><strong>Current Status:</strong> 
                            <?php 
                            $status_map = [
                                'විමර්ෂණයේ පවතී' => 'Under Investigation',
                                'අවසන් කර ඇත' => 'Completed',
                                'වෙනත් ස්ථානයකට මාරු කර ඇත' => 'Transferred'
                            ];
                            echo $status_map[$formal_investigation['pi_status']] ?? $formal_investigation['pi_status'];
                            ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <p><strong>Offense Description:</strong></p>
                        <p class="text-muted" style="font-family: 'Noto Sans Sinhala', sans-serif;">
                            <?php echo nl2br(htmlspecialchars($formal_investigation['offense_description'])); ?>
                        </p>
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
        
        // Form validation
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
        
        // Real-time validation feedback
        document.querySelectorAll('[required]').forEach(field => {
            field.addEventListener('input', function() {
                if (this.value.trim()) {
                    this.classList.remove('is-invalid');
                    this.classList.add('is-valid');
                } else {
                    this.classList.remove('is-valid');
                    this.classList.add('is-invalid');
                }
            });
        });
        
        // Auto-save draft functionality (optional)
        function saveDraft() {
            const formData = new FormData(document.querySelector('form'));
            const draftData = {};
            for (let [key, value] of formData.entries()) {
                if (key !== 'update_investigation') {
                    draftData[key] = value;
                }
            }
            localStorage.setItem('formal_investigation_draft_<?php echo $investigation_id; ?>', JSON.stringify(draftData));
        }
        
        // Load draft on page load
        window.addEventListener('load', function() {
            const draft = localStorage.getItem('formal_investigation_draft_<?php echo $investigation_id; ?>');
            if (draft) {
                const draftData = JSON.parse(draft);
                Object.keys(draftData).forEach(key => {
                    const field = document.querySelector(`[name="${key}"]`);
                    if (field && field.value === '') {
                        field.value = draftData[key];
                    }
                });
            }
        });
        
        // Save draft on form change
        document.querySelector('form').addEventListener('input', saveDraft);
        
        // Clear draft on successful submission
        <?php if ($success): ?>
        localStorage.removeItem('formal_investigation_draft_<?php echo $investigation_id; ?>');
        <?php endif; ?>
    </script>
</body>
</html>