<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get ID from the request
$id = $_GET['id'];

// Prepare delete statement
$stmt = $conn->prepare("DELETE FROM savings_account_types WHERE id = ?");
$stmt->bind_param("i", $id);

// Execute the delete
if ($stmt->execute()) {
    echo "<script>alert('Savings account type deleted successfully!'); window.location.href = 'savings_account_management.php';</script>";
} else {
    echo "<script>alert('Error deleting savings account type: " . $stmt->error . "'); window.location.href = 'savings_account_management.php';</script>";
}

// Close statement and connection
$stmt->close();
$conn->close();
?>