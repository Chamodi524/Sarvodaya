<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if PDF generation is requested
if (isset($_GET['generate_pdf'])) {
    require('fpdf/fpdf.php');
    
    // Initialize variables
    $filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
    $filterMemberNumber = isset($_GET['filter_member_number']) ? trim($_GET['filter_member_number']) : '';
    $dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
    $customDateFrom = isset($_GET['custom_date_from']) ? $_GET['custom_date_from'] : '';
    $customDateTo = isset($_GET['custom_date_to']) ? $_GET['custom_date_to'] : '';
    
    // Base query for all receipts
    $baseQuery = "
        SELECT 
            receipts.id AS receipt_id,
            members.id AS member_id,
            members.name AS member_name,
            receipts.receipt_type,
            receipts.amount,
            receipts.receipt_date
        FROM receipts
        JOIN members ON receipts.member_id = members.id
    ";
    
    // Apply filters if provided
    $query = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $query .= " AND receipts.receipt_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        $query .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    // Add date filters
    if (!empty($dateFilter)) {
        switch ($dateFilter) {
            case 'today':
                $query .= " AND DATE(receipts.receipt_date) = CURDATE()";
                break;
            case 'yesterday':
                $query .= " AND DATE(receipts.receipt_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $query .= " AND YEARWEEK(receipts.receipt_date, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'last_week':
                $query .= " AND YEARWEEK(receipts.receipt_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
                break;
            case 'this_month':
                $query .= " AND YEAR(receipts.receipt_date) = YEAR(CURDATE()) AND MONTH(receipts.receipt_date) = MONTH(CURDATE())";
                break;
            case 'last_month':
                $query .= " AND YEAR(receipts.receipt_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(receipts.receipt_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case 'this_year':
                $query .= " AND YEAR(receipts.receipt_date) = YEAR(CURDATE())";
                break;
            case 'last_year':
                $query .= " AND YEAR(receipts.receipt_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
                break;
            case 'custom':
                if (!empty($customDateFrom) && !empty($customDateTo)) {
                    $query .= " AND DATE(receipts.receipt_date) BETWEEN '" . $conn->real_escape_string($customDateFrom) . "' AND '" . $conn->real_escape_string($customDateTo) . "'";
                } elseif (!empty($customDateFrom)) {
                    $query .= " AND DATE(receipts.receipt_date) >= '" . $conn->real_escape_string($customDateFrom) . "'";
                } elseif (!empty($customDateTo)) {
                    $query .= " AND DATE(receipts.receipt_date) <= '" . $conn->real_escape_string($customDateTo) . "'";
                }
                break;
        }
    }
    
    $query .= " ORDER BY receipts.receipt_date DESC";
    $result = $conn->query($query);
    
    // Create PDF in Landscape mode for better table fit
    $pdf = new FPDF('L', 'mm', 'A4');
    $pdf->AddPage();
    
    // Set font for title
    $pdf->SetFont('Arial', 'B', 16);
    
    // Title
    $pdf->Cell(0, 10, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 7, 'Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda', 0, 1, 'C');
    $pdf->Cell(0, 7, 'Receipt Details Report', 0, 1, 'C');
    $pdf->Ln(10);
    
    // Filters applied
    if (!empty($filterType) || !empty($filterMemberNumber) || !empty($dateFilter)) {
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 7, 'Filters Applied:', 0, 1, 'L');
        $pdf->SetFont('Arial', '', 12);
        
        $filters = [];
        if (!empty($filterType)) {
            $filters[] = "Receipt Type: " . ucfirst(str_replace('_', ' ', $filterType));
        }
        if (!empty($filterMemberNumber)) {
            $filters[] = "Member Number: " . $filterMemberNumber;
        }
        if (!empty($dateFilter)) {
            $dateFilterText = ucfirst(str_replace('_', ' ', $dateFilter));
            if ($dateFilter == 'custom' && (!empty($customDateFrom) || !empty($customDateTo))) {
                if (!empty($customDateFrom) && !empty($customDateTo)) {
                    $dateFilterText = "Custom Range: " . date('d M Y', strtotime($customDateFrom)) . " to " . date('d M Y', strtotime($customDateTo));
                } elseif (!empty($customDateFrom)) {
                    $dateFilterText = "From: " . date('d M Y', strtotime($customDateFrom));
                } elseif (!empty($customDateTo)) {
                    $dateFilterText = "Until: " . date('d M Y', strtotime($customDateTo));
                }
            }
            $filters[] = "Date Filter: " . $dateFilterText;
        }
        
        $pdf->MultiCell(0, 7, implode(', ', $filters), 0, 'L');
        $pdf->Ln(5);
    }
    
    // Report date
    $pdf->SetFont('Arial', 'I', 10);
    $pdf->Cell(0, 7, 'Report Generated: ' . date('d F Y, h:i A'), 0, 1, 'R');
    $pdf->Ln(5);
    
    // Table header - Adjusted column widths for perfect fit
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->SetFillColor(255, 167, 38); // Orange color
    $pdf->SetTextColor(255);
    
    $pdf->Cell(20, 10, 'Receipt ID', 1, 0, 'C', true);
    $pdf->Cell(25, 10, 'Member ID', 1, 0, 'C', true);
    $pdf->Cell(60, 10, 'Member Name', 1, 0, 'C', true);
    $pdf->Cell(50, 10, 'Receipt Type', 1, 0, 'C', true);
    $pdf->Cell(30, 10, 'Amount (Rs.)', 1, 0, 'C', true);
    $pdf->Cell(40, 10, 'Receipt Date', 1, 1, 'C', true);
    
    // Table data
    $pdf->SetFont('Arial', '', 10);
    $pdf->SetTextColor(0);
    $totalAmount = 0;
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $totalAmount += $row['amount'];
            
            $pdf->Cell(20, 8, $row['receipt_id'], 1, 0, 'C');
            $pdf->Cell(25, 8, $row['member_id'], 1, 0, 'C');
            $pdf->Cell(60, 8, substr($row['member_name'], 0, 30), 1, 0, 'L');
            $pdf->Cell(50, 8, ucfirst(str_replace('_', ' ', substr($row['receipt_type'], 0, 25))), 1, 0, 'L');
            $pdf->Cell(30, 8, number_format($row['amount'], 2), 1, 0, 'R');
            $pdf->Cell(40, 8, date('d M Y', strtotime($row['receipt_date'])), 1, 1, 'C');
        }
    } else {
        $pdf->Cell(225, 10, 'No receipts found matching your filter criteria.', 1, 1, 'C');
    }
    
    // Total row
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(195, 10, 'Total Amount:', 1, 0, 'R');
    $pdf->Cell(30, 10, number_format($totalAmount, 2), 1, 1, 'R');
    
    // Count row
    $pdf->Cell(195, 10, 'Total Records:', 1, 0, 'R');
    $pdf->Cell(30, 10, ($result) ? $result->num_rows : 0, 1, 1, 'R');
    
    // Signature section with date left and signature right
    $pdf->Ln(15);
    
    // Date on left
    $pdf->SetFont('Arial', '', 10);
    $pdf->Cell(40, 5, 'Date: ' . date('d/m/Y'), 0, 0, 'L');
    
    // Signature on right
    $pdf->SetX(-60); // Move to right side
    $pdf->Cell(40, 5, '_________________________', 0, 1, 'R');
    $pdf->SetX(-60);
    $pdf->Cell(40, 5, 'Manager', 0, 1, 'R');
    $pdf->SetX(-60);
    $pdf->Cell(40, 5, 'Sarvodaya Shramadhana Society', 0, 1, 'R');
    
    // Output PDF
    $pdf->Output('Receipt_Report_'.date('Ymd_His').'.pdf', 'D');
    exit;
}

