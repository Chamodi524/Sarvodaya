<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Ledger - Sarvodaya Bank</title>
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
        .table {
            margin-top: 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .table th, .table td {
            vertical-align: middle;
        }
        .member-details {
            background-color: #fff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .loan-details {
            background-color: #f8f4e5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .payment-row {
            background-color: #e8f5e9;
        }
        .principal-row {
            background-color: #e3f2fd;
        }
        .interest-row {
            background-color: #fff8e1;
        }
        .disburse-row {
            background-color: #ffebee;
        }
        .penalty-row {
            background-color: #ede7f6;
        }
        .fee-row {
            background-color: #fce4ec;
        }
        .overdue-row {
            background-color: #ffcdd2;
        }
        .loader {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Loan Ledger - Sarvodaya Bank</h1>

        <!-- Search Form -->
        <div class="card">
            <h2>Search Member Loan Ledger</h2>
            <form method="GET" action="" id="loanSearchForm">
                <div class="mb-3">
                    <label for="member_id" class="form-label" style="font-size: 20px;">Member ID</label>
                    <input type="number" class="form-control" id="member_id" style="font-size: 20px;" name="member_id" required>
                </div>
                <div id="loanSelectContainer" class="mb-3" style="display: none;">
                    <label for="loan_id" class="form-label" style="font-size: 20px;">Loan Type</label>
                    <select class="form-control" id="loan_id" style="font-size: 20px;" name="loan_id" required disabled>
                        <option value="" style="font-size: 20px;">Select a loan</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="start_date" class="form-label" style="font-size: 20px;">Start Date</label>
                    <input type="date" class="form-control" id="start_date" style="font-size: 20px;" name="start_date" required>
                </div>
                <div class="mb-3">
                    <label for="end_date" class="form-label" style="font-size: 20px;">End Date</label>
                    <input type="date" class="form-control" id="end_date" style="font-size: 20px;" name="end_date" required>
                </div>
                <button type="submit" class="btn btn-custom" style="font-size: 20px;">Search</button>
            </form>
            <div id="loader" class="loader">
                <div class="spinner-border text-warning" role="status">
                    <span class="visually-hidden" style="font-size: 20px;">Loading...</span>
                </div>
                <p style="font-size: 20px;">Loading active loans...</p>
            </div>
        </div>

        <!-- Member Details and Ledger Table -->
        <?php
        if (isset($_GET['member_id']) && isset($_GET['loan_id']) && isset($_GET['start_date']) && isset($_GET['end_date'])) {
            $member_id = $_GET['member_id'];
            $loan_id = $_GET['loan_id'];
            $start_date = $_GET['start_date'];
            $end_date = $_GET['end_date'];

            // Fetch member details
            $member_sql = "SELECT name, address FROM members WHERE id = ?";
            $stmt = $conn->prepare($member_sql);
            $stmt->bind_param("i", $member_id);
            $stmt->execute();
            $member_result = $stmt->get_result();

            if ($member_result->num_rows > 0) {
                $member = $member_result->fetch_assoc();
                $member_name = $member['name'];
                $member_address = $member['address'];

                // Fetch loan details
                $loan_sql = "SELECT l.*, lt.loan_name, lt.interest_rate 
                             FROM loans l 
                             JOIN loan_types lt ON l.loan_type_id = lt.id 
                             WHERE l.id = ? AND l.member_id = ?";
                $stmt = $conn->prepare($loan_sql);
                $stmt->bind_param("ii", $loan_id, $member_id);
                $stmt->execute();
                $loan_result = $stmt->get_result();

                if ($loan_result->num_rows > 0) {
                    $loan = $loan_result->fetch_assoc();
                    $loan_type = $loan['loan_name'];
                    $loan_interest_rate = $loan['interest_rate'];
                    $loan_amount = $loan['amount'];
                    $loan_status = $loan['status'];
                    $loan_start_date = $loan['start_date'];
                    $loan_end_date = $loan['end_date'];
                    $total_repayment_amount = $loan['total_repayment_amount'];

                    // Display member details
                    echo '<div class="member-details">
                        <h2>Member Details</h2>
                        <p style="font-size: 20px;"><strong>Name:</strong> ' . htmlspecialchars($member_name) . '</p>
                        <p style="font-size: 20px;"><strong>Address:</strong> ' . htmlspecialchars($member_address) . '</p>
                        
                        <div class="loan-details">
                            <h3>Loan Details</h3>
                            <p style="font-size: 20px;"><strong>Loan Type:</strong> ' . htmlspecialchars($loan_type) . '</p>
                            <p style="font-size: 20px;"><strong>Loan Amount:</strong> Rs. ' . number_format($loan_amount, 2) . '</p>
                            <p style="font-size: 20px;"><strong>Interest Rate:</strong> ' . $loan_interest_rate . '%</p>
                            <p style="font-size: 20px;"><strong>Start Date:</strong> ' . date('d/m/Y', strtotime($loan_start_date)) . '</p>
                            <p style="font-size: 20px;"><strong>End Date:</strong> ' . date('d/m/Y', strtotime($loan_end_date)) . '</p>
                            <p style="font-size: 20px;"><strong>Status:</strong> ' . ucfirst($loan_status) . '</p>
                            <p style="font-size: 20px;"><strong>Total Repayment Amount:</strong> Rs. ' . number_format($total_repayment_amount, 2) . '</p>
                        </div>
                    </div>';

                    // Get total repaid amount - sum of all payments before the start date
                    $total_repaid_sql = "
                        SELECT SUM(actual_payment_amount) as total_repaid 
                        FROM loan_installments 
                        WHERE loan_id = ? 
                        AND payment_status = 'paid'
                        AND actual_payment_date < ?
                    ";
                    $stmt = $conn->prepare($total_repaid_sql);
                    $stmt->bind_param("is", $loan_id, $start_date);
                    $stmt->execute();
                    $total_repaid_result = $stmt->get_result();
                    $total_repaid_row = $total_repaid_result->fetch_assoc();
                    $total_repaid = $total_repaid_row['total_repaid'] ?: 0;

                    // Get outstanding balance as of start date
                    $outstanding_balance = $total_repayment_amount - $total_repaid;

                    // Fetch installments for the date range - Updated to use actual_payment_date for overdue
                    $installments_sql = "
                        SELECT 
                            id,
                            installment_number,
                            payment_date,
                            payment_amount,
                            principal_amount,
                            interest_amount,
                            remaining_balance,
                            payment_status,
                            actual_payment_date,
                            actual_payment_amount,
                            late_fee
                        FROM loan_installments
                        WHERE loan_id = ? 
                        AND member_id = ?
                        AND (
                            (payment_status = 'paid' AND actual_payment_date BETWEEN ? AND ?)
                            OR 
                            (payment_status = 'pending' AND payment_date BETWEEN ? AND ?)
                            OR
                            (payment_status = 'overdue' AND actual_payment_date BETWEEN ? AND ?)
                        )
                        ORDER BY 
                            CASE WHEN payment_status IN ('paid', 'overdue') THEN actual_payment_date ELSE payment_date END ASC,
                            installment_number ASC
                    ";
                    
                    $stmt = $conn->prepare($installments_sql);
                    $stmt->bind_param("iissssss", $loan_id, $member_id, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
                    $stmt->execute();
                    $installments_result = $stmt->get_result();

                    // Display ledger table
                    echo '<div class="card">
                        <h2>Loan Ledger for Member ID: ' . htmlspecialchars($member_id) . '</h2>
                        <p style="font-size: 20px;">Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</p>';

                    echo '<table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="font-size: 20px;">Date</th>
                                    <th style="font-size: 20px;">Installment #</th>
                                    <th style="font-size: 20px;">Description</th>
                                    <th style="font-size: 20px;">Payment</th>
                                    <th style="font-size: 20px;">Principal</th>
                                    <th style="font-size: 20px;">Interest</th>
                                    <th style="font-size: 20px;">Late Fee</th>
                                </tr>
                            </thead>
                            <tbody>';

                    // Initialize totals
                    $total_payments = 0;
                    $total_principal = 0;
                    $total_interest = 0;
                    $total_late_fees = 0;
                    $closing_balance = $outstanding_balance;

                    // Process installments
                    if ($installments_result->num_rows > 0) {
                        while ($row = $installments_result->fetch_assoc()) {
                            $payment = 0;
                            $principal = 0;
                            $interest = 0;
                            $late_fee = $row['late_fee'] ? $row['late_fee'] : 0;
                            $row_class = '';
                            $description = "Installment #" . $row['installment_number'];

                            // For paid installments
                            if ($row['payment_status'] == 'paid') {
                                $payment = $row['actual_payment_amount'];
                                $principal = ($row['principal_amount'] / $row['payment_amount']) * $payment;
                                $interest = ($row['interest_amount'] / $row['payment_amount']) * $payment;
                                $closing_balance = $row['remaining_balance'];
                                $row_class = 'payment-row';
                                $display_date = date('d/m/Y', strtotime($row['actual_payment_date']));
                            } else {
                                // For pending or overdue installments
                                $payment = $row['payment_amount'];
                                $principal = $row['principal_amount'];
                                $interest = $row['interest_amount'];
                                
                                if ($row['payment_status'] == 'overdue') {
                                    $row_class = 'overdue-row';
                                    $description .= ' (OVERDUE)';
                                    // Use actual_payment_date for overdue if available, otherwise payment_date
                                    $display_date = $row['actual_payment_date'] ? 
                                                  date('d/m/Y', strtotime($row['actual_payment_date'])) : 
                                                  date('d/m/Y', strtotime($row['payment_date']));
                                } else {
                                    $row_class = 'interest-row';
                                    $display_date = date('d/m/Y', strtotime($row['payment_date']));
                                }
                            }

                            // ADD ALL AMOUNTS TO TOTALS (regardless of payment status)
                            $total_payments += $payment;
                            $total_principal += $principal;
                            $total_interest += $interest;
                            $total_late_fees += $late_fee;

                            echo '<tr class="' . $row_class . '">
                                <td style="font-size: 20px;">' . $display_date . '</td>
                                <td style="font-size: 20px;">' . $row['installment_number'] . '</td>
                                <td style="font-size: 20px;">' . $description . '</td>
                                <td style="font-size: 20px;">' . ($payment > 0 ? 'Rs. ' . number_format($payment, 2) : '') . '</td>
                                <td style="font-size: 20px;">' . ($principal > 0 ? 'Rs. ' . number_format($principal, 2) : '') . '</td>
                                <td style="font-size: 20px;">' . ($interest > 0 ? 'Rs. ' . number_format($interest, 2) : '') . '</td>
                                <td style="font-size: 20px;">' . ($late_fee > 0 ? 'Rs. ' . number_format($late_fee, 2) : '') . '</td>
                            </tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">No installments found for the selected period.</td></tr>';
                    }

                    // Initialize additional totals
                    $total_fees = 0;
                    $total_adjustments = 0;
                    
                    // Check if loan_additional_transactions table exists
                    $table_check_sql = "SHOW TABLES LIKE 'loan_additional_transactions'";
                    $table_result = $conn->query($table_check_sql);
                    $additional_table_exists = ($table_result->num_rows > 0);
                    
                    // Only fetch additional transactions if the table exists
                    if ($additional_table_exists) {
                        // Fetch additional transactions (fees, adjustments, etc.)
                        $additional_sql = "
                            SELECT 
                                id,
                                transaction_type,
                                amount,
                                description,
                                transaction_date,
                                affecting_balance
                            FROM loan_additional_transactions
                            WHERE loan_id = ? AND member_id = ?
                            AND transaction_date BETWEEN ? AND ?
                            ORDER BY transaction_date ASC, id ASC
                        ";
                        
                        $stmt = $conn->prepare($additional_sql);
                        $stmt->bind_param("iiss", $loan_id, $member_id, $start_date, $end_date);
                        $stmt->execute();
                        $additional_result = $stmt->get_result();

                        // Process additional transactions
                        if ($additional_result && $additional_result->num_rows > 0) {
                            while ($row = $additional_result->fetch_assoc()) {
                                $fee = 0;
                                $adjustment = 0;
                                $row_class = '';
                                
                                // Set values based on transaction type
                                switch ($row['transaction_type']) {
                                    case 'FEE':
                                        $fee = $row['amount'];
                                        $total_fees += $fee;
                                        $row_class = 'fee-row';
                                        if ($row['affecting_balance']) {
                                            $closing_balance += $fee;
                                        }
                                        break;
                                    case 'ADJUSTMENT':
                                        $adjustment = $row['amount'];
                                        $total_adjustments += $adjustment;
                                        $row_class = 'interest-row';
                                        if ($row['affecting_balance']) {
                                            $closing_balance += $adjustment;
                                        }
                                        break;
                                }

                                echo '<tr class="' . $row_class . '">
                                    <td style="font-size: 20px;">' . date('d/m/Y', strtotime($row['transaction_date'])) . '</td>
                                    <td style="font-size: 20px;">' . $row['id'] . '</td>
                                    <td style="font-size: 20px;">' . htmlspecialchars($row['description']) . '</td>
                                    <td style="font-size: 20px;">-</td>
                                    <td style="font-size: 20px;">-</td>
                                    <td style="font-size: 20px;">-</td>
                                    <td style="font-size: 20px;">';
                                
                                if ($fee > 0) {
                                    echo 'Rs. ' . number_format($fee, 2);
                                } elseif ($adjustment != 0) {
                                    $prefix = $adjustment > 0 ? '+' : '';
                                    echo $prefix . 'Rs. ' . number_format($adjustment, 2);
                                }
                                
                                echo '</td>
                                </tr>';
                            }
                        }
                    }

                    // Display totals row
                    echo '<tr class="table-secondary">
                        <td colspan="3" style="font-size: 20px;"><strong>Totals</strong></td>
                        <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_payments, 2) . '</strong></td>
                        <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_principal, 2) . '</strong></td>
                        <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_interest, 2) . '</strong></td>
                        <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_late_fees, 2) . '</strong></td>
                    </tr>';

                    echo '</tbody>
                        </table>
                        
                        <div class="loan-details mt-3">
                            
                        </div>
                    </div>';
                } else {
                    echo '<div class="alert alert-danger mt-4">Loan not found or does not belong to this member!</div>';
                }
            } else {
                echo '<div class="alert alert-danger mt-4">Member not found!</div>';
            }
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const memberIdInput = document.getElementById('member_id');
            const loanSelect = document.getElementById('loan_id');
            const loanSelectContainer = document.getElementById('loanSelectContainer');
            const loader = document.getElementById('loader');
            
            memberIdInput.addEventListener('change', function() {
                const memberId = this.value.trim();
                
                if (memberId) {
                    // Clear previous options
                    loanSelect.innerHTML = '<option value="">Select a loan</option>';
                    loanSelect.disabled = true;
                    loanSelectContainer.style.display = 'none';
                    
                    // Show loader
                    loader.style.display = 'block';
                    
                    // Fetch active loans for this member
                    fetch(`get_active_loans.php?member_id=${memberId}`)
                        .then(response => response.json())
                        .then(data => {
                            // Hide loader
                            loader.style.display = 'none';
                            
                            if (data.success && data.loans.length > 0) {
                                // Add loan options
                                data.loans.forEach(loan => {
                                    const option = document.createElement('option');
                                    option.value = loan.id;
                                    option.textContent = `${loan.loan_name} - Rs. ${loan.amount} (${loan.status})`;
                                    loanSelect.appendChild(option);
                                });
                                
                                // Enable loan selection
                                loanSelect.disabled = false;
                                loanSelectContainer.style.display = 'block';
                            } else {
                                alert('No loans found for this member.');
                            }
                        })
                        .catch(error => {
                            console.error('Error fetching loans:', error);
                            loader.style.display = 'none';
                            alert('Failed to fetch loans. Please try again.');
                        });
                }
            });
            
            // Pre-select values if they exist in URL
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('member_id')) {
                const memberId = urlParams.get('member_id');
                memberIdInput.value = memberId;
                
                // Trigger the change event to load loans
                const event = new Event('change');
                memberIdInput.dispatchEvent(event);
                
                // Wait for loans to load and then select the loan_id from URL
                setTimeout(() => {
                    if (urlParams.has('loan_id')) {
                        const loanId = urlParams.get('loan_id');
                        loanSelect.value = loanId;
                    }
                }, 1000);
            }
        });
    </script>
</body>
</html>