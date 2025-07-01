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
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Sarvodaya Payment Analysis Report', 0, 1, 'C');
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 10, "From: $this->startDate To: $this->endDate", 0, 1, 'C');
        $this->Ln(10);
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
        $this->Cell(0, 10, 'Total Payments Summary', 'B', 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 7, 'Total Transactions: ' . $this->data['totalPayments']['total_transactions'], 0, 1);
        $this->Cell(0, 7, 'Total Amount: Rs.' . number_format($this->data['totalPayments']['total_amount'], 2), 0, 1);
        $this->Cell(0, 7, 'Average Transaction: Rs.' . number_format($this->data['totalPayments']['average_amount'], 2), 0, 1);
        $this->Ln(10);

        // Payments by Type
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Payments by Type', 'B', 1);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(60, 7, 'Payment Type', 1);
        $this->Cell(40, 7, 'Transactions', 1);
        $this->Cell(60, 7, 'Total Amount (Rs.)', 1);
        $this->Ln();

        $this->SetFont('Arial', '', 10);
        foreach ($this->data['paymentTypes'] as $type) {
            $this->Cell(60, 7, $type['payment_type'], 1);
            $this->Cell(40, 7, $type['count'], 1);
            $this->Cell(60, 7, 'Rs.' . number_format($type['total_amount'], 2), 1);
            $this->Ln();
        }
        $this->Ln(10);

        // Monthly Payment Summary
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Monthly Payment Summary', 'B', 1);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(40, 7, 'Year-Month', 1);
        $this->Cell(40, 7, 'Transactions', 1);
        $this->Cell(60, 7, 'Total Amount (Rs.)', 1);
        $this->Ln();

        $this->SetFont('Arial', '', 10);
        foreach ($this->data['monthlySummary'] as $month) {
            $this->Cell(40, 7, $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT), 1);
            $this->Cell(40, 7, $month['transaction_count'], 1);
            $this->Cell(60, 7, 'Rs.' . number_format($month['total_amount'], 2), 1);
            $this->Ln();
        }
        $this->Ln(20);
        
        // Add date and signature spaces
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, 'Date: _____________________', 0, 0, 'L');
        
        // Signature positioned further to the right
        $this->Cell(20, 7, '', 0, 0); // Add empty space to push signature further right
        $this->Cell(80, 7, 'Manager Signature: _____________________', 0, 1, 'R');
    }
    
    // New function to add date and signature spaces
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
        $this->Cell(95, 0, '', 'B', 1);
        
        // Name/Title below signature line
        $this->Ln(5);
        $this->Cell(95, 10, '', 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 10, '(Name and Title)', 0, 1, 'C');
        
        // Add verification text
        $this->Ln(10);
        $this->SetFont('Arial', 'I', 8);
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
        // Collect all necessary data with date range
        $reportData = [
            'totalPayments' => $this->getTotalPayments($startDate, $endDate),
            'paymentTypes' => $this->getPaymentsByType($startDate, $endDate),
            'monthlySummary' => $this->getMonthlyPaymentSummary($startDate, $endDate)
        ];

        // Create PDF
        $pdf = new PaymentReportPDF($reportData, $startDate ?? 'Beginning', $endDate ?? 'Now');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->GenerateReport();
        
        $pdfFile = 'payment_analysis_report.pdf';
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
    header('Content-Disposition: attachment; filename="payment_analysis_report.pdf"');
    header('Content-Length: ' . filesize($pdfFile));
    readfile($pdfFile);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payment Analysis Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
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
        .download-btn {
            display: inline-block;
            background-color: rgb(255, 140, 0);
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            transition: background-color 0.3s ease;
        }
        .download-btn:hover {
            background-color: rgba(255, 140, 0, 0.8);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: rgba(255, 140, 0, 0.1);
            color: rgb(255, 140, 0);
        }
        h1, h2 {
            color: rgb(255, 140, 0);
        }
        .date-range-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .date-range-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="dashboard">
        <h1>Payment Analysis Dashboard</h1>
        
        <form method="get" class="date-range-form">
            <label for="start_date" style="font-size: 20px;">Start Date:</label>
            <input type="date" name="start_date" id="start_date" style="font-size: 20px;">
            
            <label for="end_date" style="font-size: 20px;">End Date:</label>
            <input type="date" name="end_date" id="end_date" style="font-size: 20px;">
            
            <input type="hidden" name="action" value="download_pdf" style="font-size: 20px;">
            <button type="submit" class="download-btn" >Generate PDF Report</button>
        </form>
        
        <?php
        $analysis = new PaymentAnalysis();
        
        // If no date range is selected, show default dashboard
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        // Total Payments Summary
        $totalPayments = $analysis->getTotalPayments($startDate, $endDate);
        ?>
        
        <div class="summary-card">
            <h2>Total Payments Summary</h2>
            <p style="font-size: 20px;">Total Transactions: <?php echo $totalPayments['total_transactions']; ?></p>
            <p style="font-size: 20px;">Total Amount: Rs.<?php echo number_format($totalPayments['total_amount'], 2); ?></p>
            <p style="font-size: 20px;">Average Transaction: Rs.<?php echo number_format($totalPayments['average_amount'], 2); ?></p>
        </div>
        
        <?php
        // Payments by Type
        $paymentTypes = $analysis->getPaymentsByType($startDate, $endDate);
        ?>
        <h2>Payments by Type</h2>
        <table>
            <thead>
                <tr>
                    <th style="font-size: 20px;">Payment Type</th>
                    <th style="font-size: 20px;">Transaction Count</th>
                    <th style="font-size: 20px;">Total Amount(Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paymentTypes as $type): ?>
                <tr>
                    <td style="font-size: 20px;"><?php echo htmlspecialchars($type['payment_type']); ?></td>
                    <td style="font-size: 20px;"><?php echo $type['count']; ?></td>
                    <td style="font-size: 20px;">Rs. <?php echo number_format($type['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Monthly Payment Summary
        $monthlySummary = $analysis->getMonthlyPaymentSummary($startDate, $endDate);
        ?>
        <h2>Monthly Payment Summary</h2>
        <table>
            <thead>
                <tr>
                    <th style="font-size: 20px;">Year-Month</th>
                    <th style="font-size: 20px;">Transaction Count</th>
                    <th style="font-size: 20px;">Total Amount(Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlySummary as $month): ?>
                <tr>
                    <td style="font-size: 20px;"><?php echo $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT); ?></td>
                    <td style="font-size: 20px;"><?php echo $month['transaction_count']; ?></td>
                    <td style="font-size: 20px;">Rs.<?php echo number_format($month['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>