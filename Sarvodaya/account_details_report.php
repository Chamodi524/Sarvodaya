<?php
// Include FPDF library
require('fpdf/fpdf.php');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// PDF Generation Function
function generatePDF($conn) {
    // Custom PDF Class
    class SavingsAccountPDF extends FPDF {
        // Page header
        function Header() {
            $this->SetFont('Arial', 'B', 15);
            $this->SetTextColor(255, 167, 38);
            $this->Cell(0, 10, 'Sarvodaya Bank - Savings Account Types Report', 0, 1, 'C');
            $this->Ln(10);
        }
        
        // Page footer
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Create PDF instance
    $pdf = new SavingsAccountPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Fetch distinct savings account types
    $account_types_query = "SELECT id, account_name, minimum_balance, interest_rate FROM savings_account_types";
    $account_types_result = $conn->query($account_types_query);

    if ($account_types_result->num_rows > 0) {
        while ($account_type = $account_types_result->fetch_assoc()) {
            // Account Type Header
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->SetFillColor(255, 167, 38);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 10, $account_type['account_name'] . ' Account Type', 1, 1, 'C', true);
            
            // Account Type Details
            $pdf->SetFont('Arial', '', 10);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Cell(0, 7, 'Minimum Balance: ' . number_format($account_type['minimum_balance'], 2), 0, 1);
            $pdf->Cell(0, 7, 'Interest Rate: ' . number_format($account_type['interest_rate'], 2) . '%', 0, 1);
            $pdf->Ln(5);

            // Fetch members for this specific account type
            $account_type_id = $account_type['id'];
            $members_query = "SELECT id, name, account_type FROM members WHERE account_type = $account_type_id";
            $members_result = $conn->query($members_query);

            if ($members_result->num_rows > 0) {
                // Members Table Header
                $pdf->SetFont('Arial', 'B', 10);
                $pdf->Cell(30, 10, 'Member ID', 1);
                $pdf->Cell(160, 10, 'Member Name', 1);
                $pdf->Ln();

                // Members Table Rows
                $pdf->SetFont('Arial', '', 10);
                while ($member = $members_result->fetch_assoc()) {
                    $pdf->Cell(30, 7, $member['id'], 1);
                    $pdf->Cell(160, 7, $member['name'], 1);
                    $pdf->Ln();
                }
            } else {
                $pdf->Cell(0, 10, 'No members found for this account type.', 1, 1, 'C');
            }
            
            $pdf->Ln(10);
        }
    } else {
        $pdf->Cell(0, 10, 'No savings account types found.', 1, 1, 'C');
    }

    // Add space for Date and Bank Manager's signature
    $pdf->Ln(20);
    
    // Set up date on left side
    $pdf->SetFont('Arial', 'B', 10);
    
    // Date line on left side
    $pdf->Line(20, $pdf->GetY(), 80, $pdf->GetY());
    
    // Add "Date" text below the date line on left side
    $pdf->SetY($pdf->GetY() + 5);
    $pdf->SetX(20);
    $pdf->Cell(60, 10, 'Date', 0, 0, 'C');
    
    // Set up signature on right side (on the same vertical position as date)
    $pdf->SetX(130);
    
    // Signature line on right side
    $pdf->Line(130, $pdf->GetY() - 5, 190, $pdf->GetY() - 5);
    
    // Add "Bank Manager" text below the signature line on right side
    $pdf->Cell(60, 10, 'Bank Manager', 0, 1, 'C');

    // Output the PDF
    $pdf->Output('Savings_Account_Types_Report.pdf', 'F'); // Save to file
}

// Check if download is requested
if (isset($_GET['download']) && $_GET['download'] == 'pdf') {
    generatePDF($conn);
    
    // Force download of the generated PDF
    $file = 'Savings_Account_Types_Report.pdf';
    if (file_exists($file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Account Types Summary - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table {
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .account-type-header {
            background-color: #ffa726;
            color: white;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .download-btn {
            margin-bottom: 20px;
        }
        /* Custom Orange Button */
        .btn-orange {
            background-color: #ffa726;
            border-color: #ffa726;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-orange:hover {
            background-color: #ff9800;
            border-color: #ff9800;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Savings Account Types Summary - Sarvodaya Bank</h1>

        <!-- Download PDF Button with Orange Color -->
        <div class="text-center download-btn">
            <a href="?download=pdf" class="btn btn-orange">
                <i class="fas fa-download"></i> Download PDF Report
            </a>
        </div>
        

        <?php
        // Fetch distinct savings account types
        $account_types_query = "SELECT id, account_name, minimum_balance, interest_rate FROM savings_account_types";
        $account_types_result = $conn->query($account_types_query);

        if ($account_types_result->num_rows > 0) {
            while ($account_type = $account_types_result->fetch_assoc()) {
                // Fetch members for this specific account type
                $account_type_id = $account_type['id'];
                $members_query = "SELECT id, name, account_type FROM members WHERE account_type = $account_type_id";
                $members_result = $conn->query($members_query);

                echo '<div class="card">';
                echo '<div class="account-type-header">';
                echo '<h2>' . htmlspecialchars($account_type['account_name']) . ' Account Type</h2>';
                echo '</div>';

                // Account Type Details
                echo '<div class="mb-3">';
                echo '<p style="font-size: 20px;"><strong>Minimum Balance:</strong> ' . number_format($account_type['minimum_balance'], 2) . '</p>';
                echo '<p style="font-size: 20px;"><strong>Interest Rate:</strong> ' . number_format($account_type['interest_rate'], 2) . '%</p>';
                echo '</div>';

                if ($members_result->num_rows > 0) {
                    echo '<table class="table table-bordered table-hover">';
                    echo '<thead>
                            <tr>
                                <th style="font-size: 20px;">Member ID</th>
                                <th style="font-size: 20px;">Member Name</th>
                            </tr>
                          </thead>
                          <tbody>';

                    while ($member = $members_result->fetch_assoc()) {
                        echo '<tr>
                                <td style="font-size: 20px;">' . htmlspecialchars($member['id']) . '</td>
                                <td style="font-size: 20px;">' . htmlspecialchars($member['name']) . '</td>
                              </tr>';
                    }

                    echo '</tbody></table>';
                } else {
                    echo '<div class="alert alert-info">No members found for this account type.</div>';
                }

                echo '</div>'; // Close card div
            }
        } else {
            echo '<div class="alert alert-warning">No savings account types found.</div>';
        }
        

        // Close the database connection
        $conn->close();
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</body>
</html>