// Initialize variables for HTML view
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filterMemberNumber = isset($_GET['filter_member_number']) ? trim($_GET['filter_member_number']) : '';
$dateFilter = isset($_GET['date_filter']) ? $_GET['date_filter'] : '';
$customDateFrom = isset($_GET['custom_date_from']) ? $_GET['custom_date_from'] : '';
$customDateTo = isset($_GET['custom_date_to']) ? $_GET['custom_date_to'] : '';

// Base query for all receipts
$baseQuery = "
    SELECT 
        receipts.id AS receipt_id,
        members.id AS member_id,
        members.name AS member_name,
        receipts.receipt_type,
        receipts.amount,
        receipts.receipt_date
    FROM receipts
    JOIN members ON receipts.member_id = members.id
";

// Default query with no filters
$query = $baseQuery . " ORDER BY receipts.receipt_date DESC";
$result = $conn->query($query);

// Apply filters if provided
if (!empty($filterType) || !empty($filterMemberNumber) || !empty($dateFilter)) {
    $filterQuery = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $filterQuery .= " AND receipts.receipt_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        $filterQuery .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    // Add date filters
    if (!empty($dateFilter)) {
        switch ($dateFilter) {
            case 'today':
                $filterQuery .= " AND DATE(receipts.receipt_date) = CURDATE()";
                break;
            case 'yesterday':
                $filterQuery .= " AND DATE(receipts.receipt_date) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)";
                break;
            case 'this_week':
                $filterQuery .= " AND YEARWEEK(receipts.receipt_date, 1) = YEARWEEK(CURDATE(), 1)";
                break;
            case 'last_week':
                $filterQuery .= " AND YEARWEEK(receipts.receipt_date, 1) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL 1 WEEK), 1)";
                break;
            case 'this_month':
                $filterQuery .= " AND YEAR(receipts.receipt_date) = YEAR(CURDATE()) AND MONTH(receipts.receipt_date) = MONTH(CURDATE())";
                break;
            case 'last_month':
                $filterQuery .= " AND YEAR(receipts.receipt_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND MONTH(receipts.receipt_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
                break;
            case 'this_year':
                $filterQuery .= " AND YEAR(receipts.receipt_date) = YEAR(CURDATE())";
                break;
            case 'last_year':
                $filterQuery .= " AND YEAR(receipts.receipt_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 YEAR))";
                break;
            case 'custom':
                if (!empty($customDateFrom) && !empty($customDateTo)) {
                    $filterQuery .= " AND DATE(receipts.receipt_date) BETWEEN '" . $conn->real_escape_string($customDateFrom) . "' AND '" . $conn->real_escape_string($customDateTo) . "'";
                } elseif (!empty($customDateFrom)) {
                    $filterQuery .= " AND DATE(receipts.receipt_date) >= '" . $conn->real_escape_string($customDateFrom) . "'";
                } elseif (!empty($customDateTo)) {
                    $filterQuery .= " AND DATE(receipts.receipt_date) <= '" . $conn->real_escape_string($customDateTo) . "'";
                }
                break;
        }
    }
    
    $filterQuery .= " ORDER BY receipts.receipt_date DESC";
    $result = $conn->query($filterQuery);
}

