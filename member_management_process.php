<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $accountTypeId = $_POST['account_type']; // Account type ID from the dropdown
    
    // Get account type details to determine which fields to process
    $accountTypeQuery = $conn->prepare("SELECT account_name, detail_no FROM savings_account_types WHERE id = ?");
    $accountTypeQuery->bind_param("i", $accountTypeId);
    $accountTypeQuery->execute();
    $accountTypeResult = $accountTypeQuery->get_result()->fetch_assoc();
    $detailNo = $accountTypeResult['detail_no'];
    
    // Initialize variables for optional fields
    $guardianName = null;
    $guardianNic = null;
    $guardianOccupation = null;
    $nic = null;
    $occupation = null;
    $filePath = null;
    
    // Handle file uploads and additional fields based on detail_no
    if ($detailNo == 1) {
        // Detail No 1 (Guardian/Children) fields
        $guardianName = isset($_POST['guardian_name']) ? $_POST['guardian_name'] : null;
        $guardianNic = isset($_POST['guardian_nic']) ? $_POST['guardian_nic'] : null;
        $guardianOccupation = isset($_POST['guardian_occupation']) ? $_POST['guardian_occupation'] : null;
        
        // Handle birth certificate upload
        if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['birth_certificate'];
            $fileName = 'birth_certificate_' . time() . '_' . basename($file['name']);
            $uploadPath = 'Uploads/birth_certificates/' . $fileName;
            
            // Create directory if it doesn't exist
            if (!file_exists('Uploads/birth_certificates')) {
                mkdir('Uploads/birth_certificates', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $filePath = $uploadPath; // Store relative path for database
            }
        }
    } elseif ($detailNo == 2) {
        // Detail No 2 (Normal) fields
        $nic = isset($_POST['nic']) ? $_POST['nic'] : null;
        $occupation = isset($_POST['occupation']) ? $_POST['occupation'] : null;
        
        // Handle NIC photo upload
        if (isset($_FILES['nic_photo']) && $_FILES['nic_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['nic_photo'];
            $fileName = 'nic_photo_' . time() . '_' . basename($file['name']);
            $uploadPath = 'Uploads/nic_photos/' . $fileName;
            
            // Create directory if it doesn't exist
            if (!file_exists('Uploads/nic_photos')) {
                mkdir('Uploads/nic_photos', 0777, true);
            }
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $filePath = $uploadPath; // Store relative path for database
            }
        }
    }
    
    // Prepare and execute insert statement
    $stmt = $conn->prepare("
        INSERT INTO members (
            name, email, phone, address, account_type, 
            guardian_name, guardian_nic, guardian_occupation, 
            nic, occupation, 
            file_path
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->bind_param(
        "ssssissssss", 
        $name, $email, $phone, $address, $accountTypeId, 
        $guardianName, $guardianNic, $guardianOccupation, 
        $nic, $occupation, 
        $filePath
    );
    
    try {
        if ($stmt->execute()) {
            // Redirect back to the member list with a success message
            header("Location: member_management.php?success=1");
            exit();
        } else {
            // Redirect back with error message
            header("Location: member_management.php?error=1&msg=" . urlencode($stmt->error));
            exit();
        }
    } catch (Exception $e) {
        // Redirect back with error message
        header("Location: member_management.php?error=1&msg=" . urlencode($e->getMessage()));
        exit();
    } finally {
        $stmt->close();
    }
}

// Close the connection
$conn->close();
?>