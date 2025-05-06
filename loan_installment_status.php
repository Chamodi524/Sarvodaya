<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sarvodaya";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if member_id parameter is provided
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$selected_loan_type = isset($_GET['loan_type_id']) ? intval($_GET['loan_type_id']) : 0;

// Function to get member's loan types
function getMemberLoanTypes($conn, $member_id) {
    $member_loan_types = [];
    
    if ($member_id > 0) {
        $sql = "SELECT DISTINCT lt.id, lt.loan_name 
                FROM loans l
                JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE l.member_id = ? AND l.status = 'active'
                ORDER BY lt.loan_name";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $member_loan_types[$row['id']] = $row['loan_name'];
            }
        }
        $stmt->close();
    }
    
    return $member_loan_types;
}

// Get loan types for this member
$member_loan_types = getMemberLoanTypes($conn, $member_id);

// Process status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $installment_id = $_POST['installment_id'];
    $new_status = $_POST['new_status'];
    $actual_payment_date = null;
    $actual_payment_amount = null;
    
    // If status is changed to paid, record actual payment date and amount
    if ($new_status == 'paid') {
        $actual_payment_date = isset($_POST['actual_payment_date']) ? $_POST['actual_payment_date'] : date('Y-m-d');
        $actual_payment_amount = isset($_POST['actual_payment_amount']) ? $_POST['actual_payment_amount'] : 0;
        
        // Update the status and payment details
        $sql = "UPDATE loan_installments 
                SET payment_status = ?, 
                    actual_payment_date = ?, 
                    actual_payment_amount = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssdi", $new_status, $actual_payment_date, $actual_payment_amount, $installment_id);
    } else {
        // Just update the status without payment details
        $sql = "UPDATE loan_installments 
                SET payment_status = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $installment_id);
    }
    
    if ($stmt->execute()) {
        $success_message = "Installment status updated successfully!";
    } else {
        $error_message = "Error updating status: " . $conn->error;
    }
    
    $stmt->close();
}

// Get installments based on member_id and optional loan_type_id
$result = null;
if ($member_id > 0) {
    $sql = "SELECT li.*, l.loan_type_id, l.member_id, lt.loan_name
            FROM loan_installments li
            JOIN loans l ON li.loan_id = l.id
            JOIN loan_types lt ON l.loan_type_id = lt.id
            WHERE l.member_id = ?";
    
    // Add loan_type filter if selected
    if ($selected_loan_type > 0) {
        $sql .= " AND l.loan_type_id = ?";
        $sql .= " ORDER BY li.loan_id, li.payment_date";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $member_id, $selected_loan_type);
    } else {
        $sql .= " ORDER BY li.loan_id, li.payment_date";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $member_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
}