// Get receipt types for filter dropdown
$typesQuery = "SELECT DISTINCT receipt_type FROM receipts ORDER BY receipt_type";
$typesResult = $conn->query($typesQuery);
$receiptTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $receiptTypes[] = $type['receipt_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Receipts - Sarvodaya Shramadhana Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .organization-header {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .organization-header h1 {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .organization-header .subtitle {
            font-size: 1.1rem;
            margin-bottom: 15px;
            opacity: 0.95;
        }
        .organization-header .contact-info {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .organization-header .reg-info {
            font-size: 0.9rem;
            opacity: 0.85;
            font-style: italic;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
        }
        .table-custom th,
        .table-custom td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table-custom th {
            background-color: #ffa726;
            color: white;
        }
        .table-custom tbody tr:hover {
            background-color: #ffe0b2;
        }
        .btn-action {
            background-color: #ffa726;
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
            background-color: #fb8c00;
            transform: scale(1.05);
            color: white;
        }
        .btn-pdf {
            background-color: #dc3545;
            color: white;
        }
        .btn-pdf:hover {
            background-color: #c82333;
        }
        .filter-section {
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .total-section {
            background-color: #fff8e1;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-weight: bold;
            text-align: right;
            font-size: 1.1em;
        }
        .active-filter {
            border: 2px solid #ffa726;
            background-color: #fff8e1;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            padding: 6px 12px;
            display: inline-block;
            text-align: center;
        }
        .custom-date-fields {
            display: none;
        }
        .custom-date-fields.show {
            display: block;
        }
        .date-filter-group {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            background-color: #f8f9fa;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            .filter-section {
                display: none;
            }
            .print-only {
                display: block !important;
            }
            .screen-only {
                display: none;
            }
            body {
                padding: 0;
                background-color: white;
            }
            .card {
                box-shadow: none;
            }
            .organization-header {
                background: #ffa726 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        .signature-section {
            display: none;
            background-color: #fff;
            padding: 30px 20px;
            margin-top: 30px;
            border-top: 2px solid #ffa726;
        }
        .signature-section .date-section p {
            margin-bottom: 10px;
            font-size: 1rem;
        }
        .signature-section .signature-section-content p {
            margin-bottom: 8px;
            font-size: 1rem;
        }
        .signature-line {
            height: 60px;
        }
        
        .screen-only {
            display: block;
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Organization Header -->
        <div class="organization-header">
            <h1>SARVODAYA SHRAMADHANA SOCIETY</h1>
            <div class="subtitle">Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda</div>
            <div class="contact-info">
                <i class="bi bi-telephone"></i> 077 690 6605 | 
                <i class="bi bi-envelope"></i> info@sarvodayabank.com
            </div>
            <div class="reg-info">Reg. No: 12345/SS/2020</div>
        </div>

        <h2 class="text-center mb-4 screen-only" style="color: #ffa726;">View Receipts</h2>
        <h2 class="text-center mb-4 print-only" style="color: #ffa726;">Receipt Details Report</h2>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="" class="row g-3">
                <!-- Receipt Type Filter -->
                <div class="col-md-3">
                    <label for="filter_type" class="form-label" style="font-size: 20px;">Filter by Receipt Type:</label>
                    <select name="filter_type" id="filter_type" style="font-size: 20px;" class="form-select <?php echo (!empty($filterType)) ? 'active-filter' : ''; ?>">
                        <option value="">All Receipt Types</option>
                        <?php foreach ($receiptTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filterType == $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Direct Member Number Input -->
                <div class="col-md-3">
                    <label for="filter_member_number" class="form-label" style="font-size: 20px;">Filter by Member Number:</label>
                    <input type="text" name="filter_member_number" id="filter_member_number" style="font-size: 20px;" 
                           class="form-control <?php echo (!empty($filterMemberNumber)) ? 'active-filter' : ''; ?>" 
                           placeholder="Enter Member ID" 
                           value="<?php echo htmlspecialchars($filterMemberNumber); ?>">
                </div>
                
                <!-- Date Filter Section -->
                <div class="col-md-6">
                    <div class="date-filter-group">
                        <label for="date_filter" class="form-label" style="font-size: 20px;"><i class="bi bi-calendar"></i> Filter by Date:</label>
                        <select name="date_filter" id="date_filter" style="font-size: 20px;" class="form-select <?php echo (!empty($dateFilter)) ? 'active-filter' : ''; ?>">
                            <option value="">All Dates</option>
                            <option value="today" <?php echo ($dateFilter == 'today') ? 'selected' : ''; ?>>Today</option>
                            <option value="yesterday" <?php echo ($dateFilter == 'yesterday') ? 'selected' : ''; ?>>Yesterday</option>
                            <option value="this_week" <?php echo ($dateFilter == 'this_week') ? 'selected' : ''; ?>>This Week</option>
                            <option value="last_week" <?php echo ($dateFilter == 'last_week') ? 'selected' : ''; ?>>Last Week</option>
                            <option value="this_month" <?php echo ($dateFilter == 'this_month') ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo ($dateFilter == 'last_month') ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_year" <?php echo ($dateFilter == 'this_year') ? 'selected' : ''; ?>>This Year</option>
                            <option value="last_year" <?php echo ($dateFilter == 'last_year') ? 'selected' : ''; ?>>Last Year</option>
                            <option value="custom" <?php echo ($dateFilter == 'custom') ? 'selected' : ''; ?>>Custom Date Range</option>
                        </select>
                        
                        <!-- Custom Date Range Fields -->
                        <div class="custom-date-fields <?php echo ($dateFilter == 'custom') ? 'show' : ''; ?>" id="customDateFields">
                            <div class="row mt-3">
                                <div class="col-md-6">
                                    <label for="custom_date_from" class="form-label" style="font-size: 16px;">From Date:</label>
                                    <input type="date" name="custom_date_from" id="custom_date_from" 
                                           class="form-control <?php echo (!empty($customDateFrom)) ? 'active-filter' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($customDateFrom); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label for="custom_date_to" class="form-label" style="font-size: 16px;">To Date:</label>
                                    <input type="date" name="custom_date_to" id="custom_date_to" 
                                           class="form-control <?php echo (!empty($customDateTo)) ? 'active-filter' : ''; ?>" 
                                           value="<?php echo htmlspecialchars($customDateTo); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-12 d-flex justify-content-center gap-3 mt-3">
                    <button type="submit" class="btn-action" style="font-size: 18px;">
                        <i class="bi bi-filter" style="font-size: 20px;"></i> Apply Filters
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-action btn-pdf" style="font-size: 18px;">
                        <i class="bi bi-x-circle" style="font-size: 20px;"></i> Reset All
                    </a>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($filterType) || !empty($filterMemberNumber) || !empty($dateFilter)): ?>
        <div class="alert alert-info mb-3 no-print">
            <strong><i class="bi bi-funnel"></i> Active Filters:</strong> 
            <?php 
            $appliedFilters = [];
            if (!empty($filterType)) {
                $appliedFilters[] = "Receipt Type: " . ucfirst(str_replace('_', ' ', $filterType));
            }
            if (!empty($filterMemberNumber)) {
                $appliedFilters[] = "Member Number: " . htmlspecialchars($filterMemberNumber);
            }
            if (!empty($dateFilter)) {
                $dateFilterText = ucfirst(str_replace('_', ' ', $dateFilter));
                if ($dateFilter == 'custom' && (!empty($customDateFrom) || !empty($customDateTo))) {
                    if (!empty($customDateFrom) && !empty($customDateTo)) {
                        $dateFilterText = "Custom Range: " . date('d M Y', strtotime($customDateFrom)) . " to " . date('d M Y', strtotime($customDateTo));
                    } elseif (!empty($customDateFrom)) {
                        $dateFilterText = "From: " . date('d M Y', strtotime($customDateFrom));
                    } elseif (!empty($customDateTo)) {
                        $dateFilterText = "Until: " . date('d M Y', strtotime($customDateTo));
                    }
                }
                $appliedFilters[] = "Date Filter: " . $dateFilterText;
            }
            echo implode(' | ', $appliedFilters);
            ?>
        </div>
        <?php endif; ?>

        <!-- Receipts Table -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3><i class="bi bi-receipt"></i> Receipt Records</h3>
                <div class="no-print">
                    <!-- Only PDF button remains -->
                    <a href="?generate_pdf=1&<?php echo http_build_query($_GET); ?>" class="btn-action btn-pdf" style="font-size: 18px;">
                        <i class="bi bi-file-earmark-pdf" style="font-size: 18px;"></i> Generate PDF
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="font-size: 20px;">Receipt ID</th>
                            <th style="font-size: 20px;">Member ID</th>
                            <th style="font-size: 20px;">Member Name</th>
                            <th style="font-size: 20px;">Receipt Type</th>
                            <th style="font-size: 20px;">Amount (Rs.)</th>
                            <th style="font-size: 20px;">Receipt Date</th>
                            <th class="no-print" style="font-size: 20px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalAmount = 0;
                        if ($result && $result->num_rows > 0): 
                        ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $totalAmount += $row['amount'];
                            ?>
                                <tr>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['receipt_id']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['member_id']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['receipt_type']))); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(number_format($row['amount'], 2)); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['receipt_date']))); ?></td>
                                    <td class="no-print" style="font-size: 20px;">
                                        <div class="action-buttons">
                                            <a href="view_receipt_detail.php?receipt_id=<?php echo htmlspecialchars($row['receipt_id']); ?>" class="btn-action action-btn">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="genarate_receipt_receipt.php?receipt_id=<?php echo htmlspecialchars($row['receipt_id']); ?>" class="btn-action action-btn">
                                                <i class="bi bi-file-earmark-pdf"></i> Receipt
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center" style="font-size: 18px; padding: 30px;">
                                    <i class="bi bi-search" style="font-size: 48px; color: #ccc;"></i><br>
                                    No receipts found matching your filter criteria.<br>
                                    <small class="text-muted">Try adjusting your filters or check back later.</small>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Total Section -->
            <div class="total-section">
                <div class="row">
                    <div class="col-md-6 text-start">
                        <?php if ($result): ?>
                            <span><i class="bi bi-list-ol"></i> Total Records: <?php echo $result->num_rows; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-end" >
                        Total Amount: Rs. <?php echo number_format($totalAmount, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date and Signature Section (Visible only in print) -->
        <div class="signature-section print-only">
            <div class="row mt-5">
                <div class="col-md-6 text-start">
                    <p><strong>Date:</strong> <?php echo date('d/m/Y'); ?></p>
                </div>
                <div class="col-md-6 text-end">
                    <div class="signature-section-content">
                        <div style="height: 60px;"></div>
                        <p>_________________________</p>
                        <p><small>Manager<br>Sarvodaya Shramadhana Society</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make table rows clickable to view receipt details
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-custom tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Only navigate if the click wasn't on a button or link
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const receiptId = this.querySelector('td:first-child').textContent;
                        if (receiptId && receiptId.trim() !== '') {
                            window.location.href = 'view_receipt_detail.php?receipt_id=' + receiptId;
                        }
                    }
                });
                row.style.cursor = 'pointer';
            });

            // Handle date filter dropdown change
            const dateFilterSelect = document.getElementById('date_filter');
            const customDateFields = document.getElementById('customDateFields');
            
            dateFilterSelect.addEventListener('change', function() {
                if (this.value === 'custom') {
                    customDateFields.classList.add('show');
                } else {
                    customDateFields.classList.remove('show');
                    // Clear custom date values if not using custom filter
                    document.getElementById('custom_date_from').value = '';
                    document.getElementById('custom_date_to').value = '';
                }
            });

            // Set max date for custom date inputs to today
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('custom_date_from').setAttribute('max', today);
            document.getElementById('custom_date_to').setAttribute('max', today);

            // Validate date range
            const dateFromInput = document.getElementById('custom_date_from');
            const dateToInput = document.getElementById('custom_date_to');
            
            dateFromInput.addEventListener('change', function() {
                dateToInput.setAttribute('min', this.value);
            });
            
            dateToInput.addEventListener('change', function() {
                dateFromInput.setAttribute('max', this.value);
            });
        });

        // Quick date filter shortcuts
        function setQuickDateFilter(filter) {
            document.getElementById('date_filter').value = filter;
            document.getElementById('customDateFields').classList.remove('show');
            // Auto-submit form
            document.querySelector('form').submit();
        }

        // Add keyboard shortcuts for common filters
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey) {
                switch(e.key) {
                    case '1':
                        e.preventDefault();
                        setQuickDateFilter('today');
                        break;
                    case '2':
                        e.preventDefault();
                        setQuickDateFilter('this_week');
                        break;
                    case '3':
                        e.preventDefault();
                        setQuickDateFilter('this_month');
                        break;
                }
            }
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>