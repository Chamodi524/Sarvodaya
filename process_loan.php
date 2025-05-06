<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process loan application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'];
    $loanTypeId = $_POST['loan_type'];
    $amount = $_POST['amount'];

    // Insert loan application into the database
    $stmt = $conn->prepare("INSERT INTO loans (member_id, loan_type_id, amount) VALUES (?, ?, ?)");
    $stmt->bind_param("iid", $memberId, $loanTypeId, $amount);
    if ($stmt->execute()) {
        echo "Loan application submitted successfully!";
    } else {
        echo "Error submitting loan application.";
    }
    $stmt->close();
}

$conn->close();
?>