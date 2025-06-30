<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Display messages if they exist in session
session_start();

// Flag to determine whether to show the payment form
$show_payment_form = true;
if (isset($_SESSION['transaction_details'])) {
    // If we have transaction details, don't show the form
    $show_payment_form = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Payments - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #ffa726;
            color: white;
            border-radius: 5px;
            border: none;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #fb8c00;
            transform: scale(1.05);
        }
        .form-control {
            border-radius: 5px;
        }
        .voucher {
            border: 1px solid #ddd;
            padding: 20px;
            margin-top: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        /* Receipt Styling */
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
        #printable-receipt {
            display: none;
        }
        .balance-info {
            margin-top: 10px;
            padding: 10px;
            border-radius: 5px;
            background-color: #f0f0f0;
        }
        .balance-info.error {
            background-color: #f8d7da;
            color: #721c24;
        }
        .balance-info.success {
            background-color: #d4edda;
            color: #155724;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #printable-receipt, #printable-receipt * {
                visibility: visible;
            }
            #printable-receipt {
                display: block;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4 no-print" style="color: #ffa726;">Money Payments - Sarvodaya Bank</h1>
        
        <?php
        // Display success message if exists
        if (isset($_SESSION['success_message'])) {
            echo '<div class="alert alert-success no-print">' . $_SESSION['success_message'] . '</div>';
            
            // If we have transaction details, show a receipt
            if (isset($_SESSION['transaction_details'])) {
                $transaction = $_SESSION['transaction_details'];
                
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
                        
                        if ($wholenum < 20) {
                            $result .= $ones[$wholenum];
                        } else {
                            $result .= $tens[(int)($wholenum/10)];
                            if ($wholenum % 10 > 0) {
                                $result .= " " . $ones[$wholenum % 10];
                            }
                        }
                    }
                    
                    // Add "Rupees" text
                    if ($result == "") {
                        $result = "Zero";
                    }
                    $result .= " Rupees";
                    
                    // Process decimal part (paise)
                    if ((int)$decnum > 0) {
                        $result .= " and ";
                        if ((int)$decnum < 20) {
                            $result .= $ones[(int)$decnum];
                        } else {
                            $result .= $tens[(int)($decnum/10)];
                            if ((int)$decnum % 10 > 0) {
                                $result .= " " . $ones[(int)$decnum % 10];
                            }
                        }
                        $result .= " Paise";
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
                
                // Basic receipt in the main view
                echo '<div class="voucher no-print">';
                echo '<h3 class="text-center">Transaction Receipt</h3>';
                echo '<hr>';
                echo '<div class="row">';
                echo '<div class="col-6">Transaction ID:</div>';
                echo '<div class="col-6">' . $transaction['id'] . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">Member ID:</div>';
                echo '<div class="col-6">' . $transaction['member_id'] . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">Member Name:</div>';
                echo '<div class="col-6">' . $transaction['member_name'] . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">Transaction Type:</div>';
                echo '<div class="col-6">' . $transaction['transaction_type'] . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">Amount:</div>';
                echo '<div class="col-6">Rs.' . number_format($transaction['amount'], 2) . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">New Balance:</div>';
                echo '<div class="col-6">Rs.' . number_format($transaction['new_balance'], 2) . '</div>';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-6">Transaction Date:</div>';
                echo '<div class="col-6">' . $transaction['date'] . '</div>';
                echo '</div>';
                echo '<div class="row mt-3">';
                echo '<div class="col-12 text-center">';
                echo '<button class="btn btn-sm btn-secondary me-2" onclick="window.print()">Print Receipt</button>';
                echo '<a href="payment_management.php?new=1" class="btn btn-sm btn-custom">New Payment</a>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                // Formatted printable receipt (hidden by default, shown when printing)
                // Receipt number - use transaction ID with a prefix
                $receipt_number = 'TXN-' . str_pad($transaction['id'], 6, '0', STR_PAD_LEFT);
                $transaction_type_formatted = ucwords(strtolower($transaction['transaction_type']));
                
                echo '<div id="printable-receipt">';
                echo '<div class="receipt-container">';
                echo '<div class="receipt-header">';
                echo '<div class="receipt-logo">';
                echo '<div class="receipt-bank-name">SARVODAYA SHRAMADHANA SOCIETY</div>';
                echo '</div>';
                echo '<div class="receipt-bank-address">';
                echo 'Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda.<br>';
                echo 'Phone: 077 690 6605 | Email: info@sarvodayabank.com';
                echo '</div>';
                echo '<div class="row">';
                echo '<div class="col-md-6">';
                echo '<div class="receipt-number">Receipt No: ' . $receipt_number . '</div>';
                echo '<div class="receipt-date">Date: ' . date('d-m-Y', strtotime($transaction['date'])) . '</div>';
                echo '</div>';
                echo '<div class="col-md-6 text-end">';
                echo '<div class="receipt-title">RECEIPT</div>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="receipt-body">';
                echo '<div class="receipt-row">';
                echo '<div class="receipt-label">Received From:</div>';
                echo '<div class="receipt-value">' . $transaction['member_name'] . '</div>';
                echo '</div>';
                
                echo '<div class="receipt-row">';
                echo '<div class="receipt-label">Member ID:</div>';
                echo '<div class="receipt-value">' . $transaction['member_id'] . '</div>';
                echo '</div>';
                
                echo '<div class="receipt-row">';
                echo '<div class="receipt-label">Transaction Type:</div>';
                echo '<div class="receipt-value">' . $transaction_type_formatted . '</div>';
                echo '</div>';
                
                echo '<div class="receipt-amount">';
                echo 'Rs.' . number_format($transaction['amount'], 2);
                echo '</div>';
                
                echo '<div class="receipt-amount-words">';
                echo numberToWords($transaction['amount']);
                echo '</div>';
                
                echo '<div class="receipt-signature">';
                echo '<div class="sign-box">';
                echo '<div class="sign-line"></div>';
                echo 'Member Signature';
                echo '</div>';
                echo '<div class="sign-box">';
                echo '<div class="sign-line"></div>';
                echo 'Authorized Signature';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                echo '<div class="receipt-footer">';
                echo '<p>This is a computer-generated receipt. Thank you for your payment.</p>';
                echo '<p>For any enquiries, please contact our customer service or visit our office.</p>';
                echo '</div>';
                echo '</div>';
                echo '</div>';
                
                // Clear the transaction details
                unset($_SESSION['transaction_details']);
            }
            
            // Clear the message
            unset($_SESSION['success_message']);
        }
        
        // Display error message if exists
        if (isset($_SESSION['error_message'])) {
            echo '<div class="alert alert-danger no-print">' . $_SESSION['error_message'] . '</div>';
            // Clear the message
            unset($_SESSION['error_message']);
        }
        
        // Show payment form if flag is true or if "new=1" is set in URL
        if ($show_payment_form || isset($_GET['new'])) {
        ?>
            <!-- Payment Input Form -->
            <div class="card no-print">
                <h2>Add New Payment</h2>
                <form method="POST" action="payment_management_process.php">
                    <div class="mb-3">
                        <label for="member_id" class="form-label" style="font-size: 20px;">Member ID</label>
                        <input type="number" class="form-control" id="member_id" style="font-size: 20px;" name="member_id" required>
                        <div id="member_info" class="mt-2"></div>
                    </div>
                    <div class="mb-3">
                        <label for="transaction_type" class="form-label" style="font-size: 20px;">Transaction Type</label>
                        <select class="form-select" id="transaction_type" style="font-size: 20px;" name="transaction_type" required>
                            <option value="" style="font-size: 20px;">Select Transaction Type</option>
                            <option value="WITHDRAWAL" style="font-size: 20px;">Withdrawal</option>
                            <option value="OTHER" style="font-size: 20px;">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label" style="font-size: 20px;">Amount</label>
                        <input type="number" class="form-control" id="amount" style="font-size: 20px;"name="amount" step="0.01" required>
                        <div id="amount_warning" class="text-danger mt-1" style="display: none;">
                            Warning: Amount exceeds current balance!
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label" style="font-size: 20px;">Description</label>
                        <textarea class="form-control" id="description" style="font-size: 20px;" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="reference" class="form-label" style="font-size: 20px;">Reference</label>
                        <input type="text" class="form-control" id="reference" style="font-size: 20px;" name="reference">
                    </div>
                    <button type="submit" class="btn btn-custom" style="font-size: 20px;">Submit Payment</button>
                </form>
            </div>
        <?php
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variable to store current balance
        let currentBalance = 0;
        
        // Function to fetch member information when member ID is entered
        document.getElementById('member_id').addEventListener('blur', function() {
            const memberId = this.value;
            if (memberId) {
                // Create an AJAX request
                const xhr = new XMLHttpRequest();
                xhr.open('GET', 'get_member_balance.php?member_id=' + memberId, true);
                xhr.onload = function() {
                    if (this.status === 200) {
                        try {
                            const response = JSON.parse(this.responseText);
                            const memberInfoDiv = document.getElementById('member_info');
                            
                            if (response.success) {
                                // Store the current balance globally
                                currentBalance = parseFloat(response.balance);
                                
                                // Display member information
                                memberInfoDiv.innerHTML = `
                                    <div class="balance-info success">
                                        <strong>Member:</strong> ${response.member_name}<br>
                                        <strong>Account Type:</strong> ${response.account_type}<br>
                                        <strong>Current Balance:</strong> Rs.${parseFloat(response.balance).toFixed(2)}
                                    </div>
                                `;
                                // Show the div
                                memberInfoDiv.style.display = 'block';
                            } else {
                                memberInfoDiv.innerHTML = `
                                    <div class="balance-info error">
                                        <strong>Error:</strong> ${response.message}
                                    </div>
                                `;
                                memberInfoDiv.style.display = 'block';
                            }
                        } catch (e) {
                            console.error('Error parsing JSON:', e);
                        }
                    }
                };
                xhr.send();
            } else {
                document.getElementById('member_info').style.display = 'none';
            }
        });
        
        // Check if amount exceeds balance when transaction type is WITHDRAWAL
        document.getElementById('amount').addEventListener('input', checkWithdrawalAmount);
        document.getElementById('transaction_type').addEventListener('change', checkWithdrawalAmount);
        
        function checkWithdrawalAmount() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const transactionType = document.getElementById('transaction_type').value;
            const amountWarning = document.getElementById('amount_warning');
            
            if (transactionType === 'WITHDRAWAL' && amount > currentBalance) {
                amountWarning.style.display = 'block';
            } else {
                amountWarning.style.display = 'none';
            }
        }
    </script>
</body>
</html>