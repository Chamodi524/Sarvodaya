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

// Handle PDF download request
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    require('fpdf/fpdf.php');
    
    // Query to get loan type analysis
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
                loans l ON l.loan_type_id = lt.id
            GROUP BY 
                lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
            ORDER BY 
                loan_count DESC";
    
    $result = $conn->query($sql);
    
    // Create PDF
    $pdf = new FPDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','B',16);
    
    // Title
    $pdf->SetFillColor(255, 140, 0);
    $pdf->SetTextColor(255);
    $pdf->Cell(0,10,'Loan Type Analysis',0,1,'C',true);
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
    $pdf->Output('D', 'loan_type_analysis_'.date('Y-m-d').'.pdf');
    exit();
}

// Handle CSV download request
if (isset($_GET['download']) && $_GET['download'] == 'csv') {
    // Query to get loan type analysis
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
                loans l ON l.loan_type_id = lt.id
            GROUP BY 
                lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
            ORDER BY 
                loan_count DESC";
    
    $result = $conn->query($sql);
    
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
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="loan_type_analysis_'.date('Y-m-d').'.csv"');
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
            max-width: 900px;
            margin: 0 auto;
        }
        h1 {
            color: rgb(255, 140, 0);
            text-align: center;
            border-bottom: 2px solid rgb(255, 140, 0);
            padding-bottom: 10px;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>Loan Type Analysis</h1>
        
        <?php
        // Query to analyze loan types
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
                    loans l ON l.loan_type_id = lt.id
                GROUP BY 
                    lt.id, lt.loan_name, lt.maximum_amount, lt.interest_rate
                ORDER BY 
                    loan_count DESC";

        $result = $conn->query($sql);

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
                    'loanCount' => $row["loan_count"],
                    'totalAmount' => $row["total_loan_amount"]
                ];
            }
            echo "</table>";

            // Prepare chart data for JavaScript
            $chartDataJson = json_encode($chartData);
        } else {
            echo "<p>No loan types found.</p>";
        }
        $conn->close();
        ?>

        <div class="chart">
            <canvas id="loanTypeChart"></canvas>
        </div>
        
        <div class="download-btns">
            <a href="?download=pdf" class="download-btn">Download as PDF</a>
            
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.7.1/chart.min.js"></script>
    <script>
        // Chart.js visualization
        document.addEventListener('DOMContentLoaded', function() {
            var ctx = document.getElementById('loanTypeChart').getContext('2d');
            var chartData = <?php echo $chartDataJson ?? '[]'; ?>;

            if (chartData.length > 0) {
                // Bar chart for Loan Count
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: chartData.map(item => item.name),
                        datasets: [
                            {
                                label: 'Number of Loans',
                                data: chartData.map(item => item.loanCount),
                                backgroundColor: 'rgb(255, 140, 0)',
                                borderColor: 'rgb(230, 120, 0)',
                                borderWidth: 1
                            },
                            {
                                label: 'Total Loan Amount',
                                data: chartData.map(item => item.totalAmount),
                                backgroundColor: 'rgb(255, 180, 100)',
                                borderColor: 'rgb(255, 160, 50)',
                                borderWidth: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: {
                                display: true,
                                text: 'Loan Distribution by Type'
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Number of Loans / Total Loan Amount(Rs.)'
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