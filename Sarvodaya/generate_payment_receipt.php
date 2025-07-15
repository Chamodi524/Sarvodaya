<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if payment_id is provided
if (!isset($_GET['payment_id']) || empty($_GET['payment_id'])) {
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? 'index.php');
    exit;
}

$payment_id = (int)$_GET['payment_id'];

// Query to get detailed payment information
$query = "
    SELECT 
        payments.id AS payment_id,
        members.id AS member_id,
        members.name AS member_name,
        members.address,
        members.phone,
        payments.payment_type,
        payments.amount,
        payments.description,
        payments.payment_date
    FROM payments
    JOIN members ON payments.member_id = members.id
    WHERE payments.id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $payment_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Payment not found";
    exit;
}

$payment = $result->fetch_assoc();

// Receipt number - use payment ID with a prefix
$receipt_number = 'V-' . str_pad($payment['payment_id'], 6, '0', STR_PAD_LEFT);

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

// Close the database connection
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Voucher - Sarvodaya Bank</title>
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
            border-bottom: 2px solid #ff8c00 ;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-title {
            color: #ff8c00 ;
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
            color: #ff8c00 ;
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
            color: #ff8c00 ;
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
            background-color:#ff8c00 ;
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
                <div class="receipt-bank-name">SARVODAYA SHRAMADANA SOCIETY</div>
            </div>
            <div class="receipt-bank-address">
            Samaghi Sarvodaya Shramadhana Society,Kubaloluwa,Veyangoda. <br>
                Phone: 077 690 6605 | Email: info@sarvodayabank.com
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="receipt-number">Voucher No: <?php echo htmlspecialchars($receipt_number); ?></div>
                    <div class="receipt-date">Date: <?php echo htmlspecialchars(date('d-m-Y', strtotime($payment['payment_date']))); ?></div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="receipt-title">Payment Voucher</div>
                </div>
            </div>
        </div>
        
        <div class="receipt-body">
            <div class="receipt-row">
                <div class="receipt-label">Paid To:</div>
                <div class="receipt-value"><?php echo htmlspecialchars($payment['member_name']); ?></div>
            </div>
            
            <div class="receipt-row">
                <div class="receipt-label">Member ID:</div>
                <div class="receipt-value"><?php echo htmlspecialchars($payment['member_id']); ?></div>
            </div>
            
            <?php if (!empty($payment['address'])): ?>
            <div class="receipt-row">
                <div class="receipt-label">Address:</div>
                <div class="receipt-value"><?php echo nl2br(htmlspecialchars($payment['address'])); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="receipt-row">
                <div class="receipt-label">Payment Type:</div>
                <div class="receipt-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_type']))); ?></div>
            </div>
            
            
            
            <div class="receipt-amount">
                Rs.<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?>
            </div>
            
            <div class="receipt-amount-words">
                <?php echo numberToWords($payment['amount']); ?>
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
    </div>
    
    <div class="actions">
        <button onclick="window.print();" class="btn-action">Print Voucher</button>
        <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'index.php'; ?>" class="btn-action">Back</a>
    </div>
</body>
</html>