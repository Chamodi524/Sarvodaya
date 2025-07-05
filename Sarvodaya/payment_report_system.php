<?php
// FPDF Library - Ensure you have downloaded from https://fpdf.org/
require('fpdf/fpdf.php');

// Database Connection Class (Previous implementation remains the same)
class Database {
    private static $instance = null;
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'sarvodaya';
    public $conn;

    private function __construct() {
        $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
        
        if ($this->conn->connect_error) {
            die("Connection failed: " . $this->conn->connect_error);
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->conn;
    }
}

// Custom PDF Report Class
class PaymentReportPDF extends FPDF {
    private $data;
    private $startDate;
    private $endDate;

    public function __construct($paymentData, $startDate, $endDate) {
        parent::__construct();
        $this->data = $paymentData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    function Header() {
        // Check if this is the first page
        if ($this->PageNo() == 1) {
            // Full header for first page only
            // Organization Name - Main Header
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(255, 140, 0);
            $this->Cell(0, 8, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
            
            // Sub-organization Name
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, 'Samaghi Sarvodaya Shramadhana Society', 0, 1, 'C');
            
            // Address
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(80, 80, 80);
            $this->Cell(0, 5, 'Kubaloluwa, Veyangoda', 0, 1, 'C');
            
            // Contact Information
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, 'Phone: 077 690 6605 | Email: info@sarvodayabank.com', 0, 1, 'C');
            
            // Add a line separator
            $this->Ln(3);
            $this->SetDrawColor(255, 140, 0);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(5);
            
            // Report Title
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(255, 140, 0);
            $this->Cell(0, 8, 'Payment Analysis Report', 0, 1, 'C');
            
            // Date Range
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 6, "Report Period: $this->startDate to $this->endDate", 0, 1, 'C');
            
            // Reset line color and add space
            $this->SetDrawColor(0, 0, 0);
            $this->Ln(8);
        } else {
            // Simple header for subsequent pages
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(255, 140, 0);
            $this->Cell(0, 8, 'Payment Analysis Report - Continued', 0, 1, 'C');
            
            // Add a simple line separator
            $this->SetDrawColor(255, 140, 0);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->SetDrawColor(0, 0, 0);
            $this->Ln(10);
        }
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function GenerateReport() {
        // Total Payments Summary
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Total Payments Summary', 0, 1);
        $this->Ln(2);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 7, 'Total Transactions: ' . $this->data['totalPayments']['total_transactions'], 0, 1);
        $this->Cell(0, 7, 'Total Amount: Rs.' . number_format($this->data['totalPayments']['total_amount'], 2), 0, 1);
        $this->Cell(0, 7, 'Average Transaction: Rs.' . number_format($this->data['totalPayments']['average_amount'], 2), 0, 1);
        $this->Ln(10);

        // Payments by Type
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Payments by Type', 0, 1);
        $this->Ln(2);
        
        // Table header with clean design
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(245, 245, 245);
        $this->Cell(60, 8, 'Payment Type', 'TB', 0, 'L', true);
        $this->Cell(40, 8, 'Transactions', 'TB', 0, 'C', true);
        $this->Cell(60, 8, 'Total Amount (Rs.)', 'TB', 1, 'C', true);

        // Table data
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        foreach ($this->data['paymentTypes'] as $type) {
            $this->Cell(60, 7, $type['payment_type'], 0, 0, 'L');
            $this->Cell(40, 7, $type['count'], 0, 0, 'C');
            $this->Cell(60, 7, 'Rs.' . number_format($type['total_amount'], 2), 0, 1, 'C');
        }
        
        // Bottom border for table
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 170, $this->GetY());
        $this->Ln(15);

        // Monthly Payment Summary
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Monthly Payment Summary', 0, 1);
        $this->Ln(2);
        
        // Table header with clean design
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->SetFillColor(245, 245, 245);
        $this->Cell(40, 8, 'Year-Month', 'TB', 0, 'C', true);
        $this->Cell(40, 8, 'Transactions', 'TB', 0, 'C', true);
        $this->Cell(60, 8, 'Total Amount (Rs.)', 'TB', 1, 'C', true);

        // Table data
        $this->SetFont('Arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        foreach ($this->data['monthlySummary'] as $month) {
            $this->Cell(40, 7, $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT), 0, 0, 'C');
            $this->Cell(40, 7, $month['transaction_count'], 0, 0, 'C');
            $this->Cell(60, 7, 'Rs.' . number_format($month['total_amount'], 2), 0, 1, 'C');
        }
        
        // Bottom border for table
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 150, $this->GetY());
        $this->Ln(25);
        
        // Add date and signature spaces
        $this->AddDateAndSignatureSpace();
    }
    
    // Function to add date and signature spaces
    function AddDateAndSignatureSpace() {
        // Current date
        $currentDate = date('Y-m-d');
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        
        // Left side - Date section
        $this->Cell(95, 10, 'Date: ' . $currentDate, 0, 0);
        
        // Right side - Signature section
        $this->Cell(95, 10, 'Authorized Signature:', 0, 1);
        
        // Add space for signature
        $this->Ln(15);
        
        // Signature line on right side
        $this->Cell(95, 0, '', 0, 0);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(105, $this->GetY(), 180, $this->GetY());
        $this->Ln(10);
        
        // Name/Title below signature line
        $this->Cell(95, 10, '', 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 10, '(Manager/Authorized Personnel)', 0, 1, 'C');
        
        // Add verification text
        $this->Ln(10);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'This report was automatically generated from the Sarvodaya payment system.', 0, 1, 'C');
        $this->Cell(0, 10, 'Report generation date: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    }
}

