<?php
session_start();

// Set timezone to Sri Lanka
date_default_timezone_set('Asia/Colombo');

// Include PHPMailer classes
require 'PHPMailer-master/src/Exception.php';
require 'PHPMailer-master/src/PHPMailer.php';
require 'PHPMailer-master/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = trim($_POST['email']);
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
        
        // Generate a unique reset token
        $reset_token = bin2hex(random_bytes(16));
        // Set expiration to 1 hour from now in Sri Lanka time
        $reset_expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Update user record with reset token
        $update_stmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?");
        $update_stmt->bind_param("sss", $reset_token, $reset_expires, $email);
        
        if ($update_stmt->execute()) {
            // Send email using PHPMailer
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Change to your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'youremail@gmail.com'; // Your email
                $mail->Password   = 'abcd efgh ijkl mnop'; // Your email app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                
                // Recipients
                $mail->setFrom('kaushalyachamo256@gmail.com', 'Sarvodaya Support');
                $mail->addAddress($email, $user_data['username']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Password Reset Request - Sarvodaya';
                
                $reset_link = "http://localhost/Sarvodaya/Sarvodaya/Sarvodaya/reset_password.php?token=" . $reset_token;
                
                // Show current Sri Lanka time in email for debugging
                $current_sl_time = date('Y-m-d H:i:s T');
                $expires_sl_time = date('Y-m-d H:i:s T', strtotime($reset_expires));
                
                $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background: linear-gradient(135deg, #FFCF40, #FF8C00); padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                        .header h1 { color: white; margin: 0; }
                        .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                        .btn { background: linear-gradient(to right, #FF8C00, #FFCF40); color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 20px 0; }
                        .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
                        .debug-info { background: #e9ecef; padding: 10px; border-radius: 5px; margin: 10px 0; font-size: 12px; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>Sarvodaya</h1>
                        </div>
                        <div class='content'>
                            <h2>Password Reset Request</h2>
                            <p>Hello " . htmlspecialchars($user_data['username']) . ",</p>
                            <p>We received a request to reset your password. If you didn't make this request, please ignore this email.</p>
                            <p>To reset your password, click the button below:</p>
                            <a href='" . $reset_link . "' class='btn'>Reset Password</a>
                            <p>Or copy and paste this link into your browser:</p>
                            <p style='word-break: break-all;'>" . $reset_link . "</p>
                            <p><strong>This link will expire in 1 hour (Sri Lanka Time).</strong></p>
                            <div class='debug-info'>
                                <strong>Debug Information:</strong><br>
                                Email sent at: " . $current_sl_time . "<br>
                                Link expires at: " . $expires_sl_time . "
                            </div>
                            <p>If you have any questions, please contact our support team.</p>
                        </div>
                        <div class='footer'>
                            <p>&copy; 2024 Sarvodaya. All rights reserved.</p>
                        </div>
                    </div>
                </body>
                </html>
                ";
                
                $mail->AltBody = "Hello " . $user_data['username'] . ",\n\n" .
                               "We received a request to reset your password.\n\n" .
                               "To reset your password, visit this link: " . $reset_link . "\n\n" .
                               "This link will expire in 1 hour (Sri Lanka Time).\n\n" .
                               "Email sent at: " . $current_sl_time . "\n" .
                               "Link expires at: " . $expires_sl_time . "\n\n" .
                               "If you didn't request this, please ignore this email.\n\n" .
                               "Sarvodaya Support Team";
                
                $mail->send();
                
                $_SESSION['message'] = 'Password reset link has been sent to your email address. The link will expire in 1 hour (Sri Lanka Time).';
                $_SESSION['msg_type'] = 'success';
                
            } catch (Exception $e) {
                $_SESSION['message'] = 'Failed to send email. Please try again later.';
                $_SESSION['msg_type'] = 'error';
            }
        } else {
            $_SESSION['message'] = 'Error processing request. Please try again.';
            $_SESSION['msg_type'] = 'error';
        }
        
        $update_stmt->close();
    } else {
        // Don't reveal if email exists or not for security
        $_SESSION['message'] = 'If this email exists in our system, you will receive a password reset link.';
        $_SESSION['msg_type'] = 'success';
    }
    
    $stmt->close();
    header('Location: forgot_password.php');
    exit();
}

$conn->close();
?>