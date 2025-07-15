<?php
session_start(); // Moved to the very top before any output

// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sarvodaya";

// Establish database connection
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

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
    $hundreds = array("", "Hundred", "Thousand", "Lakh", "Crore");

    $number = number_format($number, 2, '.', ',');
    $number_array = explode('.', $number);
    $wholenum = $number_array[0];
    $decnum = $number_array[1];
    $whole_arr = array_reverse(explode(',', $wholenum));
    $rupees = '';

    foreach($whole_arr as $key => $i) {
        $i = (int)$i; // Ensure it's an integer
        
        if($i == 0) {
            // Skip if the value is zero, unless it's the only digit
            if(count($whole_arr) == 1) {
                $rupees = "Zero";
            }
            continue;
        }
        
        if($i < 20) {
            $rupees .= $ones[$i];
        } else {
            $first_digit = (int)(substr((string)$i, 0, 1));
            $second_digit = (int)(substr((string)$i, 1, 1));
            
            $rupees .= $tens[$first_digit];
            if($second_digit > 0) {
                $rupees .= " " . $ones[$second_digit];
            }
        }
        
        if($i > 0) {
            $rupees .= " " . $hundreds[$key] . " ";
        }
    }

    $paise = '';
    $decnum = (int)$decnum; // Convert to integer
    
    if($decnum > 0) {
        if($decnum < 20) {
            $paise = $ones[$decnum] . " Paise";
        } else {
            $first_digit = (int)(substr((string)$decnum, 0, 1));
            $second_digit = (int)(substr((string)$decnum, 1, 1));
            
            $paise = $tens[$first_digit];
            if($second_digit > 0) {
                $paise .= " " . $ones[$second_digit];
            }
            $paise .= " Paise";
        }
    }

    return trim($rupees) . " Rupees" . ($paise ? " and " . $paise : " Only");
}

// Process form submission
$receiptCreated = false;
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $member_id = $_POST['member_id'];
    $loan_type_id = $_POST['loan_type'];
    $amount = $_POST['amount'];
    $description = $_POST['description'];

    // First check if amount exceeds maximum for this loan type
    $loanLimitQuery = $conn->prepare("SELECT maximum_amount, loan_name FROM loan_types WHERE id = ?");
    $loanLimitQuery->bind_param("i", $loan_type_id);
    $loanLimitQuery->execute();
    $loanLimitResult = $loanLimitQuery->get_result();
    
    if ($loanLimitResult->num_rows > 0) {
        $loanData = $loanLimitResult->fetch_assoc();
        $loanLimit = $loanData['maximum_amount'];
        $loanName = $loanData['loan_name'];
        
        if ($amount > $loanLimit) {
            $errorMessage = "Error: Payment amount (Rs. " . number_format($amount, 2) . 
                 ") exceeds maximum allowed amount of Rs. " . number_format($loanLimit, 2) . " for this loan type.";
        } else {
            // Get member information for receipt
            $memberQuery = $conn->prepare("SELECT name, address, phone FROM members WHERE id = ?");
            $memberQuery->bind_param("i", $member_id);
            $memberQuery->execute();
            $memberResult = $memberQuery->get_result();
            
            if ($memberResult->num_rows > 0) {
                $member = $memberResult->fetch_assoc();
                $memberName = $member['name'];
                $memberAddress = $member['address'];
                $memberPhone = $member['phone'];
                
                // Insert the payment into the payments table
                $paymentQuery = "INSERT INTO payments (member_id, payment_type, amount, description, payment_date) VALUES (?, 'Loan Payment', ?, ?, NOW())";
                $stmt = $conn->prepare($paymentQuery);
                $stmt->bind_param("ids", $member_id, $amount, $description);

                if ($stmt->execute()) {
                    // Get the payment ID
                    $payment_id = $conn->insert_id;
                    
                    $successMessage = "Payment recorded successfully!";
                    
                    // Generate receipt number
                    $receipt_number = 'V-' . str_pad($payment_id, 6, '0', STR_PAD_LEFT);
                    
                    // Store receipt data in session to pass to receipt view
                    $_SESSION['receipt_data'] = [
                        'payment_id' => $payment_id,
                        'receipt_number' => $receipt_number,
                        'member_id' => $member_id,
                        'member_name' => $memberName,
                        'member_address' => $memberAddress,
                        'member_phone' => $memberPhone,
                        'loan_name' => $loanName,
                        'payment_type' => 'Loan Payment',
                        'amount' => $amount,
                        'amount_in_words' => numberToWords($amount),
                        'description' => $description,
                        'date' => date('d-m-Y'),
                        'time' => date('h:i:s A')
                    ];
                    
                    // Set flag to redirect after rendering page
                    $receiptCreated = true;
                } else {
                    $errorMessage = "Error: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $errorMessage = "Member not found.";
            }
        }
    }
}

