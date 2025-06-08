<?php
session_start();

// Set timezone to Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Database connection
$host = 'localhost';
$db = 'sarvodaya';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set MySQL timezone to Sri Lanka time
$conn->query("SET time_zone = '+05:30'");

$token = '';
$valid_token = false;
$expired = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is not expired
    $current_time = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT id, username, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        $reset_expires = $user_data['reset_expires'];
        
        // Check if token has expired
        if ($reset_expires && strtotime($reset_expires) > strtotime($current_time)) {
            $valid_token = true;
        } else {
            $expired = true;
        }
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Sarvodaya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #FFCF40, #FF8C00);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
        }
        
        .container {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(246, 142, 32, 0.35);
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header img {
            width: 120px;
            margin-bottom: 20px;
        }
        
        .header h2 {
            color: #FF6600;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .divider {
            height: 5px;
            width: 80px;
            background: linear-gradient(to right, #FF8C00, #FFCF40);
            margin: 15px auto;
            border-radius: 5px;
        }
        
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #ebebeb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #fafafa;
        }
        
        .form-control:focus {
            border-color: #FF8C00;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.25);
            outline: none;
            background-color: #fff;
        }
        
        .btn {
            background: linear-gradient(to right, #FF8C00, #FFCF40);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(255, 140, 0, 0.35);
        }
        
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .back-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 15px;
        }
        
        .back-link a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }
        
        .password-requirements {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
        }
        
        .password-requirements h4 {
            color: #495057;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .password-requirements li {
            color: #6c757d;
            margin-bottom: 3px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
            <h2>Reset Password</h2>
            <?php if ($valid_token): ?>
                <p>Hello <?php echo htmlspecialchars($user_data['username']); ?>, please enter your new password.</p>
            <?php endif; ?>
            <div class="divider"></div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (!isset($_GET['token']) || empty($_GET['token'])): ?>
            <div class="alert alert-error">
                <strong>Invalid Request:</strong> No reset token provided.
            </div>
            <div class="back-link">
                <a href="forgot_password.php">Request a new password reset</a> | 
                <a href="login.php">Back to Login</a>
            </div>
        
        <?php elseif ($expired): ?>
            <div class="alert alert-warning">
                <strong>Token Expired:</strong> This password reset link has expired. Please request a new one.
            </div>
            <div class="back-link">
                <a href="forgot_password.php">Request a new password reset</a> | 
                <a href="login.php">Back to Login</a>
            </div>
        
        <?php elseif (!$valid_token): ?>
            <div class="alert alert-error">
                <strong>Invalid Token:</strong> This password reset link is invalid or has already been used.
            </div>
            <div class="back-link">
                <a href="forgot_password.php">Request a new password reset</a> | 
                <a href="login.php">Back to Login</a>
            </div>
        
        <?php else: ?>
            <div class="password-requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li>At least 8 characters long</li>
                    <li>Contains at least one uppercase letter</li>
                    <li>Contains at least one lowercase letter</li>
                    <li>Contains at least one number</li>
                    <li>Contains at least one special character (!@#$%^&*)</li>
                </ul>
            </div>
            
            <form action="reset_password_process.php" method="post">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="password">New Password</label>
                    <input type="password" id="password" name="password" class="form-control" required 
                           placeholder="Enter your new password" minlength="8">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required 
                           placeholder="Confirm your new password" minlength="8">
                </div>
                
                <button type="submit" name="reset_password" class="btn">Reset Password</button>
            </form>
            
            <div class="back-link">
                Remember your password? <a href="login.php">Back to Login</a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Client-side password validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    const passwordValue = password.value;
                    const confirmPasswordValue = confirmPassword.value;
                    
                    // Check if passwords match
                    if (passwordValue !== confirmPasswordValue) {
                        e.preventDefault();
                        alert('Passwords do not match. Please try again.');
                        return;
                    }
                    
                    // Password strength validation
                    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/;
                    
                    if (!passwordRegex.test(passwordValue)) {
                        e.preventDefault();
                        alert('Password does not meet the requirements. Please check the password requirements and try again.');
                        return;
                    }
                });
            }
        });
    </script>
</body>
</html>