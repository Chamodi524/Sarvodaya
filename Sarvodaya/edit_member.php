<?php
// Start session for potential error messaging
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Sanitize and validate input
    $id = intval($_POST['id']);
    $name = $conn->real_escape_string($_POST['name']);
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = $conn->real_escape_string($_POST['phone']);
    $account_type = intval($_POST['account_type']);
    $address = $conn->real_escape_string($_POST['address']);

    // Validate email
    if (!$email) {
        $_SESSION['error'] = "Invalid email address.";
        header("Location: edit_member.php?id=$id&error=1");
        exit();
    }

    // Validate phone number (exactly 10 digits)
    if (!preg_match('/^\d{10}$/', $phone)) {
        $_SESSION['error'] = "Phone number must be exactly 10 digits.";
        header("Location: edit_member.php?id=$id&error=1");
        exit();
    }

    // File upload handling
    $file_path = '';
    if (!empty($_FILES['file']['name'])) {
        $upload_dir = 'uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        $file_name = uniqid() . '_' . basename($_FILES['file']['name']);
        $file_path = $upload_dir . $file_name;
        
        // File type validation
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        if (!in_array($_FILES['file']['type'], $allowed_types)) {
            $_SESSION['error'] = "Invalid file type. Only JPG, PNG, GIF, and PDF are allowed.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }
        
        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
            // File uploaded successfully
        } else {
            $_SESSION['error'] = "File upload failed.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }
    }

    // Prepare additional fields based on account type
    $additional_fields = '';
    $additional_values = [];

    // Fetch account type details
    $type_query = $conn->prepare("SELECT detail_no FROM savings_account_types WHERE id = ?");
    $type_query->bind_param("i", $account_type);
    $type_query->execute();
    $type_result = $type_query->get_result()->fetch_assoc();

    if ($type_result['detail_no'] == 1) {
        // Children's account fields
        $guardian_name = $conn->real_escape_string($_POST['guardian_name'] ?? '');
        $guardian_nic = $conn->real_escape_string($_POST['guardian_nic'] ?? '');
        $guardian_occupation = $conn->real_escape_string($_POST['guardian_occupation'] ?? '');
        
        // Validate Guardian's NIC (exactly 12 digits)
        if (!preg_match('/^\d{12}$/', $guardian_nic)) {
            $_SESSION['error'] = "Guardian's NIC must be exactly 12 digits.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }

        // Check if Guardian's NIC already exists (excluding current member)
        $nic_check = $conn->prepare("SELECT id FROM members WHERE guardian_nic = ? AND id != ?");
        $nic_check->bind_param("si", $guardian_nic, $id);
        $nic_check->execute();
        if ($nic_check->get_result()->num_rows > 0) {
            $_SESSION['error'] = "Guardian's NIC already exists in the system.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }
        
        $additional_fields = ", guardian_name = ?, guardian_nic = ?, guardian_occupation = ?";
        $additional_values = [
            $guardian_name, 
            $guardian_nic, 
            $guardian_occupation
        ];
    } else {
        // Normal account fields
        $nic = $conn->real_escape_string($_POST['nic'] ?? '');
        $occupation = $conn->real_escape_string($_POST['occupation'] ?? '');
        
        // Validate NIC (exactly 12 digits)
        if (!preg_match('/^\d{12}$/', $nic)) {
            $_SESSION['error'] = "NIC must be exactly 12 digits.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }

        // Check if NIC already exists (excluding current member)
        $nic_check = $conn->prepare("SELECT id FROM members WHERE nic = ? AND id != ?");
        $nic_check->bind_param("si", $nic, $id);
        $nic_check->execute();
        if ($nic_check->get_result()->num_rows > 0) {
            $_SESSION['error'] = "NIC already exists in the system.";
            header("Location: edit_member.php?id=$id&error=1");
            exit();
        }
        
        $additional_fields = ", nic = ?, occupation = ?";
        $additional_values = [
            $nic, 
            $occupation
        ];
    }

    // If file was uploaded, add file path to update
    $file_update = $file_path ? ", file_path = ?" : "";

    // Prepare the update query
    $update_query = $conn->prepare("UPDATE members 
        SET name = ?, 
            email = ?, 
            phone = ?, 
            account_type = ?, 
            address = ? 
            $additional_fields 
            $file_update
        WHERE id = ?");

    // Dynamically build parameter types
    $param_types = "sssis" . 
        str_repeat("s", count($additional_values)) . 
        ($file_path ? "s" : "") . 
        "i";
    
    // Prepare parameters for binding
    $bind_params = array_merge(
        [$update_query, $param_types, $name, $email, $phone, $account_type, $address],
        $additional_values
    );
    
    // Add file path if uploaded
    if ($file_path) {
        $bind_params[] = $file_path;
    }
    
    // Add member ID for WHERE clause
    $bind_params[] = $id;

    // Use call_user_func_array to dynamically bind parameters
    call_user_func_array('mysqli_stmt_bind_param', $bind_params);

    // Execute the update
    if ($update_query->execute()) {
        $_SESSION['success'] = "Member updated successfully!";
        header("Location: member_management.php");
        exit();
    } else {
        $_SESSION['error'] = "Error updating member: " . $conn->error;
        header("Location: edit_member.php?id=$id&error=1");
        exit();
    }
}

// Fetch account types
$account_types_result = $conn->query("SELECT * FROM savings_account_types");

// Check if member ID is provided
if (!isset($_GET['id'])) {
    die("No member ID provided.");
}

$member_id = intval($_GET['id']);

// Fetch member details with account type information
$member_query = $conn->prepare("
    SELECT m.*, s.account_name, s.detail_no 
    FROM members m
    JOIN savings_account_types s ON m.account_type = s.id 
    WHERE m.id = ?
");
$member_query->bind_param("i", $member_id);
$member_query->execute();
$member = $member_query->get_result()->fetch_assoc();

if (!$member) {
    die("Member not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Member - Sarvodaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Poppins', sans-serif;
        }
        .container {
            margin-top: 20px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
            margin-bottom: 20px;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(255, 167, 38, 0.15);
        }
        .btn-custom {
            background: linear-gradient(135deg, #ffa726, #ff7043);
            color: white;
            border-radius: 6px;
            border: none;
            padding: 8px 16px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background: linear-gradient(135deg, #ff9800, #ff5722);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(255, 152, 0, 0.2);
            color: white;
        }
        .navbar-custom {
            background: linear-gradient(135deg, #ffa726, #ff7043);
            padding: 10px 0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: white !important;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: 600;
        }
        .navbar-brand img {
            height: 100px;
            width: 100px;
            margin-right: 10px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid white;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        .navbar-brand img:hover {
            transform: scale(1.05);
        }
        /* Custom Navigation Links */
        .nav-link {
            display: flex;
            align-items: center;
            padding: 6px 15px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            color: white !important;
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        .nav-link:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.15);
        }
        .nav-link i {
            font-size: 1.1rem;
            margin-right: 8px;
        }
        h1 {
            color: #ff7043;
            margin-bottom: 25px;
            font-weight: 600;
            font-size: 2rem;
            text-align: center;
            position: relative;
            padding-bottom: 10px;
        }
        h1:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(90deg, #ffa726, #ff7043);
            border-radius: 2px;
        }
        .form-label {
            font-weight: 500;
            color: #555;
            margin-bottom: 6px;
            font-size: 0.9rem;
        }
        .form-control, .form-select {
            border-radius: 6px;
            padding: 8px 12px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #ffa726;
            box-shadow: 0 0 0 0.2rem rgba(255, 167, 38, 0.2);
        }
        .form-control.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.2);
        }
        .invalid-feedback {
            display: block;
            width: 100%;
            margin-top: 0.25rem;
            font-size: 0.8rem;
            color: #dc3545;
        }
        .alert {
            border-radius: 8px;
            border: none;
            padding: 12px 15px;
            margin-bottom: 20px;
            animation: fadeIn 0.5s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-success {
            background-color: #e8f5e9;
            color: #2e7d32;
            border-left: 4px solid #4caf50;
        }
        .alert-danger {
            background-color: #ffebee;
            color: #c62828;
            border-left: 4px solid #f44336;
        }
        /* Dynamic form section styling */
        .dynamic-section {
            background-color: #fff8e1;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            border-left: 4px solid #ffa726;
        }
        .dynamic-section h4 {
            color: #f57c00;
            font-size: 1.1rem;
            margin-bottom: 15px;
            font-weight: 600;
        }
        .file-input-container {
            position: relative;
            margin-bottom: 15px;
        }
        .file-input-container .form-control {
            padding-right: 100px;
        }
        .file-info {
            margin-top: 5px;
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            color: #666;
        }
        .file-info i {
            margin-right: 5px;
            color: #ffa726;
        }
        /* Button container */
        .button-container {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-secondary {
            background-color: #757575;
            border: none;
            transition: all 0.3s ease;
        }
        .btn-secondary:hover {
            background-color: #616161;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
                <span>Sarvodaya Member Management</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item">
                        <a class="nav-link" href="member_management.php">
                            <i class="bi bi-arrow-left"></i> Back to Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1>Edit Member Information</h1>
        
        <?php 
        // Display success or error messages
        if (isset($_SESSION['success'])) {
            echo "<div class='alert alert-success'>" . 
                 "<i class='bi bi-check-circle me-2'></i>" .
                 htmlspecialchars($_SESSION['success']) . 
                 "</div>";
            unset($_SESSION['success']);
        }
        if (isset($_SESSION['error'])) {
            echo "<div class='alert alert-danger'>" . 
                 "<i class='bi bi-exclamation-triangle me-2'></i>" .
                 htmlspecialchars($_SESSION['error']) . 
                 "</div>";
            unset($_SESSION['error']);
        }
        ?>

        <div class="card p-4">
            <form action="edit_member.php" method="POST" enctype="multipart/form-data" id="memberForm">
                <input type="hidden" name="id" value="<?php echo $member_id; ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="name" class="form-label">
                            <i class="bi bi-person me-1"></i> Full Name
                        </label>
                        <input type="text" class="form-control" id="name" name="name" 
                               value="<?php echo htmlspecialchars($member['name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope me-1"></i> Email Address
                        </label>
                        <input type="email" class="form-control" id="email" name="email" 
                               value="<?php echo htmlspecialchars($member['email']); ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="phone" class="form-label">
                            <i class="bi bi-telephone me-1"></i> Phone Number
                        </label>
                        <input type="text" class="form-control" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($member['phone']); ?>" 
                               pattern="\d{10}" maxlength="10" required>
                        <div class="invalid-feedback" id="phoneError"></div>
                        <small class="form-text text-muted">Enter exactly 10 digits</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="account_type" class="form-label">
                            <i class="bi bi-wallet2 me-1"></i> Account Type
                        </label>
                        <select class="form-select" id="account_type" name="account_type" required>
                            <?php 
                            $account_types_result->data_seek(0);
                            while ($account_type = $account_types_result->fetch_assoc()): ?>
                                <option value="<?php echo $account_type['id']; ?>"
                                    data-detail-no="<?php echo $account_type['detail_no']; ?>"
                                    <?php echo ($member['account_type'] == $account_type['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($account_type['account_name']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">
                        <i class="bi bi-geo-alt me-1"></i> Address
                    </label>
                    <textarea class="form-control" id="address" name="address" rows="3" required><?php 
                        echo htmlspecialchars($member['address']); 
                    ?></textarea>
                </div>

                <!-- Dynamic Fields Container -->
                <div id="dynamicFields">
                    <!-- Children Account Fields -->
                    <div id="childrenFields" class="dynamic-section" style="display: <?php 
                        echo ($member['detail_no'] == 1) ? 'block' : 'none'; ?>;">
                        <h4><i class="bi bi-person-heart"></i> Guardian Information</h4>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="guardian_name" class="form-label">Guardian's Name</label>
                                <input type="text" class="form-control" id="guardian_name" 
                                       name="guardian_name" value="<?php 
                                       echo htmlspecialchars($member['guardian_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="guardian_nic" class="form-label">Guardian's NIC</label>
                                <input type="text" class="form-control" id="guardian_nic" 
                                       name="guardian_nic" value="<?php 
                                       echo htmlspecialchars($member['guardian_nic'] ?? ''); ?>"
                                       pattern="\d{12}" maxlength="12">
                                <div class="invalid-feedback" id="guardianNicError"></div>
                                <small class="form-text text-muted">Enter exactly 12 digits</small>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="guardian_occupation" class="form-label">Guardian's Occupation</label>
                                <input type="text" class="form-control" id="guardian_occupation" 
                                       name="guardian_occupation" value="<?php 
                                       echo htmlspecialchars($member['guardian_occupation'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3 file-input-container">
                            <label for="file" class="form-label">
                                <i class="bi bi-file-earmark-text me-1"></i> Birth Certificate
                            </label>
                            <input type="file" class="form-control" id="file" 
                                   name="file" accept=".pdf,.jpg,.jpeg,.png,.gif">
                            <?php if (!empty($member['file_path'])): ?>
                                <div class="file-info">
                                    <i class="bi bi-paperclip"></i>
                                    Current file: <?php echo htmlspecialchars(basename($member['file_path'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Normal Account Fields -->
                    <div id="normalFields" class="dynamic-section" style="display: <?php 
                        echo ($member['detail_no'] == 2) ? 'block' : 'none'; ?>;">
                        <h4><i class="bi bi-person-vcard"></i> Member Details</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nic" class="form-label">NIC Number</label>
                                <input type="text" class="form-control" id="nic" 
                                       name="nic" value="<?php 
                                       echo htmlspecialchars($member['nic'] ?? ''); ?>"
                                       pattern="\d{12}" maxlength="12">
                                <div class="invalid-feedback" id="nicError"></div>
                                <small class="form-text text-muted">Enter exactly 12 digits</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="occupation" class="form-label">Occupation</label>
                                <input type="text" class="form-control" id="occupation" 
                                       name="occupation" value="<?php 
                                       echo htmlspecialchars($member['occupation'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="mb-3 file-input-container">
                            <label for="file" class="form-label">
                                <i class="bi bi-file-earmark-image me-1"></i> NIC Photo
                            </label>
                            <input type="file" class="form-control" id="file" 
                                   name="file" accept=".pdf,.jpg,.jpeg,.png,.gif">
                            <?php if (!empty($member['file_path'])): ?>
                                <div class="file-info">
                                    <i class="bi bi-paperclip"></i>
                                    Current file: <?php echo htmlspecialchars(basename($member['file_path'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="button-container">
                    <button type="submit" class="btn btn-custom">
                        <i class="bi bi-check-circle me-1"></i> Update Member
                    </button>
                    <a href="member_management.php" class="btn btn-secondary">
                        <i class="bi bi-x-circle me-1"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Phone number validation
        function validatePhone() {
            const phoneInput = document.getElementById('phone');
            const phoneError = document.getElementById('phoneError');
            const phoneValue = phoneInput.value.trim();
            
            if (phoneValue.length === 0) {
                phoneInput.classList.remove('is-invalid');
                phoneError.textContent = '';
                return true;
            }
            
            if (!/^\d{10}$/.test(phoneValue)) {
                phoneInput.classList.add('is-invalid');
                phoneError.textContent = 'Phone number must be exactly 10 digits';
                return false;
            }
            
            phoneInput.classList.remove('is-invalid');
            phoneError.textContent = '';
            return true;
        }

        // NIC validation
        function validateNIC() {
            const nicInput = document.getElementById('nic');
            const nicError = document.getElementById('nicError');
            const nicValue = nicInput.value.trim();
            
            if (nicValue.length === 0) {
                nicInput.classList.remove('is-invalid');
                nicError.textContent = '';
                return true;
            }
            
            if (!/^\d{12}$/.test(nicValue)) {
                nicInput.classList.add('is-invalid');
                nicError.textContent = 'NIC must be exactly 12 digits';
                return false;
            }
            
            nicInput.classList.remove('is-invalid');
            nicError.textContent = '';
            return true;
        }

        // Guardian NIC validation
        function validateGuardianNIC() {
            const guardianNicInput = document.getElementById('guardian_nic');
            const guardianNicError = document.getElementById('guardianNicError');
            const guardianNicValue = guardianNicInput.value.trim();
            
            if (guardianNicValue.length === 0) {
                guardianNicInput.classList.remove('is-invalid');
                guardianNicError.textContent = '';
                return true;
            }
            
            if (!/^\d{12}$/.test(guardianNicValue)) {
                guardianNicInput.classList.add('is-invalid');
                guardianNicError.textContent = 'Guardian\'s NIC must be exactly 12 digits';
                return false;
            }
            
            guardianNicInput.classList.remove('is-invalid');
            guardianNicError.textContent = '';
            return true;
        }

        // Add event listeners for real-time validation
        document.getElementById('phone').addEventListener('input', function(e) {
            // Only allow digits
            this.value = this.value.replace(/\D/g, '');
            validatePhone();
        });

        document.getElementById('nic').addEventListener('input', function(e) {
            // Only allow digits
            this.value = this.value.replace(/\D/g, '');
            validateNIC();
        });

        document.getElementById('guardian_nic').addEventListener('input', function(e) {
            // Only allow digits
            this.value = this.value.replace(/\D/g, '');
            validateGuardianNIC();
        });

        // Account type change handler
        document.getElementById('account_type').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const detailNo = selectedOption.getAttribute('data-detail-no');
            
            const childrenFields = document.getElementById('childrenFields');
            const normalFields = document.getElementById('normalFields');

            if (detailNo == 1) {
                childrenFields.style.display = 'block';
                normalFields.style.display = 'none';
                
                // Make children fields required
                ['guardian_name', 'guardian_nic', 'guardian_occupation'].forEach(id => {
                    document.getElementById(id).setAttribute('required', 'required');
                });
                
                // Remove required from normal fields
                ['nic', 'occupation'].forEach(id => {
                    document.getElementById(id).removeAttribute('required');
                });
            } else {
                childrenFields.style.display = 'none';
                normalFields.style.display = 'block';
                
                // Make normal fields required
                ['nic', 'occupation'].forEach(id => {
                    document.getElementById(id).setAttribute('required', 'required');
                });
                
                // Remove required from children fields
                ['guardian_name', 'guardian_nic', 'guardian_occupation'].forEach(id => {
                    document.getElementById(id).removeAttribute('required');
                });
            }
        });

        // Form submission validation
        document.getElementById('memberForm').addEventListener('submit', function(e) {
            let isValid = true;
            
            // Validate phone
            if (!validatePhone()) {
                isValid = false;
            }
            
            // Validate based on account type
            const accountType = document.getElementById('account_type');
            const selectedOption = accountType.options[accountType.selectedIndex];
            const detailNo = selectedOption.getAttribute('data-detail-no');
            
            if (detailNo == 1) {
                // Validate guardian NIC for children's account
                if (!validateGuardianNIC()) {
                    isValid = false;
                }
            } else {
                // Validate NIC for normal account
                if (!validateNIC()) {
                    isValid = false;
                }
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please correct the validation errors before submitting.');
                return false;
            }
            
            // Show loading state
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Updating...';
            submitBtn.disabled = true;
        });

        // File size validation
        const fileInput = document.querySelector('input[type="file"]');
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB
        
        if (fileInput) {
            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileSize = this.files[0].size;
                    if (fileSize > MAX_FILE_SIZE) {
                        alert('File size exceeds 2MB limit. Please select a smaller file.');
                        this.value = ''; // Clear the file input
                    }
                }
            });
        }

        // Initialize validation on page load
        document.addEventListener('DOMContentLoaded', function() {
            validatePhone();
            validateNIC();
            validateGuardianNIC();
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>