// Payment Analysis Class
class PaymentAnalysis {
    private $conn;

    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    // Get total payments with date range
    public function getTotalPayments($startDate = null, $endDate = null) {
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(AVG(amount), 0) as average_amount
                  FROM payments
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND payment_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND payment_date <= '$endDate'";
        }
        
        $result = $this->conn->query($query);
        
        if ($result === false) {
            error_log("Query failed: " . $this->conn->error);
            return [
                'total_transactions' => 0,
                'total_amount' => 0,
                'average_amount' => 0
            ];
        }
        
        return $result->fetch_assoc();
    }

    // Get payments by type with date range
    public function getPaymentsByType($startDate = null, $endDate = null) {
        $query = "SELECT 
                    payment_type, 
                    COUNT(*) as count, 
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM payments 
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND payment_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND payment_date <= '$endDate'";
        }
        
        $query .= " GROUP BY payment_type";
        
        $result = $this->conn->query($query);
        $paymentTypes = [];
        
        if ($result === false) {
            error_log("Query failed: " . $this->conn->error);
            return $paymentTypes;
        }
        
        while ($row = $result->fetch_assoc()) {
            $paymentTypes[] = $row;
        }
        return $paymentTypes;
    }

    // Get monthly payment summary with date range
    public function getMonthlyPaymentSummary($startDate = null, $endDate = null) {
        $query = "SELECT 
                    YEAR(payment_date) as year,
                    MONTH(payment_date) as month,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM payments
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND payment_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND payment_date <= '$endDate'";
        }
        
        $query .= " GROUP BY YEAR(payment_date), MONTH(payment_date)
                    ORDER BY year, month";
        
        $result = $this->conn->query($query);
        $monthlySummary = [];
        
        if ($result === false) {
            error_log("Query failed: " . $this->conn->error);
            return $monthlySummary;
        }
        
        while ($row = $result->fetch_assoc()) {
            $monthlySummary[] = $row;
        }
        return $monthlySummary;
    }

    // Generate PDF Report with Date Range
    public function generatePDFReport($startDate = null, $endDate = null) {
        // Format date display
        $startDateDisplay = $startDate ? date('F j, Y', strtotime($startDate)) : 'Beginning';
        $endDateDisplay = $endDate ? date('F j, Y', strtotime($endDate)) : 'Present';
        
        // Collect all necessary data with date range
        $reportData = [
            'totalPayments' => $this->getTotalPayments($startDate, $endDate),
            'paymentTypes' => $this->getPaymentsByType($startDate, $endDate),
            'monthlySummary' => $this->getMonthlyPaymentSummary($startDate, $endDate)
        ];

        // Create PDF
        $pdf = new PaymentReportPDF($reportData, $startDateDisplay, $endDateDisplay);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->GenerateReport();
        
        $pdfFile = 'sarvodaya_payment_analysis_report.pdf';
        $pdf->Output('F', $pdfFile);
        return $pdfFile;
    }
}

// Handle PDF Generation with Date Range
if (isset($_GET['action']) && $_GET['action'] == 'download_pdf') {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    $analysis = new PaymentAnalysis();
    $pdfFile = $analysis->generatePDFReport($startDate, $endDate);
    
    // Force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="sarvodaya_payment_analysis_report.pdf"');
    header('Content-Length: ' . filesize($pdfFile));
    readfile($pdfFile);
    exit;
}

