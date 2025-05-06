<?php
// Database connection
function connectDB() {
    $servername = "localhost";
    $username = "root"; // Replace with your database username
    $password = ""; // Replace with your database password
    $dbname = "sarvodaya"; // Replace with your database name

    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    return $conn;
}

// Function to get payments within a specific time period
function getPaymentsByPeriod($start_date, $end_date) {
    $conn = connectDB();
    
    $query = "SELECT * FROM payments WHERE payment_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $payments = array();
    $totalPayments = 0;
    
    while($row = $result->fetch_assoc()) {
        $payments[] = $row;
        $totalPayments += $row['amount'];
    }
    
    $stmt->close();
    $conn->close();
    
    return array(
        'payments' => $payments,
        'totalPayments' => $totalPayments
    );
}

// Function to get receipts within a specific time period
function getReceiptsByPeriod($start_date, $end_date) {
    $conn = connectDB();
    
    $query = "SELECT * FROM receipts WHERE receipt_date BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $receipts = array();
    $totalReceipts = 0;
    
    while($row = $result->fetch_assoc()) {
        $receipts[] = $row;
        $totalReceipts += $row['amount'];
    }
    
    $stmt->close();
    $conn->close();
    
    return array(
        'receipts' => $receipts,
        'totalReceipts' => $totalReceipts
    );
}

// Function to calculate profit or loss
function calculateProfitOrLoss($totalReceipts, $totalPayments) {
    return $totalReceipts - $totalPayments;
}

// Handle PDF export
if (isset($_POST['export_pdf'])) {
    require('fpdf/fpdf.php'); // Include FPDF library

    $start_date = $_POST['start_date'] . ' 00:00:00';
    $end_date = $_POST['end_date'] . ' 23:59:59';
    $paymentsData = getPaymentsByPeriod($start_date, $end_date);
    $receiptsData = getReceiptsByPeriod($start_date, $end_date);
    $totalPayments = $paymentsData['totalPayments'];
    $totalReceipts = $receiptsData['totalReceipts'];
    $profitOrLoss = calculateProfitOrLoss($totalReceipts, $totalPayments);

    // Generate PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 16);

    // Add title
    $pdf->Cell(0, 10, 'Balance Sheet Report', 0, 1, 'C');
    
    // Add date range information
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 8, 'Period: ' . date('Y-m-d', strtotime($_POST['start_date'])) . ' to ' . date('Y-m-d', strtotime($_POST['end_date'])), 0, 1, 'C');
    $pdf->Ln(4);

    // Add receipts table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Receipts', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(20, 10, 'ID', 1);
    $pdf->Cell(25, 10, 'Member ID', 1);
    $pdf->Cell(50, 10, 'Description', 1);
    $pdf->Cell(35, 10, 'Amount (Rs.)', 1);
    $pdf->Cell(60, 10, 'Date', 1);
    $pdf->Ln();

    foreach ($receiptsData['receipts'] as $receipt) {
        // Format the date to only show the date part (without time)
        $formatted_date = date('Y-m-d', strtotime($receipt['receipt_date']));
        
        $pdf->Cell(20, 10, $receipt['id'], 1);
        $pdf->Cell(25, 10, $receipt['member_id'], 1);
        $pdf->Cell(50, 10, $receipt['receipt_type'], 1);
        $pdf->Cell(35, 10, number_format($receipt['amount'], 2), 1);
        $pdf->Cell(60, 10, $formatted_date, 1);
        $pdf->Ln();
    }

    // Add payments table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Payments', 0, 1);
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(20, 10, 'ID', 1);
    $pdf->Cell(25, 10, 'Member ID', 1);
    $pdf->Cell(50, 10, 'Description', 1);
    $pdf->Cell(35, 10, 'Amount (Rs.)', 1);
    $pdf->Cell(60, 10, 'Date', 1);
    $pdf->Ln();

    foreach ($paymentsData['payments'] as $payment) {
        // Format the date to only show the date part (without time)
        $formatted_date = date('Y-m-d', strtotime($payment['payment_date']));
        
        $pdf->Cell(20, 10, $payment['id'], 1);
        $pdf->Cell(25, 10, $payment['member_id'], 1);
        $pdf->Cell(50, 10, $payment['payment_type'], 1);
        $pdf->Cell(35, 10, number_format($payment['amount'], 2), 1);
        $pdf->Cell(60, 10, $formatted_date, 1);
        $pdf->Ln();
    }

    // Add totals and profit/loss
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 10, 'Totals', 0, 1);
    $pdf->Cell(100, 10, 'Total Receipts: Rs. ' . number_format($totalReceipts, 2), 0, 1);
    $pdf->Cell(100, 10, 'Total Payments: Rs. ' . number_format($totalPayments, 2), 0, 1);
    
    // Different color for profit/loss
    if ($profitOrLoss >= 0) {
        $pdf->SetTextColor(0, 128, 0); // Green for profit
        $pdf->Cell(100, 10, 'Profit: Rs. ' . number_format($profitOrLoss, 2), 0, 1);
    } else {
        $pdf->SetTextColor(255, 0, 0); // Red for loss
        $pdf->Cell(100, 10, 'Loss: Rs. ' . number_format(abs($profitOrLoss), 2), 0, 1);
    }
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    
    // Add date and signature spaces
    $pdf->Ln(20);
    
    // Date section
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(50, 10, 'Date: _______________', 0, 0);
    
    // Signature section
    $pdf->Cell(0, 10, 'Signature: _________________________', 0, 1, 'R');
    
    // Add a note at the bottom
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->Cell(0, 10, 'This is a computer generated report. No signature required if printed with official seal.', 0, 1, 'C');

    // Output the PDF
    $pdf->Output('D', 'balance_sheet_report.pdf');
    exit();
}

