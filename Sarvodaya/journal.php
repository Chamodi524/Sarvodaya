<?php
// Database connection
$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'sarvodaya';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Default date range (current month)
$start_date = date('Y-m-01');
$end_date = date('Y-m-t');

// Handle date filter
if (isset($_GET['filter'])) {
    $start_date = $_GET['start_date'];
    $end_date = $_GET['end_date'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>General Journal</title>
    <style>
        :root {
            --primary-color: rgb(255, 140, 0);
            --primary-dark: rgb(230, 126, 0);
            --primary-light: rgba(255, 140, 0, 0.1);
            --secondary-color: #2c3e50;
            --accent-color: #e74c3c;
            --success-color: #27ae60;
            --info-color: #3498db;
            --warning-color: #f39c12;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            padding: 0;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 25px 30px;
            text-align: center;
            position: relative;
        }
        
        h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.2);
        }
        
        .subtitle {
            font-size: 14px;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .filter-form {
            background: white;
            padding: 20px;
            border-bottom: 1px solid #eee;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: flex-end;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        
        .form-group {
            margin-bottom: 0;
            flex: 1;
            min-width: 200px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--secondary-color);
            font-size: 14px;
        }
        
        input[type="date"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="date"]:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px var(--primary-light);
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            height: 40px;
        }
        
        button:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .print-btn {
            background-color: var(--secondary-color);
            margin-left: auto;
        }
        
        .print-btn:hover {
            background-color: #1a252f;
        }
        
        .content {
            padding: 0 20px 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            font-size: 14px;
        }
        
        th {
            background-color: var(--primary-light);
            color: var(--secondary-color);
            font-weight: 600;
            padding: 12px 15px;
            text-align: left;
            border-bottom: 2px solid var(--primary-color);
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        td {
            padding: 12px 15px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .debit {
            color: var(--accent-color);
            font-weight: bold;
            text-align: right;
        }
        
        .credit {
            color: var(--success-color);
            font-weight: bold;
            text-align: right;
        }
        
        .payment {
            border-left: 4px solid var(--info-color);
        }
        
        .receipt {
            border-left: 4px solid var(--success-color);
        }
        
        .interest {
            border-left: 4px solid var(--warning-color);
        }
        
        .amount-cell {
            text-align: right;
        }
        
        .zero-amount {
            color: #bbb;
        }
        
        .tfoot-totals {
            background-color: #f8f8f8;
            font-weight: bold;
        }
        
        .tfoot-period {
            background-color: var(--primary-light);
            color: var(--secondary-color);
        }
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            background-color: var(--primary-light);
            color: var(--primary-dark);
        }
        
        @media print {
            .filter-form, .print-btn {
                display: none;
            }
            
            body {
                background: none;
                padding: 0;
                font-size: 12px;
            }
            
            .container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                width: 100%;
            }
            
            .header {
                padding: 15px;
                page-break-after: avoid;
            }
            
            th, td {
                padding: 8px 10px;
            }
        }
        
        /* Animation */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        table tbody tr {
            animation: fadeIn 0.3s ease forwards;
            opacity: 0;
        }
        
        table tbody tr:nth-child(1) { animation-delay: 0.1s; }
        table tbody tr:nth-child(2) { animation-delay: 0.2s; }
        table tbody tr:nth-child(3) { animation-delay: 0.3s; }
        table tbody tr:nth-child(4) { animation-delay: 0.4s; }
        table tbody tr:nth-child(5) { animation-delay: 0.5s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>General Journal</h1>
            <div class="subtitle">Financial Transactions Overview</div>
        </div>
        
        <form method="GET" class="filter-form">
            <div class="form-group">
                <label for="start_date">From Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= $start_date ?>" required>
            </div>
            
            <div class="form-group">
                <label for="end_date">To Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= $end_date ?>" required>
            </div>
            
            <button type="submit" name="filter">Apply Filter</button>
            <button type="button" class="print-btn" onclick="window.print()">
                <i class="fas fa-print"></i> Print Journal
            </button>
        </form>
        
        <div class="content">
            <?php
            // Get filtered transactions with debit and credit swapped
            $query = "SELECT 
                        'payment' AS transaction_type,
                        id,
                        member_id,
                        payment_date AS transaction_date,
                        0 AS debit_amount,
                        amount AS credit_amount,
                        description AS details,
                        NULL AS reference_id,
                        payment_type AS reference_type
                      FROM payments
                      WHERE DATE(payment_date) BETWEEN ? AND ?
                      
                      UNION ALL
                      
                      SELECT 
                        'receipt' AS transaction_type,
                        id,
                        member_id,
                        receipt_date AS transaction_date,
                        amount AS debit_amount,
                        0 AS credit_amount,
                        NULL AS details,
                        loan_id AS reference_id,
                        receipt_type AS reference_type
                      FROM receipts
                      WHERE DATE(receipt_date) BETWEEN ? AND ?
                      
                      UNION ALL
                      
                      SELECT 
                        'interest' AS transaction_type,
                        id,
                        member_id,
                        created_at AS transaction_date,
                        interest_amount AS debit_amount,
                        0 AS credit_amount,
                        CONCAT('Interest for ', period_start_date, ' to ', period_end_date) AS details,
                        account_type_id AS reference_id,
                        status AS reference_type
                      FROM interest_calculations
                      WHERE DATE(created_at) BETWEEN ? AND ?
                      
                      ORDER BY transaction_date DESC";

            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
            $stmt->execute();
            $result = $stmt->get_result();
            ?>
            
            <table>
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Member</th>
                        <th>Reference</th>
                        <th>Details</th>
                        <th>Debit</th>
                        <th>Credit</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $result->fetch_assoc()): ?>
                    <tr class="<?= $row['transaction_type'] ?>">
                        <td><?= date('M j, Y', strtotime($row['transaction_date'])) ?></td>
                        <td>
                            <?php if($row['transaction_type'] == 'payment'): ?>
                                <span class="badge">Payment</span>
                            <?php elseif($row['transaction_type'] == 'receipt'): ?>
                                <span class="badge" style="background-color: rgba(39, 174, 96, 0.1); color: #27ae60;">Receipt</span>
                            <?php else: ?>
                                <span class="badge" style="background-color: rgba(243, 156, 18, 0.1); color: #f39c12;">Interest</span>
                            <?php endif; ?>
                        </td>
                        <td>#<?= $row['member_id'] ?></td>
                        <td>
                            <?= $row['reference_type'] ?>
                            <?= $row['reference_id'] ? '<br><small>Ref #' . $row['reference_id'] . '</small>' : '' ?>
                        </td>
                        <td><?= $row['details'] ? $row['details'] : '&mdash;' ?></td>
                        <td class="debit amount-cell">
                            <?= $row['debit_amount'] > 0 ? number_format($row['debit_amount'], 2) : '<span class="zero-amount">0.00</span>' ?>
                        </td>
                        <td class="credit amount-cell">
                            <?= $row['credit_amount'] > 0 ? number_format($row['credit_amount'], 2) : '<span class="zero-amount">0.00</span>' ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                    
                    <?php if($result->num_rows === 0): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 30px; color: #999;">
                            No transactions found for the selected period
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
                <tfoot>
                    <?php
                    // Calculate totals for the filtered period with debit and credit swapped
                    $total_query = "SELECT 
                                    SUM(debit) AS total_debit,
                                    SUM(credit) AS total_credit
                                  FROM (
                                    SELECT 0 AS debit, amount AS credit FROM payments WHERE DATE(payment_date) BETWEEN ? AND ?
                                    UNION ALL
                                    SELECT amount AS debit, 0 AS credit FROM receipts WHERE DATE(receipt_date) BETWEEN ? AND ?
                                    UNION ALL
                                    SELECT interest_amount AS debit, 0 AS credit FROM interest_calculations WHERE DATE(created_at) BETWEEN ? AND ?
                                  ) AS combined_transactions";

                    $total_stmt = $conn->prepare($total_query);
                    $total_stmt->bind_param("ssssss", $start_date, $end_date, $start_date, $end_date, $start_date, $end_date);
                    $total_stmt->execute();
                    $totals = $total_stmt->get_result()->fetch_assoc();
                    ?>
                    <tr class="tfoot-totals">
                        <td colspan="5" style="text-align: right; font-weight: bold;">Totals:</td>
                        <td class="debit amount-cell"><?= number_format($totals['total_debit'], 2) ?></td>
                        <td class="credit amount-cell"><?= number_format($totals['total_credit'], 2) ?></td>
                    </tr>
                    <tr class="tfoot-period">
                        <td colspan="7" style="text-align: center; font-weight: bold;">
                            <?= date('F j, Y', strtotime($start_date)) ?> to <?= date('F j, Y', strtotime($end_date)) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <script>
        // Simple animation trigger
        document.addEventListener('DOMContentLoaded', function() {
            // Add sequential animation delays for table rows
            const rows = document.querySelectorAll('table tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
            });
        });
    </script>
</body>
</html>

<?php
$conn->close();
?>