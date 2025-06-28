<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sarvodaya";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get date filter parameters
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Build WHERE clause for date filtering
$date_condition = "";
$date_params = "";
if (!empty($start_date) && !empty($end_date)) {
    $date_condition = "AND m.created_at BETWEEN '$start_date 00:00:00' AND '$end_date 23:59:59'";
    $date_params = "&start_date=$start_date&end_date=$end_date";
} elseif (!empty($start_date)) {
    $date_condition = "AND m.created_at >= '$start_date 00:00:00'";
    $date_params = "&start_date=$start_date";
} elseif (!empty($end_date)) {
    $date_condition = "AND m.created_at <= '$end_date 23:59:59'";
    $date_params = "&end_date=$end_date";
}

// Handle PDF download request
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    require('fpdf/fpdf.php');
    
    // Query to get account data with date filter
    $sql = "SELECT 
                sat.account_name, 
                COUNT(m.id) as member_count, 
                sat.minimum_balance, 
                sat.interest_rate
            FROM 
                savings_account_types sat
            LEFT JOIN 
                members m ON m.account_type = sat.id $date_condition
            GROUP BY 
                sat.id, sat.account_name, sat.minimum_balance, sat.interest_rate
            ORDER BY 
                member_count DESC";
    
    $result = $conn->query($sql);
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    
    // Header - Bank Name and Address
    $pdf->SetFont('Arial','B', 18);
    $pdf->SetTextColor(255, 140, 0);
    $pdf->Cell(0, 10, 'SARVODAYA SHRAMADHANA SOCIETY', 0, 1, 'C');
    
    $pdf->SetFont('Arial','', 12);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda.', 0, 1, 'C');
    $pdf->Cell(0, 6, 'Phone: 077 690 6605  |  Email: info@sarvodayabank.com', 0, 1, 'C');
    
    // Line separator
    $pdf->Ln(5);
    $pdf->SetLineWidth(0.5);
    $pdf->SetDrawColor(255, 140, 0);
    $pdf->Line(20, $pdf->GetY(), 190, $pdf->GetY());
    $pdf->Ln(10);
    
    // Report Title
    $pdf->SetFont('Arial','B',16);
    $pdf->SetFillColor(255, 140, 0);
    $pdf->SetTextColor(255);
    $pdf->Cell(0,10,'Savings Account Type Analysis',0,1,'C',true);
    
    // Add date range info if filtered
    if (!empty($start_date) || !empty($end_date)) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(0);
        $dateRange = "Date Range: ";
        if (!empty($start_date)) $dateRange .= "From $start_date ";
        if (!empty($end_date)) $dateRange .= "To $end_date";
        $pdf->Cell(0,10,$dateRange,0,1,'C');
    }
    
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(0);
    $pdf->SetFillColor(255, 200, 150);
    $pdf->Cell(70,10,'Account Type',1,0,'C',true);
    $pdf->Cell(40,10,'Members',1,0,'C',true);
    $pdf->Cell(40,10,'Min Balance',1,0,'C',true);
    $pdf->Cell(40,10,'Interest Rate',1,1,'C',true);
    
    // Table data
    $pdf->SetFont('Arial','',10);
    $pdf->SetFillColor(255, 240, 220);
    $fill = false;
    
    while($row = $result->fetch_assoc()) {
        $pdf->Cell(70,10,$row["account_name"],1,0,'L',$fill);
        $pdf->Cell(40,10,$row["member_count"],1,0,'C',$fill);
        $pdf->Cell(40,10,'Rs.'.number_format($row["minimum_balance"],2),1,0,'R',$fill);
        $pdf->Cell(40,10,$row["interest_rate"].'%',1,1,'C',$fill);
        $fill = !$fill;
    }
    
    // Add more space before signature and date
    $pdf->Ln(30);
    
    // Add date field on left side
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40,10,'Date:',0,0,'L');
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(50,10,date('Y-m-d'),0,0,'L');
    
    // Add signature line on right side
    $pdf->Cell(20,10,'',0,0); // Space between date and signature
    $pdf->SetFont('Arial','B',10);
    $pdf->Cell(40,10,'Manager signature:',0,1,'L');
    
    // Add more space between "Signature:" label and the line
    $pdf->Ln(10);
    
    // Add signature line
    $pdf->SetX(120); // Position for signature line
    $pdf->Cell(70,0,'','B',1,'L'); // Bottom border only for signature line
    
    // Output PDF
    $filename = 'savings_account_analysis_'.date('Y-m-d');
    if (!empty($start_date) || !empty($end_date)) {
        $filename .= '_filtered';
    }
    $pdf->Output('D', $filename.'.pdf');
    exit();
}

