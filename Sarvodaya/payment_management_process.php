<?php
// Start session to manage messages and data between pages
session_start();

// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $member_id = mysqli_real_escape_string($conn, $_POST['member_id']);
    $transaction_type = mysqli_real_escape_string($conn, $_POST['transaction_type']);
    $amount = floatval($_POST['amount']);
    $description = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
    $reference = mysqli_real_escape_string($conn, $_POST['reference'] ?? '');
    $created_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

    // Validate inputs
    if (empty($member_id) || empty($transaction_type) || empty($amount)) {
        $_SESSION['error_message'] = "All required fields must be filled!";
        header("Location: payment_management.php");
        exit;
    }
    
    // Verify member exists and get account type
    $member_query = "SELECT id, name, account_type FROM members WHERE id = '$member_id'";
    $member_result = $conn->query($member_query);
    
    if (!$member_result || $member_result->num_rows == 0) {
        $_SESSION['error_message'] = "Member with ID $member_id does not exist!";
        header("Location: payment_management.php");
        exit;
    }
    
    $member_data = $member_result->fetch_assoc();
    $account_type_id = $member_data['account_type'];
    $member_name = $member_data['name'];
    
    // Make sure amount is positive
    if ($amount <= 0) {
        $_SESSION['error_message'] = "Amount must be greater than zero!";
        header("Location: payment_management.php");
        exit;
    }

    // Start transaction
    $conn->begin_transaction();
    try {
        // Calculate current balance
        $balance_sql = "SELECT COALESCE(SUM(CASE 
            WHEN transaction_type IN ('DEPOSIT', 'INTEREST') THEN amount 
            WHEN transaction_type IN ('WITHDRAWAL', 'FEE', 'ADJUSTMENT') THEN -amount 
            ELSE 0 END), 0) AS balance 
            FROM savings_transactions 
            WHERE member_id = '$member_id' AND account_type_id = '$account_type_id'";
        
        $balance_result = $conn->query($balance_sql);
        if (!$balance_result) {
            throw new Exception("Error calculating current balance: " . $conn->error);
        }
        
        $row = $balance_result->fetch_assoc();
        $current_balance = floatval($row['balance']);
        
        // Calculate new balance
        $new_balance = $current_balance;
        
        if (in_array($transaction_type, ['DEPOSIT', 'INTEREST'])) {
            $new_balance += $amount;
        } elseif (in_array($transaction_type, ['WITHDRAWAL', 'FEE', 'ADJUSTMENT'])) {
            // Check if there are sufficient funds for withdrawal
            if ($transaction_type == 'WITHDRAWAL' && $current_balance < $amount) {
                throw new Exception("Insufficient funds. Current balance: Rs." . number_format($current_balance, 2));
            }
            $new_balance -= $amount;
        }
        
        // 1. Record transaction in the savings_transactions table
        $transaction_sql = "INSERT INTO savings_transactions 
            (member_id, account_type_id, transaction_type, amount, running_balance, 
            reference, description, transaction_date, created_by) 
            VALUES ('$member_id', '$account_type_id', '$transaction_type', '$amount', '$new_balance', 
            '$reference', '$description', NOW(), " . ($created_by ? "'$created_by'" : "NULL") . ")";
        
        if (!$conn->query($transaction_sql)) {
            throw new Exception("Error recording savings transaction: " . $conn->error);
        }
        
        // Get the new transaction ID
        $transaction_id = $conn->insert_id;
        
        // 2. Also record the payment in the payments table
        // Map transaction_type to payment_type if needed
        $payment_type = $transaction_type; // Default to using the same type
        
        $payment_sql = "INSERT INTO payments
            (member_id, payment_type, amount, description, payment_date)
            VALUES ('$member_id', '$payment_type', '$amount', '$description', NOW())";
            
        if (!$conn->query($payment_sql)) {
            throw new Exception("Error recording payment: " . $conn->error);
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message
        $_SESSION['success_message'] = "Transaction processed successfully! Transaction ID: $transaction_id";
        $_SESSION['transaction_details'] = [
            'id' => $transaction_id,
            'member_id' => $member_id,
            'member_name' => $member_name,
            'transaction_type' => $transaction_type,
            'amount' => $amount,
            'new_balance' => $new_balance,
            'date' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        $conn->rollback();
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    // Redirect back to payment management page
    header("Location: payment_management.php");
    exit;
}

// If we get here without a POST, redirect to the form
header("Location: payment_management.php");
exit;
?>