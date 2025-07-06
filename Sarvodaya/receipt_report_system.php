<?php
// FPDF Library - Ensure you have downloaded from https://fpdf.org/
require('fpdf/fpdf.php');

// Database Connection Class
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

// Custom PDF Report Class for Receipts
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
        $this->Cell(0, 8, 'Receipt Analysis Report', 0, 1, 'C');
        
        // Date Range
        $this->SetFont('Arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 6, "Report Period: $this->startDate to $this->endDate", 0, 1, 'C');
        
        // Reset line color and add space
        $this->SetDrawColor(0, 0, 0);
        $this->Ln(8);
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
        $this->Cell(0, 7, 'Total Amount: Rs.' . number_format($this->data['totalReceipts']['total_amount'], 2), 0, 1);
        $this->Cell(0, 7, 'Average Transaction: Rs.' . number_format($this->data['totalReceipts']['average_amount'], 2), 0, 1);
        $this->Ln(10);

        // Receipts by Type - Properly formatted table
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Receipts by Type', 'B', 1);
        $this->Ln(2);
        
        // Set table properties
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(0.3);
        
        // Header row with borders
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(255, 140, 0);
        $this->SetTextColor(255, 255, 255);
        
        // Calculate column widths (total width = 190mm for A4)
        $col1_width = 70;  // Receipt Type
        $col2_width = 40;  // Transactions
        $col3_width = 50;  // Amount
        $col4_width = 30;  // Percentage
        
        $this->Cell($col1_width, 8, 'Receipt Type', 1, 0, 'C', true);
        $this->Cell($col2_width, 8, 'Transactions', 1, 0, 'C', true);
        $this->Cell($col3_width, 8, 'Amount (Rs.)', 1, 0, 'C', true);
        $this->Cell($col4_width, 8, 'Percentage', 1, 1, 'C', true);

        // Data rows with borders
        $this->SetFont('Arial', '', 9);
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0, 0, 0);
        
        $fill = false;
        foreach ($this->data['receiptTypes'] as $type) {
            $percentage = $this->data['totalReceipts']['total_amount'] > 0 ? 
                ($type['total_amount'] / $this->data['totalReceipts']['total_amount']) * 100 : 0;
            
            $this->Cell($col1_width, 7, $type['receipt_type'], 1, 0, 'L', $fill);
            $this->Cell($col2_width, 7, number_format($type['count']), 1, 0, 'C', $fill);
            $this->Cell($col3_width, 7, number_format($type['total_amount'], 2), 1, 0, 'R', $fill);
            $this->Cell($col4_width, 7, number_format($percentage, 1) . '%', 1, 1, 'C', $fill);
            
            $fill = !$fill; // Alternate row colors
        }
        
        $this->Ln(10);

        // Monthly Receipt Summary - Properly formatted table
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(255, 140, 0);
        $this->Cell(0, 10, 'Monthly Receipt Summary', 'B', 1);
        $this->Ln(2);
        
        // Header row with borders
        $this->SetFont('Arial', 'B', 10);
        $this->SetFillColor(255, 140, 0);
        $this->SetTextColor(255, 255, 255);
        
        // Calculate column widths for monthly summary
        $month_col1 = 40;  // Year-Month
        $month_col2 = 40;  // Transactions
        $month_col3 = 50;  // Amount
        $month_col4 = 45;  // Average
        
        $this->Cell($month_col1, 8, 'Year-Month', 1, 0, 'C', true);
        $this->Cell($month_col2, 8, 'Transactions', 1, 0, 'C', true);
        $this->Cell($month_col3, 8, 'Amount (Rs.)', 1, 0, 'C', true);
        $this->Cell($month_col4, 8, 'Average (Rs.)', 1, 1, 'C', true);

        // Data rows with borders
        $this->SetFont('Arial', '', 9);
        $this->SetFillColor(245, 245, 245);
        $this->SetTextColor(0, 0, 0);
        
        $fill = false;
        foreach ($this->data['monthlySummary'] as $month) {
            $avgPerTransaction = $month['transaction_count'] > 0 ? 
                $month['total_amount'] / $month['transaction_count'] : 0;
            
            $yearMonth = $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT);
            
            $this->Cell($month_col1, 7, $yearMonth, 1, 0, 'C', $fill);
            $this->Cell($month_col2, 7, number_format($month['transaction_count']), 1, 0, 'C', $fill);
            $this->Cell($month_col3, 7, number_format($month['total_amount'], 2), 1, 0, 'R', $fill);
            $this->Cell($month_col4, 7, number_format($avgPerTransaction, 2), 1, 1, 'R', $fill);
            
            $fill = !$fill; // Alternate row colors
        }
        
        $this->Ln(15);
        
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
        $this->Cell(95, 0, '', 'B', 1);
        
        // Name/Title below signature line
        $this->Ln(5);
        $this->Cell(95, 10, '', 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->Cell(95, 10, '(Manager/Authorized Personnel)', 0, 1, 'C');
        
        // Add verification text
        $this->Ln(10);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 10, 'This report was automatically generated from the Sarvodaya receipt system.', 0, 1, 'C');
        $this->Cell(0, 10, 'Report generation date: ' . date('Y-m-d H:i:s'), 0, 1, 'C');
    }
}

