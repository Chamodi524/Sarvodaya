<?php
// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sarvodaya';

// Establish connection
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if PDF generation is requested
if (isset($_GET['generate_pdf']) && $_GET['generate_pdf'] == '1' && isset($_GET['membership_number']) && !empty($_GET['membership_number'])) {
    require('fpdf/fpdf.php');
    
    $membership_number = trim($_GET['membership_number']);
    $start_date = isset($_GET['start_date']) && !empty($_GET['start_date']) ? $_GET['start_date'] : '2000-01-01';
    $end_date = isset($_GET['end_date']) && !empty($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    
    // Get member details
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("s", $membership_number);
    $stmt->execute();
    $result = $stmt->get_result();
    $member = $result->fetch_assoc();
    
    if ($member) {
        // Get transactions
        $query = "SELECT t.*, at.account_name as account_type_name 
                  FROM savings_transactions t
                  JOIN savings_account_types at ON t.account_type_id = at.id
                  WHERE t.member_id = ? 
                  AND DATE(t.transaction_date) BETWEEN ? AND ?
                  ORDER BY t.transaction_date ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iss", $membership_number, $start_date, $end_date);
        $stmt->execute();
        $transactions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        // Calculate totals
        $total_deposits = 0;
        $total_withdrawals = 0;
        foreach ($transactions as $transaction) {
            if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST') {
                $total_deposits += $transaction['amount'];
            } else {
                $total_withdrawals += $transaction['amount'];
            }
        }
        
        $current_balance = !empty($transactions) ? end($transactions)['running_balance'] : 0;
        
        // Create PDF with custom class for styling
        class PDF extends FPDF {
            // Orange header
            function Header() {
                $this->SetFont('Arial','B',16);
                $this->SetTextColor(255, 140, 0);
                $this->Cell(0,10,'SARVODAYA SHRAMADHANA SOCIETY',0,1,'C');
                $this->SetFont('Arial','',12);
                $this->Cell(0,8,'Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda',0,1,'C');
                $this->SetFont('Arial','B',14);
                $this->SetTextColor(230, 120, 0);
                $this->Cell(0,10,'MEMBER PASSBOOK',0,1,'C');
                $this->Ln(5);
            }
            
            // Orange footer
            function Footer() {
                $this->SetY(-15);
                $this->SetFont('Arial','I',8);
                $this->SetTextColor(255, 140, 0);
                $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
            }
            
            // Colored table header
            function TableHeader() {
                $this->SetFillColor(255, 140, 0);
                $this->SetTextColor(255);
                $this->SetDrawColor(230, 120, 0);
                $this->SetLineWidth(.3);
                $this->SetFont('Arial','B',12);
            }
            
            // Light orange table row
            function TableRowLight() {
                $this->SetFillColor(255, 236, 214);
                $this->SetTextColor(0);
                $this->SetFont('Arial','',10);
            }
            
            // White table row
            function TableRowWhite() {
                $this->SetFillColor(255);
                $this->SetTextColor(0);
                $this->SetFont('Arial','',10);
            }
        }
        
        $pdf = new PDF('P','mm','A4');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        
        // Member info section with light orange background
        $pdf->SetFillColor(255, 236, 214);
        $pdf->SetDrawColor(255, 140, 0);
        $pdf->SetLineWidth(.5);
        $pdf->Rect(10, $pdf->GetY(), 190, 35, 'DF');
        
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(230, 120, 0);
        $pdf->Cell(0,8,'Member Information',0,1);
        
        $pdf->SetFont('Arial','',11);
        $pdf->SetTextColor(0);
        $pdf->Cell(95,6,'Member: ' . $member['name'],0,0);
        $pdf->Cell(95,6,'Membership Number: ' . $membership_number,0,1);
        $pdf->Cell(95,6,'NIC: ' . $member['nic'],0,0);
        $pdf->Cell(95,6,'Contact: ' . $member['phone'],0,1);
        $pdf->Cell(95,6,'Member Since: ' . date('d M Y', strtotime($member['created_at'])),0,0);
        $pdf->Cell(95,6,'Statement Period: ' . date('d M Y', strtotime($start_date)) . ' - ' . date('d M Y', strtotime($end_date)),0,1);
        $pdf->Ln(10);
        
        // Transactions table header
        $pdf->TableHeader();
        $pdf->Cell(40,10,'Date',1,0,'C',true);
        $pdf->Cell(30,10,'Type',1,0,'C',true);
        $pdf->Cell(40,10,'Deposit(Rs.)',1,0,'C',true);
        $pdf->Cell(40,10,'Withdrawal(Rs.)',1,0,'C',true);
        $pdf->Cell(40,10,'Balance(Rs.)',1,1,'C',true);
        
        // Transactions rows with alternating colors
        $fill = false;
        foreach ($transactions as $transaction) {
            $fill ? $pdf->TableRowLight() : $pdf->TableRowWhite();
            $fill = !$fill;
            
            $pdf->Cell(40,8,date('Y-m-d H:i', strtotime($transaction['transaction_date'])),1,0,'L',true);
            $pdf->Cell(30,8,$transaction['transaction_type'],1,0,'C',true);
            
            if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST') {
                $pdf->SetTextColor(40, 167, 69); // Green for deposits
                $pdf->Cell(40,8,number_format($transaction['amount'],2),1,0,'R',true);
                $pdf->Cell(40,8,'',1,0,'R',true);
            } else {
                $pdf->Cell(40,8,'',1,0,'R',true);
                $pdf->SetTextColor(220, 53, 69); // Red for withdrawals
                $pdf->Cell(40,8,number_format($transaction['amount'],2),1,0,'R',true);
            }
            
            $pdf->SetTextColor(0);
            $pdf->Cell(40,8,number_format($transaction['running_balance'],2),1,1,'R',true);
        }
        
        // Summary section with light orange background
        $pdf->Ln(5);
        $pdf->SetFillColor(255, 236, 214);
        $pdf->SetDrawColor(255, 140, 0);
        $pdf->SetLineWidth(.5);
        $pdf->Rect(10, $pdf->GetY(), 190, 30, 'DF');
        
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(230, 120, 0);
        $pdf->Cell(0,8,'Transaction Summary',0,1);
        
        $pdf->SetFont('Arial','',11);
        $pdf->SetTextColor(0);
        $pdf->Cell(95,6,'Total Deposits: Rs. ' . number_format($total_deposits,2),0,0);
        $pdf->Cell(95,6,'Total Withdrawals: Rs. ' . number_format($total_withdrawals,2),0,1);
        $pdf->Cell(95,6,'Current Balance: Rs. ' . number_format($current_balance,2),0,0);
        $pdf->Cell(95,6,'Generated on: ' . date('Y-m-d H:i:s'),0,1);
        
        // Manager signature section
        $pdf->Ln(15);
        $pdf->SetFont('Arial','',11);
        $pdf->Cell(0,6,'Certified Correct:',0,1);
        $pdf->Ln(15);
        
        $pdf->SetDrawColor(150);
        $pdf->Line($pdf->GetX()+30, $pdf->GetY(), $pdf->GetX()+80, $pdf->GetY());
        $pdf->Line($pdf->GetX()+110, $pdf->GetY(), $pdf->GetX()+160, $pdf->GetY());
        
        $pdf->SetFont('Arial','I',10);
        $pdf->Cell(80,6,'Manager Signature',0,0,'C');
        $pdf->Cell(30,6,'',0,0);
        $pdf->Cell(80,6,'Date',0,1,'C');
        
        $pdf->Output('I', 'Passbook_' . $membership_number . '.pdf');
        exit;
    }
}

