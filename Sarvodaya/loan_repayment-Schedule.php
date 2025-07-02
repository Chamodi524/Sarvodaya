<?php
// Database connection parameters
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sarvodaya";

// Variable to store loan types
$loanTypes = [];
$members = [];
$error = '';
$success = '';

// Only fetch data when page is loaded, not during AJAX requests
if (!isset($_GET['fetch'])) {
    // Create connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    // Check connection
    if ($conn->connect_error) {
        $error = "Database connection failed: " . $conn->connect_error;
    } else {
        // Query to get loan types
        $sql = "SELECT id, loan_name, maximum_amount, interest_rate, max_period, description FROM loan_types";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                // Convert to appropriate types
                $row['id'] = (int)$row['id'];
                $row['maximum_amount'] = (float)$row['maximum_amount'];
                $row['interest_rate'] = (float)$row['interest_rate'];
                $row['max_period'] = (int)$row['max_period'];
                
                $loanTypes[] = $row;
            }
        } else {
            $error = "No loan types found in database.";
        }
        
        // Query to get members for dropdown
        $sql = "SELECT id, name, nic FROM members ORDER BY name";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $members[] = $row;
            }
        }
        
        $conn->close();
    }
}

// Handle PDF generation request
if (isset($_GET['generate_pdf'])) {
    require('fpdf/fpdf.php');
    
    // Get data from POST
    $summaryData = json_decode($_POST['summaryData'], true);
    $scheduleData = json_decode($_POST['scheduleData'], true);
    
    // Create PDF with custom header and footer
    class PDF extends FPDF {
        // Page header
        function Header() {
            // Logo
            $this->Image('Sarwodaya logo.jpg', 10, 8, 25);
            
            // Set font
            $this->SetFont('Arial', 'B', 16);
            
            // Move to the right
            $this->Cell(30);
            
            // Title (orange color)
            $this->SetTextColor(255, 140, 0);
            $this->Cell(0, 10, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
            
            // Subtitle
            $this->SetFont('Arial', 'B', 12);
            $this->Cell(0, 8, 'Samaghi Sarvodaya Shramadhana Society', 0, 1, 'C');
            
            // Location
            $this->SetFont('Arial', 'I', 10);
            $this->Cell(0, 6, 'Kubaloluwa, Veyangoda', 0, 1, 'C');
            
            // Contact info
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 6, 'Phone: 077 690 6605 | Email: info@sarvodayabank.com', 0, 1, 'C');
            
            // Report title
            $this->SetFont('Arial', 'B', 14);
            $this->SetTextColor(0, 0, 0); // Black color
            $this->Ln(5);
            $this->Cell(0, 10, 'Loan Repayment Schedule', 0, 1, 'C');
            $this->Ln(5);
            
            // Line break
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(5);
        }
        
        // Page footer
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-25);
            
            // Line
            $this->Line(10, $this->GetY(), 200, $this->GetY());
            $this->Ln(3);
            
            // Date
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 6, 'Generated on: ' . date('Y-m-d H:i:s'), 0, 0, 'L');
            
            // Page number
            $this->Cell(0, 6, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
            $this->Ln(8);
            
            // Signature space
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(80, 6, 'Manager Signature: ________________________', 0, 1, 'L');
            $this->Cell(80, 6, 'Date: ________________________', 0, 0, 'L');
        }
    }
    
    // Create PDF instance
    $pdf = new PDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Summary section
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(0, 8, 'Loan Summary - ' . $summaryData['loanName'], 0, 1);
    $pdf->SetFont('Arial', '', 10);
    
    $pdf->Cell(60, 6, 'Member:', 0, 0);
    $pdf->Cell(0, 6, 'ID: ' . $summaryData['memberId'] . ' - ' . $summaryData['memberName'], 0, 1);
    
    $pdf->Cell(60, 6, 'Loan ID:', 0, 0);
    $pdf->Cell(0, 6, $summaryData['loanId'], 0, 1);
    
    $pdf->Cell(60, 6, 'Loan Amount:', 0, 0);
    $pdf->Cell(0, 6, $summaryData['loanAmount'], 0, 1);
    
    $pdf->Cell(60, 6, 'Monthly Payment:', 0, 0);
    $pdf->Cell(0, 6, $summaryData['monthlyPayment'], 0, 1);
    
    $pdf->Cell(60, 6, 'Total Interest:', 0, 0);
    $pdf->Cell(0, 6, $summaryData['totalInterest'], 0, 1);
    
    $pdf->Cell(60, 6, 'Total Amount to Repay:', 0, 0);
    $pdf->Cell(0, 6, $summaryData['totalPayment'], 0, 1);
    
    $pdf->Ln(10);
    
    // Schedule table header
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->SetFillColor(255, 235, 210); // Light orange background
    $pdf->Cell(15, 8, '#', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Date', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Payment', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Principal', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Interest', 1, 0, 'C', true);
    $pdf->Cell(30, 8, 'Balance', 1, 1, 'C', true);
    
    // Schedule data
    $pdf->SetFont('Arial', '', 8);
    $fill = false;
    foreach ($scheduleData as $payment) {
        $pdf->Cell(15, 6, $payment['paymentNumber'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $payment['paymentDate'], 1, 0, 'C', $fill);
        $pdf->Cell(30, 6, $payment['paymentAmount'], 1, 0, 'R', $fill);
        $pdf->Cell(30, 6, $payment['principal'], 1, 0, 'R', $fill);
        $pdf->Cell(30, 6, $payment['interest'], 1, 0, 'R', $fill);
        $pdf->Cell(30, 6, $payment['remainingBalance'], 1, 1, 'R', $fill);
        $fill = !$fill;
    }
    
    // Output PDF
    $pdf->Output('D', 'Loan_Repayment_Schedule_'.date('Ymd_His').'.pdf');
    exit;
}

