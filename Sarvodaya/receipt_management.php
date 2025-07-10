<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Function to convert number to words for Indian Rupees
function numberToWords($number) {
    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen",
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );

    // Format the number with 2 decimal places
    $number = (float)$number;
    $number_parts = explode('.', number_format($number, 2, '.', ''));

    $wholenum = $number_parts[0];
    $decnum = $number_parts[1];

    // Handle the whole number portion
    $result = "";

    // Process crores (if any)
    $crores = (int)($wholenum / 10000000);
    if ($crores > 0) {
        $result .= numberToWordsIndian($crores) . " Crore ";
        $wholenum %= 10000000;
    }

    // Process lakhs (if any)
    $lakhs = (int)($wholenum / 100000);
    if ($lakhs > 0) {
        $result .= numberToWordsIndian($lakhs) . " Lakh ";
        $wholenum %= 100000;
    }

    // Process thousands (if any)
    $thousands = (int)($wholenum / 1000);
    if ($thousands > 0) {
        $result .= numberToWordsIndian($thousands) . " Thousand ";
        $wholenum %= 1000;
    }

    // Process hundreds (if any)
    $hundreds = (int)($wholenum / 100);
    if ($hundreds > 0) {
        $result .= numberToWordsIndian($hundreds) . " Hundred ";
        $wholenum %= 100;
    }

    // Process tens and ones
    if ($wholenum > 0) {
        if ($result != "") {
            $result .= "and ";
        }
        $result .= numberToWordsIndian($wholenum);
    }

    // Add "Rupees" text
    if ($result == "") {
        $result = "Zero";
    }
    $result .= " Rupees";

    // Process decimal part (paise)
    if ((int)$decnum > 0) {
        $result .= " and " . numberToWordsIndian((int)$decnum) . " Paise";
    }

    return $result . " Only";
}

// Helper function to convert small numbers to words
function numberToWordsIndian($num) {
    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen",
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );

    $num = (int)$num;

    if ($num < 20) {
        return $ones[$num];
    } elseif ($num < 100) {
        return $tens[(int)($num/10)] . ($num % 10 ? " " . $ones[$num % 10] : "");
    }

    return ""; // Should not reach here with proper usage
}

// Fetch active loans and loan types if receipt type is loan repayment
$active_loans = [];
$active_loan_types = [];

// Check if we have POST data for member_id and receipt_type
if (isset($_POST['receipt_type']) && 
   (($_POST['receipt_type'] == 'loan_repayment') || ($_POST['receipt_type'] == 'late_fee')) && 
   isset($_POST['member_id'])) {
    $member_id = $_POST['member_id'];
    
    // Get active loans for this member
    $active_loans_sql = "SELECT l.id, l.amount, l.loan_type_id, lt.loan_name 
                         FROM loans l 
                         JOIN loan_types lt ON l.loan_type_id = lt.id 
                         WHERE l.member_id = ? AND l.status = 'active'";
    
    $stmt = $conn->prepare($active_loans_sql);
    $stmt->bind_param('i', $member_id);
    $stmt->execute();
    $active_loans_result = $stmt->get_result();
    
    if ($active_loans_result->num_rows > 0) {
        while ($row = $active_loans_result->fetch_assoc()) {
            $active_loans[] = $row;
            if (!in_array($row['loan_type_id'], $active_loan_types)) {
                $active_loan_types[] = $row['loan_type_id'];
            }
        }
    }
}

