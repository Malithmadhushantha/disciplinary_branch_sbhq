<?php
require_once 'config.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rank = sanitizeInput($_POST['rank']);
    $official_number = sanitizeInput($_POST['official_number']);
    $name_with_initials = sanitizeInput($_POST['name_with_initials']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $re_password = $_POST['re_password'];
    
    // Validation
    if (empty($rank) || empty($name_with_initials) || empty($email) || empty($password) || empty($re_password)) {
        $error = 'Please fill in all required fields';
    } elseif ($password !== $re_password) {
        $error = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'This email is already registered';
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (rank, official_number, name_with_initials, email, password) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$rank, $official_number, $name_with_initials, $email, $hashed_password])) {
                $success = 'Account created successfully. Redirecting to login page...';
                header("refresh:2;url=login.php");
            } else {
                $error = 'Error creating account';
            }
        }
    }
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Disciplinary Branch SBHQ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="icon" type="image/png" sizes="32x32" href="image/favicon.png">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Noto+Sans+Sinhala:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .register-card {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .register-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            padding: 2rem;
            text-align: center;
        }
        
        .form-control, .form-select {
            border-radius: 10px;
            border: 2px solid #e1e5e9;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .police-badge {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #ffd700;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="register-card">
                        <div class="register-header">
                            <i class="fas fa-user-plus police-badge"></i>
                            <h2 class="mb-0">Registration</h2>
                            <p class="mb-0">Disciplinary Branch - POLICE SPECIAL BRANCH</p>
                        </div>
                        
                        <div class="card-body p-4">
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
                                        <label for="rank" class="form-label">
                                            <i class="fas fa-star me-2"></i>Rank *
                                        </label>
                                        <select class="form-select" id="rank" name="rank" required>
                                            <option value="">Select Rank</option>
                                            <?php foreach ($ranks as $key => $value): ?>
                                                <option value="<?php echo $key; ?>"><?php echo $key; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="official_number" class="form-label">
                                            <i class="fas fa-id-badge me-2"></i>Official Number
                                        </label>
                                        <input type="text" class="form-control" id="official_number" name="official_number" placeholder="Official Number (Optional)">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="name_with_initials" class="form-label">
                                        <i class="fas fa-user me-2"></i>Name with Initials * (Sinhala)
                                    </label>
                                    <input type="text" class="form-control" id="name_with_initials" name="name_with_initials" required placeholder="e.g., A.B.C. Perera">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-2"></i>Email Address *
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" required placeholder="email@example.com">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Password *
                                        </label>
                                        <input type="password" class="form-control" id="password" name="password" required placeholder="At least 6 characters">
                                    </div>
                                    
                                    <div class="col-md-6 mb-4">
                                        <label for="re_password" class="form-label">
                                            <i class="fas fa-lock me-2"></i>Confirm Password *
                                        </label>
                                        <input type="password" class="form-control" id="re_password" name="re_password" required placeholder="Re-enter password">
                                    </div>
                                </div>
                                
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary btn-register">
                                        <i class="fas fa-user-plus me-2"></i>Register
                                    </button>
                                </div>
                                
                                <div class="text-center">
                                    <p class="mb-0">Already have an account? 
                                        <a href="login.php" class="text-decoration-none">
                                            <strong>Login here</strong>
                                        </a>
                                    </p>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>