// Get current filter values
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;
$showFiltered = !empty($startDate) || !empty($endDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Analysis Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f4f4f4;
        }
        .dashboard {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .summary-card {
            background-color: rgba(255, 140, 0, 0.1);
            border-left: 4px solid rgb(255, 140, 0);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .filter-section {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-form label {
            font-weight: bold;
            font-size: 16px;
            color: #333;
        }
        .filter-form input[type="date"] {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            text-decoration: none;
            display: inline-block;
            transition: background-color 0.3s ease;
        }
        .btn-primary {
            background-color: rgb(255, 140, 0);
            color: white;
        }
        .btn-primary:hover {
            background-color: rgba(255, 140, 0, 0.8);
        }
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        .btn-success:hover {
            background-color: #218838;
        }
        .filter-info {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            color: #155724;
        }
        .no-filter-info {
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            color: #0c5460;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: rgba(255, 140, 0, 0.1);
            color: rgb(255, 140, 0);
            font-weight: bold;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        tbody tr:hover {
            background-color: rgba(255, 140, 0, 0.05);
        }
        h1, h2 {
            color: rgb(255, 140, 0);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background-color: rgba(255, 140, 0, 0.1);
            border: 1px solid rgba(255, 140, 0, 0.3);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: rgb(255, 140, 0);
        }
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        .section-divider {
            border-top: 2px solid rgba(255, 140, 0, 0.3);
            margin: 30px 0;
        }
        @media (max-width: 768px) {
            .filter-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-form > * {
                width: 100%;
                margin-bottom: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <h1>Payment Analysis Dashboard</h1>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h2 style="margin-top: 0;">Filter Options</h2>
            
            <form method="get" class="filter-form">
                <label for="start_date">Start Date:</label>
                <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                
                <label for="end_date">End Date:</label>
                <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                
                <button type="submit" class="btn btn-primary">Apply Filter</button>
                <a href="?" class="btn btn-secondary">Clear Filter</a>
            </form>
            
            <!-- PDF Download Form -->
            <form method="get" style="margin-top: 15px;">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                <input type="hidden" name="action" value="download_pdf">
                <button type="submit" class="btn btn-success">📥 Download PDF Report</button>
            </form>
        </div>
        
        <!-- Filter Status Information -->
        <?php if ($showFiltered): ?>
            <div class="filter-info">
                <strong>📊 Filtered Results:</strong> 
                Showing data from 
                <?php echo $startDate ? date('F j, Y', strtotime($startDate)) : 'beginning'; ?> 
                to 
                <?php echo $endDate ? date('F j, Y', strtotime($endDate)) : 'now'; ?>
            </div>
        <?php else: ?>
            <div class="no-filter-info">
                <strong>📈 All Data:</strong> Displaying all available payment records. Use the filter above to narrow down results.
            </div>
        <?php endif; ?>
        
        <?php
        $analysis = new PaymentAnalysis();
        
        // Get data based on current filters
        $totalPayments = $analysis->getTotalPayments($startDate, $endDate);
        $paymentTypes = $analysis->getPaymentsByType($startDate, $endDate);
        $monthlySummary = $analysis->getMonthlyPaymentSummary($startDate, $endDate);
        ?>
        
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($totalPayments['total_transactions']); ?></div>
                <div class="stat-label">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rs.<?php echo number_format($totalPayments['total_amount'], 2); ?></div>
                <div class="stat-label">Total Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rs.<?php echo number_format($totalPayments['average_amount'], 2); ?></div>
                <div class="stat-label">Average Transaction</div>
            </div>
        </div>
        
        <div class="section-divider"></div>
        
        <!-- Payments by Type -->
        <h2>💳 Payments by Type</h2>
        <?php if (empty($paymentTypes)): ?>
            <div class="no-filter-info">
                <strong>No data found:</strong> No payment records match the current filter criteria.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Payment Type</th>
                        <th>Transaction Count</th>
                        <th>Total Amount (Rs.)</th>
                        <th>Percentage of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($paymentTypes as $type): ?>
                        <?php 
                        $percentage = $totalPayments['total_amount'] > 0 ? 
                            ($type['total_amount'] / $totalPayments['total_amount']) * 100 : 0;
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($type['payment_type']); ?></td>
                            <td><?php echo number_format($type['count']); ?></td>
                            <td>Rs.<?php echo number_format($type['total_amount'], 2); ?></td>
                            <td><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="section-divider"></div>
        
        <!-- Monthly Payment Summary -->
        <h2>📅 Monthly Payment Summary</h2>
        <?php if (empty($monthlySummary)): ?>
            <div class="no-filter-info">
                <strong>No data found:</strong> No payment records match the current filter criteria.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Year-Month</th>
                        <th>Transaction Count</th>
                        <th>Total Amount (Rs.)</th>
                        <th>Average per Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlySummary as $month): ?>
                        <?php 
                        $avgPerTransaction = $month['transaction_count'] > 0 ? 
                            $month['total_amount'] / $month['transaction_count'] : 0;
                        ?>
                        <tr>
                            <td><?php echo $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo number_format($month['transaction_count']); ?></td>
                            <td>Rs.<?php echo number_format($month['total_amount'], 2); ?></td>
                            <td>Rs.<?php echo number_format($avgPerTransaction, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="section-divider"></div>
        
        <!-- Footer Information -->
        <div style="text-align: center; color: #666; font-size: 12px; margin-top: 30px;">
            <p>Report generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Sarvodaya Payment Analysis System</p>
        </div>
    </div>
    
    <script>
        // Auto-submit form when dates change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            
            // Optional: Auto-submit when both dates are selected
            // Uncomment the following lines if you want automatic filtering
            /*
            function autoSubmit() {
                if (startDate.value && endDate.value) {
                    startDate.closest('form').submit();
                }
            }
            
            startDate.addEventListener('change', autoSubmit);
            endDate.addEventListener('change', autoSubmit);
            */
        });
    </script>
</body>
</html>