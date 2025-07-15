<?php
session_start();

// Include PHPMailer classes at the top
require_once 'PHPMailer-master/src/Exception.php';
require_once 'PHPMailer-master/src/PHPMailer.php';
require_once 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $token = trim($_POST['token']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate input
    if (empty($token) || empty($password) || empty($confirm_password)) {
        $_SESSION['message'] = 'All fields are required.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        $_SESSION['message'] = 'Passwords do not match.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        $_SESSION['message'] = 'Password must be at least 8 characters long.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    // Check password requirements
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])[A-Za-z\d!@#$%^&*]{8,}$/', $password)) {
        $_SESSION['message'] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    // Verify token and check expiration
    $current_time = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT id, username, email, reset_expires FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $_SESSION['message'] = 'Invalid reset token.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    $user_data = $result->fetch_assoc();
    $reset_expires = $user_data['reset_expires'];
    
    // Check if token has expired
    if (!$reset_expires || strtotime($reset_expires) <= strtotime($current_time)) {
        $_SESSION['message'] = 'Reset token has expired. Please request a new password reset.';
        $_SESSION['msg_type'] = 'error';
        header('Location: forgot_password.php');
        exit();
    }
    $user_id = $user_data['id'];
    $username = $user_data['username'];
    $email = $user_data['email'];
    
    // Hash the new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update password and clear reset token
    $update_stmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
    $update_stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($update_stmt->execute()) {
        // Send confirmation email (optional)
        sendPasswordChangeNotification($email, $username);
        
        // Set success message
        $_SESSION['message'] = 'Your password has been successfully reset. You can now login with your new password.';
        $_SESSION['msg_type'] = 'success';
        
        // Redirect to login page
        header('Location: login.php');
        exit();
    } else {
        $_SESSION['message'] = 'Failed to update password. Please try again.';
        $_SESSION['msg_type'] = 'error';
        header('Location: reset_password.php?token=' . urlencode($token));
        exit();
    }
    
    $update_stmt->close();
    $stmt->close();
} else {
    // Invalid request
    header('Location: login.php');
    exit();
}

$conn->close();

// Function to send password change notification
function sendPasswordChangeNotification($email, $username) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'kaushalyachamo256@gmail.com'; // Your email
        $mail->Password   = 'bbco yvbh bohc repm'; // Your email app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('chamo256@gmail.com', 'Sarvodaya Support');
        $mail->addAddress($email, $username);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Password Successfully Changed - Sarvodaya';
        
        $mail->Body = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #FFCF40, #FF8C00); padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                .header h1 { color: white; margin: 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                .alert { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Sarvodaya</h1>
                </div>
                <div class='content'>
                    <h2>Password Successfully Changed</h2>
                    <p>Hello " . htmlspecialchars($username) . ",</p>
                    <div class='alert'>
                        <strong>Your password has been successfully changed.</strong>
                    </div>
                    <p>Your Sarvodaya account password was recently changed on " . date('F j, Y \a\t g:i A') . ".</p>
                    <p>If you made this change, you can ignore this email. If you did not change your password, please contact our support team immediately.</p>
                    <p><strong>Security Tips:</strong></p>
                    <ul>
                        <li>Never share your password with anyone</li>
                        <li>Use a unique password for your Sarvodaya account</li>
                        <li>Consider using a password manager</li>
                        <li>Log out of your account when using shared computers</li>
                    </ul>
                    <p>If you have any questions or concerns, please contact our support team.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Sarvodaya. All rights reserved.</p>
                    <p>This is an automated message, please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hello " . $username . ",\n\n" .
                       "Your Sarvodaya account password was successfully changed on " . date('F j, Y \a\t g:i A') . ".\n\n" .
                       "If you made this change, you can ignore this email. If you did not change your password, please contact our support team immediately.\n\n" .
                       "Security Tips:\n" .
                       "- Never share your password with anyone\n" .
                       "- Use a unique password for your Sarvodaya account\n" .
                       "- Consider using a password manager\n" .
                       "- Log out of your account when using shared computers\n\n" .
                       "Sarvodaya Support Team";
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        // Log error but don't stop the password reset process
        error_log("Password change notification email failed: " . $mail->ErrorInfo);
        return false;
    }
}
?>