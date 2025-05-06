<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Delete loan type
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Use prepared statements to prevent SQL injection
    $stmt = $conn->prepare("DELETE FROM loan_types WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redirect back to the main page after successful deletion
        header("Location: loanTypes.php");
        exit(); // Ensure no further code is executed after redirection
    } else {
        echo "Error deleting record: " . $stmt->error;
    }

    $stmt->close();
} else {
    echo "Invalid request. No ID provided.";
}

$conn->close();
?>