// Function to save loan installments to database
function saveInstallmentsToDatabase($loanId, $schedule, $conn) {
    // First check if loan exists and get member_id
    $checkSql = "SELECT id, member_id FROM loans WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("i", $loanId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        return "Loan ID not found in database";
    }
    
    $loanData = $checkResult->fetch_assoc();
    $memberId = $loanData['member_id'];
    
    $checkStmt->close();
    
    // Check if installments for this loan already exist
    $checkInstallmentsSql = "SELECT COUNT(*) as count FROM loan_installments WHERE loan_id = ?";
    $checkInstallmentsStmt = $conn->prepare($checkInstallmentsSql);
    $checkInstallmentsStmt->bind_param("i", $loanId);
    $checkInstallmentsStmt->execute();
    $result = $checkInstallmentsStmt->get_result();
    $row = $result->fetch_assoc();
    
    if ($row['count'] > 0) {
        // Delete existing installments
        $deleteSql = "DELETE FROM loan_installments WHERE loan_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $loanId);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    
    $checkInstallmentsStmt->close();
    
    // Now insert new installments
    $stmt = $conn->prepare("INSERT INTO loan_installments 
        (loan_id, member_id, installment_number, payment_date, payment_amount, principal_amount, interest_amount, remaining_balance, payment_status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    
    if (!$stmt) {
        return "Error preparing statement: " . $conn->error;
    }
    
    $conn->begin_transaction();
    
    try {
        foreach ($schedule as $payment) {
            $installmentNumber = $payment['paymentNumber'];
            $paymentDate = $payment['paymentDate'];
            $paymentAmount = $payment['paymentAmount'];
            $principalAmount = $payment['principal'];
            $interestAmount = $payment['interest'];
            $remainingBalance = $payment['remainingBalance'];
            
            $stmt = $conn->prepare("INSERT INTO loan_installments 
                (loan_id, member_id, installment_number, payment_date, payment_amount, principal_amount, interest_amount, remaining_balance, payment_status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                
            $stmt->bind_param(
                "iiisdddd", 
                $loanId,
                $memberId,
                $installmentNumber,
                $paymentDate,
                $paymentAmount,
                $principalAmount,
                $interestAmount,
                $remainingBalance
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Error executing statement: " . $stmt->error);
            }
            
            $stmt->close();
        }
        
        $conn->commit();
        return true;
    } catch (Exception $e) {
        $conn->rollback();
        return $e->getMessage();
    }
}

// Function to get member info
function getMemberInfo($memberId, $conn) {
    $stmt = $conn->prepare("SELECT id, name, nic FROM members WHERE id = ?");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return null;
    }
    
    return $result->fetch_assoc();
}

// Function to get active loans for a member
function getActiveLoansByMember($memberId, $conn) {
    $stmt = $conn->prepare("SELECT id, loan_type_id, amount, interest_rate, max_period, start_date 
                           FROM loans 
                           WHERE member_id = ? AND status = 'active'
                           ORDER BY application_date DESC");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $loans[] = $row;
    }
    
    return $loans;
}

// If it's an AJAX request to get loan types
if (isset($_GET['fetch']) && $_GET['fetch'] === 'loan_types') {
    header('Content-Type: application/json');
    echo json_encode($loanTypes);
    exit;
}

// If it's an AJAX request to get member info
if (isset($_GET['fetch']) && $_GET['fetch'] === 'member_info' && isset($_GET['member_id'])) {
    header('Content-Type: application/json');
    
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    
    $memberId = (int)$_GET['member_id'];
    
    if ($memberId <= 0) {
        echo json_encode(['error' => 'Please enter a valid member ID']);
        $conn->close();
        exit;
    }
    
    $memberInfo = getMemberInfo($memberId, $conn);
    
    if ($memberInfo === null) {
        echo json_encode(['error' => 'Member not found. Please check the member ID and try again.']);
        $conn->close();
        exit;
    }
    
    $loans = getActiveLoansByMember($memberId, $conn);
    
    $conn->close();
    
    echo json_encode([
        'member' => $memberInfo,
        'loans' => $loans
    ]);
    exit;
}

// Handle form submission to save schedule
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['saveSchedule'])) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        $error = "Database connection failed: " . $conn->connect_error;
    } else {
        $loanId = isset($_POST['loanId']) ? (int)$_POST['loanId'] : 0;
        $scheduleData = isset($_POST['scheduleData']) ? json_decode($_POST['scheduleData'], true) : null;
        
        if (!$loanId) {
            $error = "Invalid loan ID provided.";
        } else if (!$scheduleData) {
            $error = "Invalid schedule data provided.";
        } else {
            $result = saveInstallmentsToDatabase($loanId, $scheduleData, $conn);
            
            if ($result === true) {
                $success = "Loan installments successfully saved to database!";
            } else {
                $error = "Failed to save installments: " . $result;
            }
        }
        
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Repayment Schedule Calculator</title>
    <style>
        :root {
            --primary-color: rgb(255, 140, 0);
            --primary-dark: rgb(230, 115, 0);
            --primary-light: rgb(255, 175, 85);
            --primary-very-light: rgb(255, 235, 210);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f9f9f9;
        }

        .container {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
            border-top: 4px solid var(--primary-color);
        }

        h1, h2 {
            color: var(--primary-dark);
            text-align: center;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #444;
        }

        select, input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
            font-size: 16px;
            transition: border 0.3s;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-very-light);
        }

        .input-row {
            display: flex;
            gap: 15px;
        }

        .input-row .form-group {
            flex: 1;
        }

        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
            transition: background-color 0.3s;
            font-weight: 600;
        }

        button:hover {
            background-color: var(--primary-dark);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }

        th, td {
            padding: 12px 15px;
            text-align: right;
            border-bottom: 1px solid #ddd;
        }

        th {
            background-color: var(--primary-very-light);
            font-weight: 600;
            color: #444;
        }

        td:first-child, th:first-child {
            text-align: left;
        }

        tr:hover {
            background-color: #f5f5f5;
        }

        .summary {
            background-color: var(--primary-very-light);
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.05);
            border-left: 4px solid var(--primary-color);
        }

        .summary h3 {
            margin-top: 0;
            color: var(--primary-dark);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .summary-label {
            font-weight: 600;
            color: #444;
        }

        .loan-description {
            font-style: italic;
            color: #666;
            margin-top: 8px;
            padding-left: 5px;
            border-left: 2px solid var(--primary-light);
        }

        .loading {
            text-align: center;
            padding: 20px;
            font-style: italic;
            color: #666;
        }

        .error-message {
            background-color: #ffe6e6;
            color: #d33;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #d33;
        }

        .success-message {
            background-color: #e6ffe6;
            color: #3a3;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            border-left: 4px solid #3a3;
        }

        .member-info {
            background-color: #f8f8f8;
            padding: 15px;
            margin: 15px 0;
            border-radius: 5px;
            border-left: 4px solid var(--primary-light);
        }

        .member-info p {
            margin: 5px 0;
        }

        .existing-loans {
            margin-top: 15px;
        }

        .existing-loans h4 {
            margin-bottom: 10px;
            color: var(--primary-dark);
        }

        .existing-loan-item {
            background-color: #fff;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 4px;
            border: 1px solid #ddd;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .existing-loan-item:hover {
            background-color: var(--primary-very-light);
        }

        .existing-loan-item.selected {
            background-color: var(--primary-very-light);
            border-color: var(--primary-color);
        }

        /* Header Styles */
        .header-container {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            margin-bottom: 30px;
            padding: 15px 20px;
            border-bottom: 2px solid var(--primary-color);
            background: linear-gradient(135deg, #fafafa 0%, #f0f0f0 100%);
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 30px -20px;
        }

        .logo-container {
            margin-right: 20px;
            flex-shrink: 0;
        }

        .logo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 2px 8px rgba(255, 140, 0, 0.3);
            border: 2px solid var(--primary-color);
        }

        .header-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .organization-header {
            text-align: left;
        }

        .org-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary-dark);
            margin: 0 0 5px 0;
            letter-spacing: 0.5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .org-subtitle {
            font-size: 16px;
            font-weight: 600;
            color: #444;
            margin: 0 0 5px 0;
            letter-spacing: 0.3px;
        }

        .org-location {
            font-size: 14px;
            color: #666;
            margin-bottom: 8px;
            font-style: italic;
        }

        .org-contact {
            font-size: 12px;
            color: #555;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .contact-item {
            font-weight: 500;
        }

        .contact-separator {
            color: var(--primary-color);
            font-weight: bold;
        }

        .page-title-section {
            text-align: left;
            margin-top: 10px;
        }

        .page-title {
            font-size: 18px;
            font-weight: 600;
            color: var(--primary-dark);
            margin: 0;
            padding: 8px 0;
            position: relative;
        }

        .page-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 80px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
            border-radius: 1px;
        }

        /* Remove duplicate page title */
        .duplicate-title {
            display: none;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .header-container {
                flex-direction: column;
                align-items: center;
                text-align: center;
                padding: 15px;
            }
            
            .logo-container {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .logo {
                width: 80px;
                height: 80px;
            }
            
            .organization-header {
                text-align: center;
            }
            
            .page-title-section {
                text-align: center;
            }
            
            .page-title::after {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .org-title {
                font-size: 18px;
            }
            
            .org-subtitle {
                font-size: 14px;
            }
            
            .org-contact {
                flex-direction: column;
                gap: 5px;
            }
            
            .contact-separator {
                display: none;
            }
            
            .page-title {
                font-size: 16px;
            }
        }

        @media (max-width: 480px) {
            .logo {
                width: 70px;
                height: 70px;
            }
            
            .org-title {
                font-size: 16px;
                line-height: 1.3;
            }
            
            .org-subtitle {
                font-size: 13px;
            }
            
            .org-location {
                font-size: 12px;
            }
            
            .org-contact {
                font-size: 11px;
            }
            
            .page-title {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-container">
            <div class="logo-container">
                <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo" class="logo">
            </div>
            <div class="header-content">
                <div class="organization-header">
                    <h1 class="org-title" style="font-size: 3rem;">SARVODAYA SHRAMADHANA SOCIETY</h1>
                    <h2 class="org-subtitle" style="font-size: 1.5rem;">Samaghi Sarvodaya Shramadhana Society</h2>
                    <div class="org-location" style="font-size: 1.25rem;">Kubaloluwa, Veyangoda</div>
                    <div class="org-contact">
                        <span class="contact-item" style="font-size: 1.25rem;">Phone: 077 690 6605</span>
                        <span class="contact-separator">|</span>
                        <span class="contact-item" style="font-size: 1.25rem;">Email: info@sarvodayabank.com</span>
                    </div>
                </div>
                <div class="page-title-section">
                    <h3 class="page-title" style="font-size: 1.5rem;">Loan Repayment Schedule Calculator</h3>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
        <div class="error-message">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
        <div class="success-message">
            <?php echo htmlspecialchars($success); ?>
        </div>
        <?php endif; ?>
        
        <?php if (empty($loanTypes) && !$error): ?>
        <div id="loadingMessage" class="loading" style="font-size: 1.25rem;">Loading loan types...</div>
        <?php endif; ?>
        
        <div id="calculatorForm" <?php echo (empty($loanTypes) && !$error) ? 'style="display: none;"' : ''; ?>>
            <div class="input-row">
                <div class="form-group" style="flex: 3;">
                    <label for="memberId" style="font-size: 1.25rem;">Membership No:</label>
                    <input type="number" id="memberId" placeholder="Enter member ID" style="font-size: 1.25rem;">
                </div>
                <div class="form-group" style="flex: 1; display: flex; align-items: flex-end;">
                    <button type="button" onclick="loadMemberInfo()" style="margin: 0; width: 100%;">Search</button>
                </div>
            </div>
            
            <div id="memberInfoContainer" style="display: none;" class="member-info">
                <p id="memberDetails" style="font-size: 1.25rem;"></p>
                <div id="existingLoansContainer" class="existing-loans" style="display: none;">
                    <h4 style="font-size: 1.25rem;">Existing Active Loans</h4>
                    <div id="existingLoansList" style="font-size: 1.25rem;"></div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="loanType" style="font-size: 1.25rem;">Loan Type:</label>
                <select id="loanType" onchange="updateLoanDetails()" style="font-size: 1.25rem;">
                    <option value="" style="font-size: 1.25rem;">Select a loan type</option>
                    <?php foreach ($loanTypes as $loan): ?>
                    <option value="<?php echo htmlspecialchars($loan['id']); ?>">
                        <?php echo htmlspecialchars($loan['loan_name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <p id="loanDescription" class="loan-description"></p>
            </div>
            
            <div class="input-row">
                <div class="form-group">
                    <label for="loanAmount" style="font-size: 1.25rem;">Loan Amount (LKR):</label>
                    <input type="number" id="loanAmount" placeholder="Enter loan amount" style="font-size: 1.25rem;">
                </div>
                
                <div class="form-group">
                    <label for="interestRate" style="font-size: 1.25rem;">Interest Rate (% per month):</label>
                    <input type="number" id="interestRate" step="0.01" placeholder="Interest rate" readonly style="font-size: 1.25rem;">
                </div>
            </div>
            
            <div class="input-row">
                <div class="form-group">
                    <label for="loanTerm" style="font-size: 1.25rem;">Loan Term (months):</label>
                    <input type="number" id="loanTerm" placeholder="Enter loan term" style="font-size: 1.25rem;">
                </div>
                
                <div class="form-group">
                    <label for="startDate" style="font-size: 1.25rem;">Start Date:</label>
                    <input type="date" id="startDate" style="font-size: 1.25rem;">
                </div>
            </div>
            
            <div class="input-row">
                <div class="form-group">
                    <label for="loanId" style="font-size: 1.25rem;">Loan ID:</label>
                    <input type="number" id="loanId" placeholder="Enter loan ID to save schedule" style="font-size: 1.25rem;">
                </div>
            </div>
            
            <button onclick="generateSchedule()" class="no-print" style="font-size: 1.25rem;">Generate Repayment Schedule</button>
        </div>
    </div>
    
    <div id="results" class="container" style="display: none;">
        <div id="summary" class="summary"></div>
        <div style="overflow-x: auto;">
            <table id="scheduleTable">
                <thead>
                    <tr>
                        <th style="font-size: 1.25rem;">Payment #</th>
                        <th style="font-size: 1.25rem;">Payment Date</th>
                        <th style="font-size: 1.25rem;">Payment Amount</th>
                        <th style="font-size: 1.25rem;">Principal</th>
                        <th style="font-size: 1.25rem;">Interest</th>
                        <th style="font-size: 1.25rem;">Remaining Balance</th>
                    </tr>
                </thead>
                <tbody id="scheduleBody"></tbody>
            </table>
        </div>
        <div class="no-print" style="text-align: center; margin-top: 20px;">
            <button onclick="generatePDF()" style="font-size: 1.25rem;">Download PDF</button>
        </div>
        <div id="saveButtonContainer" class="no-print" style="text-align: center; margin-top: 20px;">
            <button id="saveButton" onclick="saveScheduleToDatabase()" style="font-size: 1.25rem;">Save Schedule to Database</button>
        </div>
    </div>

    <script>
        // Global variables
        let loanTypes = <?php echo json_encode($loanTypes); ?>;
        let currentSchedule = null;
        let selectedLoanName = '';
        let memberLoans = [];
        let selectedMemberName = '';

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            if (loanTypes.length === 0 && !document.querySelector('.error-message')) {
                fetchLoanTypes();
            } else {
                setUpLoanDetails();
            }
            
            document.getElementById('startDate').valueAsDate = new Date();
        });

        // Set up loan details event handlers
        function setUpLoanDetails() {
            if (loanTypes.length > 0) {
                const loadingMessage = document.getElementById('loadingMessage');
                if (loadingMessage) loadingMessage.style.display = 'none';
                
                document.getElementById('calculatorForm').style.display = 'block';
            }
        }

        // Fetch loan types via AJAX
        async function fetchLoanTypes() {
            try {
                const response = await fetch('?fetch=loan_types');
                
                if (!response.ok) {
                    throw new Error('Failed to fetch loan types');
                }
                
                loanTypes = await response.json();
                
                const loadingMessage = document.getElementById('loadingMessage');
                if (loadingMessage) loadingMessage.style.display = 'none';
                
                document.getElementById('calculatorForm').style.display = 'block';
                
                populateLoanTypes();
            } catch (error) {
                console.error('Error:', error);
                const loadingMessage = document.getElementById('loadingMessage');
                if (loadingMessage) {
                    loadingMessage.classList.remove('loading');
                    loadingMessage.classList.add('error-message');
                    loadingMessage.textContent = 
                        'Unable to load loan types. Please refresh the page or try again later.';
                }
            }
        }

        // Populate loan types dropdown (only needed for AJAX load)
        function populateLoanTypes() {
            const loanTypeSelect = document.getElementById('loanType');
            
            while (loanTypeSelect.options.length > 1) {
                loanTypeSelect.remove(1);
            }
            
            loanTypes.forEach(loan => {
                const option = document.createElement('option');
                option.value = loan.id;
                option.textContent = loan.loan_name;
                loanTypeSelect.appendChild(option);
            });
        }

        // Load member information when selected
        async function loadMemberInfo() {
            const memberId = document.getElementById('memberId').value;
            
            if (!memberId) {
                document.getElementById('memberInfoContainer').style.display = 'none';
                document.getElementById('existingLoansContainer').style.display = 'none';
                return;
            }
            
            try {
                const response = await fetch(`?fetch=member_info&member_id=${memberId}`);
                
                if (!response.ok) {
                    throw new Error('Failed to fetch member info');
                }
                
                const data = await response.json();
                
                if (data.error) {
                    document.getElementById('memberInfoContainer').style.display = 'none';
                    document.getElementById('existingLoansContainer').style.display = 'none';
                    alert(data.error);
                    return;
                }
                
                const member = data.member;
                if (member) {
                    document.getElementById('memberDetails').textContent = 
                        `Member ID: ${member.id} | Name: ${member.name} | NIC: ${member.nic}`;
                    document.getElementById('memberInfoContainer').style.display = 'block';
                    selectedMemberName = member.name;
                }
                
                memberLoans = data.loans || [];
                
                if (memberLoans.length > 0) {
                    const loansList = document.getElementById('existingLoansList');
                    loansList.innerHTML = '';
                    
                    memberLoans.forEach(loan => {
                        const loanType = loanTypes.find(type => type.id === parseInt(loan.loan_type_id));
                        const loanTypeName = loanType ? loanType.loan_name : 'Unknown Loan Type';
                        
                        const loanItem = document.createElement('div');
                        loanItem.className = 'existing-loan-item';
                        loanItem.dataset.loanId = loan.id;
                        loanItem.dataset.loanAmount = loan.amount;
                        loanItem.dataset.interestRate = loan.interest_rate;
                        loanItem.dataset.loanTerm = loan.max_period;
                        loanItem.dataset.startDate = loan.start_date;
                        loanItem.dataset.loanTypeId = loan.loan_type_id;
                        
                        loanItem.innerHTML = `
                            <strong>Loan ID: ${loan.id}</strong> - ${loanTypeName}<br>
                            Amount: ${formatCurrency(loan.amount)} | Interest: ${loan.interest_rate}% | 
                            Term: ${loan.max_period} months | Start Date: ${formatDateString(loan.start_date)}
                        `;
                        
                        loanItem.addEventListener('click', function() {
                            selectLoan(this);
                        });
                        
                        loansList.appendChild(loanItem);
                    });
                    
                    document.getElementById('existingLoansContainer').style.display = 'block';
                } else {
                    document.getElementById('existingLoansContainer').style.display = 'none';
                }
                
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('memberInfoContainer').style.display = 'none';
                document.getElementById('existingLoansContainer').style.display = 'none';
                alert('Failed to load member information. Please try again.');
            }
        }  
        
        // Format date string from database (YYYY-MM-DD)
        function formatDateString(dateStr) {
            if (!dateStr) return 'N/A';
            const parts = dateStr.split('-');
            if (parts.length !== 3) return dateStr;
            
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        
        // Select an existing loan
        function selectLoan(loanElement) {
            document.querySelectorAll('.existing-loan-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            loanElement.classList.add('selected');
            
            const loanId = loanElement.dataset.loanId;
            const loanAmount = loanElement.dataset.loanAmount;
            const interestRate = loanElement.dataset.interestRate;
            const loanTerm = loanElement.dataset.loanTerm;
            const startDate = loanElement.dataset.startDate;
            const loanTypeId = loanElement.dataset.loanTypeId;
            
            document.getElementById('loanId').value = loanId;
            document.getElementById('loanAmount').value = loanAmount;
            document.getElementById('loanTerm').value = loanTerm;
            
            const loanTypeSelect = document.getElementById('loanType');
            loanTypeSelect.value = loanTypeId;
            updateLoanDetails();
            
            if (startDate && startDate !== '1970-01-01') {
                document.getElementById('startDate').value = startDate;
            }
        }

        // Update loan details based on selected loan type
        function updateLoanDetails() {
            const loanTypeId = document.getElementById('loanType').value;
            if (!loanTypeId) {
                clearLoanDetails();
                return;
            }
            
            const selectedLoan = loanTypes.find(loan => loan.id == loanTypeId);
            if (selectedLoan) {
                selectedLoanName = selectedLoan.loan_name;
                document.getElementById('loanAmount').max = selectedLoan.maximum_amount;
                document.getElementById('loanAmount').placeholder = `Enter amount (max ${formatCurrency(selectedLoan.maximum_amount)})`;
                
                document.getElementById('interestRate').value = selectedLoan.interest_rate;
                document.getElementById('loanTerm').max = selectedLoan.max_period;
                document.getElementById('loanTerm').placeholder = `Enter term (max ${selectedLoan.max_period} months)`;
                
                document.getElementById('loanDescription').textContent = selectedLoan.description || '';
            }
        }

        // Clear loan details
        function clearLoanDetails() {
            document.getElementById('loanAmount').value = '';
            document.getElementById('loanAmount').placeholder = 'Enter loan amount';
            document.getElementById('interestRate').value = '';
            document.getElementById('loanTerm').value = '';
            document.getElementById('loanTerm').placeholder = 'Enter loan term';
            document.getElementById('loanDescription').textContent = '';
            selectedLoanName = '';
        }

        // Generate the loan repayment schedule
        function generateSchedule() {
            const loanTypeId = document.getElementById('loanType').value;
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const interestRate = parseFloat(document.getElementById('interestRate').value);
            const loanTerm = parseInt(document.getElementById('loanTerm').value);
            const startDate = new Date(document.getElementById('startDate').value);
            const memberId = document.getElementById('memberId').value;
            
            if (!memberId) {
                alert('Please enter a member ID.');
                return;
            }
            
            if (!loanTypeId) {
                alert('Please select a loan type.');
                return;
            }
            
            if (isNaN(loanAmount) || loanAmount <= 0) {
                alert('Please enter a valid loan amount.');
                return;
            }
            
            if (isNaN(interestRate) || interestRate <= 0) {
                alert('Please enter a valid interest rate.');
                return;
            }
            
            if (isNaN(loanTerm) || loanTerm <= 0) {
                alert('Please enter a valid loan term.');
                return;
            }
            
            if (isNaN(startDate.getTime())) {
                alert('Please enter a valid start date.');
                return;
            }
            
            const selectedLoan = loanTypes.find(loan => loan.id == loanTypeId);
            
            if (loanAmount > selectedLoan.maximum_amount) {
                alert(`Loan amount exceeds the maximum allowed amount of ${formatCurrency(selectedLoan.maximum_amount)} for this loan type.`);
                return;
            }
            
            if (loanTerm > selectedLoan.max_period) {
                alert(`Loan term exceeds the maximum allowed period of ${selectedLoan.max_period} months for this loan type.`);
                return;
            }
            
            const monthlyInterestRate = interestRate / 100;
            const monthlyPayment = calculateMonthlyPayment(loanAmount, monthlyInterestRate, loanTerm);
            
            const schedule = [];
            let remainingBalance = loanAmount;
            let totalInterest = 0;
            let paymentDate = new Date(startDate);
            
            for (let month = 1; month <= loanTerm; month++) {
                paymentDate = new Date(paymentDate);
                paymentDate.setMonth(paymentDate.getMonth() + 1);
                
                const interestPayment = remainingBalance * monthlyInterestRate;
                const principalPayment = monthlyPayment - interestPayment;
                
                remainingBalance -= principalPayment;
                if (month === loanTerm) {
                    remainingBalance = 0;
                }
                
                totalInterest += interestPayment;
                
                schedule.push({
                    paymentNumber: month,
                    paymentDate: new Date(paymentDate),
                    paymentAmount: monthlyPayment,
                    principal: principalPayment,
                    interest: interestPayment,
                    remainingBalance: remainingBalance >= 0 ? remainingBalance : 0
                });
            }
            
            currentSchedule = schedule;
            displayResults(schedule, loanAmount, monthlyPayment, totalInterest, selectedLoan.loan_name);
        }
        
        // Calculate monthly payment
        function calculateMonthlyPayment(principal, monthlyRate, term) {
            if (monthlyRate === 0) {
                return principal / term;
            }
            
            return principal * monthlyRate * Math.pow(1 + monthlyRate, term) / (Math.pow(1 + monthlyRate, term) - 1);
        }
        
        // Display the results in the table
        function displayResults(schedule, loanAmount, monthlyPayment, totalInterest, loanName) {
            const totalPayment = loanAmount + totalInterest;
            const memberId = document.getElementById('memberId').value;
            const memberName = selectedMemberName || 'Unknown Member';
            
            const summaryHTML = `
                <h3 style="font-size: 1.25rem;">Loan Summary - ${loanName}</h3>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Member:</span>
                    <span style="font-size: 1.25rem;">ID: ${memberId} - ${memberName}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Loan ID:</span>
                    <span style="font-size: 1.25rem;">${document.getElementById('loanId').value || 'Not specified'}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Loan Amount:</span>
                    <span style="font-size: 1.25rem;">${formatCurrency(loanAmount)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Monthly Payment:</span>
                    <span style="font-size: 1.25rem;">${formatCurrency(monthlyPayment)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Total Interest:</span>
                    <span style="font-size: 1.25rem;">${formatCurrency(totalInterest)}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label" style="font-size: 1.25rem;">Total Amount to Repay:</span>
                    <span style="font-size: 1.25rem;">${formatCurrency(totalPayment)}</span>
                </div>
            `;
            
            document.getElementById('summary').innerHTML = summaryHTML;
            
            const tableBody = document.getElementById('scheduleBody');
            tableBody.innerHTML = '';
            
            schedule.forEach(payment => {
                const row = document.createElement('tr');
                
                row.innerHTML = `
                    <td style="font-size: 1.25rem;">${payment.paymentNumber}</td>
                    <td style="font-size: 1.25rem;">${formatDate(payment.paymentDate)}</td>
                    <td style="font-size: 1.25rem;">${formatCurrency(payment.paymentAmount)}</td>
                    <td style="font-size: 1.25rem;">${formatCurrency(payment.principal)}</td>
                    <td style="font-size: 1.25rem;">${formatCurrency(payment.interest)}</td>
                    <td style="font-size: 1.25rem;">${formatCurrency(payment.remainingBalance)}</td>
                `;
                
                tableBody.appendChild(row);
            });
            
            document.getElementById('results').style.display = 'block';
            document.getElementById('results').scrollIntoView({ behavior: 'smooth' });
        }
        
        // Generate PDF report
        function generatePDF() {
            if (!currentSchedule) {
                alert('Please generate a schedule first.');
                return;
            }
            
            const memberId = document.getElementById('memberId').value;
            const memberName = selectedMemberName || 'Unknown Member';
            const loanId = document.getElementById('loanId').value || 'Not specified';
            const loanAmount = parseFloat(document.getElementById('loanAmount').value);
            const monthlyPayment = currentSchedule[0].paymentAmount;
            const totalInterest = currentSchedule.reduce((sum, payment) => sum + payment.interest, 0);
            const totalPayment = loanAmount + totalInterest;
            const loanName = selectedLoanName;
            
            // Prepare summary data
            const summaryData = {
                memberId: memberId,
                memberName: memberName,
                loanId: loanId,
                loanName: loanName,
                loanAmount: formatCurrency(loanAmount),
                monthlyPayment: formatCurrency(monthlyPayment),
                totalInterest: formatCurrency(totalInterest),
                totalPayment: formatCurrency(totalPayment)
            };
            
            // Prepare schedule data for PDF
            const pdfScheduleData = currentSchedule.map(payment => {
                return {
                    paymentNumber: payment.paymentNumber,
                    paymentDate: formatDate(payment.paymentDate),
                    paymentAmount: formatCurrency(payment.paymentAmount),
                    principal: formatCurrency(payment.principal),
                    interest: formatCurrency(payment.interest),
                    remainingBalance: formatCurrency(payment.remainingBalance)
                };
            });
            
            // Create a form to submit the data
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '?generate_pdf=1';
            form.style.display = 'none';
            
            // Add summary data
            const summaryInput = document.createElement('input');
            summaryInput.type = 'hidden';
            summaryInput.name = 'summaryData';
            summaryInput.value = JSON.stringify(summaryData);
            form.appendChild(summaryInput);
            
            // Add schedule data
            const scheduleInput = document.createElement('input');
            scheduleInput.type = 'hidden';
            scheduleInput.name = 'scheduleData';
            scheduleInput.value = JSON.stringify(pdfScheduleData);
            form.appendChild(scheduleInput);
            
            // Append to body and submit
            document.body.appendChild(form);
            form.submit();
        }
        
        // Save schedule to database
        function saveScheduleToDatabase() {
            if (!currentSchedule) {
                alert('Please generate a schedule first.');
                return;
            }
            
            const loanId = document.getElementById('loanId').value;
            if (!loanId) {
                alert('Please enter a loan ID to save the schedule.');
                return;
            }
            
            const scheduleData = currentSchedule.map(payment => {
                return {
                    paymentNumber: payment.paymentNumber,
                    paymentDate: payment.paymentDate.toISOString().split('T')[0],
                    paymentAmount: payment.paymentAmount,
                    principal: payment.principal,
                    interest: payment.interest,
                    remainingBalance: payment.remainingBalance
                };
            });
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const loanIdInput = document.createElement('input');
            loanIdInput.type = 'hidden';
            loanIdInput.name = 'loanId';
            loanIdInput.value = loanId;
            form.appendChild(loanIdInput);
            
            const scheduleInput = document.createElement('input');
            scheduleInput.type = 'hidden';
            scheduleInput.name = 'scheduleData';
            scheduleInput.value = JSON.stringify(scheduleData);
            form.appendChild(scheduleInput);
            
            const submitInput = document.createElement('input');
            submitInput.type = 'hidden';
            submitInput.name = 'saveSchedule';
            submitInput.value = '1';
            form.appendChild(submitInput);
            
            document.getElementById('saveButton').disabled = true;
            document.getElementById('saveButton').textContent = 'Saving...';
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Format currency for Sri Lankan Rupees
        function formatCurrency(amount) {
            return new Intl.NumberFormat('en-LK', {
                style: 'currency',
                currency: 'LKR',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        }
        
        // Format date
        function formatDate(date) {
            return new Intl.DateTimeFormat('en-LK', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            }).format(date);
        }
    </script>
</body>
</html>