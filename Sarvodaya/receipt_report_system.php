<?php
// FPDF Library - Ensure you have downloaded from https://fpdf.org/
require('fpdf/fpdf.php');

// Database Connection Class remains the same
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

// Custom PDF Report Class for Receipts with adjusted signature position
class ReceiptReportPDF extends FPDF {
    private $data;
    private $startDate;
    private $endDate;

    public function __construct($receiptData, $startDate, $endDate) {
        parent::__construct();
        $this->data = $receiptData;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    function Header() {
        $this->SetFont('Arial', 'B', 15);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Sarvodaya Receipts Analysis Report', 0, 1, 'C');
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
        // Total Receipts Summary
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Total Receipts Summary', 'B', 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 7, 'Total Transactions: ' . $this->data['totalReceipts']['total_transactions'], 0, 1);
        $this->Cell(0, 7, 'Total Amount: Rs. ' . number_format($this->data['totalReceipts']['total_amount'], 2), 0, 1);
        $this->Cell(0, 7, 'Average Transaction: Rs. ' . number_format($this->data['totalReceipts']['average_amount'], 2), 0, 1);
        $this->Ln(10);

        // Receipts by Type
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Receipts by Type', 'B', 1);
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(60, 7, 'Receipt Type', 1);
        $this->Cell(40, 7, 'Transactions', 1);
        $this->Cell(60, 7, 'Total Amount (Rs.)', 1);
        $this->Ln();

        $this->SetFont('Arial', '', 10);
        foreach ($this->data['receiptTypes'] as $type) {
            $this->Cell(60, 7, $type['receipt_type'], 1);
            $this->Cell(40, 7, $type['count'], 1);
            $this->Cell(60, 7, 'Rs.' . number_format($type['total_amount'], 2), 1);
            $this->Ln();
        }
        $this->Ln(10);

        // Monthly Receipts Summary
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Monthly Receipts Summary', 'B', 1);
        
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
            $this->Cell(60, 7, 'Rs. ' . number_format($month['total_amount'], 2), 1);
            $this->Ln();
        }
        
        // Add signature and date section at the bottom with adjusted position
        $this->Ln(20);
        
        // Date on left side
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(80, 7, 'Date: _____________________', 0, 0, 'L');
        
        // Signature positioned further to the right
        $this->Cell(20, 7, '', 0, 0); // Add empty space to push signature further right
        $this->Cell(80, 7, 'Manager Signature: _____________________', 0, 1, 'R');
    }
}

// The rest of the code remains the same
class ReceiptAnalysis {
    private $conn;

    public function __construct() {
        $db = Database::getInstance();
        $this->conn = $db->getConnection();
    }

    // Get total receipts with date range
    public function getTotalReceipts($startDate = null, $endDate = null) {
        $query = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(amount), 0) as total_amount,
                    COALESCE(AVG(amount), 0) as average_amount
                  FROM receipts
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND receipt_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND receipt_date <= '$endDate'";
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

    // Get receipts by type with date range
    public function getReceiptsByType($startDate = null, $endDate = null) {
        $query = "SELECT 
                    receipt_type, 
                    COUNT(*) as count, 
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM receipts 
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND receipt_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND receipt_date <= '$endDate'";
        }
        
        $query .= " GROUP BY receipt_type";
        
        $result = $this->conn->query($query);
        $receiptTypes = [];
        
        if ($result === false) {
            error_log("Query failed: " . $this->conn->error);
            return $receiptTypes;
        }
        
        while ($row = $result->fetch_assoc()) {
            $receiptTypes[] = $row;
        }
        return $receiptTypes;
    }

    // Get monthly receipts summary with date range
    public function getMonthlyReceiptsSummary($startDate = null, $endDate = null) {
        $query = "SELECT 
                    YEAR(receipt_date) as year,
                    MONTH(receipt_date) as month,
                    COUNT(*) as transaction_count,
                    COALESCE(SUM(amount), 0) as total_amount
                  FROM receipts
                  WHERE 1=1";
        
        if ($startDate) {
            $query .= " AND receipt_date >= '$startDate'";
        }
        if ($endDate) {
            $query .= " AND receipt_date <= '$endDate'";
        }
        
        $query .= " GROUP BY YEAR(receipt_date), MONTH(receipt_date)
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
            'totalReceipts' => $this->getTotalReceipts($startDate, $endDate),
            'receiptTypes' => $this->getReceiptsByType($startDate, $endDate),
            'monthlySummary' => $this->getMonthlyReceiptsSummary($startDate, $endDate)
        ];

        // Create PDF
        $pdf = new ReceiptReportPDF($reportData, $startDate ?? 'Beginning', $endDate ?? 'Now');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->GenerateReport();
        
        $pdfFile = 'receipts_analysis_report.pdf';
        $pdf->Output('F', $pdfFile);
        return $pdfFile;
    }
}

// Handle PDF Generation with Date Range
if (isset($_GET['action']) && $_GET['action'] == 'download_pdf') {
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : null;
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : null;
    
    $analysis = new ReceiptAnalysis();
    $pdfFile = $analysis->generatePDFReport($startDate, $endDate);
    
    // Force download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="receipts_analysis_report.pdf"');
    header('Content-Length: ' . filesize($pdfFile));
    readfile($pdfFile);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipts Analysis Dashboard</title>
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
        <h1>Receipts Analysis Dashboard</h1>
        
        <form method="get" class="date-range-form">
            <label for="start_date">Start Date:</label>
            <input type="date" name="start_date" id="start_date">
            
            <label for="end_date">End Date:</label>
            <input type="date" name="end_date" id="end_date">
            
            <input type="hidden" name="action" value="download_pdf">
            <button type="submit" class="download-btn">Generate PDF Report</button>
        </form>
        
        <?php
        $analysis = new ReceiptAnalysis();
        
        // If no date range is selected, show default dashboard
        $startDate = $_GET['start_date'] ?? null;
        $endDate = $_GET['end_date'] ?? null;
        
        // Total Receipts Summary
        $totalReceipts = $analysis->getTotalReceipts($startDate, $endDate);
        ?>
        
        <div class="summary-card">
            <h2>Total Receipts Summary</h2>
            <p>Total Transactions: <?php echo $totalReceipts['total_transactions']; ?></p>
            <p>Total Amount: Rs.<?php echo number_format($totalReceipts['total_amount'], 2); ?></p>
            <p>Average Transaction: Rs.<?php echo number_format($totalReceipts['average_amount'], 2); ?></p>
        </div>
        
        <?php
        // Receipts by Type
        $receiptTypes = $analysis->getReceiptsByType($startDate, $endDate);
        ?>
        <h2>Receipts by Type</h2>
        <table>
            <thead>
                <tr>
                    <th>Receipt Type</th>
                    <th>Transaction Count</th>
                    <th>Total Amount(Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($receiptTypes as $type): ?>
                <tr>
                    <td><?php echo htmlspecialchars($type['receipt_type']); ?></td>
                    <td><?php echo $type['count']; ?></td>
                    <td>Rs.<?php echo number_format($type['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php
        // Monthly Receipts Summary
        $monthlySummary = $analysis->getMonthlyReceiptsSummary($startDate, $endDate);
        ?>
        <h2>Monthly Receipts Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Year-Month</th>
                    <th>Transaction Count</th>
                    <th>Total Amount(Rs.)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($monthlySummary as $month): ?>
                <tr>
                    <td><?php echo $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo $month['transaction_count']; ?></td>
                    <td>Rs. <?php echo number_format($month['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>