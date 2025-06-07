<?php
// Database connection settings
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';
$db_name = 'sarvodaya';

// Set timezone to Sri Lanka's timezone (Asia/Colombo)
date_default_timezone_set('Asia/Colombo');

// Initialize variables
$error_message = '';
$success_message = '';
$action_performed = false;
$member_id = '';
$account_type_id = '';
$calculation_date = date('Y-m-d');
$period_start_date = date('Y-m-d', strtotime('-30 days'));
$period_end_date = date('Y-m-d');
$calculation_results = [];

// Establish database connection
try {
    $conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Connection failed: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['calculate_interest'])) {
        // Get form data
        $member_id = isset($_POST['member_id']) ? trim($_POST['member_id']) : '';
        $account_type_id = isset($_POST['account_type_id']) ? trim($_POST['account_type_id']) : '';
        $calculation_date = isset($_POST['calculation_date']) ? trim($_POST['calculation_date']) : date('Y-m-d');
        $period_start_date = isset($_POST['period_start_date']) ? trim($_POST['period_start_date']) : date('Y-m-d', strtotime('-30 days'));
        $period_end_date = isset($_POST['period_end_date']) ? trim($_POST['period_end_date']) : date('Y-m-d');
        
        // Validate input
        if (empty($calculation_date) || empty($period_start_date) || empty($period_end_date)) {
            $error_message = "Please fill in all date fields.";
        } else if (strtotime($period_end_date) < strtotime($period_start_date)) {
            $error_message = "End date cannot be before start date.";
        } else {
            try {
                // Build the query based on user input
                $params = [];
                $whereClause = '';
                
                // Create member filter if provided
                if (!empty($member_id)) {
                    $whereClause .= " AND st.member_id = :member_id";
                    $params[':member_id'] = $member_id;
                }
                
                // Create account type filter if provided
                if (!empty($account_type_id)) {
                    $whereClause .= " AND st.account_type_id = :account_type_id";
                    $params[':account_type_id'] = $account_type_id;
                }
                
                // Get the latest balance for all relevant accounts
                $sql = "SELECT 
                          st.member_id,
                          m.name AS member_name,
                          st.account_type_id,
                          sat.account_name,
                          sat.interest_rate,
                          sat.minimum_balance,
                          sat.detail_no,
                          st.running_balance AS current_balance,
                          st.transaction_date AS last_transaction_date
                        FROM 
                          savings_transactions st
                        JOIN 
                          members m ON st.member_id = m.id
                        JOIN 
                          savings_account_types sat ON st.account_type_id = sat.id
                        WHERE 
                          st.id = (
                            SELECT MAX(id) 
                            FROM savings_transactions 
                            WHERE member_id = st.member_id 
                            AND account_type_id = st.account_type_id
                            AND transaction_date <= :period_end_date
                          ) 
                          $whereClause
                        ORDER BY 
                          st.member_id, st.account_type_id";
                
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':period_end_date', $period_end_date);
                
                // Bind any additional parameters
                foreach ($params as $param => $value) {
                    $stmt->bindValue($param, $value);
                }
                
                $stmt->execute();
                $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($accounts)) {
                    $error_message = "No eligible accounts found for interest calculation.";
                } else {
                    // Calculate interest for each account
                    $calculation_results = [];
                    
                    foreach ($accounts as $account) {
                        // Check if balance meets minimum requirement
                        $eligible_for_interest = $account['current_balance'] >= $account['minimum_balance'];
                        
                        // Calculate interest - CORRECTED: Using monthly rate directly
                        $interest_amount = 0;
                        if ($eligible_for_interest) {
                            // Simply multiply balance by monthly interest rate (already in percentage)
                            $interest_amount = $account['current_balance'] * ($account['interest_rate'] / 100);
                            
                            // Round to 2 decimal places
                            $interest_amount = round($interest_amount, 2);
                        }
                        
                        // Add to calculation results
                        $calculation_results[] = [
                            'member_id' => $account['member_id'],
                            'member_name' => $account['member_name'],
                            'account_type_id' => $account['account_type_id'],
                            'account_name' => $account['account_name'],
                            'opening_balance' => $account['current_balance'],
                            'interest_rate' => $account['interest_rate'],
                            'interest_amount' => $interest_amount,
                            'eligible_for_interest' => $eligible_for_interest,
                            'minimum_balance' => $account['minimum_balance'],
                            'detail_no' => $account['detail_no']
                        ];
                    }
                    
                    $action_performed = true;
                }
                
            } catch(PDOException $e) {
                $error_message = "Error calculating interest: " . $e->getMessage();
            }
        }
    } elseif (isset($_POST['post_interest'])) {
        // Post interest to both tables
        try {
            // Start transaction
            $conn->beginTransaction();
            
            // Get form data
            $calculation_date = isset($_POST['calculation_date']) ? trim($_POST['calculation_date']) : date('Y-m-d');
            $period_start_date = isset($_POST['period_start_date']) ? trim($_POST['period_start_date']) : '';
            $period_end_date = isset($_POST['period_end_date']) ? trim($_POST['period_end_date']) : '';
            
            // FIX: Create a timestamp with Sri Lanka time for transaction records
            $current_time = date('H:i:s'); // Current Sri Lanka time (already set via date_default_timezone_set)
            $transaction_datetime = $calculation_date . ' ' . $current_time;
            
            // Process each account from the hidden form fields
            $count = isset($_POST['account_count']) ? intval($_POST['account_count']) : 0;
            $posted_count = 0;
            
            for ($i = 0; $i < $count; $i++) {
                $member_id = $_POST["member_id_$i"];
                $account_type_id = $_POST["account_type_id_$i"];
                $opening_balance = $_POST["opening_balance_$i"];
                $interest_rate = $_POST["interest_rate_$i"];
                $interest_amount = $_POST["interest_amount_$i"];
                $eligible = $_POST["eligible_$i"];
                
                // Only process if eligible for interest and amount is greater than zero
                if ($eligible == 'true' && $interest_amount > 0) {
                    // 1. Insert the transaction record
                    $sql_transaction = "INSERT INTO savings_transactions (
                        member_id, 
                        account_type_id, 
                        transaction_type, 
                        amount, 
                        running_balance, 
                        reference, 
                        description, 
                        transaction_date
                    ) VALUES (
                        :member_id,
                        :account_type_id,
                        'INTEREST',
                        :amount,
                        :running_balance,
                        :reference,
                        :description,
                        :transaction_date
                    )";
                    
                    $stmt_transaction = $conn->prepare($sql_transaction);
                    
                    // Get current balance
                    $sql_balance = "SELECT running_balance 
                                    FROM savings_transactions 
                                    WHERE member_id = :member_id 
                                    AND account_type_id = :account_type_id 
                                    ORDER BY id DESC LIMIT 1";
                                    
                    $stmt_balance = $conn->prepare($sql_balance);
                    $stmt_balance->bindParam(':member_id', $member_id);
                    $stmt_balance->bindParam(':account_type_id', $account_type_id);
                    $stmt_balance->execute();
                    
                    $current_balance = $stmt_balance->fetchColumn();
                    $new_balance = $current_balance + $interest_amount;
                    
                    // FIX: Use Sri Lanka time format in the reference number
                    $reference = "INT-" . date('YmdHis') . "-" . $member_id . "-" . $account_type_id;
                    $description = "Interest credited for period " . 
                                    date('M d, Y', strtotime($period_start_date)) . " to " . 
                                    date('M d, Y', strtotime($period_end_date));
                    
                    $stmt_transaction->bindParam(':member_id', $member_id);
                    $stmt_transaction->bindParam(':account_type_id', $account_type_id);
                    $stmt_transaction->bindParam(':amount', $interest_amount);
                    $stmt_transaction->bindParam(':running_balance', $new_balance);
                    $stmt_transaction->bindParam(':reference', $reference);
                    $stmt_transaction->bindParam(':description', $description);
                    // FIX: Use full datetime format with Sri Lanka time
                    $stmt_transaction->bindParam(':transaction_date', $transaction_datetime);
                    
                    $stmt_transaction->execute();
                    $transaction_id = $conn->lastInsertId();
                    
                    // 2. Insert the interest calculation record
                    $sql_interest = "INSERT INTO interest_calculations (
                        member_id,
                        account_type_id,
                        calculation_date,
                        period_start_date,
                        period_end_date,
                        opening_balance,
                        interest_rate,
                        interest_amount,
                        days_calculated,
                        transaction_id,
                        status,
                        notes
                    ) VALUES (
                        :member_id,
                        :account_type_id,
                        :calculation_date,
                        :period_start_date,
                        :period_end_date,
                        :opening_balance,
                        :interest_rate,
                        :interest_amount,
                        :days_calculated,
                        :transaction_id,
                        'POSTED',
                        :notes
                    )";
                    
                    $stmt_interest = $conn->prepare($sql_interest);
                    
                    // For monthly interest, we're using 30 days or actual days in period
                    $days_calculated = (strtotime($period_end_date) - strtotime($period_start_date)) / (60 * 60 * 24) + 1;
                    
                    $notes = "Monthly interest calculation based on balance of " . number_format($opening_balance, 2) . 
                            " at " . $interest_rate . "% monthly rate";
                    
                    $stmt_interest->bindParam(':member_id', $member_id);
                    $stmt_interest->bindParam(':account_type_id', $account_type_id);
                    // Use full datetime for calculation_date as well for consistency
                    $stmt_interest->bindParam(':calculation_date', $transaction_datetime);
                    $stmt_interest->bindParam(':period_start_date', $period_start_date);
                    $stmt_interest->bindParam(':period_end_date', $period_end_date);
                    $stmt_interest->bindParam(':opening_balance', $opening_balance);
                    $stmt_interest->bindParam(':interest_rate', $interest_rate);
                    $stmt_interest->bindParam(':interest_amount', $interest_amount);
                    $stmt_interest->bindParam(':days_calculated', $days_calculated);
                    $stmt_interest->bindParam(':transaction_id', $transaction_id);
                    $stmt_interest->bindParam(':notes', $notes);
                    
                    $stmt_interest->execute();
                    $posted_count++;
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            if ($posted_count > 0) {
                $success_message = "Successfully posted interest for $posted_count account(s).";
            } else {
                $success_message = "No eligible accounts found for interest posting.";
            }
            
        } catch(PDOException $e) {
            // Roll back transaction on error
            $conn->rollBack();
            $error_message = "Error posting interest: " . $e->getMessage();
        }
    }
}