// Get member information if member_id is provided
$member_info = null;
if ($member_id > 0) {
    $member_sql = "SELECT name, email, phone FROM members WHERE id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("i", $member_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    
    if ($member_result->num_rows > 0) {
        $member_info = $member_result->fetch_assoc();
    }
    $member_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Loan Management</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: rgb(255, 140, 0);
            --primary-light: rgba(255, 140, 0, 0.1);
            --primary-medium: rgba(255, 140, 0, 0.3);
            --primary-dark: rgb(230, 126, 0);
            --text-color: #333;
            --background-color: #f9f9f9;
            --white: #ffffff;
            --border-radius: 8px;
            --box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: var(--text-color);
            background: #f0f0f0 url('data:image/svg+xml;utf8,<svg width="100" height="100" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><rect width="100%" height="100%" fill="%23f0f0f0"/><path d="M0 0L100 100M100 0L0 100" stroke="rgba(255,140,0,0.03)" stroke-width="2"/></svg>') repeat;
            padding: 0;
            margin: 0;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background-color: var(--white);
            padding: 25px;
            box-shadow: var(--box-shadow);
            border-radius: var(--border-radius);
            border-top: 5px solid var(--primary-color);
        }
        
        h1, h2 {
            color: var(--primary-dark);
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        h1 {
            text-align: center;
            padding-bottom: 15px;
            position: relative;
            font-size: 2.2em;
        }
        
        h1:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: linear-gradient(to right, transparent, var(--primary-color), transparent);
        }
        
        .member-info {
            background-color: var(--primary-light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            border-left: 5px solid var(--primary-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .member-info h2 {
            color: var(--primary-dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .member-info h2:before {
            content: "\f007";
            font-family: "Font Awesome 5 Free";
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .member-info p {
            margin: 10px 0;
            display: flex;
            align-items: center;
        }
        
        .member-info p i {
            width: 20px;
            margin-right: 10px;
            color: var(--primary-color);
        }
        
        .filter-section {
            margin-bottom: 25px;
            padding: 20px;
            background-color: var(--white);
            border-radius: var(--border-radius);
            border: 1px solid #eee;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .filter-section form {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .filter-section input[type="number"] {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            width: 120px;
            transition: var(--transition);
        }
        
        .filter-section input[type="number"]:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 140, 0, 0.2);
            outline: none;
        }
        
        .filter-section label {
            margin-right: 10px;
            font-weight: 600;
        }
        
        .filter-section button {
            padding: 12px 20px;
            background-color: var(--primary-color);
            color: var(--white);
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            transition: var(--transition);
            font-weight: 600;
            display: flex;
            align-items: center;
        }
        
        .filter-section button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .filter-section button:before {
            content: "\f002";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 8px;
        }
        
        .filter-form-group {
            margin-right: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            border-radius: var(--border-radius);
            overflow: hidden;
        }
        
        table th, table td {
            padding: 15px;
            border: 1px solid #eee;
            text-align: left;
        }
        
        table th {
            background-color: var(--primary-color);
            color: var(--white);
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.85em;
        }
        
        tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        tr:hover {
            background-color: var(--primary-light);
        }
        
        .status-pending {
            background-color: rgba(255, 193, 7, 0.15);
        }
        
        .status-paid {
            background-color: rgba(76, 175, 80, 0.15);
        }
        
        .status-overdue {
            background-color: rgba(244, 67, 54, 0.15);
        }
        
        .action-btn {
            padding: 8px 14px;
            margin: 3px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 0.85em;
            transition: var(--transition);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-paid {
            background-color: #4caf50;
            color: white;
        }
        
        .btn-paid:before {
            content: "\f00c";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 5px;
        }
        
        .btn-pending {
            background-color: #ff9800;
            color: white;
        }
        
        .btn-pending:before {
            content: "\f017";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 5px;
        }
        
        .btn-overdue {
            background-color: #f44336;
            color: white;
        }
        
        .btn-overdue:before {
            content: "\f071";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 5px;
        }
        
        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: var(--border-radius);
            border: 1px solid transparent;
            display: flex;
            align-items: center;
        }
        
        .alert:before {
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 15px;
            font-size: 1.2em;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }
        
        .alert-success:before {
            content: "\f058";
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
        
        .alert-error:before {
            content: "\f057";
            color: #721c24;
        }
        
        .payment-modal {
            display: none;
            position: fixed;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            overflow: auto;
            animation: fadeIn 0.3s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 30px;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 500px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            animation: slideIn 0.3s;
            border-top: 4px solid var(--primary-color);
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        .modal-content h3 {
            margin-top: 0;
            color: var(--primary-dark);
            font-size: 1.5em;
            text-align: center;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }
        
        .modal-content h3:after {
            content: "";
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--primary-color);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text-color);
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: var(--border-radius);
            box-sizing: border-box;
            transition: var(--transition);
        }
        
        .form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(255, 140, 0, 0.2);
            outline: none;
        }
        
        .form-buttons {
            text-align: right;
            margin-top: 30px;
            display: flex;
            justify-content: flex-end;
        }
        
        .form-buttons button {
            padding: 10px 20px;
            margin-left: 10px;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-cancel:hover {
            background-color: #5a6268;
        }
        
        .btn-submit {
            background-color: var(--primary-color);
            color: white;
        }
        
        .btn-submit:hover {
            background-color: var(--primary-dark);
        }
        
        .loan-type {
            font-weight: 600;
            color: var(--primary-dark);
        }
        
        .loan-id {
            font-weight: 600;
            color: var(--primary-color);
        }
        
        .no-results {
            padding: 30px;
            text-align: center;
            background-color: var(--background-color);
            border-radius: var(--border-radius);
            margin-top: 20px;
            border: 1px dashed #ddd;
        }
        
        .no-results p {
            color: #666;
            font-size: 1.1em;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        
        .no-results p:before {
            content: "\f57e";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            font-size: 3em;
            color: #ccc;
            margin-bottom: 15px;
        }
        
        .instructions {
            margin-bottom: 25px;
            padding: 20px;
            background-color: var(--primary-light);
            border-radius: var(--border-radius);
            border-left: 5px solid var(--primary-color);
            display: flex;
            align-items: center;
        }
        
        .instructions:before {
            content: "\f05a";
            font-family: "Font Awesome 5 Free";
            font-weight: 900;
            margin-right: 15px;
            font-size: 1.5em;
            color: var(--primary-color);
        }
        
        .loan-type-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 30px;
            background-color: var(--white);
            border: 2px solid var(--primary-color);
            margin: 5px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            color: var(--primary-color);
        }
        
        .loan-type-badge:hover {
            background-color: var(--primary-light);
            transform: translateY(-2px);
        }
        
        .loan-type-badge.active {
            background-color: var(--primary-color);
            color: var(--white);
        }
        
        .loan-types-container {
            margin: 20px 0;
            padding: 15px;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            background-color: var(--background-color);
            border-radius: var(--border-radius);
        }
        
        /* Responsive design */
        @media screen and (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px;
                width: auto;
            }
            
            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .filter-section form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .filter-form-group {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .action-btn {
                padding: 6px 10px;
                font-size: 0.75em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Member Loan Management</h1>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="instructions">
            <p>Enter a Member ID to view their loan types and installments.</p>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="" id="memberForm">
                <div class="filter-form-group">
                    <label for="member_id">Member ID:</label>
                    <input type="number" name="member_id" id="member_id" min="1" value="<?php echo $member_id; ?>" required>
                </div>
                
                <button type="submit">Find Member</button>
            </form>
        </div>
        
        <?php if ($member_id > 0): ?>
            <?php if ($member_info): ?>
                <div class="member-info">
                    <h2><i class="fas fa-user-circle"></i> Member Details</h2>
                    <p><i class="fas fa-user"></i> <strong>Name:</strong> <?php echo htmlspecialchars($member_info['name']); ?></p>
                    <p><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($member_info['email']); ?></p>
                    <p><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($member_info['phone']); ?></p>
                </div>
                
                <?php if (!empty($member_loan_types)): ?>
                    <h2><i class="fas fa-file-invoice-dollar"></i> Loan Types</h2>
                    <div class="loan-types-container">
                        <a href="?member_id=<?php echo $member_id; ?>" class="loan-type-badge <?php echo $selected_loan_type == 0 ? 'active' : ''; ?>">
                            All Types
                        </a>
                        <?php foreach ($member_loan_types as $type_id => $type_name): ?>
                            <a href="?member_id=<?php echo $member_id; ?>&loan_type_id=<?php echo $type_id; ?>" 
                               class="loan-type-badge <?php echo $selected_loan_type == $type_id ? 'active' : ''; ?>">
                                <?php echo htmlspecialchars($type_name); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if ($result && $result->num_rows > 0): ?>
                        <h2><i class="fas fa-calendar-alt"></i> Loan Installments</h2>
                        <table>
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>Loan Type</th>
                                    <th>Installment #</th>
                                    <th>Payment Date</th>
                                    <th>Payment Amount</th>
                                    <th>Principal</th>
                                    <th>Interest</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th>Actual Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                while($row = $result->fetch_assoc()): 
                                    $status_class = 'status-' . $row['payment_status'];
                                ?>
                                <tr class="<?php echo $status_class; ?>">
                                    <td class="loan-id"><?php echo $row['loan_id']; ?></td>
                                    <td class="loan-type"><?php echo htmlspecialchars($row['loan_name']); ?></td>
                                    <td><?php echo $row['installment_number']; ?></td>
                                    <td><?php echo $row['payment_date']; ?></td>
                                    <td>$<?php echo number_format($row['payment_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($row['principal_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($row['interest_amount'], 2); ?></td>
                                    <td>$<?php echo number_format($row['remaining_balance'], 2); ?></td>
                                    <td>
                                        <?php if ($row['payment_status'] == 'paid'): ?>
                                            <span style="color: #4caf50;"><i class="fas fa-check-circle"></i> Paid</span>
                                        <?php elseif ($row['payment_status'] == 'pending'): ?>
                                            <span style="color: #ff9800;"><i class="fas fa-clock"></i> Pending</span>
                                        <?php else: ?>
                                            <span style="color: #f44336;"><i class="fas fa-exclamation-circle"></i> Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['actual_payment_date']): ?>
                                            <i class="fas fa-calendar-check"></i> <?php echo $row['actual_payment_date']; ?><br>
                                            <i class="fas fa-money-bill-wave"></i> $<?php echo number_format($row['actual_payment_amount'], 2); ?>
                                        <?php else: ?>
                                            <i class="fas fa-times-circle"></i> Not paid yet
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['payment_status'] != 'paid'): ?>
                                            <button class="action-btn btn-paid" onclick="showPaymentModal(<?php echo $row['id']; ?>, <?php echo $row['payment_amount']; ?>)">Mark Paid</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['payment_status'] != 'pending'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="installment_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="pending">
                                                <button type="submit" name="update_status" class="action-btn btn-pending">Mark Pending</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['payment_status'] != 'overdue'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="installment_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="overdue">
                                                <button type="submit" name="update_status" class="action-btn btn-overdue">Mark Overdue</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-results">
                            <p>No installments found for the selected loan type.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="no-results">
                        <p>This member has no active loans.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="no-results">
                    <p>No member found with ID: <?php echo $member_id; ?></p>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="no-results">
                <p>Please enter a Member ID to view their loan information.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Payment Modal -->
    <div id="paymentModal" class="payment-modal">
        <div class="modal-content">
            <h3><i class="fas fa-money-check-alt"></i> Record Payment</h3>
            <form method="post" id="paymentForm">
                <input type="hidden" name="installment_id" id="modal_installment_id">
                <input type="hidden" name="new_status" value="paid"><input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="loan_type_id" value="<?php echo $selected_loan_type; ?>">
                
                <div class="form-group">
                    <label for="actual_payment_date"><i class="far fa-calendar-alt"></i> Payment Date:</label>
                    <input type="date" name="actual_payment_date" id="actual_payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="actual_payment_amount"><i class="fas fa-dollar-sign"></i> Payment Amount ($):</label>
                    <input type="number" name="actual_payment_amount" id="actual_payment_amount" step="0.01" required>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn-cancel" onclick="closePaymentModal()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" name="update_status" class="btn-submit"><i class="fas fa-save"></i> Save Payment</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functionality
        var paymentModal = document.getElementById("paymentModal");
        
        function showPaymentModal(installmentId, suggestedAmount) {
            document.getElementById("modal_installment_id").value = installmentId;
            document.getElementById("actual_payment_amount").value = suggestedAmount;
            paymentModal.style.display = "block";
            
            // Add animation class to modal content
            document.querySelector(".modal-content").classList.add("animate");
            
            // Set focus on the date field
            setTimeout(function() {
                document.getElementById("actual_payment_date").focus();
            }, 300);
        }
        
        function closePaymentModal() {
            // Fade out animation
            paymentModal.style.opacity = "0";
            setTimeout(function() {
                paymentModal.style.display = "none";
                paymentModal.style.opacity = "1";
            }, 300);
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            if (event.target == paymentModal) {
                closePaymentModal();
            }
        }
        
        // Highlight the row that was just updated
        document.addEventListener("DOMContentLoaded", function() {
            // Check if there's a success message
            var successAlert = document.querySelector(".alert-success");
            if (successAlert) {
                // Add a subtle highlight animation to the table
                var tableRows = document.querySelectorAll("table tbody tr");
                tableRows.forEach(function(row) {
                    row.style.transition = "background-color 1s";
                });
                
                // Scroll to the success message
                successAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            // Add hover effect to buttons
            var buttons = document.querySelectorAll("button");
            buttons.forEach(function(button) {
                button.addEventListener("mouseenter", function() {
                    this.style.transform = "translateY(-2px)";
                    this.style.boxShadow = "0 4px 8px rgba(0, 0, 0, 0.1)";
                });
                
                button.addEventListener("mouseleave", function() {
                    this.style.transform = "";
                    this.style.boxShadow = "";
                });
            });
        });
        
        // Add responsive table functionality
        window.addEventListener("resize", function() {
            adjustTableResponsiveness();
        });
        
        function adjustTableResponsiveness() {
            var table = document.querySelector("table");
            if (table) {
                if (window.innerWidth < 768) {
                    table.classList.add("responsive");
                } else {
                    table.classList.remove("responsive");
                }
            }
        }
        
        // Call once on page load
        adjustTableResponsiveness();
    </script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>