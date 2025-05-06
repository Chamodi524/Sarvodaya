<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$account_name = $_POST['account_name'];
$minimum_balance = $_POST['minimum_balance'];
$interest_rate = $_POST['interest_rate'];
$detail_no = $_POST['detail_no']; // New field added

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO savings_account_types (account_name, minimum_balance, interest_rate, detail_no) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssdi", $account_name, $minimum_balance, $interest_rate, $detail_no);

// Execute the statement
if ($stmt->execute()) {
    echo "<script>alert('Savings account type added successfully!'); window.location.href = 'savings_account_management.php';</script>";
} else {
    echo "<script>alert('Error: " . $stmt->error . "'); window.location.href = 'savings_account_management.php';</script>";
}

// Close statement and connection
$stmt->close();
$conn->close();
?>