<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get form data
$loan_name = $_POST['loan_name'];
$maximum_amount = $_POST['maximum_amount'];
$interest_rate = $_POST['interest_rate'];
$max_period = $_POST['max_period'];
$description = $_POST['description'];

// Insert into database - no status field in form, it will use the default 'active' value in the database
$stmt = $conn->prepare("INSERT INTO loan_types (loan_name, maximum_amount, interest_rate, max_period, description) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sddis", $loan_name, $maximum_amount, $interest_rate, $max_period, $description);

if ($stmt->execute()) {
    header("Location: loanTypes.php"); // Redirect back to the main page
} else {
    echo "Error: " . $stmt->error;
}

$stmt->close();
$conn->close();
?>