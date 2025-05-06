<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if ID and status are set
if (isset($_GET['id']) && isset($_GET['status'])) {
    $id = $_GET['id'];
    $status = $_GET['status'];
    
    // Validate the status value
    if ($status !== 'active' && $status !== 'closed') {
        die("Invalid status value");
    }
    
    // Update the savings account type status
    $stmt = $conn->prepare("UPDATE savings_account_types SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    
    if ($stmt->execute()) {
        // Redirect back to the savings account types page
        header("Location: savings_account_management.php");
        exit;
    } else {
        echo "Error updating status: " . $conn->error;
    }
    
    $stmt->close();
} else {
    echo "Missing required parameters";
}

$conn->close();
?>