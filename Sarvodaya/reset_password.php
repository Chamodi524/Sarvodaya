<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sarvodaya';
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Start session
session_start();

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate inputs
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 8 || !preg_match("#[0-9]+#", $new_password) || !preg_match("#[a-zA-Z]+#", $new_password)) {
        $error = "Password must be at least 8 characters long and contain at least one number and one letter";
    } else {
        // Check if token is valid
        $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            $error = "Invalid or expired token";
        } else {
            $user = $result->fetch_assoc();
            
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user's password and clear reset token
            $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $update_stmt->bind_param("si", $hashed_password, $user['id']);
            
            if ($update_stmt->execute()) {
                $success = "Password has been reset successfully! You can now login with your new password.";
            } else {
                $error = "Failed to reset password. Please try again.";
            }
            
            $update_stmt->close();
        }
        $stmt->close();
    }
}

// Get token from URL if present
$token = $_GET['token'] ?? '';
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
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
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
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 15px;
        }
        
        .login-link a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .message {
            text-align: center;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }
        
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }
        
        .password-requirements {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
            padding-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
            <h2>Reset Your Password</h2>
            <div class="divider"></div>
        </div>
        
        <?php if (isset($error)): ?>
            <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="message success-message"><?php echo htmlspecialchars($success); ?></div>
            <div class="login-link">
                <a href="login.php">Return to Login</a>
            </div>
        <?php else: ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" required placeholder="Enter new password">
                    <div class="password-requirements">
                        Must be at least 8 characters with at least one number and one letter
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm new password">
                </div>
                
                <button type="submit" class="btn">Reset Password</button>
            </form>
            
            <div class="login-link">
                Remember your password? <a href="login.php">Login here</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php
$conn->close();
?>