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
        members.email,
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Details - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .detail-header {
            background-color: #fff8e1;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .detail-section {
            margin-bottom: 20px;
        }
        .detail-label {
            font-weight: bold;
            color: #555;
        }
        .detail-value {
            font-size: 1.1em;
        }
        .btn-action {
            background-color: #ffa726; /* Orange color */
            color: white;
            border-radius: 5px;
            border: none;
            padding: 8px 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-right: 5px;
            display: inline-block;
        }
        .btn-action:hover {
            background-color: #fb8c00; /* Darker orange on hover */
            transform: scale(1.05);
            color: white;
        }
        .btn-print {
            background-color: #28a745; /* Green color */
            color: white;
        }
        .btn-print:hover {
            background-color: #218838; /* Darker green on hover */
        }
        .btn-back {
            background-color: #6c757d; /* Gray color */
            color: white;
        }
        .btn-back:hover {
            background-color: #5a6268; /* Darker gray on hover */
            color: white;
        }
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                padding: 0;
                background-color: white;
            }
            .card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 style="color: #ffa726;">Payment Details</h1>
            <div class="no-print">
                <a href="<?php echo $_SERVER['HTTP_REFERER'] ?? 'index.php'; ?>" class="btn-action btn-back">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
                <a href="generate_payment_receipt.php?payment_id=<?php echo $payment_id; ?>" class="btn-action">
                    <i class="bi bi-file-earmark-pdf"></i> Generate Receipt
                </a>
                <a href="#" onclick="window.print();" class="btn-action btn-print">
                    <i class="bi bi-printer"></i> Print
                </a>
            </div>
        </div>

        <div class="card">
            <div class="detail-header">
                <div class="row">
                    <div class="col-md-6">
                        <h3>Payment #<?php echo htmlspecialchars($payment['payment_id']); ?></h3>
                        <p class="mb-0">
                            <span class="badge bg-warning">
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_type']))); ?>
                            </span>
                            <span class="ms-2"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($payment['payment_date']))); ?></span>
                        </p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <h3>₹<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></h3>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="detail-section">
                        <h4>Member Information</h4>
                        <div class="mb-2">
                            <span class="detail-label">Member ID:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['member_id']); ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['member_name']); ?></span>
                        </div>
                        <?php if (!empty($payment['phone'])): ?>
                        <div class="mb-2">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['phone']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($payment['email'])): ?>
                        <div class="mb-2">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($payment['email']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($payment['address'])): ?>
                        <div class="mb-2">
                            <span class="detail-label">Address:</span>
                            <span class="detail-value"><?php echo nl2br(htmlspecialchars($payment['address'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="detail-section">
                        <h4>Payment Information</h4>
                        <div class="mb-2">
                            <span class="detail-label">Payment Date:</span>
                            <span class="detail-value"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($payment['payment_date']))); ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="detail-label">Payment Type:</span>
                            <span class="detail-value"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $payment['payment_type']))); ?></span>
                        </div>
                        <div class="mb-2">
                            <span class="detail-label">Amount:</span>
                            <span class="detail-value">₹<?php echo htmlspecialchars(number_format($payment['amount'], 2)); ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($payment['description'])): ?>
            <div class="detail-section mt-3">
                <h4>Description</h4>
                <div class="p-3 bg-light rounded">
                    <?php echo nl2br(htmlspecialchars($payment['description'])); ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
// Close the database connection
$stmt->close();
$conn->close();
?>