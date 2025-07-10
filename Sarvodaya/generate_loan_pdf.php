<?php
// Include database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


// Include FPDF library
require_once 'fpdf/fpdf.php';

// Get loan ID from request
$loan_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($loan_id <= 0) {
    die("Invalid loan ID");
}

// Query to fetch loan details - adjusted to match your table structure
$sql = "SELECT l.*, 
               m.name as member_name, 
               m.phone as member_contact,
               m.email as member_email,
               m.address as member_address,
               m.nic as member_id_number,
               lt.loan_name as loan_type_name,
               g1.name as guarantor1_name,
               g1.phone as guarantor1_contact,
               g2.name as guarantor2_name,
               g2.phone as guarantor2_contact
        FROM loans l
        LEFT JOIN members m ON l.member_id = m.id
        LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
        LEFT JOIN members g1 ON l.guarantor1_id = g1.id
        LEFT JOIN members g2 ON l.guarantor2_id = g2.id
        WHERE l.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $loan_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Loan not found");
}

$loan = $result->fetch_assoc();

// Create PDF document
class LoanPDF extends FPDF {
    // Properties
    public $loan_id;
    public $org_name = "Sarvodaya Shramadhana Society";
    public $org_logo = "logo.png"; // If you have a logo
    
    function Header() {
        // Logo if exists
        if (file_exists($this->org_logo)) {
            $this->Image($this->org_logo, 10, 10, 30);
            $this->SetX(45);
        }
        
        // Organization name
        $this->SetFont('Arial', 'B', 16);
        $this->Cell(0, 10, $this->org_name, 0, 1, 'C');
        
        // Subtitle
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 10, 'LOAN APPLICATION FORM', 0, 1, 'C');
        
        // Reference number
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 6, 'Reference: ' . date('Y') . '/' . sprintf('%06d', $this->loan_id), 0, 1, 'C');
        
        // Date
        $this->Cell(0, 6, 'Date Generated: ' . date('F j, Y'), 0, 1, 'C');
        
        $this->Ln(5);
        
        // Line separator
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }
    
    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        $this->SetY(-10);
        $this->Cell(0, 10, 'This is an official document of ' . $this->org_name, 0, 0, 'C');
    }
    
    function SectionTitle($title) {
        $this->SetFont('Arial', 'B', 12);
        $this->SetFillColor(220, 220, 250);
        $this->Cell(0, 8, $title, 0, 1, 'L', true);
        $this->Ln(2);
    }
    
    function LabelValue($label, $value, $width1 = 60, $width2 = 130) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($width1, 6, $label . ':', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell($width2, 6, $value, 0, 1);
    }
    
    function TwoColumnLabelValue($label1, $value1, $label2, $value2) {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 6, $label1 . ':', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(55, 6, $value1, 0, 0);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(40, 6, $label2 . ':', 0, 0);
        $this->SetFont('Arial', '', 10);
        $this->Cell(55, 6, $value2, 0, 1);
    }
}

// Initialize PDF
$pdf = new LoanPDF();
$pdf->loan_id = $loan_id;
$pdf->AliasNbPages();
$pdf->AddPage();

// Member Information
$pdf->SectionTitle('1. MEMBER INFORMATION');
$pdf->LabelValue('Member ID', $loan['member_id']);
$pdf->LabelValue('Full Name', $loan['member_name'] ?? 'N/A');
$pdf->LabelValue('NIC', $loan['member_id_number'] ?? 'N/A'); // Changed from ID Number to NIC
$pdf->LabelValue('Contact Number', $loan['member_contact'] ?? 'N/A');
$pdf->LabelValue('Email Address', $loan['member_email'] ?? 'N/A');
$pdf->LabelValue('Physical Address', $loan['member_address'] ?? 'N/A');
$pdf->Ln(5);

// Loan Details
$pdf->SectionTitle('2. LOAN DETAILS');
$pdf->TwoColumnLabelValue('Loan Type', $loan['loan_type_name'] ?? 'N/A', 'Application Date', date('F j, Y', strtotime($loan['application_date'])));
$pdf->TwoColumnLabelValue('Loan Amount', 'Rs. ' . number_format($loan['amount'], 2), 'Interest Rate', $loan['interest_rate'] . '% per month');
$pdf->TwoColumnLabelValue('Repayment Period', $loan['max_period'] . ' months', 'Total Repayment', 'Rs. ' . number_format($loan['total_repayment_amount'], 2));
$pdf->TwoColumnLabelValue('Start Date', date('F j, Y', strtotime($loan['start_date'])), 'End Date', date('F j, Y', strtotime($loan['end_date'])));
$pdf->Ln(5);

// Guarantor Information
$pdf->SectionTitle('3. GUARANTOR INFORMATION');
$pdf->LabelValue('Guarantor 1', $loan['guarantor1_name'] ?? 'None');
if (!empty($loan['guarantor1_name'])) {
    $pdf->LabelValue('Contact Number', $loan['guarantor1_contact'] ?? 'N/A');
}
$pdf->Ln(3);
$pdf->LabelValue('Guarantor 2', $loan['guarantor2_name'] ?? 'None');
if (!empty($loan['guarantor2_name'])) {
    $pdf->LabelValue('Contact Number', $loan['guarantor2_contact'] ?? 'N/A');
}
$pdf->Ln(5);

