<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all members with account type details
$members_result = $conn->query("
    SELECT m.*, s.detail_no, s.account_name 
    FROM members m
    JOIN savings_account_types s ON m.account_type = s.id
");

// Fetch all savings account types with detail_no
$account_types_result = $conn->query("SELECT * FROM savings_account_types");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Management System</title>
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
        }
        .table-custom {
            border-radius: 8px;
            overflow: hidden;
        }
        .table-custom th {
            background: linear-gradient(135deg, #ffa726, #ff9800);
            color: white;
            font-weight: 500;
            border: none;
            padding: 12px;
            font-size: 0.9rem;
        }
        .table-custom td {
            padding: 10px 12px;
            vertical-align: middle;
            font-size: 0.9rem;
        }
        .table-custom tbody tr {
            transition: all 0.2s ease;
        }
        .table-custom tbody tr:hover {
            background-color: #fff3e0;
            transform: scale(1.01);
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
            height: 100px; /* Adjusted from 50px to 70px */
            width: auto; /* Maintain aspect ratio */
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
        /* Custom Logout Button Styling */
        .btn-logout {
            display: flex;
            align-items: center;
            padding: 6px 15px;
            background: linear-gradient(135deg, #ff9f43, #ff7f50);
            border: none;
            border-radius: 20px;
            color: white !important;
            text-decoration: none;
            font-weight: 500;
            box-shadow: 0 3px 8px rgba(255, 107, 107, 0.2);
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 0.9rem;
        }
        .btn-logout:hover {
            background: linear-gradient(135deg, #ff7f50, #ff6b6b);
            transform: scale(1.05);
            box-shadow: 0 4px 10px rgba(255, 107, 107, 0.3);
        }
        .btn-logout:active {
            transform: scale(0.95);
        }
        .btn-logout i {
            font-size: 1.1rem;
            margin-right: 8px;
            transition: transform 0.3s ease;
        }
        .btn-logout:hover i {
            transform: translateX(3px);
        }
        .logout-text {
            font-weight: 500;
        }
        h1, h2 {
            color: #ff7043;
            margin-bottom: 15px;
            font-weight: 600;
        }
        h1 {
            font-size: 2rem;
            text-align: center;
            margin-bottom: 20px;
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
        .card-header {
            background: linear-gradient(135deg, #ffa726, #ff9800);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px;
        }
        .card-header h2 {
            color: white;
            margin-bottom: 0;
            font-size: 1.3rem;
        }
        .btn-sm {
            padding: 4px 10px;
            font-size: 0.8rem;
            border-radius: 4px;
        }
        .btn-warning {
            background-color: #ffa726;
            border-color: #ffa726;
            color: white;
        }
        .btn-danger {
            background-color: #ff5252;
            border-color: #ff5252;
        }
        .action-buttons .btn {
            margin-bottom: 4px;
            width: 100%;
        }
        .searchbar-container {
            position: relative;
            margin-bottom: 15px;
        }
        .searchbar-container i {
            position: absolute;
            top: 50%;
            left: 12px;
            transform: translateY(-50%);
            color: #aaa;
        }
        #searchInput {
            padding-left: 35px;
            border-radius: 20px;
            border: 1px solid #ddd;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            font-size: 0.9rem;
        }
        #searchInput:focus {
            border-color: #ffa726;
            box-shadow: 0 0 0 0.2rem rgba(255, 167, 38, 0.2);
        }
        .system-title {
            background: linear-gradient(135deg, #ffa726, #ff7043);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            font-weight: 700;
            font-size: 2.2rem;
            text-shadow: 1px 1px 2px rgba(255,255,255,0.2);
            letter-spacing: 1px;
        }
        /* File input styling */
        input[type="file"] {
            padding: 6px;
            font-size: 0.85rem;
        }
        /* Table responsive tweaks */
        @media (max-width: 992px) {
            .table-custom {
                font-size: 0.8rem;
            }
            .action-buttons .btn {
                padding: 3px 6px;
                font-size: 0.75rem;
            }
        }
        /* Limit file size text */
        .file-size-note {
            font-size: 0.8rem;
            color: #666;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
                <span>Sarvodaya Member Management</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link btn-logout" href="logout.php">
                            <i class="bi bi-box-arrow-right"></i>
                            <span class="logout-text">Logout</span>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <h1 class="system-title text-center mb-4">Member Management System</h1>
        
        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Add New Member</h2>
            </div>
            <div class="card-body p-3">
                <form action="member_management_process.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="tel" class="form-control" id="phone" name="phone" maxlength="10" pattern="[0-9]{10}" title="Please enter exactly 10 digits" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="account_type" class="form-label">Account Type</label>
                            <select class="form-select" id="account_type" name="account_type" required>
                                <option value="">Select Account Type</option>
                                <?php
                                $account_types_result->data_seek(0);
                                while ($account_type = $account_types_result->fetch_assoc()) {
                                    echo "<option value='{$account_type['id']}' data-detail-no='{$account_type['detail_no']}'>{$account_type['account_name']}</option>";
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="address" class="form-label">Address</label>
                        <textarea class="form-control" id="address" name="address" rows="2" required></textarea>
                    </div>
                    
                    <!-- Dynamic Fields Container -->
                    <div id="dynamicFields" style="display: none;">
                        <!-- Fields for Detail No 1 (Guardian) -->
                        <div id="detailNo1Fields" style="display: none;" class="border rounded p-3 mb-3 bg-light">
                            <h5 class="mb-2" style="font-size: 1.1rem;">Guardian Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="guardian_name" class="form-label">Guardian's Name</label>
                                    <input type="text" class="form-control" id="guardian_name" name="guardian_name">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="guardian_nic" class="form-label">Guardian's NIC</label>
                                    <input type="tel" class="form-control" id="guardian_nic" name="guardian_nic" maxlength="12" pattern="[0-9]{12}" title="Please enter exactly 12 digits">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="guardian_occupation" class="form-label">Guardian's Occupation</label>
                                <input type="text" class="form-control" id="guardian_occupation" name="guardian_occupation">
                            </div>
                            <div class="mb-2">
                                <label for="birth_certificate" class="form-label">Upload Birth Certificate</label>
                                <input type="file" class="form-control" id="birth_certificate" name="birth_certificate">
                                <div class="file-size-note">Maximum file size: 2MB. Accepted formats: JPG, PNG, PDF</div>
                            </div>
                        </div>
                        
                        <!-- Fields for Detail No 2 (Normal) -->
                        <div id="detailNo2Fields" style="display: none;" class="border rounded p-3 mb-3 bg-light">
                            <h5 class="mb-2" style="font-size: 1.1rem;">Member Details</h5>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label for="nic" class="form-label">NIC Number</label>
                                    <input type="tel" class="form-control" id="nic" name="nic" maxlength="12" pattern="[0-9]{12}" title="Please enter exactly 12 digits">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label for="occupation" class="form-label">Occupation</label>
                                    <input type="text" class="form-control" id="occupation" name="occupation">
                                </div>
                            </div>
                            <div class="mb-2">
                                <label for="nic_photo" class="form-label">Upload NIC Photo</label>
                                <input type="file" class="form-control" id="nic_photo" name="nic_photo">
                                <div class="file-size-note">Maximum file size: 2MB. Accepted formats: JPG, PNG, PDF</div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-custom">Add Member</button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h2 class="mb-0">Members List</h2>
            </div>
            <div class="card-body p-3">
                <!-- Search Bar with NIC/Guardian NIC Search -->
                <div class="searchbar-container mb-3">
                    <i class="bi bi-search"></i>
                    <input type="text" id="searchInput" class="form-control" placeholder="Search by NIC or Guardian NIC...">
                </div>
                <div class="table-responsive">
                    <table class="table table-custom" id="membersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Address</th>
                                <th>Account</th>
                                <th>NIC</th>
                                <th>Guardian NIC</th>
                                <th>Docs</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($row = $members_result->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $row['id']; ?></td>
                                    <td><?php echo $row['name']; ?></td>
                                    <td><?php echo $row['email']; ?></td>
                                    <td><?php echo $row['phone']; ?></td>
                                    <td><?php echo $row['address']; ?></td>
                                    <td><?php echo $row['account_name']; ?></td>
                                    <td><?php echo $row['nic']; ?></td>
                                    <td><?php echo $row['guardian_nic']; ?></td>
                                    <td>
                                        <?php if (!empty($row['file_path'])): ?>
                                            <a href="view_member.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-info">View</a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="edit_member.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning mb-1">
                                            <i class="bi bi-pencil-square"></i> Edit
                                        </a>
                                        <a href="delete_member.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this member?')">
                                            <i class="bi bi-trash"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Search functionality for NIC and Guardian NIC
        document.getElementById('searchInput').addEventListener('input', function () {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#membersTable tbody tr');

            rows.forEach(row => {
                const nic = row.querySelector('td:nth-child(7)').textContent.toLowerCase();
                const guardianNic = row.querySelector('td:nth-child(8)').textContent.toLowerCase();

                // Check if the search term matches NIC or Guardian NIC
                if (nic.includes(searchValue) || guardianNic.includes(searchValue)) {
                    row.style.display = ''; // Show the row
                } else {
                    row.style.display = 'none'; // Hide the row
                }
            });
        });

        // Dynamic field display based on account type
        document.getElementById('account_type').addEventListener('change', function () {
            const selectedOption = this.options[this.selectedIndex];
            const detailNo = selectedOption.getAttribute('data-detail-no');
            const dynamicFields = document.getElementById('dynamicFields');
            const detailNo1Fields = document.getElementById('detailNo1Fields');
            const detailNo2Fields = document.getElementById('detailNo2Fields');

            // Show dynamic fields container
            dynamicFields.style.display = 'block';

            // Hide both sets of fields first
            detailNo1Fields.style.display = 'none';
            detailNo2Fields.style.display = 'none';

            // Show appropriate fields based on detail_no
            if (detailNo === '1') {
                detailNo1Fields.style.display = 'block';
                // Make guardian fields required
                ['guardian_name', 'guardian_nic', 'guardian_occupation', 'birth_certificate'].forEach(id => {
                    document.getElementById(id).setAttribute('required', 'required');
                });
                // Remove required from detail_no 2 fields
                ['nic', 'occupation', 'nic_photo'].forEach(id => {
                    document.getElementById(id).removeAttribute('required');
                });
            } else if (detailNo === '2') {
                detailNo2Fields.style.display = 'block';
                // Make detail_no 2 fields required
                ['nic', 'occupation', 'nic_photo'].forEach(id => {
                    document.getElementById(id).setAttribute('required', 'required');
                });
                // Remove required from guardian fields
                ['guardian_name', 'guardian_nic', 'guardian_occupation', 'birth_certificate'].forEach(id => {
                    document.getElementById(id).removeAttribute('required');
                });
            }
        });

        // Trigger initial display on page load
        document.addEventListener('DOMContentLoaded', function() {
            const accountTypeSelect = document.getElementById('account_type');
            if (accountTypeSelect.value) {
                accountTypeSelect.dispatchEvent(new Event('change'));
            }
        });
    

        // File size validation
        const fileInputs = document.querySelectorAll('input[type="file"]');
        const MAX_FILE_SIZE = 2 * 1024 * 1024; // 2MB

        fileInputs.forEach(input => {
            input.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileSize = this.files[0].size;
                    if (fileSize > MAX_FILE_SIZE) {
                        alert('File size exceeds 2MB limit. Please select a smaller file.');
                        this.value = ''; // Clear the file input
                    }
                }
            });
        });
        
        // Add this JavaScript code to your existing script section in member_management.php

// Phone number validation function
function validatePhoneNumber(phoneInput) {
    const phoneValue = phoneInput.value.replace(/\D/g, ''); // Remove non-digits
    
    if (phoneValue.length > 10) {
        alert('Phone number cannot exceed 10 digits!');
        phoneInput.value = phoneValue.substring(0, 10); // Trim to 10 digits
        return false;
    } else if (phoneValue.length < 10 && phoneValue.length > 0) {
        phoneInput.setCustomValidity('Phone number must be exactly 10 digits');
        return false;
    } else if (phoneValue.length === 10) {
        phoneInput.setCustomValidity(''); // Clear any previous validation message
        phoneInput.value = phoneValue; // Set cleaned value
        return true;
    } else {
        phoneInput.setCustomValidity(''); // Clear validation message for empty field
        return true;
    }
}

// Add event listeners for phone number validation
document.addEventListener('DOMContentLoaded', function() {
    const phoneInput = document.getElementById('phone');
    
    // Validate on input (as user types)
    phoneInput.addEventListener('input', function() {
        validatePhoneNumber(this);
    });
    
    // Validate on blur (when user leaves the field)
    phoneInput.addEventListener('blur', function() {
        const phoneValue = this.value.replace(/\D/g, '');
        if (phoneValue.length > 0 && phoneValue.length !== 10) {
            this.setCustomValidity('Phone number must be exactly 10 digits');
        } else {
            this.setCustomValidity('');
        }
    });
    
    // Prevent non-numeric characters
    phoneInput.addEventListener('keypress', function(e) {
        // Allow backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)) {
            return;
        }
        
        // Ensure that it is a number and stop the keypress
        if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
            e.preventDefault();
        }
        
        // Check if adding this digit would exceed 10 digits
        const currentLength = this.value.replace(/\D/g, '').length;
        if (currentLength >= 10) {
            e.preventDefault();
            alert('Phone number cannot exceed 10 digits!');
        }
    });
});

// Form submission validation
document.querySelector('form').addEventListener('submit', function(e) {
    const phoneInput = document.getElementById('phone');
    const phoneValue = phoneInput.value.replace(/\D/g, '');
    
    if (phoneValue.length > 0 && phoneValue.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits!');
        phoneInput.focus();
        return false;
    }
}); 
// Add this JavaScript code to your existing script section in member_management.php

// NIC validation function
function validateNIC(nicInput) {
    const nicValue = nicInput.value.replace(/\D/g, ''); // Remove non-digits
    
    if (nicValue.length > 12) {
        alert('NIC number must be exactly 12 digits!');
        nicInput.value = nicValue.substring(0, 12); // Trim to 12 digits
        return false;
    } else if (nicValue.length < 12 && nicValue.length > 0) {
        nicInput.setCustomValidity('NIC number must be exactly 12 digits');
        return false;
    } else if (nicValue.length === 12) {
        nicInput.setCustomValidity(''); // Clear any previous validation message
        nicInput.value = nicValue; // Set cleaned value
        return true;
    } else {
        nicInput.setCustomValidity(''); // Clear validation message for empty field
        return true;
    }
}

// Check for duplicate NIC numbers
function checkDuplicateNIC(nicValue, currentNicInput) {
    // Get all NIC input fields
    const nicInputs = document.querySelectorAll('#nic, #guardian_nic');
    
    for (let input of nicInputs) {
        if (input !== currentNicInput && input.value.replace(/\D/g, '') === nicValue && nicValue.length === 12) {
            alert('This NIC number is already entered! Each NIC must be unique.');
            currentNicInput.value = '';
            currentNicInput.focus();
            return false;
        }
    }
    return true;
}

// Add event listeners for NIC validation
document.addEventListener('DOMContentLoaded', function() {
    const nicInput = document.getElementById('nic');
    const guardianNicInput = document.getElementById('guardian_nic');
    
    // Function to add NIC validation to an input field
    function addNICValidation(inputField) {
        if (!inputField) return; // Skip if field doesn't exist
        
        // Validate on input (as user types)
        inputField.addEventListener('input', function() {
            validateNIC(this);
        });
        
        // Validate on blur (when user leaves the field)
        inputField.addEventListener('blur', function() {
            const nicValue = this.value.replace(/\D/g, '');
            if (nicValue.length > 0 && nicValue.length !== 12) {
                this.setCustomValidity('NIC number must be exactly 12 digits');
            } else if (nicValue.length === 12) {
                // Check for duplicates
                checkDuplicateNIC(nicValue, this);
                this.setCustomValidity('');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Prevent non-numeric characters
        inputField.addEventListener('keypress', function(e) {
            // Allow backspace, delete, tab, escape, enter
            if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                (e.keyCode === 65 && e.ctrlKey === true) ||
                (e.keyCode === 67 && e.ctrlKey === true) ||
                (e.keyCode === 86 && e.ctrlKey === true) ||
                (e.keyCode === 88 && e.ctrlKey === true)) {
                return;
            }
            
            // Ensure that it is a number and stop the keypress
            if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                e.preventDefault();
            }
            
            // Check if adding this digit would exceed 12 digits
            const currentLength = this.value.replace(/\D/g, '').length;
            if (currentLength >= 12) {
                e.preventDefault();
                alert('NIC number must be exactly 12 digits!');
            }
        });
    }
    
    // Apply validation to both NIC fields
    addNICValidation(nicInput);
    addNICValidation(guardianNicInput);
});

// Update form submission validation to include NIC validation
document.querySelector('form').addEventListener('submit', function(e) {
    const phoneInput = document.getElementById('phone');
    const nicInput = document.getElementById('nic');
    const guardianNicInput = document.getElementById('guardian_nic');
    
    // Phone validation (existing)
    const phoneValue = phoneInput.value.replace(/\D/g, '');
    if (phoneValue.length > 0 && phoneValue.length !== 10) {
        e.preventDefault();
        alert('Phone number must be exactly 10 digits!');
        phoneInput.focus();
        return false;
    }
    
    // NIC validation
    if (nicInput && nicInput.style.display !== 'none') {
        const nicValue = nicInput.value.replace(/\D/g, '');
        if (nicValue.length > 0 && nicValue.length !== 12) {
            e.preventDefault();
            alert('NIC number must be exactly 12 digits!');
            nicInput.focus();
            return false;
        }
    }
    
    // Guardian NIC validation
    if (guardianNicInput && guardianNicInput.style.display !== 'none') {
        const guardianNicValue = guardianNicInput.value.replace(/\D/g, '');
        if (guardianNicValue.length > 0 && guardianNicValue.length !== 12) {
            e.preventDefault();
            alert('Guardian NIC number must be exactly 12 digits!');
            guardianNicInput.focus();
            return false;
        }
    }
    
    // Check for duplicate NICs before submission
    if (nicInput && guardianNicInput) {
        const nicValue = nicInput.value.replace(/\D/g, '');
        const guardianNicValue = guardianNicInput.value.replace(/\D/g, '');
        
        if (nicValue.length === 12 && guardianNicValue.length === 12 && nicValue === guardianNicValue) {
            e.preventDefault();
            alert('NIC and Guardian NIC cannot be the same!');
            guardianNicInput.focus();
            return false;
        }
    }
});
    </script>
</body>
</html>