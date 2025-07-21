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
    $filterDateFrom = isset($_GET['filter_date_from']) ? $_GET['filter_date_from'] : '';
    $filterDateTo = isset($_GET['filter_date_to']) ? $_GET['filter_date_to'] : '';

    // Base query for all payments
    $baseQuery = "
        SELECT 
            payments.id AS payment_id,
            members.id AS member_id,
            members.name AS member_name,
            payments.payment_type,
            payments.amount,
            payments.payment_date
        FROM payments
        JOIN members ON payments.member_id = members.id
    ";

    // Apply filters if provided
    $query = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $query .= " AND payments.payment_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        $query .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    if (!empty($filterDateFrom)) {
        $query .= " AND payments.payment_date >= '" . $conn->real_escape_string($filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $query .= " AND payments.payment_date <= '" . $conn->real_escape_string($filterDateTo) . " 23:59:59'";
    }
    
    $query .= " ORDER BY payments.payment_date DESC";
    $result = $conn->query($query);

    // Create PDF with colorful header
    class PDF extends FPDF {
        private $colWidths = [25, 25, 50, 40, 30, 35]; // Adjusted column widths
        
        function Header() {
            // Top border with orange color
            $this->SetDrawColor(255, 165, 0);
            $this->SetLineWidth(1);
            $this->Line(10, 10, $this->GetPageWidth()-10, 10);
            
            // Organization Header - Main Title (Orange)
            $this->SetY(15);
            $this->SetFont('Arial','B',16);
            $this->SetTextColor(230, 81, 0); // Dark Orange
            $this->Cell(0,8,'SARVODAYA SHRAMADHANA SOCIETY',0,1,'C');
            
            // Subtitle (Dark Blue)
            $this->SetFont('Arial','',12);
            $this->SetTextColor(0, 51, 102); // Dark Blue
            $this->Cell(0,6,'Samaghi Sarvodaya Shramadhana Society',0,1,'C');
            $this->Cell(0,6,'Kubaloluwa, Veyangoda',0,1,'C');
            
            // Contact Info (Teal)
            $this->SetFont('Arial','',11);
            $this->SetTextColor(0, 128, 128); // Teal
            $this->Cell(0,6,'077 690 6605 | info@sarvodayabank.com',0,1,'C');
            
            // Registration (Gray)
            $this->SetFont('Arial','I',10);
            $this->SetTextColor(128, 128, 128); // Gray
            $this->Cell(0,6,'Reg. No: 12345/SS/2020',0,1,'C');
            
            // Report Title (Orange)
            $this->Ln(5);
            $this->SetFont('Arial','B',14);
            $this->SetTextColor(230, 81, 0); // Dark Orange
            $this->Cell(0,8,'Payment Details Report',0,1,'C');
            $this->Ln(5);
            
            // Print filters if any (Dark Blue)
            global $filterType, $filterMemberNumber, $filterDateFrom, $filterDateTo;
            if (!empty($filterType) || !empty($filterMemberNumber) || !empty($filterDateFrom) || !empty($filterDateTo)) {
                $this->SetFont('Arial','I',10);
                $this->SetTextColor(0, 51, 102); // Dark Blue
                $this->Cell(0,6,'FILTERS APPLIED:',0,1,'L');
                $this->SetFont('Arial','',10);
                
                if (!empty($filterType)) {
                    $this->Cell(0,6,'Payment Type: ' . ucfirst(str_replace('_', ' ', $filterType)),0,1,'L');
                }
                if (!empty($filterMemberNumber)) {
                    $this->Cell(0,6,'Member Number: ' . $filterMemberNumber,0,1,'L');
                }
                if (!empty($filterDateFrom)) {
                    $this->Cell(0,6,'Date From: ' . date('d M Y', strtotime($filterDateFrom)),0,1,'L');
                }
                if (!empty($filterDateTo)) {
                    $this->Cell(0,6,'Date To: ' . date('d M Y', strtotime($filterDateTo)),0,1,'L');
                }
                $this->Ln(5);
            }
        }
        
        function Footer() {
            // Position at 3 cm from bottom
            $this->SetY(-40);
            
            // Divider line (Orange)
            $this->SetDrawColor(255, 165, 0);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), $this->GetPageWidth()-10, $this->GetY());
            $this->Ln(10);
            
            // Date field (left aligned - Dark Blue)
            $this->SetFont('Arial','',11);
            $this->SetTextColor(0, 51, 102); // Dark Blue
            $this->Cell(80, 8, 'Date: _________________________', 0, 0, 'L');
            
            // Manager signature field (right aligned - Dark Blue)
            $this->Cell(0, 8, 'Manager Signature: _________________________', 0, 0, 'R');
            $this->Ln(15);
            
            // Standard footer (Gray)
            $this->SetFont('Arial','I',8);
            $this->SetTextColor(128, 128, 128); // Gray
            $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
            $this->SetY(-15);
            $this->Cell(0,10,'Generated on: '.date('d M Y, h:i A'),0,0,'L');
            $this->Cell(0,10,'Computer Generated Report',0,0,'R');
        }
        
        function ImprovedTable($header, $data) {
            // Set column widths
            $w = $this->colWidths;
            
            // Header (Orange background with white text)
            $this->SetFont('Arial','B',12);
            $this->SetFillColor(255, 165, 0); // Orange
            $this->SetTextColor(255); // White
            $this->SetDrawColor(200, 200, 200); // Light gray border
            for($i=0;$i<count($header);$i++) {
                $this->Cell($w[$i],8,$header[$i],1,0,'C',true);
            }
            $this->Ln();
            
            // Data (Alternating row colors)
            $this->SetFont('Arial','',11);
            $this->SetTextColor(0); // Black text
            $fill = false;
            
            foreach($data as $row) {
                // Check if we need a new page
                if($this->GetY() > 250) {
                    $this->AddPage();
                }
                
                // Alternate row colors
                $fill = !$fill;
                $this->SetFillColor($fill ? 240 : 255); // Light gray or white
                
                $this->Cell($w[0],7,$row['payment_id'],'LR',0,'C',$fill);
                $this->Cell($w[1],7,$row['member_id'],'LR',0,'C',$fill);
                $this->Cell($w[2],7,$row['member_name'],'LR',0,'L',$fill);
                $this->Cell($w[3],7,ucfirst(str_replace('_', ' ', $row['payment_type'])),'LR',0,'L',$fill);
                $this->Cell($w[4],7,number_format($row['amount'], 2),'LR',0,'R',$fill);
                $this->Cell($w[5],7,date('d M Y', strtotime($row['payment_date'])),'LR',0,'C',$fill);
                $this->Ln();
            }
            
            // Closing line
            $this->Cell(array_sum($w),0,'','T');
            $this->Ln(5);
        }
    }

    // Create new PDF
    $pdf = new PDF('L');
    $pdf->AliasNbPages();
    $pdf->AddPage();
    
    // Column titles
    $header = array('Payment ID', 'Member ID', 'Member Name', 'Payment Type', 'Amount', 'Date');
    
    // Prepare data
    $data = array();
    $totalAmount = 0;
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $totalAmount += $row['amount'];
            $data[] = $row;
        }
    }
    
    // Print table
    $pdf->ImprovedTable($header, $data);
    $pdf->Ln(8);
    
    // Total section (Dark Blue)
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(0, 51, 102); // Dark Blue
    $pdf->Cell(0,10,'Total Records: '.count($data),0,0,'L');
    $pdf->Cell(0,10,'Total Amount: Rs. '.number_format($totalAmount, 2),0,0,'R');
    
    // Output PDF
    $pdf->Output('D', 'Payment_Report_'.date('Ymd').'.pdf');
    exit;
}

