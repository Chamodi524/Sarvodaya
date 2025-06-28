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

// Build date filter conditions
$date_condition = "";
$date_params = [];

if (!empty($start_date) && !empty($end_date)) {
    $date_condition = " AND l.application_date BETWEEN ? AND ?";
    $date_params = [$start_date . ' 00:00:00', $end_date . ' 23:59:59'];
} elseif (!empty($start_date)) {
    $date_condition = " AND l.application_date >= ?";
    $date_params = [$start_date . ' 00:00:00'];
} elseif (!empty($end_date)) {
    $date_condition = " AND l.application_date <= ?";
    $date_params = [$end_date . ' 23:59:59'];
}

// Handle PDF download request
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    require('fpdf/fpdf.php');
    
    // Query to get loan type analysis with date filter
    $sql = "SELECT 
                lt.loan_name, 
                COUNT(l.id) as loan_count, 
                SUM(l.amount) as total_loan_amount,
                AVG(l.amount) as average_loan_amount,
                lt.maximum_amount, 
                lt.interest_rate
            FROM 
                loan_types lt
            LEFT JOIN 
                loans l ON l.loan_type_id = lt.id" . 
                (!empty($date_condition) ? " WHERE 1=1" . $date_condition : "") . "
            GROUP BY 
                lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
            ORDER BY 
                loan_count DESC";
    
    if (!empty($date_params)) {
        $stmt = $conn->prepare($sql);
        if (count($date_params) == 2) {
            $stmt->bind_param("ss", $date_params[0], $date_params[1]);
        } else {
            $stmt->bind_param("s", $date_params[0]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    
    // Title
    $pdf->SetFillColor(255, 140, 0);
    $pdf->SetTextColor(255);
    $pdf->Cell(0,10,'Loan Type Analysis',0,1,'C',true);
    
    // Add date range info if filters are applied
    if (!empty($start_date) || !empty($end_date)) {
        $pdf->Ln(5);
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(0);
        $date_range_text = 'Date Range: ';
        if (!empty($start_date) && !empty($end_date)) {
            $date_range_text .= $start_date . ' to ' . $end_date;
        } elseif (!empty($start_date)) {
            $date_range_text .= 'From ' . $start_date;
        } else {
            $date_range_text .= 'Up to ' . $end_date;
        }
        $pdf->Cell(0,10,$date_range_text,0,1,'C');
    }
    
    $pdf->Ln(10);
    
    // Table header
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(0);
    $pdf->SetFillColor(255, 200, 150);
    $pdf->Cell(50,10,'Loan Type',1,0,'C',true);
    $pdf->Cell(30,10,'Loan Count',1,0,'C',true);
    $pdf->Cell(40,10,'Total Amount',1,0,'C',true);
    $pdf->Cell(40,10,'Avg Loan Size',1,0,'C',true);
    $pdf->Cell(30,10,'Max Amount',1,1,'C',true);
    
    // Table data
    $pdf->SetFont('Arial','',10);
    $pdf->SetFillColor(255, 240, 220);
    $fill = false;
    
    while($row = $result->fetch_assoc()) {
        $pdf->Cell(50,10,$row["loan_name"],1,0,'L',$fill);
        $pdf->Cell(30,10,$row["loan_count"],1,0,'C',$fill);
        $pdf->Cell(40,10,'Rs.'.number_format($row["total_loan_amount"],2),1,0,'R',$fill);
        $pdf->Cell(40,10,'Rs.'.number_format($row["average_loan_amount"],2),1,0,'R',$fill);
        $pdf->Cell(30,10,'Rs.'.number_format($row["maximum_amount"],2),1,1,'R',$fill);
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
    $filename = 'loan_type_analysis_' . date('Y-m-d');
    if (!empty($start_date) || !empty($end_date)) {
        $filename .= '_filtered';
    }
    $pdf->Output('D', $filename . '.pdf');
    exit();
}

// Handle CSV download request
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    // Query to get loan type analysis with date filter
    $sql = "SELECT 
                lt.loan_name, 
                COUNT(l.id) as loan_count, 
                SUM(l.amount) as total_loan_amount,
                AVG(l.amount) as average_loan_amount,
                lt.maximum_amount, 
                lt.interest_rate
            FROM 
                loan_types lt
            LEFT JOIN 
                loans l ON l.loan_type_id = lt.id" . 
                (!empty($date_condition) ? " WHERE 1=1" . $date_condition : "") . "
            GROUP BY 
                lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
            ORDER BY 
                loan_count DESC";
    
    if (!empty($date_params)) {
        $stmt = $conn->prepare($sql);
        if (count($date_params) == 2) {
            $stmt->bind_param("ss", $date_params[0], $date_params[1]);
        } else {
            $stmt->bind_param("s", $date_params[0]);
        }
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $result = $conn->query($sql);
    }
    
    // Prepare CSV data
    $csvData = "Loan Type,Loan Count,Total Loan Amount,Average Loan Amount,Maximum Amount,Interest Rate\n";
    
    while($row = $result->fetch_assoc()) {
        $csvData .= '"' . str_replace('"', '""', $row["loan_name"]) . '",';
        $csvData .= $row["loan_count"] . ',';
        $csvData .= number_format($row["total_loan_amount"], 2) . ',';
        $csvData .= number_format($row["average_loan_amount"], 2) . ',';
        $csvData .= number_format($row["maximum_amount"], 2) . ',';
        $csvData .= $row["interest_rate"] . "\n";
    }
    
    // Output CSV
    $filename = 'loan_type_analysis_' . date('Y-m-d');
    if (!empty($start_date) || !empty($end_date)) {
        $filename .= '_filtered';
    }
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    echo $csvData;
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Loan Type Analysis</title>
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
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: rgb(255, 140, 0);
            text-align: center;
            border-bottom: 2px solid rgb(255, 140, 0);
            padding-bottom: 10px;
        }
        .filter-section {
            background-color: rgb(255, 250, 240);
            border: 2px solid rgb(255, 200, 150);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .filter-title {
            color: rgb(255, 140, 0);
            font-weight: bold;
            font-size: 18px;
            margin-bottom: 15px;
            text-align: center;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: end;
            justify-content: center;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .filter-group label {
            font-weight: bold;
            margin-bottom: 5px;
            color: rgb(80, 80, 80);
        }
        .filter-group input[type="date"] {
            padding: 8px 12px;
            border: 2px solid rgb(255, 180, 100);
            border-radius: 5px;
            font-size: 14px;
            background-color: white;
            height: 40px;
            box-sizing: border-box;
        }
        .filter-group input[type="date"]:focus {
            outline: none;
            border-color: rgb(255, 140, 0);
            box-shadow: 0 0 5px rgba(255, 140, 0, 0.3);
        }
        .filter-buttons {
            display: flex;
            gap: 10px;
            align-items: end;
        }
        .filter-btn, .clear-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            height: 40px;
            box-sizing: border-box;
        }
        .filter-btn {
            background-color: rgb(255, 140, 0);
            color: white;
        }
        .filter-btn:hover {
            background-color: rgb(230, 120, 0);
            transform: translateY(-2px);
        }
        .clear-btn {
            background-color: rgb(150, 150, 150);
            color: white;
        }
        .clear-btn:hover {
            background-color: rgb(120, 120, 120);
            transform: translateY(-2px);
        }
        .current-filter {
            text-align: center;
            margin: 10px 0;
            font-style: italic;
            color: rgb(100, 100, 100);
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
        .charts-container {
            margin-top: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        .chart-section {
            background-color: rgb(255, 250, 240);
            border: 2px solid rgb(255, 200, 150);
            border-radius: 8px;
            padding: 20px;
        }
        .chart-title {
            color: rgb(255, 140, 0);
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
        }
        .chart {
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
        @media (max-width: 1000px) {
            .charts-container {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }
        @media (max-width: 600px) {
            .filter-form {
                flex-direction: column;
                gap: 20px;
            }
            .filter-buttons {
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Loan Type Analysis</h1>
        
        <!-- Date Filter Section -->
        <div class="filter-section">
            <div class="filter-title">📅 Filter by Application Date</div>
            <form method="GET" class="filter-form">
                <div class="filter-group">
                    <label for="start_date">Start Date:</label>
                    <input type="date" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                <div class="filter-group">
                    <label for="end_date">End Date:</label>
                    <input type="date" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                <div class="filter-buttons">
                    <button type="submit" class="filter-btn">Apply Filter</button>
                    <a href="?" class="clear-btn">Clear Filter</a>
                </div>
            </form>
            
            <?php if (!empty($start_date) || !empty($end_date)): ?>
                <div class="current-filter">
                    <strong>Current Filter:</strong>
                    <?php 
                    if (!empty($start_date) && !empty($end_date)) {
                        echo "From " . date('M d, Y', strtotime($start_date)) . " to " . date('M d, Y', strtotime($end_date));
                    } elseif (!empty($start_date)) {
                        echo "From " . date('M d, Y', strtotime($start_date)) . " onwards";
                    } else {
                        echo "Up to " . date('M d, Y', strtotime($end_date));
                    }
                    ?>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
        // Query to analyze loan types with date filter
        $sql = "SELECT 
                    lt.loan_name, 
                    COUNT(l.id) as loan_count, 
                    SUM(l.amount) as total_loan_amount,
                    AVG(l.amount) as average_loan_amount,
                    lt.maximum_amount, 
                    lt.interest_rate
                FROM 
                    loan_types lt
                LEFT JOIN 
                    loans l ON l.loan_type_id = lt.id" . 
                    (!empty($date_condition) ? " WHERE 1=1" . $date_condition : "") . "
                GROUP BY 
                    lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
                ORDER BY 
                    loan_count DESC";

        if (!empty($date_params)) {
            $stmt = $conn->prepare($sql);
            if (count($date_params) == 2) {
                $stmt->bind_param("ss", $date_params[0], $date_params[1]);
            } else {
                $stmt->bind_param("s", $date_params[0]);
            }
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        if ($result->num_rows > 0) {
            echo "<table>
                    <tr>
                        <th>Loan Type</th>
                        <th>Loan Count</th>
                        <th>Total Loan Amount(Rs.)</th>
                        <th>Average Loan Size(Rs.)</th>
                        <th>Max Loan Amount(Rs.)</th>
                        <th>Interest Rate</th>
                    </tr>";

            // Store data for chart
            $chartData = [];

            while($row = $result->fetch_assoc()) {
                echo "<tr>
                        <td>" . htmlspecialchars($row["loan_name"]) . "</td>
                        <td>" . $row["loan_count"] . "</td>
                        <td>Rs. " . number_format($row["total_loan_amount"], 2) . "</td>
                        <td>Rs. " . number_format($row["average_loan_amount"], 2) . "</td>
                        <td>Rs. " . number_format($row["maximum_amount"], 2) . "</td>
                        <td>" . $row["interest_rate"] . "%</td>
                      </tr>";
                
                $chartData[] = [
                    'name' => $row["loan_name"],
                    'loanCount' => (int)$row["loan_count"],
                    'totalAmount' => (float)$row["total_loan_amount"],
                    'averageAmount' => (float)$row["average_loan_amount"]
                ];
            }
            echo "</table>";

            // Prepare chart data for JavaScript
            $chartDataJson = json_encode($chartData);
        } else {
            echo "<p>No loan types found for the selected date range.</p>";
        }
        $conn->close();
        ?>

        <!-- Separate Charts Container -->
        <div class="charts-container">
            <div class="chart-section">
                <div class="chart-title">📊 Number of Loans by Type</div>
                <div class="chart">
                    <canvas id="loanCountChart" width="400" height="300"></canvas>
                </div>
            </div>
            
            <div class="chart-section">
                <div class="chart-title">💰 Total Loan Amount by Type</div>
                <div class="chart">
                    <canvas id="loanAmountChart" width="400" height="300"></canvas>
                </div>
            </div>
        </div>
        
        <div class="download-btns">
            <a href="?download=pdf<?php echo (!empty($start_date) ? '&start_date=' . urlencode($start_date) : '') . (!empty($end_date) ? '&end_date=' . urlencode($end_date) : ''); ?>" class="download-btn">Download as PDF</a>

        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script>
        // Chart.js visualization
        document.addEventListener('DOMContentLoaded', function() {
            var chartData = <?php echo $chartDataJson ?? '[]'; ?>;

            if (chartData.length > 0) {
                // Chart 1: Loan Count (Bar Chart)
                var ctx1 = document.getElementById('loanCountChart').getContext('2d');
                new Chart(ctx1, {
                    type: 'bar',
                    data: {
                        labels: chartData.map(item => item.name),
                        datasets: [{
                            label: 'Number of Loans',
                            data: chartData.map(item => item.loanCount),
                            backgroundColor: [
                                'rgba(255, 140, 0, 0.8)',
                                'rgba(255, 180, 100, 0.8)',
                                'rgba(255, 100, 50, 0.8)',
                                'rgba(200, 120, 0, 0.8)',
                                'rgba(255, 200, 150, 0.8)',
                                'rgba(180, 100, 0, 0.8)'
                            ],
                            borderColor: [
                                'rgba(230, 120, 0, 1)',
                                'rgba(230, 160, 80, 1)',
                                'rgba(230, 80, 30, 1)',
                                'rgba(180, 100, 0, 1)',
                                'rgba(230, 180, 130, 1)',
                                'rgba(160, 80, 0, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Loans'
                                },
                                ticks: {
                                    stepSize: 1
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            }
                        }
                    }
                });

                // Chart 2: Total Loan Amount (Pie Chart)
                var ctx2 = document.getElementById('loanAmountChart').getContext('2d');
                new Chart(ctx2, {
                    type: 'doughnut',
                    data: {
                        labels: chartData.map(item => item.name),
                        datasets: [{
                            label: 'Total Loan Amount (Rs.)',
                            data: chartData.map(item => item.totalAmount),
                            backgroundColor: [
                                'rgba(255, 140, 0, 0.8)',
                                'rgba(255, 180, 100, 0.8)',
                                'rgba(255, 100, 50, 0.8)',
                                'rgba(200, 120, 0, 0.8)',
                                'rgba(255, 200, 150, 0.8)',
                                'rgba(180, 100, 0, 0.8)'
                            ],
                            borderColor: [
                                'rgba(230, 120, 0, 1)',
                                'rgba(230, 160, 80, 1)',
                                'rgba(230, 80, 30, 1)',
                                'rgba(180, 100, 0, 1)',
                                'rgba(230, 180, 130, 1)',
                                'rgba(160, 80, 0, 1)'
                            ],
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    boxWidth: 12,
                                    font: {
                                        size: 11
                                    }
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        var label = context.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        label += 'Rs. ' + context.parsed.toLocaleString();
                                        
                                        // Calculate percentage
                                        var total = context.dataset.data.reduce((a, b) => a + b, 0);
                                        var percentage = ((context.parsed / total) * 100).toFixed(1);
                                        label += ' (' + percentage + '%)';
                                        
                                        return label;
                                    }
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