// Handle form submission
if (isset($_POST['generate_balance_sheet'])) {
    $start_date = $_POST['start_date'] . ' 00:00:00';
    $end_date = $_POST['end_date'] . ' 23:59:59';
    
    // Fetch payments and receipts for the selected period
    $paymentsData = getPaymentsByPeriod($start_date, $end_date);
    $receiptsData = getReceiptsByPeriod($start_date, $end_date);
    
    // Extract totals
    $totalPayments = $paymentsData['totalPayments'];
    $totalReceipts = $receiptsData['totalReceipts'];
    
    // Calculate profit or loss
    $profitOrLoss = calculateProfitOrLoss($totalReceipts, $totalPayments);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Balance Sheet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <style>
        .balance-sheet {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
            border-radius: 10px;
            box-shadow: 0 0 10px #ff8c00;
        }
        .table {
            margin-bottom: 20px;
        }
        .profit {
            color: green;
            font-weight: bold;
        }
        .loss {
            color: red;
            font-weight: bold;
        }
        /* Custom button styles */
        .btn {
            background-color: orange;
            border-color: orange;
            color: white;
        }
        .btn:hover {
            background-color: darkorange;
            border-color: darkorange;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <div class="balance-sheet">
            <h2 class="text-center mb-4">Balance Sheet</h2>
            
            <!-- Date Selection Form -->
            <form method="post" action="">
                <div class="row mb-4">
                    <div class="col-md-5">
                        <label for="start_date" class="form-label">Start Date:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-5">
                        <label for="end_date" class="form-label">End Date:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="generate_balance_sheet" class="btn btn-primary w-100">Generate</button>
                    </div>
                </div>
            </form>
            
            <?php if (isset($paymentsData) && isset($receiptsData)): ?>
                <!-- Export Buttons -->
                <div class="mb-4">
                    <form method="post" action="">
                        <input type="hidden" name="start_date" value="<?php echo $_POST['start_date']; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $_POST['end_date']; ?>">
                        <button type="submit" name="export_pdf" class="btn">Export Balance Sheet to PDF</button>
                    </form>
                </div>

                <!-- Receipts Section -->
                <h3>Receipts</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member ID</th>
                            <th>Description</th>
                            <th>Amount (Rs.)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receiptsData['receipts'] as $receipt): ?>
                        <tr>
                            <td><?php echo $receipt['id']; ?></td>
                            <td><?php echo $receipt['member_id']; ?></td>
                            <td><?php echo $receipt['receipt_type']; ?></td>
                            <td><?php echo number_format($receipt['amount'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($receipt['receipt_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total Receipts</th>
                            <th><?php echo number_format($totalReceipts, 2); ?></th>
                            <th></th>
                        </tr>
                    </tfoot>
                </table>

                <!-- Payments Section -->
                <h3>Payments</h3>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Member ID</th>
                            <th>Description</th>
                            <th>Amount (Rs.)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsData['payments'] as $payment): ?>
                        <tr>
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo $payment['member_id']; ?></td>
                            <td><?php echo $payment['payment_type']; ?></td>
                            <td><?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($payment['payment_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total Payments</th>
                            <th><?php echo number_format($totalPayments, 2); ?></th>
                            <th colspan="2"></th>
                        </tr>
                    </tfoot>
                </table>
                
                <!-- Profit or Loss Section -->
                <div class="text-center mt-4">
                    <h3>Profit or Loss</h3>
                    <p class="<?php echo ($profitOrLoss >= 0) ? 'profit' : 'loss'; ?>">
                        <?php
                        if ($profitOrLoss >= 0) {
                            echo "Profit: Rs. " . number_format($profitOrLoss, 2);
                        } else {
                            echo "Loss: Rs. " . number_format(abs($profitOrLoss), 2);
                        }
                        ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set default dates to current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            
            // Format dates as YYYY-MM-DD
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            // Set default values for date inputs
            document.getElementById('start_date').value = formatDate(firstDay);
            document.getElementById('end_date').value = formatDate(lastDay);
        });
    </script>
</body>
</html>