// Initialize variables for HTML view
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filterMemberNumber = isset($_GET['filter_member_number']) ? trim($_GET['filter_member_number']) : '';
$filterDateFrom = isset($_GET['filter_date_from']) ? $_GET['filter_date_from'] : '';
$filterDateTo = isset($_GET['filter_date_to']) ? $_GET['filter_date_to'] : '';

// Base query for all payments
$baseQuery = "
    SELECT 
        payments.id AS payment_id,
        members.id AS member_id,
        members.name AS member_name,
        payments.payment_type,
        payments.amount,
        payments.payment_date
    FROM payments
    JOIN members ON payments.member_id = members.id
";

// Default query with no filters
$query = $baseQuery . " ORDER BY payments.payment_date DESC";
$result = $conn->query($query);

// Apply filters if provided
if (!empty($filterType) || !empty($filterMemberNumber) || !empty($filterDateFrom) || !empty($filterDateTo)) {
    $filterQuery = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $filterQuery .= " AND payments.payment_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        $filterQuery .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    if (!empty($filterDateFrom)) {
        $filterQuery .= " AND payments.payment_date >= '" . $conn->real_escape_string($filterDateFrom) . "'";
    }
    
    if (!empty($filterDateTo)) {
        $filterQuery .= " AND payments.payment_date <= '" . $conn->real_escape_string($filterDateTo) . " 23:59:59'";
    }
    
    $filterQuery .= " ORDER BY payments.payment_date DESC";
    $result = $conn->query($filterQuery);
}

