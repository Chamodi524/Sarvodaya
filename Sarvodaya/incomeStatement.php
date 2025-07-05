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

// Function to get all payments (including interest) within a specific time period
function getAllPaymentsByPeriod($start_date, $end_date) {
    $conn = connectDB();
    
    // Get regular payments
    $query1 = "SELECT 'payment' as type, id, payment_type as description, amount, payment_date as date 
               FROM payments WHERE payment_date BETWEEN ? AND ?";
    $stmt1 = $conn->prepare($query1);
    $stmt1->bind_param("ss", $start_date, $end_date);
    $stmt1->execute();
    $result1 = $stmt1->get_result();
    
    $payments = array();
    while($row = $result1->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt1->close();
    
    // Get interest payments
    $query2 = "SELECT 'interest' as type, ic.id, 
               CONCAT('Interest - ', COALESCE(sat.account_name, 'Unknown Account')) as description, 
               ic.interest_amount as amount, ic.calculation_date as date
               FROM interest_calculations ic 
               LEFT JOIN savings_account_types sat ON ic.account_type_id = sat.id 
               WHERE ic.calculation_date BETWEEN ? AND ? 
               AND ic.status = 'POSTED'";
    $stmt2 = $conn->prepare($query2);
    $stmt2->bind_param("ss", $start_date, $end_date);
    $stmt2->execute();
    $result2 = $stmt2->get_result();
    
    while($row = $result2->fetch_assoc()) {
        $payments[] = $row;
    }
    $stmt2->close();
    
    // Sort payments by date
    usort($payments, function($a, $b) {
        return strtotime($a['date']) - strtotime($b['date']);
    });
    
    // Calculate total
    $totalPayments = 0;
    foreach($payments as $payment) {
        $totalPayments += $payment['amount'];
    }
    
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
    $paymentsData = getAllPaymentsByPeriod($start_date, $end_date);
    $receiptsData = getReceiptsByPeriod($start_date, $end_date);
    
    $totalPayments = $paymentsData['totalPayments'];
    $totalReceipts = $receiptsData['totalReceipts'];
    $profitOrLoss = calculateProfitOrLoss($totalReceipts, $totalPayments);

    // Generate PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Organization Header with colors
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(255, 140, 0); // Orange color
    $pdf->Cell(0, 10, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
    
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0); // Black color
    $pdf->Cell(0, 8, 'Samaghi Sarvodaya Shramadhana Society', 0, 1, 'C');
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(0, 6, 'Kubaloluwa, Veyangoda', 0, 1, 'C');
    
    $pdf->SetTextColor(0, 0, 128); // Navy blue
    $pdf->Cell(0, 6, 'Phone: 077 690 6605 | Email: info@sarvodayabank.com', 0, 1, 'C');
    $pdf->Ln(5);

    // Report title
    $pdf->SetFont('Arial', 'B', 16);
    $pdf->SetTextColor(0, 0, 0); // Black
    $pdf->Cell(0, 10, 'Income Statement', 0, 1, 'C');
    
    // Date range
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->SetTextColor(128, 128, 128); // Gray
    $pdf->Cell(0, 8, 'Period: ' . date('Y-m-d', strtotime($_POST['start_date'])) . ' to ' . date('Y-m-d', strtotime($_POST['end_date'])), 0, 1, 'C');
    $pdf->Ln(4);

    // Receipts table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 100, 0); // Dark green
    $pdf->Cell(0, 10, 'Income', 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0, 0, 0); // Black
    $pdf->SetFillColor(220, 230, 220); // Light green background for header
    $pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell(70, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Amount (Rs.)', 1, 0, 'C', true);
    $pdf->Cell(65, 10, 'Date', 1, 0, 'C', true);
    $pdf->Ln();

    foreach ($receiptsData['receipts'] as $receipt) {
        $pdf->SetTextColor(0, 0, 0); // Black
        $formatted_date = date('Y-m-d', strtotime($receipt['receipt_date']));
        
        $pdf->Cell(20, 10, $receipt['id'], 1);
        $pdf->Cell(70, 10, $receipt['receipt_type'], 1);
        $pdf->Cell(35, 10, number_format($receipt['amount'], 2), 1, 0, 'R');
        $pdf->Cell(65, 10, $formatted_date, 1);
        $pdf->Ln();
    }

    // Payments table
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(139, 0, 0); // Dark red
    $pdf->Cell(0, 10, 'Outcome', 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetFillColor(255, 220, 220); // Light red background for header
    $pdf->Cell(20, 10, 'ID', 1, 0, 'C', true);
    $pdf->Cell(70, 10, 'Description', 1, 0, 'C', true);
    $pdf->Cell(35, 10, 'Amount (Rs.)', 1, 0, 'C', true);
    $pdf->Cell(65, 10, 'Date', 1, 0, 'C', true);
    $pdf->Ln();

    foreach ($paymentsData['payments'] as $payment) {
        $formatted_date = date('Y-m-d', strtotime($payment['date']));
        
        // Different color for interest payments
        if ($payment['type'] == 'interest') {
            $pdf->SetTextColor(153, 102, 0); // Brown for interest
        } else {
            $pdf->SetTextColor(0, 0, 0); // Black for regular payments
        }
        
        $pdf->Cell(20, 10, $payment['id'], 1);
        $pdf->Cell(70, 10, $payment['description'], 1);
        $pdf->Cell(35, 10, number_format($payment['amount'], 2), 1, 0, 'R');
        $pdf->Cell(65, 10, $formatted_date, 1);
        $pdf->Ln();
    }

    // Totals section
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->SetTextColor(0, 0, 0); // Black
    $pdf->Cell(0, 10, 'Totals', 0, 1);
    
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(100, 10, 'Total Receipts: Rs. ' . number_format($totalReceipts, 2), 0, 1);
    $pdf->Cell(100, 10, 'Total Payments: Rs. ' . number_format($totalPayments, 2), 0, 1);
    
    // Profit/Loss with appropriate color
    if ($profitOrLoss >= 0) {
        $pdf->SetTextColor(0, 128, 0); // Green for profit
        $pdf->Cell(100, 10, 'Profit: Rs. ' . number_format($profitOrLoss, 2), 0, 1);
    } else {
        $pdf->SetTextColor(255, 0, 0); // Red for loss
        $pdf->Cell(100, 10, 'Loss: Rs. ' . number_format(abs($profitOrLoss), 2), 0, 1);
    }
    
    // Reset text color
    $pdf->SetTextColor(0, 0, 0);
    
    // Signature section
    $pdf->Ln(20);
    $pdf->Cell(50, 10, 'Date: _______________', 0, 0);
    $pdf->Cell(0, 10, 'Signature: _________________________', 0, 1, 'R');
    
    // Footer note
    $pdf->Ln(10);
    $pdf->SetFont('Arial', 'I', 8);
    $pdf->SetTextColor(128, 128, 128); // Gray
    $pdf->Cell(0, 10, 'This is a computer generated report. No signature required if printed with official seal.', 0, 1, 'C');

    $pdf->Output('D', 'income_statement_report.pdf');
    exit();
}

// Handle form submission
if (isset($_POST['generate_balance_sheet'])) {
    $start_date = $_POST['start_date'] . ' 00:00:00';
    $end_date = $_POST['end_date'] . ' 23:59:59';
    
    $paymentsData = getAllPaymentsByPeriod($start_date, $end_date);
    $receiptsData = getReceiptsByPeriod($start_date, $end_date);
    
    $totalPayments = $paymentsData['totalPayments'];
    $totalReceipts = $receiptsData['totalReceipts'];
    $profitOrLoss = calculateProfitOrLoss($totalReceipts, $totalPayments);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Statement</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.2.3/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .balance-sheet {
            max-width: 1000px;
            margin: 30px auto;
            padding: 30px;
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .organization-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ff8c00;
        }
        .organization-header h1 {
            color: #ff8c00;
            font-weight: bold;
            margin-bottom: 5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        .organization-header h3 {
            color: #333;
            margin-bottom: 5px;
        }
        .organization-header p {
            color: #0066cc;
            margin-bottom: 0;
        }
        .report-title {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        .table {
            margin-bottom: 30px;
            border-radius: 5px;
            overflow: hidden;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .table thead th {
            background-color: #f8f9fa;
            font-weight: 600;
            font-size: 18px;
        }
        .table tbody td {
            font-size: 16px;
            vertical-align: middle;
        }
        .income-header {
            background-color: #e8f5e9 !important;
            color: #2e7d32 !important;
        }
        .outcome-header {
            background-color: #ffebee !important;
            color: #c62828 !important;
        }
        .interest-row {
            background-color: #fff8e1;
        }
        .profit {
            color: #2e7d32;
            font-weight: bold;
        }
        .loss {
            color: #c62828;
            font-weight: bold;
        }
        .summary-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 0 10px rgba(0,0,0,0.05);
        }
        .btn-orange {
            background-color: #ff8c00;
            border-color: #ff8c00;
            color: white;
            font-weight: 600;
            padding: 10px 20px;
            transition: all 0.3s;
        }
        .btn-orange:hover {
            background-color: #e67e00;
            border-color: #e67e00;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .date-label {
            font-size: 18px;
            font-weight: 500;
            color: #555;
        }
        .form-control {
            font-size: 18px;
            padding: 10px 15px;
            border-radius: 5px;
        }
        .total-row {
            font-weight: bold;
            background-color: #f1f1f1;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="balance-sheet">
            <!-- Organization Header -->
            <div class="organization-header">
                <h1>SARVODAYA SHRAMADHANA SOCIETY</h1>
                <h3>Samaghi Sarvodaya Shramadhana Society</h3>
                <p>Kubaloluwa, Veyangoda | Phone: 077 690 6605 | Email: info@sarvodayabank.com</p>
            </div>
            
            <h2 class="report-title">Income Statement</h2>
            
            <!-- Date Selection Form -->
            <form method="post" action="">
                <div class="row mb-4">
                    <div class="col-md-5">
                        <label for="start_date" class="date-label">Start Date:</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                    </div>
                    <div class="col-md-5">
                        <label for="end_date" class="date-label">End Date:</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" name="generate_balance_sheet" class="btn btn-orange w-100">Generate</button>
                    </div>
                </div>
            </form>
            
            <?php if (isset($paymentsData) && isset($receiptsData)): ?>
                <!-- Export Buttons -->
                <div class="mb-4 text-center">
                    <form method="post" action="">
                        <input type="hidden" name="start_date" value="<?php echo $_POST['start_date']; ?>">
                        <input type="hidden" name="end_date" value="<?php echo $_POST['end_date']; ?>">
                        <button type="submit" name="export_pdf" class="btn btn-orange">
                            <i class="bi bi-file-earmark-pdf"></i> Export to PDF
                        </button>
                    </form>
                </div>

                <!-- Receipts Section -->
                <h3 class="income-header p-2 rounded">Income</h3>
                <table class="table">
                    <thead class="income-header">
                        <tr>
                            <th>ID</th>
                            <th>Description</th>
                            <th>Amount (Rs.)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($receiptsData['receipts'] as $receipt): ?>
                        <tr>
                            <td><?php echo $receipt['id']; ?></td>
                            <td><?php echo $receipt['receipt_type']; ?></td>
                            <td><?php echo number_format($receipt['amount'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($receipt['receipt_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Income</strong></td>
                            <td><strong><?php echo number_format($totalReceipts, 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>

                <!-- Payments Section -->
                <h3 class="outcome-header p-2 rounded">Outcome</h3>
                <table class="table">
                    <thead class="outcome-header">
                        <tr>
                            <th>ID</th>
                            <th>Description</th>
                            <th>Amount (Rs.)</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paymentsData['payments'] as $payment): ?>
                        <tr class="<?php echo ($payment['type'] == 'interest') ? 'interest-row' : ''; ?>">
                            <td><?php echo $payment['id']; ?></td>
                            <td><?php echo $payment['description']; ?></td>
                            <td><?php echo number_format($payment['amount'], 2); ?></td>
                            <td><?php echo date('Y-m-d', strtotime($payment['date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="2"><strong>Total Outcome</strong></td>
                            <td><strong><?php echo number_format($totalPayments, 2); ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                
                <!-- Summary Section -->
                <div class="summary-box">
                    <h3 class="text-center mb-4">Financial Summary</h3>
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="p-3 bg-light rounded">
                                <h5>Total Income</h5>
                                <p class="h4">Rs. <?php echo number_format($totalReceipts, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 bg-light rounded">
                                <h5>Total Outcome</h5>
                                <p class="h4">Rs. <?php echo number_format($totalPayments, 2); ?></p>
                            </div>
                        </div>
                        <div class="col-md-4 text-center">
                            <div class="p-3 bg-light rounded">
                                <h5>Net Result</h5>
                                <p class="h4 <?php echo ($profitOrLoss >= 0) ? 'profit' : 'loss'; ?>">
                                    <?php
                                    if ($profitOrLoss >= 0) {
                                        echo "Profit: Rs. " . number_format($profitOrLoss, 2);
                                    } else {
                                        echo "Loss: Rs. " . number_format(abs($profitOrLoss), 2);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
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