// Determine which view to show
$showReceiptView = (isset($_GET['view']) && $_GET['view'] == 'receipt' && isset($_SESSION['receipt_data']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Record Loan Payment</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-image: url('hhh.jpg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background-color: rgba(255, 255, 255, 0.92);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
            width: 480px; /* Increased from 380px to 480px */
            position: relative;
            border: 1px solid rgba(255, 140, 0, 0.3);
        }

        h2 {
            text-align: center;
            margin-bottom: 25px;
            color: rgb(255, 140, 0);
            font-size: 28px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: rgb(255, 140, 0);
            font-size: 15px;
        }

        input, select, textarea {
            width: 100%;
            padding: 12px 15px;
            margin-bottom: 18px;
            border: 1px solid rgb(255, 140, 0);
            border-radius: 8px;
            font-size: 16px;
            background-color: rgba(255, 255, 255, 0.9);
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: rgb(230, 100, 0);
            box-shadow: 0 0 8px rgba(255, 140, 0, 0.3);
        }

        button {
            width: 100%;
            padding: 14px;
            background-color: rgb(255, 140, 0);
            border: none;
            border-radius: 8px;
            color: white;
            font-size: 17px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }

        button:hover {
            background-color: rgb(230, 100, 0);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 140, 0, 0.3);
        }

        .message {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }

        .success {
            background-color: rgba(76, 175, 80, 0.2);
            color: #2e7d32;
            border: 1px solid #2e7d32;
        }

        .error {
            background-color: rgba(244, 67, 54, 0.2);
            color: #c62828;
            border: 1px solid #c62828;
        }

        .warning {
            background-color: rgba(255, 152, 0, 0.2);
            color: rgb(175, 80, 0);
            border: 1px solid rgb(175, 80, 0);
        }

        /* Animation for form elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-group {
            animation: fadeIn 0.5s ease forwards;
        }

        /* Delay animations for each form element */
        .form-group:nth-child(1) { animation-delay: 0.1s; }
        .form-group:nth-child(2) { animation-delay: 0.2s; }
        .form-group:nth-child(3) { animation-delay: 0.3s; }
        .form-group:nth-child(4) { animation-delay: 0.4s; }
        button { animation: fadeIn 0.5s ease 0.5s forwards; }

        /* Receipt styling */
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
            margin-right: 10px;
        }
        .btn-action:hover {
            background-color: #ff9800;
            color: white;
        }
        .actions {
            text-align: center;
            margin-top: 20px;
        }

        #receiptView .container {
            width: 800px;
            max-width: 100%;
        }

        #receiptView {
            display: <?php echo $showReceiptView ? 'block' : 'none'; ?>;
        }

        #formView {
            display: <?php echo $showReceiptView ? 'none' : 'block'; ?>;
        }

        @media print {
            body {
                padding: 0;
                background-color: white;
                background-image: none;
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
    <div id="formView">
        <div class="container">
            <h1>Record Loan Payment</h1>
            
            <?php if (isset($errorMessage)): ?>
                <div class='message error' style="font-size: 1.5rem;"><?php echo $errorMessage; ?></div>
            <?php endif; ?>
            
            <?php if (isset($successMessage)): ?>
                <div class='message success' style="font-size: 1.5rem;"><?php echo $successMessage; ?></div>
                <script>
                    setTimeout(function() {
                        window.location.href = "?view=receipt";
                    }, 1000);
                </script>
            <?php endif; ?>
            
            <form action="" method="POST" id="paymentForm">
                <div class="form-group">
                    <label for="member_id" style="font-size: 1.5rem;">Member ID:</label>
                    <input type="number" id="member_id"  style="font-size: 1.5rem;" name="member_id" required>
                </div>

                <div class="form-group">
                    <label for="loan_type" style="font-size: 1.5rem;">Loan Type:</label>
                    <select id="loan_type" style="font-size: 1.5rem;" name="loan_type" required onchange="checkLoanLimit()">
                        <?php
                        // Fetch loan types from the database
                        $loanTypesQuery = "SELECT id, loan_name, maximum_amount FROM loan_types";
                        $loanTypesResult = $conn->query($loanTypesQuery);

                        if ($loanTypesResult->num_rows > 0) {
                            while ($row = $loanTypesResult->fetch_assoc()) {
                                echo "<option value='" . $row['id'] . "' data-max='" . $row['maximum_amount'] . "'>" . 
                                     $row['loan_name'] . " (Max: Rs. " . number_format($row['maximum_amount'], 2) . ")</option>";
                            }
                        } else {
                            echo "<option value=''>No loan types available</option>";
                        }
                        ?>
                    </select>
                    <div id="loanLimitInfo" style="font-size: 13px; color: #666; margin-top: -10px; margin-bottom: 15px;"></div>
                </div>

                <div class="form-group">
                    <label for="amount" style="font-size: 1.5rem;">Amount (Rs.):</label>
                    <input type="number" step="0.01" id="amount" name="amount" style="font-size: 1.5rem;" required oninput="checkLoanLimit()">
                    <div id="amountWarning" class="message warning" style="display: none;"></div>
                </div>

                <div class="form-group">
                    <label for="description" style="font-size: 1.5rem;">Description:</label>
                    <textarea id="description" style="font-size: 1.5rem;" name="description" rows="3"></textarea>
                </div>

                <button type="submit" id="submitBtn" style="font-size: 1.5rem;">Record Payment</button>
            </form>
        </div>
    </div>

    <div id="receiptView">
        <?php
        if ($showReceiptView && isset($_SESSION['receipt_data'])) {
            $receipt = $_SESSION['receipt_data'];
        ?>
            <div class="receipt-container">
                <div class="receipt-header">
                    <div class="receipt-logo">
                        <!-- Bank Logo would go here -->
                        <div class="receipt-bank-name">SARVODAYA SHRAMADANA SOCIETY</div>
                    </div>
                    <div class="receipt-bank-address">
                        Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda. <br>
                        Phone: 077 690 6605 | Email: info@sarvodayabank.com
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="receipt-number">Voucher No: <?php echo htmlspecialchars($receipt['receipt_number']); ?></div>
                            <div class="receipt-date">Date: <?php echo htmlspecialchars($receipt['date']); ?></div>
                            <div class="receipt-date">Time: <?php echo htmlspecialchars($receipt['time']); ?></div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="receipt-title">Payment Voucher</div>
                        </div>
                    </div>
                </div>
                
                <div class="receipt-body">
                    <div class="receipt-row">
                        <div class="receipt-label">Paid To:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($receipt['member_name']); ?></div>
                    </div>
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Member ID:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($receipt['member_id']); ?></div>
                    </div>
                    
                    <?php if (!empty($receipt['member_address'])): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Address:</div>
                        <div class="receipt-value"><?php echo nl2br(htmlspecialchars($receipt['member_address'])); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($receipt['member_phone'])): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Phone:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($receipt['member_phone']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Payment Type:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $receipt['payment_type']))); ?></div>
                    </div>
                    
                    <div class="receipt-row">
                        <div class="receipt-label">Loan Type:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($receipt['loan_name']); ?></div>
                    </div>
                    
                    <?php if (!empty($receipt['description'])): ?>
                    <div class="receipt-row">
                        <div class="receipt-label">Description:</div>
                        <div class="receipt-value"><?php echo htmlspecialchars($receipt['description']); ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="receipt-amount">
                        Rs. <?php echo htmlspecialchars(number_format($receipt['amount'], 2)); ?>
                    </div>
                    
                    <div class="receipt-amount-words">
                        <?php echo htmlspecialchars($receipt['amount_in_words']); ?>
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
                    <p>For any enquiries, please contact our customer service at 077 690 6605 or visit our office.</p>
                </div>
                
                <div class="actions">
                    <button onclick="window.print();" class="btn-action">Print Receipt</button>
                    <a href="?view=form" class="btn-action">Back to Form</a>
                </div>
            </div>
        <?php
        } else {
            echo '<div class="container">';
            echo '<div class="message error">No receipt data found.</div>';
            echo '<button class="btn-action" onclick="window.location.href=\'?view=form\'">Back to Form</button>';
            echo '</div>';
        }
        ?>
    </div>

    <script>
        // Store loan limits for client-side validation
        const loanLimits = {};
        <?php
        $limitsQuery = "SELECT id, maximum_amount FROM loan_types";
        $limitsResult = $conn->query($limitsQuery);
        while ($row = $limitsResult->fetch_assoc()) {
            echo "loanLimits[" . $row['id'] . "] = " . $row['maximum_amount'] . ";";
        }
        $conn->close();
        ?>

        function checkLoanLimit() {
            const loanType = document.getElementById('loan_type');
            const amountInput = document.getElementById('amount');
            const warningDiv = document.getElementById('amountWarning');
            const submitBtn = document.getElementById('submitBtn');
            const loanLimitInfo = document.getElementById('loanLimitInfo');
            
            const selectedLoanId = loanType.value;
            const maxAmount = loanLimits[selectedLoanId];
            
            if (maxAmount) {
                loanLimitInfo.textContent = `Maximum allowed: Rs. ${maxAmount.toLocaleString('en-IN', {maximumFractionDigits: 2})}`;
                
                if (amountInput.value && parseFloat(amountInput.value) > maxAmount) {
                    warningDiv.style.display = 'block';
                    warningDiv.textContent = `Warning: Amount exceeds maximum limit of Rs. ${maxAmount.toLocaleString('en-IN', {maximumFractionDigits: 2})}`;
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.6';
                    submitBtn.style.cursor = 'not-allowed';
                } else {
                    warningDiv.style.display = 'none';
                    submitBtn.disabled = false;
                    submitBtn.style.opacity = '1';
                    submitBtn.style.cursor = 'pointer';
                }
            } else {
                loanLimitInfo.textContent = '';
                warningDiv.style.display = 'none';
            }
        }

        // Form submission validation
        document.getElementById('paymentForm')?.addEventListener('submit', function(e) {
            const loanType = document.getElementById('loan_type');
            const amountInput = document.getElementById('amount');
            const maxAmount = loanLimits[loanType.value];
            
            if (parseFloat(amountInput.value) > maxAmount) {
                e.preventDefault();
                alert(`Payment cannot exceed maximum amount of Rs. ${maxAmount.toLocaleString('en-IN', {maximumFractionDigits: 2})} for this loan type`);
            }
        });
    </script>
</body>
</html>