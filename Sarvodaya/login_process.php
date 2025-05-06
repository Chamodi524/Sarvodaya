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
    $email = trim(string: $_POST['email']); // Ensure email is trimmed
    $password = $_POST['password'];

    // Debugging: Print entered email and password
    echo "Entered Email: " . $email . "<br>";
    echo "Entered Password: " . $password . "<br>";

    // Fetch user from the database
    $stmt = $conn->prepare("SELECT id, username, password, position FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $db_username, $db_password, $position);

    if ($stmt->fetch()) {
        // Debugging: Print database password
        echo "Database Password: " . $db_password . "<br>";

        // Verify password
        if (password_verify($password, $db_password)) {
            // Set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;
            $_SESSION['position'] = $position;

            // Redirect based on position
            switch ($position) {
                case 'loan_handling_clerk':
                    header('Location: loan_home.php');
                    break;
                case 'transaction_handling_clerk':
                    header('Location: transaction_handling_home.php');
                    break;
                case 'membership_handling_clerk':
                    header('Location: member_management.php');
                    break;
                case 'manager':
                        header('Location: manager_home.php');
                        break;
                default:
                    echo "Invalid position.";
                    break;
            }
            exit();
        } else {
            echo "Password verification failed.";
        }
    } else {
        echo "No user found with this email.";
    }

    $stmt->close();
}

$conn->close();
?>