// Handle CSV download request
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    // Query to get account data with date filter
    $sql = "SELECT 
                sat.account_name, 
                COUNT(m.id) as member_count, 
                sat.minimum_balance, 
                sat.interest_rate
            FROM 
                savings_account_types sat
            LEFT JOIN 
                members m ON m.account_type = sat.id $date_condition
            GROUP BY 
                sat.id, sat.account_name, sat.minimum_balance, sat.interest_rate
            ORDER BY 
                member_count DESC";
    
    $result = $conn->query($sql);
    
    // Prepare CSV data
    $csvData = "Account Type,Member Count,Minimum Balance,Interest Rate\n";
    
    while($row = $result->fetch_assoc()) {
        $csvData .= '"' . str_replace('"', '""', $row["account_name"]) . '",';
        $csvData .= $row["member_count"] . ',';
        $csvData .= number_format($row["minimum_balance"], 2) . ',';
        $csvData .= $row["interest_rate"] . "\n";
    }
    
    // Output CSV
    $filename = 'savings_account_analysis_'.date('Y-m-d');
    if (!empty($start_date) || !empty($end_date)) {
        $filename .= '_filtered';
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'.csv"');
    echo $csvData;
    exit();
}

// Main page display
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Account Type Analysis - Sarvodaya Bank</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: rgb(255, 240, 220);
            margin: 0;
            padding: 20px;
            color: rgb(50, 50, 50);
        }
        .container {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header Styles - matching receipt page */
        .page-header {
            border-bottom: 2px solid #ff8c00;
            padding-bottom: 15px;
            margin-bottom: 30px;
            text-align: center;
        }
        .bank-name {
            font-size: 24px;
            font-weight: bold;
            color: #ff8c00;
            margin-bottom: 5px;
        }
        .bank-address {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .page-title {
            font-size: 20px;
            font-weight: bold;
            color: #ff8c00;
            margin-top: 15px;
        }
        
        .filter-section {
            background-color: rgb(255, 248, 240);
            border: 1px solid rgb(255, 180, 100);
            border-radius: 5px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-title {
            color: rgb(255, 140, 0);
            font-weight: bold;
            margin-bottom: 15px;
            font-size: 16px;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: center;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-group label {
            font-weight: bold;
            color: rgb(80, 80, 80);
            font-size: 14px;
        }
        .filter-group input[type="date"] {
            padding: 8px;
            border: 1px solid rgb(255, 180, 100);
            border-radius: 3px;
            font-size: 14px;
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .filter-btn {
            padding: 10px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s ease;
        }
        .filter-btn.apply {
            background-color: rgb(255, 140, 0);
            color: white;
        }
        .filter-btn.apply:hover {
            background-color: rgb(230, 120, 0);
        }
        .filter-btn.clear {
            background-color: rgb(220, 220, 220);
            color: rgb(80, 80, 80);
        }
        .filter-btn.clear:hover {
            background-color: rgb(200, 200, 200);
        }
        .active-filter {
            background-color: rgb(255, 252, 245);
            border: 1px solid rgb(255, 140, 0);
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .active-filter strong {
            color: rgb(255, 140, 0);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid rgb(255, 180, 100);
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: rgb(255, 160, 50);
            color: white;
        }
        .chart {
            margin-top: 20px;
            text-align: center;
        }
        .download-btns {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 30px;
        }
        .download-btn {
            padding: 12px 20px;
            background-color: rgb(255, 140, 0);
            color: white;
            text-align: center;
            text-decoration: none;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .download-btn:hover {
            background-color: rgb(230, 120, 0);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        .download-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        @media (max-width: 600px) {
            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }
            .filter-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header Section - matching receipt page -->
        <div class="page-header">
            <div class="bank-name">SARVODAYA SHRAMADHANA SOCIETY</div>
            <div class="bank-address">
                Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda.<br>
                Phone: 077 690 6605 | Email: info@sarvodayabank.com
            </div>
            <div class="page-title">SAVINGS ACCOUNT TYPE ANALYSIS</div>
        </div>
        
        <!-- Date Filter Section -->
        <div class="filter-section">
            <div class="filter-title">📅 Filter by Member Registration Date</div>
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn apply">Apply Filter</button>
                    <a href="?" class="filter-btn clear">Clear Filter</a>
                </div>
            </form>
        </div>

        <?php if (!empty($start_date) || !empty($end_date)): ?>
        <div class="active-filter">
            <strong>Active Filter:</strong> 
            <?php 
            if (!empty($start_date) && !empty($end_date)) {
                echo "Members registered between " . date('F j, Y', strtotime($start_date)) . " and " . date('F j, Y', strtotime($end_date));
            } elseif (!empty($start_date)) {
                echo "Members registered from " . date('F j, Y', strtotime($start_date)) . " onwards";
            } elseif (!empty($end_date)) {
                echo "Members registered up to " . date('F j, Y', strtotime($end_date));
            }
            ?>
        </div>
        <?php endif; ?>
        
        <?php
        // Query to count members by account type with date filter
        $sql = "SELECT 
                    sat.account_name, 
                    COUNT(m.id) as member_count, 
                    sat.minimum_balance, 
                    sat.interest_rate
                FROM 
                    savings_account_types sat
                LEFT JOIN 
                    members m ON m.account_type = sat.id $date_condition
                GROUP BY 
                    sat.id, sat.account_name, sat.minimum_balance, sat.interest_rate
                ORDER BY 
                    member_count DESC";

        $result = $conn->query($sql);

        if ($result->num_rows > 0) {
            echo "<table>
                    <tr>
                        <th>Account Type</th>
                        <th>Member Count</th>
                        <th>Minimum Balance(Rs.)</th>
                        <th>Interest Rate</th>
                    </tr>";

            // Store data for chart
            $chartData = [];
            $totalMembers = 0;

            while($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>" . htmlspecialchars($row["account_name"]) . "</td>
                        <td>" . $row["member_count"] . "</td>
                        <td>Rs. " . number_format($row["minimum_balance"], 2) . "</td>
                        <td>" . $row["interest_rate"] . "%</td>
                      </tr>";
                
                $chartData[] = [
                    'name' => $row["account_name"],
                    'count' => $row["member_count"]
                ];
                $totalMembers += $row["member_count"];
            }
            echo "</table>";

            // Display total members
            if (!empty($start_date) || !empty($end_date)) {
                echo "<p style='text-align: center; margin-top: 15px; font-weight: bold; color: rgb(255, 140, 0);'>Total Members in Filter: $totalMembers</p>";
            }

            // Prepare chart data for JavaScript
            $chartDataJson = json_encode($chartData);
        } else {
            echo "<p>No account types found for the selected date range.</p>";
        }
        $conn->close();
        ?>

        <div class="chart">
            <canvas id="accountTypeChart"></canvas>
        </div>
        
        <div class="download-btns">
            <a href="?download=pdf<?php echo $date_params; ?>" class="download-btn">Download as PDF</a>
            
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script>
        // Chart.js visualization
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('accountTypeChart').getContext('2d');
            var chartData = <?php echo $chartDataJson ?? '[]'; ?>;

            if (chartData.length > 0) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.map(item => item.name),
                        datasets: [{
                            label: 'Number of Members',
                            data: chartData.map(item => item.count),
                            backgroundColor: 'rgb(255, 140, 0)',
                            borderColor: 'rgb(255, 100, 0)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Membership Distribution by Account Type'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Members'
                                }
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>