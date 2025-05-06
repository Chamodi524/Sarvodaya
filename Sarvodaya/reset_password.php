<?php
session_start();

// Database connection
$host = 'localhost';
$db = 'sarvodaya';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    // Check if email exists in the database
    $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        // Email exists, send a password reset link (for demonstration, we just show a message)
        $_SESSION['message'] = "Password reset instructions have been sent to your email.";
        header("Location: login.php");
        exit();
    } else {
        // Email does not exist
        $_SESSION['error'] = "No account found with this email.";
        header("Location: forgot_password.php");
        exit();
    }

    $stmt->close();
}

$conn->close();
?>