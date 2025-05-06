<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sarvodaya';

// Create database connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    // Log the error and provide a user-friendly message
    error_log("Database Connection Failed: " . $conn->connect_error);
    die("Sorry, we're experiencing technical difficulties. Please try again later.");
}

// Validate and sanitize input
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    error_log("Invalid or missing member ID");
    die("Invalid member ID provided.");
}

$id = intval($_GET['id']); // Ensure integer type

try {
    // Start a transaction for safe deletion
    $conn->begin_transaction();

    // Fetch member details to delete associated files
    $stmt = $conn->prepare("SELECT file_path FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    $stmt->close();

    // Delete associated file if exists
    if ($member && !empty($member['file_path'])) {
        // Determine file type and path
        $fileTypes = [
            'birth_certificates' => 'Uploads/birth_certificates/',
            'nic_photos' => 'Uploads/nic_photos/'
        ];
        
        $filePath = null;
        foreach ($fileTypes as $type => $path) {
            $fullPath = $path . basename($member['file_path']);
            if (file_exists($fullPath)) {
                $filePath = $fullPath;
                break;
            }
        }

        if ($filePath) {
            if (!unlink($filePath)) {
                error_log("Failed to delete file: $filePath");
                // Continue with deletion even if file deletion fails
            }
        }
    }

    // Prepare delete statement
    $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    // Execute deletion
    if (!$stmt->execute()) {
        throw new Exception("Failed to delete member: " . $stmt->error);
    }

    // Commit transaction
    $conn->commit();

    // Log successful deletion
    error_log("Member ID $id successfully deleted");

    // Redirect back to member management page with success message
    header("Location: member_management.php?delete_success=1");
    exit();

} catch (Exception $e) {
    // Rollback transaction in case of error
    $conn->rollback();
    
    // Log detailed error
    error_log("Deletion Error: " . $e->getMessage());
    
    // Redirect with error message
    header("Location: member_management.php?delete_error=1");
    exit();
} finally {
    // Close database connection
    $stmt->close();
    $conn->close();
}
?>