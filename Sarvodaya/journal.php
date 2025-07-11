<?php
// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'sarvodaya';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default date range (current month)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Handle date filter
if (isset($_GET['filter']) || isset($_GET['download_pdf'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}

// Handle PDF generation
if (isset($_GET['download_pdf'])) {
    require('fpdf/fpdf.php');
    
    // Get filtered transactions
    $query = "SELECT 
                'payment' AS transaction_type,
                id,
                member_id,
                payment_date AS transaction_date,
                0 AS debit_amount,
                amount AS credit_amount,
                description AS details,
                id AS reference_id,
                payment_type AS reference_type
              FROM payments
              WHERE DATE(payment_date) BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'receipt' AS transaction_type,
                id,
                member_id,
                receipt_date AS transaction_date,
                amount AS debit_amount,
                0 AS credit_amount,
                NULL AS details,
                id AS reference_id,
                receipt_type AS reference_type
              FROM receipts
              WHERE DATE(receipt_date) BETWEEN ? AND ?
              
              UNION ALL
              
              SELECT 
                'interest' AS transaction_type,
                id,
                member_id,
                created_at AS transaction_date,
                0 AS debit_amount,
                interest_amount AS credit_amount,
                CONCAT('Interest for ', period_start_date, ' to ', period_end_date) AS details,
                id AS reference_id,
                status AS reference_type
              FROM interest_calculations
              WHERE DATE(created_at) BETWEEN ? AND ?
              
              ORDER BY transaction_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();

    // Calculate totals
    $total_query = "SELECT 
                    SUM(debit) AS total_debit,
                    SUM(credit) AS total_credit
                  FROM (
                    SELECT 0 AS debit, amount AS credit FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?
                    UNION ALL
                    SELECT amount AS debit, 0 AS credit FROM receipts WHERE DATE(receipt_date) BETWEEN ? AND ?
                    UNION ALL
                    SELECT 0 AS debit, interest_amount AS credit FROM interest_calculations WHERE DATE(created_at) BETWEEN ? AND ?
                  ) AS combined_transactions";

    $total_stmt = $conn->prepare($total_query);
    $total_stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
    $total_stmt->execute();
    $totals = $total_stmt->get_result()->fetch_assoc();
    
    $net_liquidity = $totals['total_debit'] - $totals['total_credit'];
    
    class PDF extends FPDF {
        
        function Header() {
            if($this->PageNo() == 1) {
                // Company name in orange - no background box
                $this->SetTextColor(255, 140, 0); // Orange color
                $this->SetFont('Arial', 'B', 16);
                $this->SetXY(10, 18);
                $this->Cell($this->GetPageWidth()-20, 8, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
                
                // Contact info in regular black
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('Arial', '', 11);
                $this->SetX(10);
                $this->Cell($this->GetPageWidth()-20, 6, 'Kubaloluwa, Veyangoda | Phone: 077 690 6605', 0, 1, 'C');
                
                // Report title section
                $this->SetY(35);
                $this->SetTextColor(255, 140, 0); // Orange color for title
                $this->SetFont('Arial', 'B', 18);
                $this->Cell(0, 10, 'GENERAL JOURNAL REPORT', 0, 1, 'C');
                
                // Date range in black
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('Arial', '', 12);
                $this->Cell(0, 8, 'Period: ' . date('M j, Y', strtotime($GLOBALS['start_date'])) . ' to ' . date('M j, Y', strtotime($GLOBALS['end_date'])), 0, 1, 'C');
                
                $this->Ln(10);
            }
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb} | Generated: ' . date('M j, Y g:i A'), 0, 0, 'C');
        }
        
        function SummaryStats($totals, $net_liquidity) {
            $this->SetFont('Arial', 'B', 11);
            
            // Summary boxes
            $boxWidth = 60;
            $startX = ($this->GetPageWidth() - ($boxWidth * 3)) / 2;
            
            // Total Receipts - using orange theme
            $this->SetFillColor(255, 140, 0);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($startX, $this->GetY());
            $this->Cell($boxWidth, 8, 'Total Receipts', 1, 0, 'C', true);
            $this->SetXY($startX, $this->GetY() + 8);
            $this->Cell($boxWidth, 8, number_format($totals['total_debit'], 2), 1, 0, 'C', true);
            
            // Total Payments - using darker orange
            $this->SetFillColor(255, 100, 0);
            $this->SetXY($startX + $boxWidth, $this->GetY() - 8);
            $this->Cell($boxWidth, 8, 'Total Payments', 1, 0, 'C', true);
            $this->SetXY($startX + $boxWidth, $this->GetY() + 8);
            $this->Cell($boxWidth, 8, number_format($totals['total_credit'], 2), 1, 0, 'C', true);
            
            // Net Position - using orange theme variations
            $netColor = $net_liquidity >= 0 ? array(255, 140, 0) : array(255, 100, 0);
            $this->SetFillColor($netColor[0], $netColor[1], $netColor[2]);
            $this->SetXY($startX + ($boxWidth * 2), $this->GetY() - 8);
            $this->Cell($boxWidth, 8, 'Net Position', 1, 0, 'C', true);
            $this->SetXY($startX + ($boxWidth * 2), $this->GetY() + 8);
            $this->Cell($boxWidth, 8, number_format($net_liquidity, 2), 1, 1, 'C', true);
            
            $this->Ln(15);
        }
        
        function TransactionTable($result, $totals, $net_liquidity) {
            // Table positioning
            $tableWidth = 250;
            $this->SetLeftMargin(($this->GetPageWidth() - $tableWidth) / 2);
            
            // Column widths - perfectly aligned
            $colWidths = array(30, 30, 25, 45, 70, 25, 25);
            
            // Table header - using orange theme
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(255, 140, 0);
            $this->SetTextColor(255, 255, 255);
            
            $this->Cell($colWidths[0], 10, 'Date', 1, 0, 'C', true);
            $this->Cell($colWidths[1], 10, 'Type', 1, 0, 'C', true);
            $this->Cell($colWidths[2], 10, 'Member', 1, 0, 'C', true);
            $this->Cell($colWidths[3], 10, 'Reference', 1, 0, 'C', true);
            $this->Cell($colWidths[4], 10, 'Details', 1, 0, 'C', true);
            $this->Cell($colWidths[5], 10, 'Debit', 1, 0, 'C', true);
            $this->Cell($colWidths[6], 10, 'Credit', 1, 1, 'C', true);
            
            // Table data
            $this->SetFont('Arial', '', 9);
            $this->SetTextColor(0, 0, 0);
            $rowCount = 0;
            
            if($result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    $rowCount++;
                    $fill = ($rowCount % 2 == 0);
                    
                    if($fill) {
                        $this->SetFillColor(255, 245, 230); // Light orange tint
                    } else {
                        $this->SetFillColor(255, 255, 255);
                    }
                    
                    // Check for page break
                    if($this->GetY() > 180) {
                        $this->AddPage();
                        $this->SetLeftMargin(($this->GetPageWidth() - $tableWidth) / 2);
                        
                        // Repeat header
                        $this->SetFont('Arial', 'B', 10);
                        $this->SetFillColor(255, 140, 0);
                        $this->SetTextColor(255, 255, 255);
                        
                        $this->Cell($colWidths[0], 10, 'Date', 1, 0, 'C', true);
                        $this->Cell($colWidths[1], 10, 'Type', 1, 0, 'C', true);
                        $this->Cell($colWidths[2], 10, 'Member', 1, 0, 'C', true);
                        $this->Cell($colWidths[3], 10, 'Reference', 1, 0, 'C', true);
                        $this->Cell($colWidths[4], 10, 'Details', 1, 0, 'C', true);
                        $this->Cell($colWidths[5], 10, 'Debit', 1, 0, 'C', true);
                        $this->Cell($colWidths[6], 10, 'Credit', 1, 1, 'C', true);
                        
                        $this->SetFont('Arial', '', 9);
                        $this->SetTextColor(0, 0, 0);
                    }
                    
                    // Row data
                    $this->Cell($colWidths[0], 8, date('M j, Y', strtotime($row['transaction_date'])), 1, 0, 'C', $fill);
                    $this->Cell($colWidths[1], 8, ucfirst($row['transaction_type']), 1, 0, 'C', $fill);
                    $this->Cell($colWidths[2], 8, '#'.$row['member_id'], 1, 0, 'C', $fill);
                    
                    $reference = $row['reference_type'];
                    if($row['reference_id']) {
                        $reference .= ' #' . $row['reference_id'];
                    }
                    $this->Cell($colWidths[3], 8, $reference, 1, 0, 'L', $fill);
                    
                    $details = $row['details'] ? $row['details'] : '-';
                    if(strlen($details) > 30) {
                        $details = substr($details, 0, 27) . '...';
                    }
                    $this->Cell($colWidths[4], 8, $details, 1, 0, 'L', $fill);
                    
                    $this->Cell($colWidths[5], 8, $row['debit_amount'] > 0 ? number_format($row['debit_amount'], 2) : '-', 1, 0, 'R', $fill);
                    $this->Cell($colWidths[6], 8, $row['credit_amount'] > 0 ? number_format($row['credit_amount'], 2) : '-', 1, 1, 'R', $fill);
                }
            } else {
                $this->SetFillColor(255, 245, 230);
                $this->Cell($tableWidth, 10, 'No transactions found for the selected period', 1, 1, 'C', true);
            }
            
            // Table footer - using orange theme
            $this->SetFont('Arial', 'B', 10);
            $this->SetFillColor(255, 140, 0);
            $this->SetTextColor(255, 255, 255);
            
            $this->Cell(array_sum(array_slice($colWidths, 0, 5)), 10, 'TOTALS:', 1, 0, 'R', true);
            $this->Cell($colWidths[5], 10, number_format($totals['total_debit'], 2), 1, 0, 'R', true);
            $this->Cell($colWidths[6], 10, number_format($totals['total_credit'], 2), 1, 1, 'R', true);
            
            // Net position - using orange theme variations
            $netColor = $net_liquidity >= 0 ? array(255, 140, 0) : array(255, 100, 0);
            $this->SetFillColor($netColor[0], $netColor[1], $netColor[2]);
            $this->Cell(array_sum(array_slice($colWidths, 0, 5)), 10, 'NET POSITION:', 1, 0, 'R', true);
            $this->Cell($colWidths[5] + $colWidths[6], 10, number_format($net_liquidity, 2), 1, 1, 'C', true);
        }
        
        function SignatureSection() {
            $this->SetY(-40);
            $this->SetFont('Arial', '', 10);
            $this->SetTextColor(0, 0, 0);
            
            $this->Cell(100, 8, 'Date: ____________________', 0, 0, 'L');
            $this->Cell(0, 8, 'Manager Signature: ____________________', 0, 1, 'R');
        }
    }

    // Create PDF
    $pdf = new PDF('L');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Add summary
    $pdf->SummaryStats($totals, $net_liquidity);
    
    // Add transaction table
    $pdf->TransactionTable($result, $totals, $net_liquidity);
    
    // Add signature section
    $pdf->SignatureSection();
    
    // Output PDF
    $pdf->Output('D', 'General_Journal_Report_'.date('Y-m-d').'.pdf');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Journal - Financial Overview</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Your existing CSS styles remain unchanged */
        :root {
            --primary: linear-gradient(135deg, rgb(255, 140, 0) 0%, rgb(230, 120, 0) 100%);
            --primary-solid: rgb(255, 140, 0);
            --primary-light: rgba(255, 140, 0, 0.1);
            --primary-dark: rgb(230, 120, 0);
            --secondary: rgb(255, 165, 50);
            --accent: rgb(255, 180, 80);
            --success: #00d4aa;
            --info: #3498db;
            --warning: #feca57;
            --dark: #2c3e50;
            --light: #ecf0f1;
            --purple: #9b59b6;
            --indigo: #6c63ff;
            --teal: #1dd1a1;
            --shadow: 0 10px 30px rgba(255, 140, 0, 0.15);
            --shadow-hover: 0 15px 40px rgba(255, 140, 0, 0.25);
            --border-radius: 12px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #fff8f0 0%, #ffe5cc 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 20px auto;
            padding: 0 20px;
        }

        .header-card {
            background: var(--primary);
            border-radius: var(--border-radius);
            padding: 40px;
            text-align: center;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .header-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        .header-card h1 {
            color: white;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.2);
        }

        .header-card .subtitle {
            color: rgba(255,255,255,0.95);
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }

        .org-info {
            background: white;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 20px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid rgba(255, 140, 0, 0.1);
        }

        .org-info h2 {
            color: var(--primary-solid);
            margin-bottom: 5px;
        }

        .org-info p {
            margin: 3px 0;
            color: #555;
        }

        .filter-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 140, 0, 0.1);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .form-group {
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: #fefcfa;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--primary-solid);
            background: white;
            box-shadow: 0 0 0 3px var(--primary-light);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
        }

        .btn-secondary {
            background: var(--dark);
            color: white;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }

        .btn-primary:hover {
            box-shadow: 0 6px 20px rgba(255, 140, 0, 0.4);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            text-align: center;
            transition: var(--transition);
            border: 1px solid rgba(255, 140, 0, 0.1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
            border-color: rgba(255, 140, 0, 0.2);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.debit { 
            background: linear-gradient(135deg, var(--success), #01a085);
        }
        .stat-icon.credit { 
            background: linear-gradient(135deg, var(--dark), #1a252f);
        }
        .stat-icon.net { 
            background: var(--primary);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-value.debit { color: var(--success); }
        .stat-value.credit { color: var(--dark); }
        .stat-value.surplus { color: var(--success); }
        .stat-value.deficit { color: var(--dark); }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .main-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            border: 1px solid rgba(255, 140, 0, 0.1);
        }

        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        thead {
            background: linear-gradient(135deg, #fef8f0 0%, #ffeedd 100%);
        }

        th {
            padding: 20px 16px;
            text-align: left;
            font-weight: 700;
            color: var(--dark);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-size: 0.8rem;
            border-bottom: 2px solid rgba(255, 140, 0, 0.2);
        }

        td {
            padding: 16px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        tbody tr {
            transition: var(--transition);
            opacity: 0;
            animation: slideInUp 0.6s ease forwards;
        }

        tbody tr:hover {
            background: #fefcfa;
            transform: scale(1.01);
            box-shadow: 0 4px 12px rgba(255, 140, 0, 0.1);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-payment {
            background: linear-gradient(135deg, var(--dark), #1a252f);
            color: white;
        }

        .badge-receipt {
            background: linear-gradient(135deg, var(--success), #01a085);
            color: white;
        }

        .badge-interest {
            background: var(--primary);
            color: white;
        }

        .amount {
            font-weight: 700;
            font-size: 1.1rem;
            text-align: right;
        }

        .debit {
            color: var(--success);
        }

        .credit {
            color: var(--dark);
        }

        .zero-amount {
            color: #cbd5e0;
            font-weight: 400;
        }

        .member-id {
            background: var(--primary);
            color: white;
            padding: 4px 8px;
            border-radius: 6px;
            font-weight: 600;
            font-size: 0.85rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .reference {
            font-size: 0.9rem;
        }

        .reference small {
            color: #64748b;
            font-size: 0.8rem;
        }

        .details {
            max-width: 200px;
            word-wrap: break-word;
            color: #475569;
        }

        tfoot {
            background: linear-gradient(135deg, var(--primary-solid), var(--primary-dark));
            color: white;
        }

        tfoot td {
            padding: 16px;
            font-weight: 600;
            border: none;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }

        .totals-row {
            font-size: 1.1rem;
        }

        .net-position {
            font-size: 1.3rem;
            text-align: center;
            background: white;
        }

        .net-position td {
            color: var(--dark);
            border: 1px solid #f1f5f9;
        }

        .net-position.surplus td {
            color: var(--success);
        }

        .net-position.deficit td {
            color: var(--dark);
        }

        .period-row {
            background: var(--primary) !important;
            text-align: center;
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.3;
            color: var(--primary-solid);
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 0 10px;
            }
            
            .header-card {
                padding: 30px 20px;
            }
            
            .header-card h1 {
                font-size: 2rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 12px 8px;
            }
        }

        @media print {
            @page {
                size: A4;
                margin: 15mm;
            }

            * {
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            body {
                background: white;
                font-size: 12pt;
                line-height: 1.4;
                color: #000;
            }
            
            .filter-card {
                display: none;
            }

            .btn {
                display: none;
            }
            
            .container {
                max-width: 100%;
                margin: 0;
                padding: 0;
            }

            .header-card {
                background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%) !important;
                -webkit-print-color-adjust: exact;
                color: white !important;
                padding: 25px;
                margin-bottom: 20px;
                border-radius: 8px;
                page-break-inside: avoid;
            }

            .header-card h1 {
                color: white !important;
                font-size: 24pt;
                margin-bottom: 8px;
            }

            .header-card .subtitle {
                color: rgba(255,255,255,0.9) !important;
                font-size: 12pt;
            }

            /* Print Summary Stats */
            .stats-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr);
                gap: 15px;
                margin-bottom: 25px;
                page-break-inside: avoid;
            }

            .stat-card {
                background: #f8f9fa !important;
                border: 2px solid #dee2e6 !important;
                border-radius: 8px;
                padding: 15px;
                text-align: center;
                page-break-inside: avoid;
            }

            .stat-icon {
                display: none; /* Hide icons in print for cleaner look */
            }

            .stat-value {
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 5px;
                color: #000 !important;
            }

            .stat-value.debit {
                color: #28a745 !important;
            }

            .stat-value.credit {
                color: #000 !important;
            }

            .stat-value.surplus {
                color: #28a745 !important;
            }

            .stat-value.deficit {
                color: #000 !important;
            }

            .stat-label {
                font-size: 10pt;
                color: #666 !important;
                text-transform: uppercase;
                font-weight: bold;
            }

            .main-card {
                box-shadow: none;
                border: 2px solid #dee2e6;
                border-radius: 8px;
                overflow: visible;
            }

            .table-container {
                overflow: visible;
            }

            table {
                width: 100%;
                font-size: 10pt;
                border-collapse: collapse;
            }

            thead {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }

            th {
                background: #e9ecef !important;
                color: #000 !important;
                padding: 12px 8px;
                font-size: 9pt;
                font-weight: bold;
                text-transform: uppercase;
                border: 1px solid #dee2e6 !important;
                text-align: left;
            }

            td {
                padding: 8px;
                border: 1px solid #dee2e6 !important;
                font-size: 10pt;
                vertical-align: top;
            }

            tbody tr {
                page-break-inside: avoid;
                animation: none;
                opacity: 1;
            }

            tbody tr:nth-child(even) {
                background: #f8f9fa !important;
                -webkit-print-color-adjust: exact;
            }

            .badge {
                background: #6c757d !important;
                color: white !important;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 8pt;
                font-weight: bold;
                -webkit-print-color-adjust: exact;
            }

            .badge-payment {
                background: #000 !important;
                color: white !important;
            }

            .badge-receipt {
                background: #28a745 !important;
                color: white !important;
            }

            .badge-interest {
                background: #ffc107 !important;
                color: #000 !important;
            }

            .member-id {
                background: #6c757d !important;
                color: white !important;
                padding: 2px 6px;
                border-radius: 3px;
                font-weight: bold;
                font-size: 9pt;
                -webkit-print-color-adjust: exact;
            }

            .amount {
                font-weight: bold;
                text-align: right;
            }

            .debit {
                color: #28a745 !important;
            }

            .credit {
                color: #000 !important;
            }

            .zero-amount {
                color: #999 !important;
            }

            .details {
                max-width: none;
                font-size: 9pt;
                color: #333 !important;
            }

            .reference {
                font-size: 9pt;
            }

            .reference small {
                color: #666 !important;
                font-size: 8pt;
            }

            tfoot {
                background: #2c3e50 !important;
                color: white !important;
                -webkit-print-color-adjust: exact;
                page-break-inside: avoid;
            }

            tfoot td {
                background: #2c3e50 !important;
                color: white !important;
                font-weight: bold;
                border: 1px solid #2c3e50 !important;
                padding: 12px 8px;
            }

            .totals-row {
                font-size: 11pt;
            }

            .net-position {
                font-size: 12pt;
                text-align: center;
                background: white !important;
            }

            .net-position td {
                color: #000 !important;
                border: 1px solid #dee2e6 !important;
            }

            .net-position.surplus td {
                color: #28a745 !important;
            }

            .net-position.deficit td {
                color: #dc3545 !important;
            }

            .period-row {
                background: #2c3e50 !important;
                color: white !important;
                text-align: center;
                font-size: 11pt;
                font-weight: bold;
            }

            /* Print-specific elements */
            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #dee2e6;
            }

            .print-date {
                display: block !important;
                text-align: right;
                font-size: 10pt;
                color: #666;
                margin-bottom: 15px;
            }

            .print-footer {
                display: block !important;
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 9pt;
                color: #666;
                padding: 10px 0;
                border-top: 1px solid #dee2e6;
            }

            /* Page breaks */
            .stats-grid {
                page-break-after: avoid;
            }

            .main-card {
                page-break-before: avoid;
            }

            tfoot {
                page-break-inside: avoid;
            }

            /* Hide empty state in print */
            .empty-state {
                font-size: 12pt;
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Print-only elements -->
        <div class="print-date" style="display: none;">
            Generated on: <?= date('F j, Y \a\t g:i A') ?>
        </div>
        
        <!-- Organization Header -->
        <div class="print-header" style="display: none; text-align: center; margin-bottom: 20px;">
            <h2 style="margin-bottom: 5px;">SARVODAYA SHRAMADHANA SOCIETY</h2>
            <p style="margin: 5px 0;">Samaghi Sarvodaya Shramadhana Society</p>
            <p style="margin: 5px 0;">Kubaloluwa, Veyangoda</p>
            <p style="margin: 5px 0;">Phone: 077 690 6605 | Email: info@sarvodayabank.com</p>
        </div>

        <!-- Organization Info -->
        <div class="org-info">
            <h2>SARVODAYA SHRAMADHANA SOCIETY</h2>
            <p>Samaghi Sarvodaya Shramadhana Society</p>
            <p>Kubaloluwa, Veyangoda</p>
            <p>Phone: 077 690 6605 | Email: info@sarvodayabank.com</p>
        </div>

        <!-- Header -->
        <div class="header-card">
            <h1><i class="fas fa-book"></i> General Journal</h1>
            <div class="subtitle" style="font-size: 20px;">Financial Transactions Overview - Money Received (Debit) / Money Paid (Credit)</div>
        </div>

        <!-- Filter Form -->
        <div class="filter-card">
            <form method="GET" class="filter-form">
                <div class="form-group">
                    <label for="start_date"><i class="fas fa-calendar-alt" style="font-size: 20px;"></i> From Date</label>
                    <input type="date" id="start_date" style="font-size: 20px;" name="start_date" value="<?= $start_date ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="end_date"><i class="fas fa-calendar-alt" style="font-size: 20px;"></i> To Date</label>
                    <input type="date" id="end_date" style="font-size: 20px;" name="end_date" value="<?= $end_date ?>" required>
                </div>
                
                <button type="submit" name="filter" class="btn btn-primary">
                    <i class="fas fa-filter" style="font-size: 20px;"></i> Apply Filter
                </button>
                
                <button type="submit" name="download_pdf" class="btn btn-secondary">
                    <i class="fas fa-download" style="font-size: 20px;"></i> Download PDF
                </button>
            </form>
        </div>

        <?php
        // Get filtered transactions - Money received (receipts) in DEBIT, Money paid (payments & interest) in CREDIT
        $query = "SELECT 
                    'payment' AS transaction_type,
                    id,
                    member_id,
                    payment_date AS transaction_date,
                    0 AS debit_amount,
                    amount AS credit_amount,
                    description AS details,
                    id AS reference_id,
                    payment_type AS reference_type
                  FROM payments
                  WHERE DATE(payment_date) BETWEEN ? AND ?
                  
                  UNION ALL
                  
                  SELECT 
                    'receipt' AS transaction_type,
                    id,
                    member_id,
                    receipt_date AS transaction_date,
                    amount AS debit_amount,
                    0 AS credit_amount,
                    NULL AS details,
                    id AS reference_id,
                    receipt_type AS reference_type
                  FROM receipts
                  WHERE DATE(receipt_date) BETWEEN ? AND ?
                  
                  UNION ALL
                  
                  SELECT 
                    'interest' AS transaction_type,
                    id,
                    member_id,
                    created_at AS transaction_date,
                    0 AS debit_amount,
                    interest_amount AS credit_amount,
                    CONCAT('Interest for ', period_start_date, ' to ', period_end_date) AS details,
                    id AS reference_id,
                    status AS reference_type
                  FROM interest_calculations
                  WHERE DATE(created_at) BETWEEN ? AND ?
                  
                  ORDER BY transaction_date DESC";

        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        $stmt->execute();
        $result = $stmt->get_result();

        // Calculate totals for the filtered period
        $total_query = "SELECT 
                        SUM(debit) AS total_debit,
                        SUM(credit) AS total_credit
                      FROM (
                        SELECT 0 AS debit, amount AS credit FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?
                        UNION ALL
                        SELECT amount AS debit, 0 AS credit FROM receipts WHERE DATE(receipt_date) BETWEEN ? AND ?
                        UNION ALL
                        SELECT 0 AS debit, interest_amount AS credit FROM interest_calculations WHERE DATE(created_at) BETWEEN ? AND ?
                      ) AS combined_transactions";

        $total_stmt = $conn->prepare($total_query);
        $total_stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
        $total_stmt->execute();
        $totals = $total_stmt->get_result()->fetch_assoc();
        
        $net_liquidity = $totals['total_debit'] - $totals['total_credit'];
        ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon debit" style="font-size: 20px;">
                    <i class="fas fa-arrow-down" style="color: white;"></i>
                </div>
                <div class="stat-value debit" ><?= number_format($totals['total_debit'], 2) ?></div>
                <div class="stat-label" style="font-size: 20px;">Total Receipts</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon credit">
                    <i class="fas fa-arrow-up" style="color: white;"></i>
                </div>
                <div class="stat-value credit"><?= number_format($totals['total_credit'], 2) ?></div>
                <div class="stat-label" style="font-size: 20px;">Total Payments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon net">
                    <i class="fas fa-balance-scale"></i>
                </div>
                <div class="stat-value <?= $net_liquidity >= 0 ? 'surplus' : 'deficit' ?>">
                    <?= number_format(abs($net_liquidity), 2) ?>
                </div>
                <div class="stat-label" style="font-size: 20px;">Net Position (<?= $net_liquidity >= 0 ? 'Surplus' : 'Deficit' ?>)</div>
            </div>
        </div>

        <!-- Main Table -->
        <div class="main-card">
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th style="font-size: 20px;"><i class="fas fa-calendar"></i> Date</th>
                            <th style="font-size: 20px;"><i class="fas fa-tag"></i> Type</th>
                            <th style="font-size: 20px;"><i class="fas fa-user"></i> Member</th>
                            <th style="font-size: 20px;"><i class="fas fa-link"></i> Reference</th>
                            <th style="font-size: 20px;"><i class="fas fa-info-circle"></i> Details</th>
                            <th style="font-size: 20px;"><i class="fas fa-plus-circle"></i> Debit</th>
                            <th style="font-size: 20px;"><i class="fas fa-minus-circle"></i> Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($result->num_rows > 0): ?>
                            <?php while($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-size: 20px;"><?= date('M j, Y', strtotime($row['transaction_date'])) ?></td>
                                <td style="font-size: 20px;">
                                    <?php if($row['transaction_type'] == 'payment'): ?>
                                        <span class="badge badge-payment" style="font-size: 20px;">
                                            <i class="fas fa-credit-card"></i> Payment
                                        </span>
                                    <?php elseif($row['transaction_type'] == 'receipt'): ?>
                                        <span class="badge badge-receipt" style="font-size: 20px;">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-interest" style="font-size: 20px;">
                                            <i class="fas fa-percentage" ></i> Interest
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 20px;"><span class="member-id" style="font-size: 20px;">#<?= $row['member_id'] ?></span></td>
                                <td class="reference" style="font-size: 20px;">
                                    <?= ucfirst($row['transaction_type']) ?> #<?= $row['reference_id'] ?>
                                    <br><small><?= $row['reference_type'] ?></small>
                                </td>
                                <td class="details" style="font-size: 20px;"><?= $row['details'] ? $row['details'] : '&mdash;' ?></td>
                                <td  style="font-size: 20px;" class="amount <?= $row['debit_amount'] > 0 ? 'debit' : 'zero-amount' ?>">
                                    <?= $row['debit_amount'] > 0 ? number_format($row['debit_amount'], 2) : '0.00' ?>
                                </td>
                                <td  style="font-size: 20px;" class="amount <?= $row['credit_amount'] > 0 ? 'credit' : 'zero-amount' ?>">
                                    <?= $row['credit_amount'] > 0 ? number_format($row['credit_amount'], 2) : '0.00' ?>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="7" class="empty-state" style="font-size: 20px;">
                                <i class="fas fa-inbox"></i>
                                <div>No transactions found for the selected period</div>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot>
                        <tr class="totals-row" style="font-size: 20px;">
                            <td colspan="5" style="text-align: right;" style="font-size: 20px;" >
                                <i class="fas fa-calculator" style="font-size: 20px;"></i> Totals:
                            </td>
                            <td class="amount debit" style="font-size: 20px;"><?= number_format($totals['total_debit'], 2) ?></td>
                            <td class="amount credit" style="font-size: 20px;"><?= number_format($totals['total_credit'], 2) ?></td>
                        </tr>
                        <tr class="net-position <?= $net_liquidity >= 0 ? 'surplus' : 'deficit' ?>">
                            <td colspan="5" style="text-align: right;" style="font-size: 20px;">
                                <i class="fas fa-balance-scale"></i> Net Position:
                            </td>
                            <td colspan="2" style="font-size: 20px;">
                                <?= number_format($net_liquidity, 2) ?>
                                <br ><small style="font-size: 20px;">(<?= $net_liquidity >= 0 ? 'Surplus' : 'Deficit' ?>)</small>
                            </td>
                        </tr>
                        <tr class="period-row" style="font-size: 20px;">
                            <td colspan="7">
                                <i class="fas fa-calendar-range"></i>
                                <?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Print-only footer -->
        <div class="print-footer" style="display: none;">
            General Journal Report | Sarvodaya Financial System | Page 1
        </div>
    </div>

    <script>
        // Animation delays for table rows
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${(index + 1) * 0.1}s`;
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>