<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include FPDF library
require('fpdf/fpdf.php');

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

class AccountTypePDFReport extends FPDF {
    function Header() {
        // Set font
        $this->SetFont('Arial', 'B', 15);
        
        // Move to the right
        $this->Cell(80);
        
        // Title
        $this->SetTextColor(255, 140, 0); // Orange color
        $this->Cell(30, 10, 'Savings Account Analysis Report', 0, 1, 'C');
        $this->Ln(10);
    }
    
    function Footer() {
        // Position from bottom
        $this->SetY(-15);
        
        // Arial italic 8
        $this->SetFont('Arial', 'I', 8);
        
        // Page number
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
    
    function GenerateReport($conn) {
        // Query to count members by account type
        $sql = "SELECT 
                    sat.account_name, 
                    COUNT(m.id) as member_count, 
                    sat.minimum_balance, 
                    sat.interest_rate
                FROM 
                    savings_account_types sat
                LEFT JOIN 
                    members m ON m.account_type = sat.id
                GROUP BY 
                    sat.id, sat.account_name, sat.minimum_balance, sat.interest_rate
                ORDER BY 
                    member_count DESC";

        $result = $conn->query($sql);

        // Table headers
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(255, 140, 0); // Orange fill
        $this->SetTextColor(255, 255, 255); // White text
        
        $this->Cell(60, 10, 'Account Type', 1, 0, 'C', true);
        $this->Cell(30, 10, 'Members', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Min Balance', 1, 0, 'C', true);
        $this->Cell(40, 10, 'Interest Rate', 1, 1, 'C', true);

        // Reset text color for data
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 10);

        // Data rows
        $totalMembers = 0;
        $highestMemberAccount = '';
        $highestMemberCount = 0;

        while($row = $result->fetch_assoc()) {
            $this->Cell(60, 10, $row['account_name'], 1);
            $this->Cell(30, 10, $row['member_count'], 1, 0, 'C');
            $this->Cell(40, 10, '$' . number_format($row['minimum_balance'], 2), 1, 0, 'R');
            $this->Cell(40, 10, $row['interest_rate'] . '%', 1, 1, 'C');

            $totalMembers += $row['member_count'];
            
            if ($row['member_count'] > $highestMemberCount) {
                $highestMemberCount = $row['member_count'];
                $highestMemberAccount = $row['account_name'];
            }
        }

        // Summary section
        $this->Ln(10);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, 'Summary Analysis', 0, 1, 'L');
        
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 10, 'Total Members: ' . $totalMembers, 0, 1);
        $this->Cell(0, 10, 'Most Popular Account: ' . $highestMemberAccount . ' (' . $highestMemberCount . ' members)', 0, 1);
    }
}

// Create PDF
$pdf = new AccountTypePDFReport();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->GenerateReport($conn);

// Output PDF
$pdf->Output('D', 'account_type_analysis_' . date('Y-m-d') . '.pdf');

// Close connection
$conn->close();
exit;
?>