// Receipt Analysis Class
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
        // Format date display
        $startDateDisplay = $startDate ? date('F j, Y', strtotime($startDate)) : 'Beginning';
        $endDateDisplay = $endDate ? date('F j, Y', strtotime($endDate)) : 'Present';
        
        // Collect all necessary data with date range
        $reportData = [
            'totalReceipts' => $this->getTotalReceipts($startDate, $endDate),
            'receiptTypes' => $this->getReceiptsByType($startDate, $endDate),
            'monthlySummary' => $this->getMonthlyReceiptsSummary($startDate, $endDate)
        ];

        // Create PDF
        $pdf = new ReceiptReportPDF($reportData, $startDateDisplay, $endDateDisplay);
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->GenerateReport();
        
        $pdfFile = 'sarvodaya_receipt_analysis_report.pdf';
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
    header('Content-Disposition: attachment; filename="sarvodaya_receipt_analysis_report.pdf"');
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
    <title>Receipt Analysis Dashboard</title>
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
        <h1>Receipt Analysis Dashboard</h1>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <h2 style="margin-top: 0;">Filter Options</h2>
            
            <form method="get" class="filter-form">
                <label for="start_date" style="font-size: 20px;">Start Date:</label>
                <input type="date" style="font-size: 20px;" name="start_date" id="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                
                <label for="end_date" style="font-size: 20px;">End Date:</label>
                <input type="date" style="font-size: 20px;" name="end_date" id="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                
                <button type="submit" class="btn btn-primary" style="font-size: 20px;">Apply Filter</button>
                <a href="?" class="btn btn-secondary" style="font-size: 20px;">Clear Filter</a>
            </form>
            
            <!-- PDF Download Form -->
            <form method="get" style="margin-top: 15px;">
                <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($startDate ?? ''); ?>">
                <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($endDate ?? ''); ?>">
                <input type="hidden" name="action" value="download_pdf">
                <button type="submit" class="btn btn-success" style="font-size: 20px;">📋 Download PDF Report</button>
            </form>
        </div>
        
        <!-- Filter Status Information -->
        <?php if ($showFiltered): ?>
            <div class="filter-info" style="font-size: 20px;">
                <strong>📊 Filtered Results:</strong> 
                Showing receipt data from 
                <?php echo $startDate ? date('F j, Y', strtotime($startDate)) : 'beginning'; ?> 
                to 
                <?php echo $endDate ? date('F j, Y', strtotime($endDate)) : 'now'; ?>
            </div>
        <?php else: ?>
            <div class="no-filter-info" style="font-size: 20px;">
                <strong>📋 All Data:</strong> Displaying all available receipt records. Use the filter above to narrow down results.
            </div>
        <?php endif; ?>
        
        <?php
        $analysis = new ReceiptAnalysis();
        
        // Get data based on current filters
        $totalReceipts = $analysis->getTotalReceipts($startDate, $endDate);
        $receiptTypes = $analysis->getReceiptsByType($startDate, $endDate);
        $monthlySummary = $analysis->getMonthlyReceiptsSummary($startDate, $endDate);
        ?>
        
        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($totalReceipts['total_transactions']); ?></div>
                <div class="stat-label" style="font-size: 20px;">Total Transactions</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rs.<?php echo number_format($totalReceipts['total_amount'], 2); ?></div>
                <div class="stat-label" style="font-size: 20px;">Total Amount</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">Rs.<?php echo number_format($totalReceipts['average_amount'], 2); ?></div>
                <div class="stat-label" style="font-size: 20px;">Average Transaction</div>
            </div>
        </div>
        
        <div class="section-divider"></div>
        
        <!-- Receipts by Type -->
        <h2>📋 Receipts by Type</h2>
        <?php if (empty($receiptTypes)): ?>
            <div class="no-filter-info">
                <strong>No data found:</strong> No receipt records match the current filter criteria.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="font-size: 20px;">Receipt Type</th>
                        <th style="font-size: 20px;">Transaction Count</th>
                        <th style="font-size: 20px;">Total Amount (Rs.)</th>
                        <th style="font-size: 20px;">Percentage of Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($receiptTypes as $type): ?>
                        <?php 
                        $percentage = $totalReceipts['total_amount'] > 0 ? 
                            ($type['total_amount'] / $totalReceipts['total_amount']) * 100 : 0;
                        ?>
                        <tr>
                            <td style="font-size: 20px;"><?php echo htmlspecialchars($type['receipt_type']); ?></td>
                            <td style="font-size: 20px;"><?php echo number_format($type['count']); ?></td>
                            <td style="font-size: 20px;">Rs.<?php echo number_format($type['total_amount'], 2); ?></td>
                            <td style="font-size: 20px;"><?php echo number_format($percentage, 1); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="section-divider"></div>
        
        <!-- Monthly Receipt Summary -->
        <h2>📅 Monthly Receipt Summary</h2>
        <?php if (empty($monthlySummary)): ?>
            <div class="no-filter-info">
                <strong>No data found:</strong> No receipt records match the current filter criteria.
            </div>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th style="font-size: 20px;">Year-Month</th>
                        <th style="font-size: 20px;">Transaction Count</th>
                        <th style="font-size: 20px;">Total Amount (Rs.)</th>
                        <th style="font-size: 20px;">Average per Transaction</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthlySummary as $month): ?>
                        <?php 
                        $avgPerTransaction = $month['transaction_count'] > 0 ? 
                            $month['total_amount'] / $month['transaction_count'] : 0;
                        ?>
                        <tr>
                            <td style="font-size: 20px;"><?php echo $month['year'] . '-' . str_pad($month['month'], 2, '0', STR_PAD_LEFT); ?></td>
                            <td style="font-size: 20px;"><?php echo number_format($month['transaction_count']); ?></td>
                            <td style="font-size: 20px;">Rs.<?php echo number_format($month['total_amount'], 2); ?></td>
                            <td style="font-size: 20px;">Rs.<?php echo number_format($avgPerTransaction, 2); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        
        <div class="section-divider"></div>
        
        <!-- Footer Information -->
        <div style="text-align: center; color: #666; font-size: 12px; margin-top: 30px;">
            <p style="font-size: 17px;">Report generated on: <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p style="font-size: 17px;">Sarvodaya Receipt Analysis System</p>
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