// Purpose of Loan
$pdf->SectionTitle('4. PURPOSE OF LOAN');
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 10, 'Please state the purpose of this loan (to be filled by applicant):', 0, 1);
$pdf->Cell(0, 10, '___________________________________________________________________', 0, 1);
$pdf->Cell(0, 10, '___________________________________________________________________', 0, 1);
$pdf->Cell(0, 10, '___________________________________________________________________', 0, 1);
$pdf->Ln(5);



$pdf->Ln(5);

// Terms and Conditions
$pdf->SectionTitle('5. TERMS AND CONDITIONS');
$pdf->SetFont('Arial', '', 9);
$terms = "1. The borrower agrees to repay the loan.\n";
$terms .= "2. Early repayment is permitted with no penalties.\n";
$terms .= "3. Default in payment may result in legal action and affect credit rating.\n";
$terms .= "4. The lender reserves the right to demand full repayment in case of three consecutive missed payments.\n";
$terms .= "5. Any change in contact details must be communicated to the lender within 7 days.";
$pdf->MultiCell(0, 5, $terms, 0, 'L');
$pdf->Ln(5);

// Declaration
$pdf->SectionTitle('6. DECLARATION AND SIGNATURES');
$pdf->SetFont('Arial', '', 10);
$pdf->MultiCell(0, 5, 'I/We declare that the information provided in this application is true and correct. I/We agree to abide by the terms and conditions of the loan as set by the society. I/We understand that any false statement may result in the rejection of my loan application or immediate demand for full repayment if the loan has already been disbursed.', 0, 'L');
$pdf->Ln(10);

$pdf->Cell(90, 7, 'Applicant Signature:', 0, 0);
$pdf->Cell(90, 7, 'Date:', 0, 1);
$pdf->Line(40, $pdf->GetY(), 90, $pdf->GetY());
$pdf->Line(140, $pdf->GetY(), 180, $pdf->GetY());
$pdf->Ln(15);

if (!empty($loan['guarantor1_name'])) {
    $pdf->Cell(90, 7, 'Guarantor 1 Signature:', 0, 0);
    $pdf->Cell(90, 7, 'Date:', 0, 1);
    $pdf->Line(40, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(140, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(15);
}

if (!empty($loan['guarantor2_name'])) {
    $pdf->Cell(90, 7, 'Guarantor 2 Signature:', 0, 0);
    $pdf->Cell(90, 7, 'Date:', 0, 1);
    $pdf->Line(40, $pdf->GetY(), 90, $pdf->GetY());
    $pdf->Line(140, $pdf->GetY(), 180, $pdf->GetY());
    $pdf->Ln(15);
}

// Official Use Section
$pdf->AddPage();
$pdf->SectionTitle('FOR OFFICIAL USE ONLY');
$pdf->SetFont('Arial', 'B', 10);

// Create a table
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(50, 7, 'Application Review', 1, 0, 'L', true);
$pdf->Cell(140, 7, 'Comments', 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 10, 'Credit Check Result:', 1, 0);
$pdf->Cell(140, 10, '', 1, 1);

$pdf->Cell(50, 10, 'Income Verification:', 1, 0);
$pdf->Cell(140, 10, '', 1, 1);

$pdf->Cell(50, 10, 'Loan Officer Assessment:', 1, 0);
$pdf->Cell(140, 10, '', 1, 1);

$pdf->Ln(10);

// Approval section
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'APPROVAL DECISION', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(50, 10, 'Application Status:', 1, 0);
$pdf->Cell(50, 10, '□ Approved   □ Declined', 1, 0, 'C');
$pdf->Cell(50, 10, 'If declined, reason:', 1, 0);
$pdf->Cell(40, 10, '', 1, 1);

$pdf->Cell(50, 10, 'Approval Date:', 1, 0);
$pdf->Cell(50, 10, '', 1, 0);
$pdf->Cell(50, 10, 'Processing Officer:', 1, 0);
$pdf->Cell(40, 10, '', 1, 1);

$pdf->Cell(50, 10, 'Approved Amount:', 1, 0);
$pdf->Cell(50, 10, '', 1, 0);
$pdf->Cell(50, 10, 'Authorized Signature:', 1, 0);
$pdf->Cell(40, 10, '', 1, 1);

$pdf->Ln(10);

// Disbursement details
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 7, 'DISBURSEMENT DETAILS', 1, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);

$pdf->Cell(50, 10, 'Disbursement Date:', 1, 0);
$pdf->Cell(50, 10, '', 1, 0);
$pdf->Cell(50, 10, 'Disbursement Method:', 1, 0);
$pdf->Cell(40, 10, '', 1, 1);

$pdf->Cell(50, 10, 'Transaction Reference:', 1, 0);
$pdf->Cell(50, 10, '', 1, 0);
$pdf->Cell(50, 10, 'Disbursed By:', 1, 0);
$pdf->Cell(40, 10, '', 1, 1);

// Output PDF
$pdf->Output('Loan_Application_' . $loan_id . '.pdf', 'I');
?>