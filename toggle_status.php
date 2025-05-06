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
    
    // Update the loan type status
    $stmt = $conn->prepare("UPDATE loan_types SET status = ? WHERE id = ?");
    $stmt->bind_param('si', $status, $id);
    
    if ($stmt->execute()) {
        // Redirect back to the loan types page
        header("Location: loanTypes.php");
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