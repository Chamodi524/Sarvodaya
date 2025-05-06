<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$id = $_POST['id'];
$account_name = $_POST['account_name'];
$minimum_balance = $_POST['minimum_balance'];
$interest_rate = $_POST['interest_rate'];

// Update in database
$sql = "UPDATE savings_account_types 
        SET account_name = '$account_name', minimum_balance = '$minimum_balance', interest_rate = '$interest_rate' 
        WHERE id = '$id'";

if ($conn->query($sql) === TRUE) {
    echo "<script>alert('Savings account type updated successfully!'); window.location.href = 'savings_account_management.php';</script>";
} else {
    echo "<script>alert('Error: " . $sql . "<br>" . $conn->error . "');</script>";
}

$conn->close();
?>