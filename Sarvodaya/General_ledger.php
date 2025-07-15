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
    <title>General Ledger - Sarvodaya Bank</title>
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
        .account-details {
            background-color: #f8f4e5;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .interest-row {
            background-color: #f8f4e5;
        }
        .deposit-row {
            background-color: #e8f5e9;
        }
        .withdrawal-row {
            background-color: #ffebee;
        }
        .adjustment-row {
            background-color: #e3f2fd;
        }
        .fee-row {
            background-color: #fff8e1;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">General Ledger - Sarvodaya Bank</h1>

        <!-- Search Form -->
        <div class="card">
            <h2>Search Member Ledger</h2>
            <form method="GET" action="">
                <div class="mb-3">
                    <label for="member_id" class="form-label" style="font-size: 20px;">Member ID</label>
                    <input type="number" class="form-control" id="member_id" style="font-size: 20px;" name="member_id" required>
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
        </div>

        <!-- Member Details and Ledger Table -->
        <?php
        if (isset($_GET['member_id']) && isset($_GET['start_date']) && isset($_GET['end_date'])) {
            $member_id = $_GET['member_id'];
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

                // Display member details
                echo '<div class="member-details">
                    <h2>Member Details</h2>
                    <p style="font-size: 20px;"><strong>Name:</strong> ' . htmlspecialchars($member_name) . '</p>
                    <p style="font-size: 20px;"><strong>Address:</strong> ' . htmlspecialchars($member_address) . '</p>
                </div>';

                // Get opening balance - the running_balance of the last transaction before start_date
                $opening_balance_sql = "
                    SELECT running_balance 
                    FROM savings_transactions 
                    WHERE member_id = ? 
                    AND transaction_date < ? 
                    ORDER BY transaction_date DESC, id DESC 
                    LIMIT 1
                ";
                $stmt = $conn->prepare($opening_balance_sql);
                $stmt->bind_param("is", $member_id, $start_date);
                $stmt->execute();
                $opening_balance_result = $stmt->get_result();
                
                // Default opening balance to 0 if no prior transactions
                $opening_balance = 0;
                
                if ($opening_balance_result->num_rows > 0) {
                    $opening_balance_row = $opening_balance_result->fetch_assoc();
                    $opening_balance = $opening_balance_row['running_balance'];
                }

                // Fetch transactions for the date range
                $transactions_sql = "
                    SELECT 
                        id,
                        transaction_type,
                        amount,
                        running_balance,
                        reference,
                        description,
                        transaction_date,
                        related_transaction_id
                    FROM savings_transactions
                    WHERE member_id = ? 
                    AND transaction_date BETWEEN ? AND ?
                    ORDER BY transaction_date ASC, id ASC
                ";
                
                $stmt = $conn->prepare($transactions_sql);
                $stmt->bind_param("iss", $member_id, $start_date, $end_date);
                $stmt->execute();
                $transactions_result = $stmt->get_result();

                // Display ledger table
                echo '<div class="card">
                    <h2>Ledger for Member ID: ' . htmlspecialchars($member_id) . '</h2>
                    <p style="font-size: 20px;">Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)) . '</p>';

                echo '<div class="account-details mb-3">
                    <h4>Opening Balance as of ' . date('d/m/Y', strtotime($start_date)) . ': 
                    <span class="text-primary">Rs.' . number_format($opening_balance, 2) . '</span></h4>
                </div>';

                echo '<table class="table table-bordered">
                        <thead>
                            <tr>
                                <th style="font-size: 20px;">Date</th>
                                <th style="font-size: 20px;">Transaction ID</th>
                                <th style="font-size: 20px;">Description</th>
                                <th style="font-size: 20px;">Deposit</th>
                                <th style="font-size: 20px;">Withdrawal</th>
                                <th style="font-size: 20px;">Interest</th>
                                
                                <th style="font-size: 20px;">Balance</th>
                            </tr>
                        </thead>
                        <tbody>';

                // Display opening balance row
                echo '<tr>
                    <td style="font-size: 20px;">' . date('d/m/Y', strtotime($start_date)) . '</td>
                    <td style="font-size: 20px;">-</td>
                    <td style="font-size: 20px;"><strong>Opening Balance</strong></td>
                    <td style="font-size: 20px;"></td>
                    <td style="font-size: 20px;"></td>
                    <td style="font-size: 20px;"></td>
                    
                    <td style="font-size: 20px;"><strong>Rs.' . number_format($opening_balance, 2) . '</strong></td>
                </tr>';

                // Initialize totals
                $total_deposits = 0;
                $total_withdrawals = 0;
                $total_interest = 0;
                $total_adjustments = 0;
                $total_fees = 0;
                $closing_balance = $opening_balance;

                // Process transactions
                if ($transactions_result->num_rows > 0) {
                    while ($row = $transactions_result->fetch_assoc()) {
                        $deposit = 0;
                        $withdrawal = 0;
                        $interest = 0;
                        $adjustment = 0;
                        $fee = 0;
                        $row_class = '';
                        $description = htmlspecialchars($row['description']);

                        // Set values based on transaction type
                        switch ($row['transaction_type']) {
                            case 'DEPOSIT':
                                $deposit = $row['amount'];
                                $total_deposits += $deposit;
                                $row_class = 'deposit-row';
                                break;
                            case 'WITHDRAWAL':
                                $withdrawal = $row['amount'];
                                $total_withdrawals += $withdrawal;
                                $row_class = 'withdrawal-row';
                                break;
                            case 'INTEREST':
                                $interest = $row['amount'];
                                $total_interest += $interest;
                                $row_class = 'interest-row';
                                break;
                            case 'ADJUSTMENT':
                                $adjustment = $row['amount'];
                                $total_adjustments += abs($adjustment);
                                $row_class = 'adjustment-row';
                                break;
                            case 'FEE':
                                $fee = $row['amount'];
                                $total_fees += $fee;
                                $row_class = 'fee-row';
                                break;
                        }

                        $closing_balance = $row['running_balance'];

                        echo '<tr class="' . $row_class . '">
                            <td>' . date('d/m/Y', strtotime($row['transaction_date'])) . '</td>
                            <td>' . $row['id'] . '</td>
                            <td>' . $description;
                        
                        if (!empty($row['reference'])) {
                            echo ' (Ref: ' . htmlspecialchars($row['reference']) . ')';
                        }
                        
                        echo '</td>
                            <td>' . ($deposit > 0 ? 'Rs. ' . number_format($deposit, 2) : '') . '</td>
                            <td>' . ($withdrawal > 0 ? 'Rs. ' . number_format($withdrawal, 2) : '') . '</td>
                            <td>' . ($interest > 0 ? 'Rs. ' . number_format($interest, 2) : '') . '</td>
                            
                            <td>Rs. ' . number_format($row['running_balance'], 2) . '</td>
                        </tr>';
                    }
                } else {
                    echo '<tr><td colspan="8" class="text-center" style="font-size: 20px;">No transactions found for the selected period.</td></tr>';
                }

                // Display totals row
                echo '<tr class="table-secondary">
                    <td colspan="3" style="font-size: 20px;"><strong>Totals</strong></td>
                    <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_deposits, 2) . '</strong></td>
                    <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_withdrawals, 2) . '</strong></td>
                    <td style="font-size: 20px;"><strong>Rs. ' . number_format($total_interest, 2) . '</strong></td>
                    
                    <td style="font-size: 20px;"><strong>Rs. ' . number_format($closing_balance, 2) . '</strong></td>
                </tr>';

                echo '</tbody>
                    </table>
                    
                    <div class="account-details mt-3">
                        <h4>Closing Balance as of ' . date('d/m/Y', strtotime($end_date)) . ': 
                        <span class="text-primary">Rs. ' . number_format($closing_balance, 2) . '</span></h4>
                    </div>
                </div>';
            } else {
                echo '<div class="alert alert-danger mt-4">Member not found!</div>';
            }
        }
        ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>