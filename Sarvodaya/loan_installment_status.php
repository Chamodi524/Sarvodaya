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

// Check if we're showing a receipt (this comes before other processing)
if (isset($_GET['receipt_id'])) {
    showReceipt($conn);
    exit;
}

// Check if member_id parameter is provided
$member_id = isset($_GET['member_id']) ? intval($_GET['member_id']) : 0;
$selected_loan_type = isset($_GET['loan_type_id']) ? intval($_GET['loan_type_id']) : 0;

// Function to get member's loan types
function getMemberLoanTypes($conn, $member_id) {
    $member_loan_types = array();
    
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
    $late_fee_amount = null;
    $receipt_id = null;
    
    // Start transaction for data consistency
    $conn->begin_transaction();
    
    try {
        // Get installment and loan details
        $installment_sql = "SELECT li.loan_id, l.member_id, li.principal_amount, li.interest_amount, 
                           li.payment_status as current_status, l.loan_type_id, lt.late_fee, lt.loan_name,
                           l.total_repayment_amount as loan_total_amount
                           FROM loan_installments li 
                           JOIN loans l ON li.loan_id = l.id 
                           JOIN loan_types lt ON l.loan_type_id = lt.id
                           WHERE li.id = ?";
        $installment_stmt = $conn->prepare($installment_sql);
        $installment_stmt->bind_param("i", $installment_id);
        $installment_stmt->execute();
        $installment_result = $installment_stmt->get_result();
        
        if ($installment_result->num_rows > 0) {
            $installment_data = $installment_result->fetch_assoc();
            $loan_id = $installment_data['loan_id'];
            $member_id_from_loan = $installment_data['member_id'];
            $principal_amount = $installment_data['principal_amount'];
            $interest_amount = $installment_data['interest_amount'];
            $current_status = $installment_data['current_status'];
            $loan_type_late_fee = $installment_data['late_fee'];
            $loan_name = $installment_data['loan_name'];
            $loan_total_amount = $installment_data['loan_total_amount'];
            
            if ($new_status == 'paid') {
                // Handle payment
                $actual_payment_date = isset($_POST['actual_payment_date']) ? $_POST['actual_payment_date'] : date('Y-m-d');
                $actual_payment_amount = isset($_POST['actual_payment_amount']) ? $_POST['actual_payment_amount'] : 0;
                
                // If changing from pending/overdue to paid, first delete any existing receipts
                if ($current_status != 'paid') {
                    $delete_receipts_sql = "DELETE FROM receipts WHERE loan_id = ? AND member_id = ? AND receipt_date >= (SELECT payment_date FROM loan_installments WHERE id = ?)";
                    $delete_stmt = $conn->prepare($delete_receipts_sql);
                    $delete_stmt->bind_param("iii", $loan_id, $member_id_from_loan, $installment_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    // Update the loan's total repayment amount (reduce by principal + interest)
                    $payment_amount = $principal_amount + $interest_amount;
                    $update_loan_sql = "UPDATE loans SET total_repayment_amount = total_repayment_amount - ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_loan_sql);
                    $update_stmt->bind_param("di", $payment_amount, $loan_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Update the installment status and payment details
                $sql = "UPDATE loan_installments 
                        SET payment_status = ?, 
                            actual_payment_date = ?, 
                            actual_payment_amount = ?,
                            late_fee = 0
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssdi", $new_status, $actual_payment_date, $actual_payment_amount, $installment_id);
                $stmt->execute();
                
                // Record combined payment receipt (principal + interest)
                $total_payment_amount = $principal_amount + $interest_amount;
                if ($total_payment_amount > 0) {
                    $receipt_sql = "INSERT INTO receipts (member_id, loan_id, receipt_type, amount, receipt_date) 
                                   VALUES (?, ?, 'loan_repayment', ?, ?)";
                    $receipt_stmt = $conn->prepare($receipt_sql);
                    $receipt_stmt->bind_param("iids", $member_id_from_loan, $loan_id, $total_payment_amount, $actual_payment_date);
                    $receipt_stmt->execute();
                    $receipt_id = $receipt_stmt->insert_id;
                    $receipt_stmt->close();
                }
                
                $stmt->close();
                $success_message = "Payment recorded successfully for installment!";
                
                // Redirect to receipt page if payment was made
                if ($receipt_id) {
                    $conn->commit();
                    header("Location: ?member_id=$member_id&loan_type_id=$selected_loan_type&receipt_id=$receipt_id");
                    exit;
                }
            } elseif ($new_status == 'overdue') {
                // Handle overdue with late fee
                $late_fee_amount = isset($_POST['late_fee_amount']) ? $_POST['late_fee_amount'] : $loan_type_late_fee;
                
                // If changing from paid to overdue, first delete any existing receipts for this installment
                if ($current_status == 'paid') {
                    $delete_receipts_sql = "DELETE FROM receipts WHERE loan_id = ? AND member_id = ? AND receipt_date >= (SELECT payment_date FROM loan_installments WHERE id = ?)";
                    $delete_stmt = $conn->prepare($delete_receipts_sql);
                    $delete_stmt->bind_param("iii", $loan_id, $member_id_from_loan, $installment_id);
                    $delete_stmt->execute();
                    $delete_stmt->close();
                    
                    // Add back the principal + interest to the loan's total repayment amount
                    $payment_amount = $principal_amount + $interest_amount;
                    $update_loan_sql = "UPDATE loans SET total_repayment_amount = total_repayment_amount + ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_loan_sql);
                    $update_stmt->bind_param("di", $payment_amount, $loan_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Calculate total payment amount (principal + interest + late fee)
                $total_payment_amount = $principal_amount + $interest_amount + $late_fee_amount;
                $today_date = date('Y-m-d');
                
                // Update the installment status with late fee and actual payment details
                $sql = "UPDATE loan_installments 
                        SET payment_status = ?, 
                            late_fee = ?,
                            actual_payment_date = ?,
                            actual_payment_amount = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("sdsdi", $new_status, $late_fee_amount, $today_date, $total_payment_amount, $installment_id);
                $stmt->execute();
                $stmt->close();
                
                // Update the loan's total repayment amount (reduce by principal + interest)
                $payment_amount = $principal_amount + $interest_amount;
                $update_loan_sql = "UPDATE loans SET total_repayment_amount = total_repayment_amount - ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_loan_sql);
                $update_stmt->bind_param("di", $payment_amount, $loan_id);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Use today's date for all receipts when marking as overdue
                $receipt_date = $today_date;
                
                // Record combined payment receipt (principal + interest + late fee)
                if ($total_payment_amount > 0) {
                    $receipt_sql = "INSERT INTO receipts (member_id, loan_id, receipt_type, amount, receipt_date) 
                                VALUES (?, ?, 'loan_repayment', ?, ?)";
                    $receipt_stmt = $conn->prepare($receipt_sql);
                    $receipt_stmt->bind_param("iids", $member_id_from_loan, $loan_id, $total_payment_amount, $receipt_date);
                    $receipt_stmt->execute();
                    $receipt_id = $receipt_stmt->insert_id;
                    $receipt_stmt->close();
                }
                
                $success_message = "Installment marked as overdue with total payment of Rs." . number_format($total_payment_amount, 2) . " (including late fee of Rs." . number_format($late_fee_amount, 2) . "). Receipt has been recorded with today's date (" . date('M d, Y') . ").";
                
                // Redirect to receipt page if overdue was marked
                if ($receipt_id) {
                    $conn->commit();
                    header("Location: ?member_id=$member_id&loan_type_id=$selected_loan_type&receipt_id=$receipt_id");
                    exit;
                }
            } elseif ($new_status == 'pending') {
                // Handle changing back to pending
                
                // Delete any existing receipts for this installment
                $delete_receipts_sql = "DELETE FROM receipts WHERE loan_id = ? AND member_id = ? AND receipt_date >= (SELECT payment_date FROM loan_installments WHERE id = ?)";
                $delete_stmt = $conn->prepare($delete_receipts_sql);
                $delete_stmt->bind_param("iii", $loan_id, $member_id_from_loan, $installment_id);
                $delete_stmt->execute();
                $delete_stmt->close();
                
                // If changing from paid to pending, add back the principal + interest to the loan's total repayment amount
                if ($current_status == 'paid') {
                    $payment_amount = $principal_amount + $interest_amount;
                    $update_loan_sql = "UPDATE loans SET total_repayment_amount = total_repayment_amount + ? WHERE id = ?";
                    $update_stmt = $conn->prepare($update_loan_sql);
                    $update_stmt->bind_param("di", $payment_amount, $loan_id);
                    $update_stmt->execute();
                    $update_stmt->close();
                }
                
                // Reset the installment to pending status
                $sql = "UPDATE loan_installments 
                        SET payment_status = ?, 
                            actual_payment_date = NULL, 
                            actual_payment_amount = NULL,
                            late_fee = 0
                        WHERE id = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $new_status, $installment_id);
                $stmt->execute();
                $stmt->close();
                
                $success_message = "Installment status changed back to pending. All related receipts have been removed.";
            }
            
            $installment_stmt->close();
        } else {
            throw new Exception("Installment not found");
        }
        
        // Commit transaction
        $conn->commit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error updating status: " . $e->getMessage();
    }
}

// Get installments based on member_id and optional loan_type_id
$result = null;
if ($member_id > 0) {
    $sql = "SELECT li.*, l.loan_type_id, l.member_id, lt.loan_name, lt.late_fee as loan_type_late_fee
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

// Function to show receipt
function showReceipt($conn) {
    // Check if receipt_id is provided
    if (!isset($_GET['receipt_id']) || empty($_GET['receipt_id'])) {
        header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'index.php');
        exit;
    }

    $receipt_id = (int)$_GET['receipt_id'];

    // Query to get detailed receipt information
    $query = "
        SELECT 
            receipts.id AS receipt_id,
            members.id AS member_id,
            members.name AS member_name,
            members.address,
            members.phone,
            receipts.receipt_type,
            receipts.amount,
            receipts.receipt_date,
            loans.id AS loan_id,
            loan_types.loan_name
        FROM receipts
        JOIN members ON receipts.member_id = members.id
        LEFT JOIN loans ON receipts.loan_id = loans.id
        LEFT JOIN loan_types ON loans.loan_type_id = loan_types.id
        WHERE receipts.id = ?
    ";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $receipt_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo "Receipt not found";
        exit;
    }

    $receipt = $result->fetch_assoc();

    // Receipt number - use receipt ID with a prefix
    $receipt_number = 'RCT-' . str_pad($receipt['receipt_id'], 6, '0', STR_PAD_LEFT);

    // Format receipt type for display
    $receipt_type_formatted = "Loan Repayment"; // Simplified receipt type

    // Function to convert number to words for Indian Rupees
    function numberToWords($number) {
        $ones = array(
            0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five", 
            6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten", 
            11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen", 
            16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
        );
        $tens = array(
            0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty", 
            6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
        );
        
        // Format the number with 2 decimal places
        $number = (float)$number;
        $number_parts = explode('.', number_format($number, 2, '.', ''));
        
        $wholenum = $number_parts[0];
        $decnum = $number_parts[1];
        
        // Handle the whole number portion
        $result = "";
        
        // Process crores (if any)
        $crores = (int)($wholenum / 10000000);
        if ($crores > 0) {
            $result .= numberToWordsIndian($crores) . " Crore ";
            $wholenum %= 10000000;
        }
        
        // Process lakhs (if any)
        $lakhs = (int)($wholenum / 100000);
        if ($lakhs > 0) {
            $result .= numberToWordsIndian($lakhs) . " Lakh ";
            $wholenum %= 100000;
        }
        
        // Process thousands (if any)
        $thousands = (int)($wholenum / 1000);
        if ($thousands > 0) {
            $result .= numberToWordsIndian($thousands) . " Thousand ";
            $wholenum %= 1000;
        }
        
        // Process hundreds (if any)
        $hundreds = (int)($wholenum / 100);
        if ($hundreds > 0) {
            $result .= numberToWordsIndian($hundreds) . " Hundred ";
            $wholenum %= 100;
        }
        
        // Process tens and ones
        if ($wholenum > 0) {
            if ($result != "") {
                $result .= "and ";
            }
            $result .= numberToWordsIndian($wholenum);
        }
        
        // Add "Rupees" text
        if ($result == "") {
            $result = "Zero";
        }
        $result .= " Rupees";
        
        // Process decimal part (paise)
        if ((int)$decnum > 0) {
            $result .= " and " . numberToWordsIndian((int)$decnum) . " Paise";
        }
        
        return $result . " Only";
    }

    // Helper function to convert small numbers to words
    function numberToWordsIndian($num) {
        $ones = array(
            0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five", 
            6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten", 
            11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen", 
            16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
        );
        $tens = array(
            0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty", 
            6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
        );
        
        $num = (int)$num;
        
        if ($num < 20) {
            return $ones[$num];
        } elseif ($num < 100) {
            return $tens[(int)($num/10)] . ($num % 10 ? " " . $ones[$num % 10] : "");
        }
        
        return ""; // Should not reach here with proper usage
    }

    // Close the database connection
    $stmt->close();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Receipt - Sarvodaya Bank</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                font-family: 'Arial', sans-serif;
                line-height: 1.6;
                color: #333;
                background-color: #f8f9fa;
                padding: 20px;
            }
            .receipt-container {
                max-width: 800px;
                margin: 0 auto;
                background: #fff;
                padding: 30px;
                border-radius: 8px;
                box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            }
            .receipt-header {
                border-bottom: 2px solid #ff8c00;
                padding-bottom: 15px;
                margin-bottom: 20px;
            }
            .receipt-title {
                color: #ff8c00;
                font-weight: bold;
                margin-bottom: 5px;
            }
            .receipt-logo {
                text-align: center;
                margin-bottom: 20px;
            }
            .receipt-logo img {
                max-height: 80px;
            }
            .receipt-bank-name {
                font-size: 24px;
                font-weight: bold;
                color: #ff8c00;
                text-align: center;
                margin-bottom: 5px;
            }
            .receipt-bank-address {
                text-align: center;
                margin-bottom: 20px;
                font-size: 14px;
            }
            .receipt-number {
                font-weight: bold;
                font-size: 16px;
                margin-bottom: 5px;
            }
            .receipt-date {
                margin-bottom: 15px;
                font-size: 14px;
            }
            .receipt-body {
                margin-bottom: 20px;
            }
            .receipt-row {
                display: flex;
                margin-bottom: 10px;
            }
            .receipt-label {
                font-weight: bold;
                width: 180px;
            }
            .receipt-value {
                flex: 1;
            }
            .receipt-amount {
                font-size: 22px;
                font-weight: bold;
                margin: 20px 0;
                text-align: center;
                color: #ff8c00;
            }
            .receipt-amount-words {
                font-style: italic;
                margin-bottom: 20px;
                text-align: center;
                padding: 10px;
                background-color: #f8f9fa;
                border-radius: 5px;
            }
            .receipt-footer {
                border-top: 1px dashed #ddd;
                padding-top: 20px;
                margin-top: 20px;
                text-align: center;
                font-size: 12px;
                color: #666;
            }
            .receipt-signature {
                margin-top: 50px;
                display: flex;
                justify-content: space-between;
            }
            .sign-box {
                text-align: center;
                width: 200px;
            }
            .sign-line {
                border-top: 1px solid #333;
                margin-bottom: 5px;
            }
            .btn-action {
                background-color: #ff8c00;
                color: white;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 20px;
            }
            .btn-action:hover {
                background-color: #ff9800;
                color: white;
            }
            .actions {
                text-align: center;
                margin-top: 20px;
            }
            @media print {
                body {
                    padding: 0;
                    background-color: white;
                }
                .receipt-container {
                    box-shadow: none;
                    padding: 15px;
                    border: 1px solid #ddd;
                }
                .actions {
                    display: none;
                }
            }
        </style>
    </head>
    <body>
        <div class="receipt-container">
            <div class="receipt-header">
                <div class="receipt-logo">
                    <!-- Bank Logo would go here -->
                    <div class="receipt-bank-name">SARVODAYA SHRAMADHANA SOCIETY</div>
                </div>
                <div class="receipt-bank-address">
                Samaghi Sarvodaya Shramadhana Society,Kubaloluwa,Veyangoda.<br>
                    Phone: 077 690 6605  | Email: info@sarvodayabank.com
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="receipt-number">Receipt No: <?php echo htmlspecialchars($receipt_number); ?></div>
                        <div class="receipt-date">Date: <?php echo htmlspecialchars(date('d-m-Y', strtotime($receipt['receipt_date']))); ?></div>
                    </div>
                    <div class="col-md-6 text-end">
                        <div class="receipt-title">RECEIPT</div>
                    </div>
                </div>
            </div>
            
            <div class="receipt-body">
                <div class="receipt-row">
                    <div class="receipt-label">Received From:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($receipt['member_name']); ?></div>
                </div>
                
                <div class="receipt-row">
                    <div class="receipt-label">Member ID:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($receipt['member_id']); ?></div>
                </div>
                
                <?php if (!empty($receipt['address'])): ?>
                <div class="receipt-row">
                    <div class="receipt-label">Address:</div>
                    <div class="receipt-value"><?php echo nl2br(htmlspecialchars($receipt['address'])); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="receipt-row">
                    <div class="receipt-label">Payment For:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($receipt_type_formatted); ?></div>
                </div>
                
                <?php if (!empty($receipt['loan_name'])): ?>
                <div class="receipt-row">
                    <div class="receipt-label">Loan Name:</div>
                    <div class="receipt-value"><?php echo htmlspecialchars($receipt['loan_name']); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="receipt-amount">
                    Rs.<?php echo htmlspecialchars(number_format($receipt['amount'], 2)); ?>
                </div>
                
                <div class="receipt-amount-words">
                    <?php echo numberToWords($receipt['amount']); ?>
                </div>
                
                <div class="receipt-signature">
                    <div class="sign-box">
                        <div class="sign-line"></div>
                        Member Signature
                    </div>
                    <div class="sign-box">
                        <div class="sign-line"></div>
                        Authorized Signature
                    </div>
                </div>
            </div>
            
            <div class="receipt-footer">
                <p>This is a computer-generated receipt. Thank you for your payment.</p>
                <p>For any enquiries, please contact our customer service at +91 123-456-7890 or visit our office.</p>
            </div>
        </div>
        
        <div class="actions">
            <button onclick="window.print();" class="btn-action">Print Receipt</button>
            <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'index.php'; ?>" class="btn-action">Back</a>
        </div>
    </body>
    </html>
    <?php
    exit;
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
            max-width: 1400px; /* Increased from 1200px */
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
            margin-top: 0; /* Changed from 20px since table-container handles margin */
            box-shadow: none; /* Removed since table-container handles shadow */
            border-radius: var(--border-radius);
            overflow: hidden;
            min-width: 1200px; /* Ensure minimum width for proper column spacing */
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
            border-radius: var(--border-radius);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        table th, table td {
            padding: 12px 8px; /* Reduced horizontal padding slightly */
            border: 1px solid #eee;
            text-align: left;
            white-space: nowrap; /* Prevent text wrapping in cells */
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
        @media screen and (max-width: 1450px) {
            .container {
                margin: 10px;
                padding: 20px;
            }
        }

        @media screen and (max-width: 768px) {
            .container {
                padding: 15px;
                margin: 10px;
                width: auto;
            }
            
            .table-container {
                margin: 10px -15px; /* Extend table container to container edges on mobile */
                border-radius: 0;
            }
            
            table {
                min-width: 1000px; /* Reduced minimum width for mobile */
            }
            
            table th, table td {
                padding: 8px 6px;
                font-size: 0.9em;
            }
            
            .action-btn {
                padding: 6px 8px;
                font-size: 0.75em;
                margin: 2px;
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
            <p style="font-size: 20px;">Enter a Member ID to view their loan types and installments.</p>
        </div>
        
        <div class="filter-section">
            <form method="GET" action="" id="memberForm">
                <div class="filter-form-group">
                    <label for="member_id" style="font-size: 20px;">Member ID:</label>
                    <input type="number" style="font-size: 20px;" name="member_id" id="member_id" min="1" value="<?php echo $member_id; ?>" required>
                </div>
                
                <button type="submit" style="font-size: 20px;">Find Member</button>
            </form>
        </div>
        
        <?php if ($member_id > 0): ?>
            <?php if ($member_info): ?>
                <div class="member-info">
                    <h2><i class="fas fa-user-circle" ></i> Member Details</h2>
                    <p style="font-size: 20px;"><i class="fas fa-user"></i> <strong>Name:</strong> <?php echo htmlspecialchars($member_info['name']); ?></p>
                    <p style="font-size: 20px;"><i class="fas fa-envelope"></i> <strong>Email:</strong> <?php echo htmlspecialchars($member_info['email']); ?></p>
                    <p style="font-size: 20px;"><i class="fas fa-phone"></i> <strong>Phone:</strong> <?php echo htmlspecialchars($member_info['phone']); ?></p>
                </div>
                
                <?php if (!empty($member_loan_types)): ?>
                    <h2><i class="fas fa-file-invoice-dollar" style="font-size: 40px;" ></i> Loan Types</h2>
                    <div class="loan-types-container" style="font-size: 20px;">
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
                                    <h2><i class="fas fa-calendar-alt" style="font-size: 40px;" ></i> Loan Installments</h2>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th style="font-size: 20px;">Loan ID</th>
                                <th style="font-size: 20px;">Loan Type</th>
                                <th style="font-size: 20px;">Install. #</th>
                                <th style="font-size: 20px;">Payment Date</th>
                                <th style="font-size: 20px;">Payment Amt</th>
                                <th style="font-size: 20px;">Principal</th>
                                <th style="font-size: 20px;">Interest</th>
                                <th style="font-size: 20px;">Late Fee</th>
                                <th style="font-size: 20px;">Balance</th>
                                <th style="font-size: 20px;">Status</th>
                                <th style="font-size: 20px;">Actual Payment</th>
                                <th style="font-size: 20px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            while($row = $result->fetch_assoc()): 
                                $status_class = 'status-' . $row['payment_status'];
                            ?>
                            <tr class="<?php echo $status_class; ?>">
                                <td style="font-size: 20px;" class="loan-id"><?php echo $row['loan_id']; ?></td>
                                <td style="font-size: 20px;" class="loan-type"><?php echo htmlspecialchars($row['loan_name']); ?></td>
                                <td style="font-size: 20px;"><?php echo $row['installment_number']; ?></td>
                                <td style="font-size: 20px;"><?php echo date('M d, Y', strtotime($row['payment_date'])); ?></td>
                                <td style="font-size: 20px;">Rs.<?php echo number_format($row['payment_amount'], 2); ?></td>
                                <td style="font-size: 20px;">Rs.<?php echo number_format($row['principal_amount'], 2); ?></td>
                                <td style="font-size: 20px;">Rs.<?php echo number_format($row['interest_amount'], 2); ?></td>
                                <td style="font-size: 20px;">
                                    <?php if ($row['late_fee'] > 0): ?>
                                        <span style="color: #f44336;">Rs.<?php echo number_format($row['late_fee'], 2); ?></span>
                                    <?php else: ?>
                                        <span style="color: #4caf50;">Rs.0.00</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 20px;">Rs.<?php echo number_format($row['remaining_balance'], 2); ?></td>
                                <td style="font-size: 20px;">
                                    <?php if ($row['payment_status'] == 'paid'): ?>
                                        <span style="color: #4caf50; font-size: 0.9em;"><i class="fas fa-check-circle"></i> Paid</span>
                                    <?php elseif ($row['payment_status'] == 'pending'): ?>
                                        <span style="color: #ff9800; font-size: 0.9em;"><i class="fas fa-clock"></i> Pending</span>
                                    <?php else: ?>
                                        <span style="color: #f44336; font-size: 0.9em;"><i class="fas fa-exclamation-circle"></i> Overdue</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 20px;">
                                    <?php if ($row['actual_payment_date']): ?>
                                        <div style="font-size: 0.85em;">
                                            <i class="fas fa-calendar-check"></i> <?php echo date('M d, Y', strtotime($row['actual_payment_date'])); ?><br>
                                            <i class="fas fa-money-bill-wave"></i> Rs.<?php echo number_format($row['actual_payment_amount'], 2); ?>
                                        </div>
                                    <?php else: ?>
                                        <span style="font-size: 0.85em; color: #999;"><i class="fas fa-times-circle"></i> Not paid</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 20px;">
                                    <div style="display: flex; flex-direction: column; gap: 3px;">
                                        <?php if ($row['payment_status'] != 'paid'): ?>
                                            <button class="action-btn btn-paid" onclick="showPaymentModal(<?php echo $row['id']; ?>, <?php echo $row['payment_amount']; ?>)" style="font-size: 0.8em;">Mark Paid</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['payment_status'] != 'pending'): ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="installment_id" value="<?php echo $row['id']; ?>">
                                                <input type="hidden" name="new_status" value="pending">
                                                <button type="submit" name="update_status" class="action-btn btn-pending" style="font-size: 0.8em;">Mark Pending</button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($row['payment_status'] != 'overdue'): ?>
                                            <button class="action-btn btn-overdue" onclick="showOverdueModal(<?php echo $row['id']; ?>, <?php echo $row['loan_type_late_fee'] ? $row['loan_type_late_fee'] : 0; ?>)" style="font-size: 0.8em;">Mark Overdue</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
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
                <input type="hidden" name="new_status" value="paid">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                <input type="hidden" name="loan_type_id" value="<?php echo $selected_loan_type; ?>">
                
                <div class="form-group">
                    <label for="actual_payment_date"><i class="far fa-calendar-alt"></i> Payment Date:</label>
                    <input type="date" name="actual_payment_date" id="actual_payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="actual_payment_amount">Payment Amount (Rs.):</label>
                    <input type="number" name="actual_payment_amount" id="actual_payment_amount" step="0.01" required>
                </div>
                
                <div class="form-buttons">
                    <button type="button" class="btn-cancel" onclick="closePaymentModal()"><i class="fas fa-times"></i> Cancel</button>
                    <button type="submit" name="update_status" class="btn-submit"><i class="fas fa-save"></i> Save Payment</button>
                </div>
            </form>
        </div>
    </div>

    <div id="overdueModal" class="payment-modal">
    <div class="modal-content">
        <h3><i class="fas fa-exclamation-triangle"></i> Mark as Overdue</h3>
        <form method="post" id="overdueForm">
            <input type="hidden" name="installment_id" id="overdue_installment_id">
            <input type="hidden" name="new_status" value="overdue">
            <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
            <input type="hidden" name="loan_type_id" value="<?php echo $selected_loan_type; ?>">
            
            <div class="form-group">
                <label for="late_fee_amount"><i class="fas fa-exclamation-circle"></i> Late Fee Amount (Rs.):</label>
                <input type="number" name="late_fee_amount" id="late_fee_amount" step="0.01" min="0" required>
                <small style="color: #666; display: block; margin-top: 5px;">
                    <i class="fas fa-info-circle"></i> Default late fee will be applied based on loan type settings
                </small>
            </div>
            
            <div class="alert" style="background-color: #fff3cd; border-color: #ffeaa7; color: #856404; margin: 15px 0;">
                <i class="fas fa-exclamation-triangle"></i>
                <strong>Warning:</strong> This will mark the installment as overdue and apply the specified late fee.
            </div>
            
            <div class="form-buttons">
                <button type="button" class="btn-cancel" onclick="closeOverdueModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" name="update_status" class="btn-submit" style="background-color: #f44336;"><i class="fas fa-exclamation-triangle"></i> Mark Overdue</button>
            </div>
        </form>
    </div>
</div>
    
    <script>
    // Modal functionality
    var paymentModal = document.getElementById("paymentModal");
    var overdueModal = document.getElementById("overdueModal");
    
    function showPaymentModal(installmentId, suggestedAmount) {
        document.getElementById("modal_installment_id").value = installmentId;
        document.getElementById("actual_payment_amount").value = suggestedAmount;
        paymentModal.style.display = "block";
        
        // Add animation class to modal content
        document.querySelector("#paymentModal .modal-content").classList.add("animate");
        
        // Set focus on the date field
        setTimeout(function() {
            document.getElementById("actual_payment_date").focus();
        }, 300);
    }
    
    function showOverdueModal(installmentId, defaultLateFee) {
        document.getElementById("overdue_installment_id").value = installmentId;
        document.getElementById("late_fee_amount").value = defaultLateFee;
        overdueModal.style.display = "block";
        
        // Add animation class to modal content
        document.querySelector("#overdueModal .modal-content").classList.add("animate");
        
        // Set focus on the late fee field
        setTimeout(function() {
            document.getElementById("late_fee_amount").focus();
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
    
    function closeOverdueModal() {
        // Fade out animation
        overdueModal.style.opacity = "0";
        setTimeout(function() {
            overdueModal.style.display = "none";
            overdueModal.style.opacity = "1";
        }, 300);
    }

    
    
    // Close modal when clicking outside of it
    window.onclick = function(event) {
        if (event.target == paymentModal) {
            closePaymentModal();
        }
        if (event.target == overdueModal) {
            closeOverdueModal();
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
        
        // Add confirmation for overdue marking
        var overdueButtons = document.querySelectorAll(".btn-overdue");
        overdueButtons.forEach(function(button) {
            button.addEventListener("click", function(e) {
                // The modal will handle the confirmation, so we don't need to prevent default here
                // Just add a subtle visual feedback
                this.style.backgroundColor = "#d32f2f";
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
    
    // Form validation for late fee
    document.getElementById("overdueForm").addEventListener("submit", function(e) {
        var lateFee = document.getElementById("late_fee_amount").value;
        if (lateFee < 0) {
            e.preventDefault();
            alert("Late fee cannot be negative!");
            return false;
        }
        
        // Show confirmation
        if (!confirm("Are you sure you want to mark this installment as overdue with a late fee of Rs." + parseFloat(lateFee).toFixed(2) + "?")) {
            e.preventDefault();
            return false;
        }
    });
</script>
</body>
</html>

<?php
// Close connection
$conn->close();
?>