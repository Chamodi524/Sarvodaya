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

// Function to check for date alerts
function checkDateAlerts($conn) {
    $today = date('Y-m-d');
    $alerts = [];
    
    // Check if today's date matches any date_value in selected_dates table
    $stmt = $conn->prepare("SELECT id, date_number, date_value FROM selected_dates WHERE date_value = ?");
    $stmt->bind_param("s", $today);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $alerts[] = [
            'id' => $row['id'],
            'date_number' => $row['date_number'],
            'date_value' => $row['date_value'],
            'message' => "Important Date Alert: Today (" . date('F j, Y') . ") . Calculate interest today." 
        ];
    }
    
    $stmt->close();
    return $alerts;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Fetch user from the database
    $stmt = $conn->prepare("SELECT id, username, password, position FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    $stmt->bind_result($id, $db_username, $db_password, $position);
    
    if ($stmt->fetch()) {
        // Verify password
        if (password_verify($password, $db_password)) {
            // Set session variables
            $_SESSION['user_id'] = $id;
            $_SESSION['username'] = $db_username;
            $_SESSION['position'] = $position;
            
            // Check for date alerts
            $alerts = checkDateAlerts($conn);
            
            // Store alerts in session if any exist
            if (!empty($alerts)) {
                $_SESSION['date_alerts'] = $alerts;
                $_SESSION['show_alerts'] = true;
            }
            
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
                    $_SESSION['error_message'] = "Invalid user position.";
                    header('Location: login.php');
                    break;
            }
            exit();
        } else {
            // Password is wrong
            $_SESSION['error_message'] = "Password is wrong. Please try again.";
            header('Location: login.php');
            exit();
        }
    } else {
        // No user found with this email
        $_SESSION['error_message'] = "No account found with this email address.";
        header('Location: login.php');
        exit();
    }
    
    $stmt->close();
}

$conn->close();
?>