// Rest of your existing code for the web view
$membership_number = '';
$member_id = 0;

if (isset($_POST['membership_number']) && !empty($_POST['membership_number'])) {
    $membership_number = trim($_POST['membership_number']);
} elseif (isset($_GET['membership_number']) && !empty($_GET['membership_number'])) {
    $membership_number = trim($_GET['membership_number']);
}

// If membership number is provided, find the member_id
if (!empty($membership_number)) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE id = ?");
    $stmt->bind_param("s", $membership_number);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $member_id = $row['id'];
    }
}

// Default date range (all history to current date)
$default_start_date = '2000-01-01'; // Historical starting point
$default_end_date = date('Y-m-d'); // Current date

// Get date range from form if submitted
$start_date = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : $default_start_date;
$end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : $default_end_date;

// Function to fetch member name
function getMemberName($conn, $member_id) {
    if ($member_id <= 0) {
        return "No Member Selected";
    }
    
    $stmt = $conn->prepare("SELECT name FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return "Unknown Member";
}

// Function to get member join date
function getMemberJoinDate($conn, $member_id) {
    if ($member_id <= 0) {
        return "2000-01-01"; // Default historical date
    }
    
    $stmt = $conn->prepare("SELECT created_at FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return date('Y-m-d', strtotime($row['created_at']));
    }
    return "2000-01-01"; // Default historical date
}

$member_name = getMemberName($conn, $member_id);

// If member is found, use their join date as start date if we're using the default historical view
if ($member_id > 0 && $start_date == $default_start_date) {
    $member_join_date = getMemberJoinDate($conn, $member_id);
    $start_date = $member_join_date;
}

// Get transactions for the member and date range
function getTransactions($conn, $member_id, $start_date, $end_date) {
    if ($member_id <= 0) {
        return [];
    }
    
    $query = "SELECT t.*, at.account_name as account_type_name 
              FROM savings_transactions t
              JOIN savings_account_types at ON t.account_type_id = at.id
              WHERE t.member_id = ? 
              AND DATE(t.transaction_date) BETWEEN ? AND ?
              ORDER BY t.transaction_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $member_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

$transactions = getTransactions($conn, $member_id, $start_date, $end_date);

// Get member details
$member_details = [];
if ($member_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $member_details = $row;
    }
}

// Get account types for filtering
$account_types = [];
if ($member_id > 0) {
    $query = "SELECT DISTINCT at.id, at.account_name 
            FROM savings_account_types at
            JOIN savings_transactions t ON at.id = t.account_type_id
            WHERE t.member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $account_types[$row['id']] = $row['account_name'];
    }
}