// Fetch all available loan types (this will be used for select options)
$loan_types = [];
$loan_sql = "SELECT id, loan_name FROM loan_types";
$loan_result = $conn->query($loan_sql);
if ($loan_result->num_rows > 0) {
    while ($row = $loan_result->fetch_assoc()) {
        $loan_types[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Money Receipts - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #ffa726;
            color: white;
            border-radius: 5px;
            border: none;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #fb8c00;
            transform: scale(1.05);
        }
        .form-control {
            border-radius: 5px;
        }
        .hidden {
            display: none;
        }
        /* Improved Receipt styling */
        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
        }
        .receipt-header {
            border-bottom: 2px solid #ff8c00;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-title {
            color: #ff8c00;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .receipt-logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .receipt-bank-name {
            font-size: 24px;
            font-weight: bold;
            color: #ff8c00;
            text-align: center;
            margin-bottom: 5px;
        }
        .receipt-bank-address {
            text-align: center;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .receipt-number {
            font-weight: bold;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .receipt-date {
            margin-bottom: 15px;
            font-size: 14px;
        }
        .receipt-body {
            margin-bottom: 20px;
        }
        .receipt-row {
            display: flex;
            margin-bottom: 10px;
        }
        .receipt-label {
            font-weight: bold;
            width: 180px;
        }
        .receipt-value {
            flex: 1;
        }
        .receipt-amount {
            font-size: 22px;
            font-weight: bold;
            margin: 20px 0;
            text-align: center;
            color: #ff8c00;
        }
        .receipt-amount-words {
            font-style: italic;
            margin-bottom: 20px;
            text-align: center;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .receipt-footer {
            border-top: 1px dashed #ddd;
            padding-top: 20px;
            margin-top: 20px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        .receipt-signature {
            margin-top: 50px;
            display: flex;
            justify-content: space-between;
        }
        .sign-box {
            text-align: center;
            width: 200px;
        }
        .sign-line {
            border-top: 1px solid #333;
            margin-bottom: 5px;
        }
        .actions {
            text-align: center;
            margin-top: 20px;
        }
        @media print {
            body * {
                visibility: hidden;
            }
            #receiptModal, #receiptModal * {
                visibility: visible;
            }
            #receiptModal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            .modal-dialog {
                width: 100%;
                max-width: 100%;
                margin: 0;
                padding: 0;
            }
            .modal-content {
                border: none;
                box-shadow: none;
            }
            .btn-print, .btn-close, .btn-secondary, .actions {
                display: none;
            }
            .receipt-container {
                box-shadow: none;
                padding: 15px;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Money Receipts - Sarvodaya Bank</h1>

        <!-- Receipt Input Form -->
        <div class="card">
            <h2>Add New Receipt</h2>
            <form id="receiptForm">
                <div class="mb-3">
                    <label for="member_id" class="form-label" style="font-size: 20px;">Member ID</label>
                    <input type="number" class="form-control" id="member_id" style="font-size: 20px;" name="member_id" required>
                </div>
                <div class="mb-3">
                    <label for="receipt_type" class="form-label" style="font-size: 20px;">Receipt Type</label>
                    <select class="form-select" id="receipt_type" style="font-size: 20px;" name="receipt_type" required>
                        <option value="" style="font-size: 20px;">Select Receipt Type</option>
                        <option value="deposit" style="font-size: 20px;">Deposit</option>
                        <option value="loan_repayment" style="font-size: 20px;">Other</option>
                        
                    </select>
                </div>
                <!-- Loan type field will be shown only for loan repayment -->
                <div class="mb-3 hidden" id="loan_type_field">
                    <label for="loan_type" class="form-label" style="font-size: 20px;">Loan Type</label>
                    <select class="form-select" id="loan_type" name="loan_type" style="font-size: 20px;">
                        <option value="" style="font-size: 20px;">Select Loan Type</option>
                        <!-- Options will be populated dynamically when a member is selected -->
                    </select>
                </div>
                <div class="mb-3 hidden" id="late_installments_field">
                    <label for="late_installments" class="form-label" style="font-size: 20px;">Number of Late Installments</label>
                    <input type="number" class="form-control" id="late_installments" name="late_installments" min="1" value="1" style="font-size: 20px;">
                </div>
                <div class="mb-3">
                    <label for="amount" class="form-label" style="font-size: 20px;">Amount(Rs.)</label>
                    <input type="number" class="form-control" id="amount" style="font-size: 20px;" name="amount" step="0.01" required>
                </div>
                <button type="submit" class="btn btn-custom" style="font-size: 20px;">Submit Receipt</button>
            </form>

            <!-- Display active loans if receipt type is loan repayment -->
            <div class="mt-4 hidden" id="active_loans_container">
                <h2>Active Loans</h2>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="font-size: 20px;">Loan ID</th>
                            <th style="font-size: 20px;">Loan Type</th>
                            <th style="font-size: 20px;">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="active_loans_table" style="font-size: 20px;">
                        <!-- Will be populated via JavaScript -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Enhanced Receipt Modal -->
    <div class="modal fade" id="receiptModal" tabindex="-1" aria-labelledby="receiptModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="receiptModalLabel">Payment Receipt</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="receipt-container">
                        <div class="receipt-header">
                            <div class="receipt-logo">
                                <div class="receipt-bank-name">SARVODAYA SHRAMADHANA SOCIETY</div>
                            </div>
                            <div class="receipt-bank-address">
                                Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda.<br>
                                Phone: 077 690 6605 | Email: info@sarvodayabank.com
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="receipt-number">Receipt No: <span id="receipt-no"></span></div>
                                    <div class="receipt-date">Date: <span id="receipt-date"></span></div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <div class="receipt-title">RECEIPT</div>
                                </div>
                            </div>
                        </div>

                        <div class="receipt-body">
                            <div class="receipt-row">
                                <div class="receipt-label">Received From:</div>
                                <div class="receipt-value" id="receipt-member-name"></div>
                            </div>

                            <div class="receipt-row">
                                <div class="receipt-label">Member ID:</div>
                                <div class="receipt-value" id="receipt-member-id"></div>
                            </div>

                            <div class="receipt-row">
                                <div class="receipt-label">Receipt Type:</div>
                                <div class="receipt-value" id="receipt-type"></div>
                            </div>

                            <div class="receipt-row loan-details hidden">
                                <div class="receipt-label">Loan Type:</div>
                                <div class="receipt-value" id="receipt-loan-type"></div>
                            </div>

                            <div class="receipt-amount" id="receipt-amount">
                                Rs. 0.00
                            </div>

                            <div class="receipt-amount-words" id="receipt-amount-words">
                                Zero Rupees Only
                            </div>

                            <div class="receipt-row balance-info hidden">
                                <div class="receipt-label">New Balance:</div>
                                <div class="receipt-value" id="receipt-balance"></div>
                            </div>

                            <div class="receipt-row loan-balance hidden">
                                <div class="receipt-label">Remaining Loan:</div>
                                <div class="receipt-value" id="receipt-loan-balance"></div>
                            </div>

                            <div class="receipt-info" id="receipt-info"></div>

                            <div class="receipt-signature">
                                <div class="sign-box">
                                    <div class="sign-line"></div>
                                    Member Signature
                                </div>
                                <div class="sign-box">
                                    <div class="sign-line"></div>
                                    Authorized Signature
                                </div>
                            </div>
                        </div>

                        <div class="receipt-footer">
                            <p>This is a computer-generated receipt. Thank you for your payment.</p>
                            <p>For any enquiries, please contact our customer service or visit our office.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-custom btn-print" onclick="printReceipt()">Print Receipt</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Get all necessary DOM elements
     // Get all necessary DOM elements
const receiptTypeField = document.getElementById('receipt_type');
const loanTypeField = document.getElementById('loan_type_field');
const amountField = document.getElementById('amount');
const loanTypeSelect = document.getElementById('loan_type');
const memberIdField = document.getElementById('member_id');
const receiptForm = document.getElementById('receiptForm');
const activeLoansContainer = document.getElementById('active_loans_container');
const activeLoansTable = document.getElementById('active_loans_table');
const lateInstallmentsField = document.getElementById('late_installments_field');
const lateInstallmentsInput = document.getElementById('late_installments');

// Show/hide loan type field based on receipt type

receiptTypeField.addEventListener('change', function () {
    // Treat both 'loan_repayment' and 'late_fee' the same way
    if (this.value === 'loan_repayment' || this.value === 'late_fee') {
        loanTypeField.classList.remove('hidden');
        loanTypeSelect.setAttribute('required', 'required');
        
        // If member ID is already filled, fetch active loans
        if (memberIdField.value) {
            fetchActiveLoans();
        }
        
        // Show late installments field only for late_fee
        if (this.value === 'late_fee') {
            lateInstallmentsField.classList.remove('hidden');
        } else {
            lateInstallmentsField.classList.add('hidden');
        }
    } else {
        loanTypeField.classList.add('hidden');
        loanTypeSelect.removeAttribute('required');
        lateInstallmentsField.classList.add('hidden');
        activeLoansContainer.classList.add('hidden');
        // Clear loan table
        activeLoansTable.innerHTML = '';
    }

    // Clear amount when switching receipt types
    amountField.value = '';
});
loanTypeSelect.addEventListener('change', calculateLateFee);
lateInstallmentsInput.addEventListener('change', calculateLateFee);
lateInstallmentsInput.addEventListener('input', calculateLateFee);

// Function to calculate late fee amount automatically
function calculateLateFee() {
    if (receiptTypeField.value !== 'late_fee') return;
    
    const loanId = loanTypeSelect.value;
    const installments = lateInstallmentsInput.value || 1;
    
    if (!loanId) {
        amountField.value = '';
        return;
    }
    
    // Fetch late fee for the selected loan
    fetch(`get_loan_late_fee.php?loan_id=${loanId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.late_fee) {
                // Calculate and set amount
                const amount = parseFloat(data.late_fee) * parseInt(installments);
                amountField.value = amount.toFixed(2);
            }
        })
        .catch(error => {
            console.error('Error fetching late fee:', error);
        });
}


// Event handler for member ID changes
memberIdField.addEventListener('change', function() {
    if (receiptTypeField.value === 'loan_repayment' || receiptTypeField.value === 'late_fee') {
        fetchActiveLoans();
    }
});

// Function to fetch active loans for a member
function fetchActiveLoans() {
    const memberId = memberIdField.value;
    if (!memberId) return;

    // Clear loan type select options except the placeholder
    while (loanTypeSelect.options.length > 1) {
        loanTypeSelect.remove(1);
    }
    
    // Show loading state
    activeLoansTable.innerHTML = '<tr><td colspan="3">Loading...</td></tr>';
    activeLoansContainer.classList.remove('hidden');
    
    // Use fetch API to get active loans via AJAX
    fetch(`get_active_loans.php?member_id=${memberId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.loans && data.loans.length > 0) {
                // Populate table with active loans
                activeLoansTable.innerHTML = '';
                data.loans.forEach(loan => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${loan.id}</td>
                        <td>${loan.loan_name}</td>
                        <td>Rs. ${formatNumber(loan.remaining_amount)}</td>
                    `;
                    activeLoansTable.appendChild(row);
                    
                    // Add loan to dropdown - using loan.id (loan_id) as the value
                    // This will be stored in receipts.loan_id
                    const option = document.createElement('option');
                    option.value = loan.id;  // This is loans.id (loan_id)
                    option.textContent = loan.loan_name;
                    loanTypeSelect.appendChild(option);
                });
            } else {
                // No active loans found
                activeLoansTable.innerHTML = '<tr><td colspan="3">No active loans found for this member.</td></tr>';
            }
        })
        .catch(error => {
            console.error('Error fetching active loans:', error);
            activeLoansTable.innerHTML = '<tr><td colspan="3">Error loading active loans. Please try again.</td></tr>';
        });
}

// Handle form submission and show receipt
receiptForm.addEventListener('submit', function(e) {
    e.preventDefault();

    // Get form data
    const formData = new FormData(this);

    // Show loading indicator
    const submitButton = this.querySelector('button[type="submit"]');
    const originalButtonText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.textContent = 'Processing...';

    // Send form data to server
    fetch('receipt_management_process.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Reset button
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;

        if (data.success) {
            // Fill receipt data
            fillReceiptData(data);

            // Show receipt modal
            const receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            receiptModal.show();

            // Reset form after successful submission
            receiptForm.reset();
            // Hide loan type field after reset
            loanTypeField.classList.add('hidden');
            activeLoansContainer.classList.add('hidden');
        } else {
            // Show error message
            alert(data.message || 'Error processing payment.');
        }
    })
    .catch(error => {
        // Reset button
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;

        // Show error message
        alert('An error occurred while processing the payment.');
        console.error(error);
    });
});

// Fill receipt data
function fillReceiptData(data) {
    // Receipt number formatting - use receipt ID with a prefix
    const receiptNo = 'RCT-' + String(data.receipt_id).padStart(6, '0');
    document.getElementById('receipt-no').textContent = receiptNo;

    // Format date
    let receiptDate = new Date();
    if (data.receipt_date) {
        receiptDate = new Date(data.receipt_date);
    }
    document.getElementById('receipt-date').textContent = formatDate(receiptDate);

    // Member info
    document.getElementById('receipt-member-id').textContent = data.member_id || '';
    document.getElementById('receipt-member-name').textContent = data.member_name || '';

    // Receipt type
    const receiptTypeText = data.receipt_type_text ||
                           (data.receipt_type ?
                            ucfirst(data.receipt_type.replace('_', ' ')) :
                            '');
    document.getElementById('receipt-type').textContent = receiptTypeText;

    // Loan type (if applicable)
    // Loan type (if applicable)
    const loanDetailsRow = document.querySelector('.loan-details');
    if (data.loan_type_name && (data.receipt_type === 'loan_repayment' || data.receipt_type === 'late_fee')) {
        loanDetailsRow.classList.remove('hidden');
        document.getElementById('receipt-loan-type').textContent = data.loan_type_name || '';
    } else {
        loanDetailsRow.classList.add('hidden');
    }

    // Amount
    const formattedAmount = 'Rs. ' + formatNumber(data.amount);
    document.getElementById('receipt-amount').textContent = formattedAmount;

    // Amount in words
    const amountInWords = data.amount_in_words || 'Amount in words not available';
    document.getElementById('receipt-amount-words').textContent = amountInWords;

    // Balance info for deposits
    const balanceInfoRow = document.querySelector('.balance-info');
    if (data.receipt_type === 'deposit' && data.new_balance !== null) {
        balanceInfoRow.classList.remove('hidden');
        document.getElementById('receipt-balance').textContent = 'Rs. ' + formatNumber(data.new_balance);
    } else {
        balanceInfoRow.classList.add('hidden');
    }

    
    // Remaining loan info for loan repayments and late fees
    const loanBalanceRow = document.querySelector('.loan-balance');
    if ((data.receipt_type === 'loan_repayment' || data.receipt_type === 'late_fee') && data.remaining_loan !== null) {
        loanBalanceRow.classList.remove('hidden');
        document.getElementById('receipt-loan-balance').textContent = 'Rs. ' + formatNumber(data.remaining_loan);

        // Add loan status message
        if (data.remaining_loan <= 0) {
            document.getElementById('receipt-info').textContent = 'This loan has been fully repaid and is now closed.';
            document.getElementById('receipt-info').style.color = '#28a745';
        } else {
            document.getElementById('receipt-info').textContent = '';
        }
    } else {
        loanBalanceRow.classList.add('hidden');
        document.getElementById('receipt-info').textContent = '';
    }
}

// Format date
function formatDate(date) {
    const day = String(date.getDate()).padStart(2, '0');
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const year = date.getFullYear();

    return `${day}-${month}-${year}`;
}

// Format number with comma separators
function formatNumber(num) {
    return parseFloat(num).toLocaleString('en-IN', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

// Uppercase first letter
function ucfirst(string) {
    return string.charAt(0).toUpperCase() + string.slice(1);
}

// Print receipt function
function printReceipt() {
    window.print();
}
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>