// Get payment types for filter dropdown
$typesQuery = "SELECT DISTINCT payment_type FROM payments ORDER BY payment_type";
$typesResult = $conn->query($typesQuery);
$paymentTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $paymentTypes[] = $type['payment_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payments - Sarvodaya Shramadhana Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-orange: #e65100;
            --secondary-orange: #ffa726;
            --dark-blue: #003366;
            --teal: #008080;
            --light-gray: #f8f9fa;
            --medium-gray: #dddddd;
            --dark-gray: #888888;
        }
        
        body {
            background-color: var(--light-gray);
            padding: 20px;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .header-section {
            background-color: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 2px solid var(--secondary-orange);
        }
        .organization-title {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--primary-orange);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .organization-subtitle {
            font-size: 1.2rem;
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-weight: 600;
        }
        .organization-contact {
            font-size: 1rem;
            color: var(--teal);
            margin-bottom: 8px;
        }
        .organization-reg {
            font-size: 0.9rem;
            color: var(--dark-gray);
            font-style: italic;
        }
        .header-divider {
            border: 1px solid var(--secondary-orange);
            margin: 15px auto;
            width: 80%;
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
            border-bottom: 1px solid var(--medium-gray);
        }
        .table-custom th {
            background-color: var(--secondary-orange);
            color: white;
        }
        .table-custom tbody tr:hover {
            background-color: #ffe0b2;
        }
        .btn-action {
            background-color: var(--secondary-orange);
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
            border: 2px solid var(--secondary-orange);
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
        
        /* Report title in web interface */
        .report-title {
            color: var(--primary-orange);
            margin-top: 15px;
            margin-bottom: 0;
        }
        
        /* Active filters alert */
        .alert-info {
            background-color: #e7f5fe;
            border-color: #b8e2fb;
            color: var(--dark-blue);
        }

        /* Date range filters */
        .date-filter-section {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }
        .date-filter-title {
            font-weight: 600;
            color: var(--dark-blue);
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        .quick-date-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 15px;
            
            
        }
        .quick-date-btn {
            background-color: var(--teal);
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .quick-date-btn:hover {
            background-color: #006666;
            transform: scale(1.05);
            
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Organization Header -->
        <div class="header-section">
            <div class="organization-title">SARVODAYA SHRAMADHANA SOCIETY</div>
            <div class="organization-subtitle">Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda</div>
            <div class="organization-contact">077 690 6605 | info@sarvodayabank.com</div>
            <div class="organization-reg">Reg. No: 12345/SS/2020</div>
            <hr class="header-divider">
            <h2 class="report-title">Payment Records Report</h2>
        </div>

        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <!-- Date Range Filter Section -->
                <div class="col-12">
                    <div class="date-filter-section">
                        <div class="date-filter-title">Date Range Filter</div>
                        
                        <!-- Quick Date Buttons -->
                        <div class="quick-date-buttons" >
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('today')"  >Today</button>
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('yesterday')">Yesterday</button>
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('this_week')">This Week</button>
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('last_week')">Last Week</button>
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('this_month')">This Month</button>
                            <button type="button"  class="quick-date-btn" onclick="setDateRange('last_month')">Last Month</button>
                            <button type="button" class="quick-date-btn" onclick="setDateRange('this_year')">This Year</button>
                            <button type="button"  class="quick-date-btn" onclick="clearDates()">Clear Dates</button>
                        </div>
                        
                        <!-- Custom Date Range -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="filter_date_from" class="form-label" style="font-size: 20px;">From Date:</label>
                                <input type="date" name="filter_date_from" id="filter_date_from" style="font-size: 20px;"
                                       class="form-control <?php echo (!empty($filterDateFrom)) ? 'active-filter' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($filterDateFrom); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="filter_date_to" class="form-label" style="font-size: 20px;">To Date:</label>
                                <input type="date" name="filter_date_to" id="filter_date_to" style="font-size: 20px;"
                                       class="form-control <?php echo (!empty($filterDateTo)) ? 'active-filter' : ''; ?>" 
                                       value="<?php echo htmlspecialchars($filterDateTo); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Type Filter -->
                <div class="col-md-4">
                    <label for="filter_type" class="form-label" style="font-size: 20px;">Filter by Payment Type:</label>
                    <select name="filter_type" id="filter_type" style="font-size: 20px;" class="form-select <?php echo (!empty($filterType)) ? 'active-filter' : ''; ?>">
                        <option value="" style="font-size: 20px;">All Payment Types</option>
                        <?php foreach ($paymentTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filterType == $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Direct Member Number Input -->
                <div class="col-md-4">
                    <label for="filter_member_number" class="form-label" style="font-size: 20px;">Filter by Member Number:</label>
                    <input type="text" name="filter_member_number" id="filter_member_number" style="font-size: 20px;"
                           class="form-control <?php echo (!empty($filterMemberNumber)) ? 'active-filter' : ''; ?>" 
                           placeholder="Enter Member ID" 
                           value="<?php echo htmlspecialchars($filterMemberNumber); ?>">
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn-action me-2" style="font-size: 20px;">
                        <i class="bi bi-filter" style="font-size: 20px;"></i> Apply Filters
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-action" style="font-size: 20px;">
                        <i class="bi bi-x-circle" style="font-size: 20px;"></i> Reset
                    </a>
                    <button type="submit" name="generate_pdf" value="1" class="btn-action btn-pdf ms-2">
                        <i class="bi bi-file-earmark-pdf" style="font-size: 20px;"></i> Generate PDF
                    </button>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($filterType) || !empty($filterMemberNumber) || !empty($filterDateFrom) || !empty($filterDateTo)): ?>
        <div class="alert alert-info mb-3">
            <strong>Active Filters:</strong> 
            <?php 
            $appliedFilters = [];
            if (!empty($filterType)) {
                $appliedFilters[] = "Payment Type: " . ucfirst(str_replace('_', ' ', $filterType));
            }
            if (!empty($filterMemberNumber)) {
                $appliedFilters[] = "Member Number: " . htmlspecialchars($filterMemberNumber);
            }
            if (!empty($filterDateFrom)) {
                $appliedFilters[] = "From Date: " . date('d M Y', strtotime($filterDateFrom));
            }
            if (!empty($filterDateTo)) {
                $appliedFilters[] = "To Date: " . date('d M Y', strtotime($filterDateTo));
            }
            echo implode(' | ', $appliedFilters);
            ?>
        </div>
        <?php endif; ?>

        <!-- Payments Table -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3 style="color: var(--dark-blue); margin: 0;">Payment Details</h3>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th style="font-size: 20px;">Payment ID</th>
                            <th style="font-size: 20px;">Member ID</th>
                            <th style="font-size: 20px;">Member Name</th>
                            <th style="font-size: 20px;">Payment Type</th>
                            <th style="font-size: 20px;">Amount (Rs.)</th>
                            <th style="font-size: 20px;">Payment Date</th>
                            <th style="font-size: 20px;">Actions</th>
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
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['payment_id']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['member_id']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['payment_type']))); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(number_format($row['amount'], 2)); ?></td>
                                    <td style="font-size: 20px;"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['payment_date']))); ?></td>
                                    <td style="font-size: 20px;">
                                        <div class="action-buttons">
                                            <a href="view_payment_detail.php?payment_id=<?php echo htmlspecialchars($row['payment_id']); ?>" class="btn-action action-btn" onclick="event.stopPropagation();">
                                                <i class="bi bi-eye" style="font-size: 20px;"></i> View
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center" style="font-size: 20px;">No payments found matching your filter criteria.</td>
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
                            <span style="font-size: 20px;">Total Records: <?php echo $result->num_rows; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-end" style="font-size: 20px;">
                        Total Amount: Rs. <?php echo number_format($totalAmount, 2); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make table rows clickable to view payment details
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-custom tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Only navigate if the click wasn't on a button or link
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const paymentId = this.querySelector('td:first-child').textContent;
                        window.location.href = 'view_payment_detail.php?payment_id=' + paymentId;
                    }
                });
                row.style.cursor = 'pointer';
            });
        });

        // Date range quick selection functions
        function setDateRange(period) {
            const today = new Date();
            const dateFromInput = document.getElementById('filter_date_from');
            const dateToInput = document.getElementById('filter_date_to');
            
            let fromDate, toDate;
            
            switch(period) {
                case 'today':
                    fromDate = toDate = today;
                    break;
                    
                case 'yesterday':
                    fromDate = toDate = new Date(today);
                    fromDate.setDate(today.getDate() - 1);
                    toDate.setDate(today.getDate() - 1);
                    break;
                    
                case 'this_week':
                    fromDate = new Date(today);
                    const dayOfWeek = today.getDay();
                    const diff = today.getDate() - dayOfWeek + (dayOfWeek === 0 ? -6 : 1); // Start from Monday
                    fromDate.setDate(diff);
                    toDate = today;
                    break;
                    
                case 'last_week':
                    const lastWeekEnd = new Date(today);
                    const lastWeekStart = new Date(today);
                    const currentDayOfWeek = today.getDay();
                    const daysToSubtractForStart = currentDayOfWeek === 0 ? 6 : currentDayOfWeek - 1;
                    lastWeekStart.setDate(today.getDate() - daysToSubtractForStart - 7);
                    lastWeekEnd.setDate(today.getDate() - daysToSubtractForStart - 1);
                    fromDate = lastWeekStart;
                    toDate = lastWeekEnd;
                    break;
                    
                case 'this_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                    toDate = today;
                    break;
                    
                case 'last_month':
                    fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                    break;
                    
                case 'this_year':
                    fromDate = new Date(today.getFullYear(), 0, 1);
                    toDate = today;
                    break;
                    
                default:
                    return;
            }
            
            // Format dates for input fields (YYYY-MM-DD)
            dateFromInput.value = formatDateForInput(fromDate);
            dateToInput.value = formatDateForInput(toDate);
            
            // Add active filter styling
            dateFromInput.classList.add('active-filter');
            dateToInput.classList.add('active-filter');
        }
        
        function clearDates() {
            const dateFromInput = document.getElementById('filter_date_from');
            const dateToInput = document.getElementById('filter_date_to');
            
            dateFromInput.value = '';
            dateToInput.value = '';
            
            // Remove active filter styling
            dateFromInput.classList.remove('active-filter');
            dateToInput.classList.remove('active-filter');
        }
        
        function formatDateForInput(date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
        
        // Add event listeners for date inputs to toggle active styling
        document.getElementById('filter_date_from').addEventListener('input', function() {
            if (this.value) {
                this.classList.add('active-filter');
            } else {
                this.classList.remove('active-filter');
            }
        });
        
        document.getElementById('filter_date_to').addEventListener('input', function() {
            if (this.value) {
                this.classList.add('active-filter');
            } else {
                this.classList.remove('active-filter');
            }
        });
        
        // Validate date range
        document.querySelector('form').addEventListener('submit', function(e) {
            const dateFrom = document.getElementById('filter_date_from').value;
            const dateTo = document.getElementById('filter_date_to').value;
            
            if (dateFrom && dateTo && new Date(dateFrom) > new Date(dateTo)) {
                e.preventDefault();
                alert('From Date cannot be later than To Date. Please check your date range.');
                return false;
            }
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>