// Calculate totals
$total_deposits = 0;
$total_withdrawals = 0;
foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST') {
        $total_deposits += $transaction['amount'];
    } else {
        $total_withdrawals += $transaction['amount'];
    }
}

// Get current balance (latest running balance)
$current_balance = !empty($transactions) ? end($transactions)['running_balance'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Passbook - Sarvodaya Shramadhana Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --orange-primary: rgb(255, 140, 0);
            --orange-light: rgba(255, 140, 0, 0.15);
            --orange-medium: rgba(255, 140, 0, 0.5);
            --orange-dark: rgb(230, 120, 0);
            --text-on-orange: #fff;
        }
        
        body { 
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* Header Styles */
        .page-header {
            background: linear-gradient(135deg, var(--orange-primary), var(--orange-dark));
            color: white;
            padding: 20px 0;
            margin: -20px -20px 30px -20px;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .organization-name {
            font-size: 2.2rem;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }

        .organization-subtitle {
            font-size: 1.1rem;
            text-align: center;
            margin-bottom: 15px;
            opacity: 0.95;
        }

        .contact-info {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 20px;
            font-size: 0.9rem;
            margin-bottom: 15px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .header-divider {
            width: 100%;
            height: 2px;
            background: rgba(255,255,255,0.3);
            margin: 15px 0 10px 0;
        }

        .page-title {
            text-align: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0;
        }
        
        .btn-primary {
            background-color: var(--orange-primary);
            border-color: var(--orange-dark);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--orange-dark);
            border-color: var(--orange-dark);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #5c636a;
        }
        
        .passbook-header { 
            background-color: var(--orange-light);
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            border-left: 5px solid var(--orange-primary);
        }
        
        .table-light {
            background-color: var(--orange-light);
        }
        
        .table>thead {
            background-color: var(--orange-primary);
            color: var(--text-on-orange);
        }
        
        .transaction-row:nth-child(even) { 
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .transaction-row:hover { 
            background-color: var(--orange-light); 
        }
        
        .deposit { 
            color: #28a745; 
        }
        
        .withdrawal { 
            color: #dc3545; 
        }
        
        .summary-box { 
            background-color: var(--orange-light);
            padding: 20px; 
            border-radius: 8px; 
            margin-top: 20px;
            border-left: 5px solid var(--orange-primary);
        }
        
        .card {
            border-left: 5px solid var(--orange-primary);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        
        h1, h4, h5 {
            color: var(--orange-dark);
        }
        
        .alert-info {
            background-color: var(--orange-light);
            border-color: var(--orange-primary);
            color: #664d03;
        }
        
        .alert-danger {
            border-left: 5px solid #dc3545;
        }
        
        .form-control:focus {
            border-color: var(--orange-primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 140, 0, 0.25);
        }
        
        /* Custom pagination styles */
        .pagination .page-item.active .page-link {
            background-color: var(--orange-primary);
            border-color: var(--orange-primary);
        }
        
        .pagination .page-link {
            color: var(--orange-primary);
        }
        
        .pagination .page-link:hover {
            color: var(--orange-dark);
        }
        
        /* Custom table styles */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .table-bordered {
            border: none;
        }
        
        @media print {
            body { 
                padding: 0; 
                font-size: 12pt; 
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .print-visible { 
                display: block !important;
                visibility: visible !important;
            }

            .page-header {
                background: white !important;
                color: black !important;
                margin: 0 0 20px 0;
                padding: 15px 0;
                border-bottom: 3px solid var(--orange-primary);
                box-shadow: none;
                border-radius: 0;
            }

            .organization-name {
                color: var(--orange-primary) !important;
                font-size: 1.8rem !important;
                text-shadow: none !important;
            }

            .organization-subtitle {
                color: #666 !important;
                font-size: 1rem !important;
            }

            .contact-info {
                color: #666 !important;
                font-size: 0.8rem !important;
            }

            .page-title {
                color: var(--orange-primary) !important;
                font-size: 1.3rem !important;
            }

            .header-divider {
                background: var(--orange-primary) !important;
                opacity: 0.5;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .passbook-header {
                background-color: transparent !important;
                border-left: 2px solid var(--orange-primary);
                padding: 10px;
                margin-bottom: 15px;
                box-shadow: none;
            }
            
            .table {
                width: 100% !important;
                max-width: 100% !important;
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
                font-size: 10pt;
                page-break-inside: auto;
            }
            
            .table>thead {
                background-color: #f0f0f0 !important;
                color: black !important;
                border-bottom: 2px solid var(--orange-primary);
            }
            
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
            
            .summary-box {
                background-color: transparent !important;
                border-left: 2px solid var(--orange-primary);
                padding: 10px;
                margin-top: 15px;
                box-shadow: none;
            }
            
            .transaction-row:nth-child(even) {
                background-color: #f9f9f9 !important;
            }
            
            .deposit { 
                color: #28a745 !important; 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .withdrawal { 
                color: #dc3545 !important; 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            h1, h4, h5 {
                color: black !important;
            }
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="organization-name" >SARVODAYA SHRAMADHANA SOCIETY</div>
            <div class="organization-subtitle" style="font-size: 20px;">Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda</div>
            
            <div class="contact-info">
                <div class="contact-item" style="font-size: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.568 17.568 0 0 0 4.168 6.608 17.569 17.569 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.678.678 0 0 0-.58-.122L9.98 10.07a6.76 6.76 0 0 1-3.05-3.05l.639-1.804a.678.678 0 0 0-.122-.58L5.653 2.328z"/>
                    </svg>
                    077 690 6605
                </div>
                <div class="contact-item" style="font-size: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4Zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2Zm13 2.383-4.708 2.825L15 11.105V5.383Zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741ZM1 11.105l4.708-2.897L1 5.383v5.722Z"/>
                    </svg>
                    info@sarvodayabank.com
                </div>
                <div class="contact-item" style="font-size: 20px;">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                        <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A31.493 31.493 0 0 1 8 14.58a31.481 31.481 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94zM8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10z"/>
                        <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4zm0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6z"/>
                    </svg>
                    Reg. No: 12345/SS/2020
                </div>
            </div>
            
            <div class="header-divider"></div>
            <div class="page-title">MEMBER PASSBOOK</div>
        </div>
    </div>

    <div class="container">
        <div class="row no-print">
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-4">
                                <label for="membership_number" class="form-label" style="font-size: 20px;">Membership Number</label>
                                <input type="text" class="form-control" id="membership_number" style="font-size: 20px;" name="membership_number" 
                                       value="<?php echo htmlspecialchars($membership_number); ?>" placeholder="Enter membership number" required>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label" style="font-size: 20px;">From Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" style="font-size: 20px;" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label" style="font-size: 20px;">To Date</label>
                                <input type="date" class="form-control" id="end_date" style="font-size: 20px;" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" style="font-size: 20px;">View Transactions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($member_id > 0): ?>
        <div class="row">
            <div class="col-12">
                <div class="passbook-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Member: <?php echo htmlspecialchars($member_name); ?></h4>
                            <p style="font-size: 20px;"><strong>Membership Number:</strong> <?php echo $member_id; ?></p>
                            <?php if (!empty($member_details)): ?>
                            <p style="font-size: 20px;"><strong>NIC:</strong> <?php echo htmlspecialchars($member_details['nic']); ?></p>
                            <p style="font-size: 20px;"><strong>Contact:</strong> <?php echo htmlspecialchars($member_details['phone']); ?></p>
                            <p style="font-size: 20px;"><strong>Member Since:</strong> <?php echo date('d M Y', strtotime($member_details['created_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h3>Statement Period</h3>
                            <p style="font-size: 20px;"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
                            <?php if (!empty($transactions)): ?>
                            <div class="mt-3 no-print">
                                <a href="?generate_pdf=1&membership_number=<?php echo $member_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-danger" style="font-size: 20px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-pdf me-1" viewBox="0 0 16 16">
                                        <path d="M14 14V4.5L9.5 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2zM9.5 3A1.5 1.5 0 0 0 11 4.5h2V14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1h5.5v2z"/>
                                        <path d="M4.603 14.087a.81.81 0 0 1-.438-.42c-.195-.388-.13-.776.08-1.102.198-.307.526-.568.897-.787a7.68 7.68 0 0 1 1.482-.645 19.697 19.697 0 0 0 1.062-2.227 7.269 7.269 0 0 1-.43-1.295c-.086-.4-.119-.796-.046-1.136.075-.354.274-.672.65-.823.192-.077.4-.12.602-.077a.7.7 0 0 1 .477.365c.088.164.12.356.127.538.007.188-.012.396-.047.614-.084.51-.27 1.134-.52 1.794a10.954 10.954 0 0 0 .98 1.686 5.753 5.753 0 0 1 1.334.05c.364.066.734.195.96.465.12.144.193.32.2.518.007.192-.047.382-.138.563a1.04 1.04 0 0 1-.354.416.856.856 0 0 1-.51.138c-.331-.014-.654-.196-.933-.417a5.712 5.712 0 0 1-.911-.95 11.651 11.651 0 0 0-1.997.406 11.307 11.307 0 0 1-1.02 1.51c-.292.35-.609.656-.927.787a.793.793 0 0 1-.58.029zm1.379-1.901c-.166.076-.32.156-.459.238-.328.194-.541.383-.647.547-.094.145-.096.25-.04.361.01.022.02.036.026.044a.266.266 0 0 0 .035-.012c.137-.056.355-.235.635-.572a8.18 8.18 0 0 0 .45-.606zm1.64-1.33a12.71 12.71 0 0 1 1.01-.193 11.744 11.744 0 0 1-.51-.858 20.801 20.801 0 0 1-.5 1.05zm2.446.45c.15.163.296.3.435.41.24.19.407.253.498.256a.107.107 0 0 0 .07-.015.307.307 0 0 0 .094-.125.436.436 0 0 0 .059-.2.095.095 0 0 0-.026-.063c-.052-.062-.2-.152-.518-.242a8.136 8.136 0 0 0-1.102-.283 7.647 7.647 0 0 1-.585.193zM8 12.022a7.771 7.771 0 0 0 .19-.015c.327-.042.642-.126.93-.242.19-.076.36-.182.493-.314a1.8 1.8 0 0 0 .165-.203c.028-.038.053-.077.074-.118.024-.043.047-.092.058-.145.013-.056.014-.113.002-.171a1.023 1.023 0 0 0-.063-.165.91.91 0 0 0-.157-.221 1.482 1.482 0 0 0-.393-.3 3.639 3.639 0 0 0-.554-.243 8.976 8.976 0 0 0-.87-.151 7.726 7.726 0 0 0-.539-.006 2.31 2.31 0 0 0-.497.038 2.668 2.668 0 0 0-.45.117 1.933 1.933 0 0 0-.398.208 1.27 1.27 0 0 0-.257.246 1.113 1.113 0 0 0-.129.239.94.94 0 0 0-.039.289c.003.062.01.118.022.17.015.062.04.12.07.171.03.051.065.095.102.131.038.036.08.064.124.085.05.025.104.04.16.046a.937.937 0 0 0 .187.013 3.89 3.89 0 0 0 .57-.069 6.405 6.405 0 0 0 .68-.176 6.833 6.833 0 0 0 .618-.299z"/>
                                    </svg>
                                    Download PDF
                                </a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info">No transactions found for the selected period.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="font-size: 20px;" style="width: 20%;">Date</th>
                                    <th style="font-size: 20px;" style="width: 20%;">Type</th>
                                    <th style="font-size: 20px;" style="width: 20%;" class="text-end">Deposit(Rs.)</th>
                                    <th style="font-size: 20px;" style="width: 20%;" class="text-end">Withdrawal(RS.)</th>
                                    <th style="font-size: 20px;" style="width: 20%;" class="text-end">Balance(Rs.)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr class="transaction-row">
                                        <td style="font-size: 20px;"><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></td>
                                        <td style="font-size: 20px;"><?php echo $transaction['transaction_type']; ?></td>
                                        <td style="font-size: 20px;" class="text-end deposit">
                                            <?php if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST'): ?>
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 20px;" class="text-end withdrawal">
                                            <?php if ($transaction['transaction_type'] == 'WITHDRAWAL' || $transaction['transaction_type'] == 'ADJUSTMENT' || $transaction['transaction_type'] == 'FEE'): ?>
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size: 20px;" class="text-end"><?php echo number_format($transaction['running_balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="summary-box">
                        <div class="row">
                            <div class="col-md-4">
                                <h3>Total Deposits(Rs.)</h3>
                                <p class="deposit fw-bold fs-4"><?php echo number_format($total_deposits, 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h3>Total Withdrawals(Rs.)</h3>
                                <p class="withdrawal fw-bold fs-4"><?php echo number_format($total_withdrawals, 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h3>Current Balance(Rs.)</h3>
                                <p class="fw-bold fs-4"><?php echo number_format($current_balance, 2); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif (!empty($membership_number)): ?>
            <div class="alert alert-danger">No member found with membership number: <?php echo htmlspecialchars($membership_number); ?></div>
        <?php else: ?>
            <div class="alert alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                </svg>
                Please enter a membership number to view the passbook.
            </div>
        <?php endif; ?>
        
        <footer class="mt-5 pt-3 border-top text-muted text-center no-print">
            <p>Sarvodaya Member Services &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>