// Get account types for dropdown
$account_types = [];
try {
    $stmt = $conn->query("SELECT id, account_name FROM savings_account_types ORDER BY id");
    $account_types = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // Silently fail, will just show empty dropdown
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interest Calculation System</title>
    <style>
        :root {
            --primary-color: rgb(255, 140, 0); /* Orange theme color */
            --primary-hover: rgb(230, 126, 0); /* Darker orange for hover */
            --primary-light: rgba(255, 140, 0, 0.1); /* Light orange for backgrounds */
            --primary-border: rgba(255, 140, 0, 0.3); /* Orange for borders */
            --secondary-color: #2c3e50;
            --success-color: #27ae60;
            --error-color: #e74c3c;
            --light-gray: #f5f5f5;
            --med-gray: #ddd;
            --dark-gray: #6c757d;
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: var(--light-gray);
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background-color: #fff;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            border-top: 4px solid var(--primary-color);
        }
        
        h1, h2 {
            color: var(--secondary-color);
            margin-bottom: 20px;
        }
        
        h1 {
            padding-bottom: 10px;
            border-bottom: 2px solid var(--primary-light);
            color: var(--primary-color);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            grid-gap: 15px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: var(--secondary-color);
        }
        
        input[type="text"], input[type="date"], select {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--med-gray);
            border-radius: 4px;
            box-sizing: border-box;
            transition: border 0.3s;
        }
        
        input[type="text"]:focus, input[type="date"]:focus, select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 2px var(--primary-border);
        }
        
        .button-group {
            margin: 20px 0;
        }
        
        button {
            background-color: var(--primary-color);
            color: white;
            border: none;
            padding: 12px 18px;
            margin-right: 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
            transition: background-color 0.3s;
        }
        
        button[type="submit"][name="post_interest"] {
            background-color: var(--success-color);
        }
        
        button[type="reset"] {
            background-color: var(--error-color);
        }
        
        button:hover {
            background-color: var(--primary-hover);
            transform: translateY(-1px);
        }
        
        button[type="submit"][name="post_interest"]:hover {
            background-color: #219d54;
        }
        
        button[type="reset"]:hover {
            background-color: #d63031;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            border-radius: 5px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid var(--med-gray);
        }
        
        th {
            background-color: var(--primary-color);
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.9em;
        }
        
        tr:hover {
            background-color: var(--primary-light);
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .numeric {
            text-align: right;
        }
        
        .positive {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .negative {
            color: var(--error-color);
            font-weight: bold;
        }
        
        .not-eligible {
            background-color: #f8f9fa;
            color: var(--dark-gray);
            font-style: italic;
        }
        
        .error-message {
            color: white;
            background-color: var(--error-color);
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .success-message {
            color: white;
            background-color: var(--success-color);
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .no-results {
            padding: 30px;
            text-align: center;
            color: var(--dark-gray);
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-top: 20px;
        }
        
        .info-box {
            background-color: var(--primary-light);
            padding: 15px;
            border-left: 4px solid var(--primary-color);
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        @media screen and (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px 10px;
            }
            
            button {
                width: 100%;
                margin-bottom: 10px;
                margin-right: 0;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Interest Calculation System</h1>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <strong>Error:</strong> <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <strong>Success:</strong> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <p><strong>This system calculates interest based on:</strong></p>
            <ul>
                <li>Account balance at the end of the specified period</li>
                <li>Monthly interest rate defined in the savings_account_types table</li>
                <li>Minimum balance requirement for each account type</li>
            </ul>
            <p><strong>Note:</strong> Interest rates are applied as monthly rates directly to the balance.</p>
        </div>
        
        <h2>Interest Calculation Parameters</h2>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label for="calculation_date">Calculation Date:</label>
                    <input type="date" id="calculation_date" name="calculation_date" value="<?php echo htmlspecialchars($calculation_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="period_start_date">Period Start Date:</label>
                    <input type="date" id="period_start_date" name="period_start_date" value="<?php echo htmlspecialchars($period_start_date); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="period_end_date">Period End Date:</label>
                    <input type="date" id="period_end_date" name="period_end_date" value="<?php echo htmlspecialchars($period_end_date); ?>" required>
                </div>
            </div>
            
            <div class="form-grid">
                <div class="form-group">
                    <label for="member_id">Member ID (Optional):</label>
                    <input type="text" id="member_id" name="member_id" value="<?php echo htmlspecialchars($member_id); ?>" placeholder="Leave blank for all members">
                </div>
                
                <div class="form-group">
                    <label for="account_type_id">Account Type (Optional):</label>
                    <select id="account_type_id" name="account_type_id">
                        <option value="">All Account Types</option>
                        <?php foreach ($account_types as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['id']); ?>" <?php echo ($account_type_id == $type['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($type['account_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="button-group">
                <button type="submit" name="calculate_interest">Calculate Interest</button>
                <button type="reset">Clear</button>
            </div>
        </form>
        
        <?php if ($action_performed && !empty($calculation_results)): ?>
            <h2>Interest Calculation Results</h2>
            
            <form method="post" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <!-- Hidden fields to preserve calculation parameters -->
                <input type="hidden" name="calculation_date" value="<?php echo htmlspecialchars($calculation_date); ?>">
                <input type="hidden" name="period_start_date" value="<?php echo htmlspecialchars($period_start_date); ?>">
                <input type="hidden" name="period_end_date" value="<?php echo htmlspecialchars($period_end_date); ?>">
                <input type="hidden" name="account_count" value="<?php echo count($calculation_results); ?>">
                
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Account Type</th>
                            <th class="numeric">Balance (Rs.)</th>
                            <th class="numeric">Min. Balance (Rs.)</th>
                            <th class="numeric">Monthly Rate</th>
                            <th class="numeric">Interest Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_interest = 0;
                        $eligible_count = 0;
                        
                        foreach ($calculation_results as $i => $result): 
                            $is_eligible = $result['eligible_for_interest'];
                            $total_interest += $result['interest_amount'];
                            if ($is_eligible && $result['interest_amount'] > 0) $eligible_count++;
                        ?>
                            <tr class="<?php echo $is_eligible ? '' : 'not-eligible'; ?>">
                                <td>
                                    <?php echo htmlspecialchars($result['member_id']); ?>
                                    <input type="hidden" name="member_id_<?php echo $i; ?>" value="<?php echo htmlspecialchars($result['member_id']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($result['member_name']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars($result['account_name']); ?>
                                    <input type="hidden" name="account_type_id_<?php echo $i; ?>" value="<?php echo htmlspecialchars($result['account_type_id']); ?>">
                                </td>
                                <td class="numeric">
                                    <?php echo number_format($result['opening_balance'], 2); ?>
                                    <input type="hidden" name="opening_balance_<?php echo $i; ?>" value="<?php echo htmlspecialchars($result['opening_balance']); ?>">
                                </td>
                                <td class="numeric"><?php echo number_format($result['minimum_balance'], 2); ?></td>
                                <td class="numeric">
                                    <?php echo number_format($result['interest_rate'], 2); ?>%
                                    <input type="hidden" name="interest_rate_<?php echo $i; ?>" value="<?php echo htmlspecialchars($result['interest_rate']); ?>">
                                </td>
                                <td class="numeric positive">
                                    <?php echo number_format($result['interest_amount'], 2); ?>
                                    <input type="hidden" name="interest_amount_<?php echo $i; ?>" value="<?php echo htmlspecialchars($result['interest_amount']); ?>">
                                    <input type="hidden" name="eligible_<?php echo $i; ?>" value="<?php echo $is_eligible ? 'true' : 'false'; ?>">
                                    <!-- Add days_calculated even though it's not used in calculation directly -->
                                    <input type="hidden" name="days_calculated_<?php echo $i; ?>" value="<?php echo htmlspecialchars((strtotime($period_end_date) - strtotime($period_start_date)) / (60 * 60 * 24) + 1); ?>">
                                </td>
                                <td>
                                    <?php if (!$is_eligible): ?>
                                        <span class="negative">Below minimum balance</span>
                                    <?php elseif ($result['interest_amount'] <= 0): ?>
                                        <span class="negative">No interest</span>
                                    <?php else: ?>
                                        <span class="positive">Eligible</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <tr>
                            <td colspan="6" style="text-align: right;"><strong>Total Interest to be Posted:</strong></td>
                            <td class="numeric positive"><strong><?php echo number_format($total_interest, 2); ?></strong></td>
                            <td><strong><?php echo $eligible_count; ?> account(s)</strong></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="button-group">
                    <button type="submit" name="post_interest">Post Interest to Accounts</button>
                </div>
            </form>
        <?php elseif ($action_performed): ?>
            <div class="no-results">
                <p>No accounts found for interest calculation with the specified criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</body>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-validate dates when they change
    const startDateInput = document.getElementById('period_start_date');
    const endDateInput = document.getElementById('period_end_date');
    
    if (startDateInput && endDateInput) {
        [startDateInput, endDateInput].forEach(input => {
            input.addEventListener('change', function() {
                const startDate = new Date(startDateInput.value);
                const endDate = new Date(endDateInput.value);
                
                if (!isNaN(startDate.getTime()) && !isNaN(endDate.getTime())) {
                    if (startDate > endDate) {
                        alert('Start date cannot be after end date.');
                        this.value = '';
                    }
                }
            });
        });
    }
});
</script>
</html>