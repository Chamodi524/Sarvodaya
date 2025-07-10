<?php
// Database connection
$host = 'localhost';
$dbname = 'sarvodaya';
$username = 'root'; // Change as needed
$password = ''; // Change as needed

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Get filter parameters
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$member_id = isset($_GET['member_id']) ? $_GET['member_id'] : '';
$account_type_id = isset($_GET['account_type_id']) ? $_GET['account_type_id'] : '';

// Fetch member contribution data
$sql = "
    SELECT 
        m.id as member_id,
        m.name as member_name,
        m.email,
        m.phone,
        sat.account_name,
        
        -- Savings Summary
        COALESCE(SUM(CASE WHEN st.transaction_type = 'DEPOSIT' THEN st.amount ELSE 0 END), 0) as total_deposits,
        COALESCE(SUM(CASE WHEN st.transaction_type = 'WITHDRAWAL' THEN st.amount ELSE 0 END), 0) as total_withdrawals,
        COALESCE(SUM(CASE WHEN st.transaction_type = 'INTEREST' THEN st.amount ELSE 0 END), 0) as total_interest,
        
        -- Loan Summary
        COALESCE(SUM(li.actual_payment_amount), 0) as total_loan_payments,
        COALESCE(SUM(li.principal_amount), 0) as total_principal_paid,
        COALESCE(SUM(li.interest_amount), 0) as total_interest_paid,
        COALESCE(SUM(li.late_fee), 0) as total_late_fees,
        
        -- Activity counts
        COUNT(DISTINCT st.id) as transaction_count,
        COUNT(DISTINCT li.id) as loan_payment_count,
        
        -- Current balance (latest running balance)
        (SELECT st2.running_balance 
         FROM savings_transactions st2 
         WHERE st2.member_id = m.id 
         ORDER BY st2.transaction_date DESC 
         LIMIT 1) as current_balance,
         
        -- Last activity date
        GREATEST(
            COALESCE(MAX(st.transaction_date), '1970-01-01'),
            COALESCE(MAX(li.actual_payment_date), '1970-01-01')
        ) as last_activity_date
        
    FROM members m
    LEFT JOIN savings_account_types sat ON m.account_type = sat.id
    LEFT JOIN savings_transactions st ON m.id = st.member_id 
        AND st.transaction_date BETWEEN ? AND ?
    LEFT JOIN loan_installments li ON m.id = li.member_id 
        AND li.actual_payment_date BETWEEN ? AND ?
        AND li.payment_status = 'paid'
    
    WHERE 1=1
    " . ($member_id ? "AND m.id = ?" : "") . "
    " . ($account_type_id ? "AND m.account_type = ?" : "") . "
    
    GROUP BY m.id, m.name, m.email, m.phone, sat.account_name
    ORDER BY (COALESCE(SUM(CASE WHEN st.transaction_type = 'DEPOSIT' THEN st.amount ELSE 0 END), 0) + COALESCE(SUM(li.actual_payment_amount), 0)) DESC
";

$params = [$date_from, $date_to, $date_from, $date_to];
if ($member_id) $params[] = $member_id;
if ($account_type_id) $params[] = $account_type_id;

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all members for dropdown
$members_list = $pdo->query("SELECT id, name FROM members ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get account types for dropdown
$account_types = $pdo->query("SELECT id, account_name FROM savings_account_types WHERE status = 'active' ORDER BY account_name")->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$total_contributions = array_sum(array_column($members, 'total_deposits'));
$total_withdrawals = array_sum(array_column($members, 'total_withdrawals'));
$total_loan_payments = array_sum(array_column($members, 'total_loan_payments'));
$active_members = count(array_filter($members, function($m) { return $m['last_activity_date'] > date('Y-m-d', strtotime('-30 days')); }));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Contribution Summary Report - Sarvodaya</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .header {
            background: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .filters {
            background: #f8f9fa;
            padding: 25px;
            border-bottom: 1px solid #e9ecef;
        }

        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            padding: 12px 24px;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }

        .btn-export {
            background: linear-gradient(135deg, #27ae60 0%, #229954 100%);
            margin-left: 10px;
        }

        .btn-export:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.4);
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            padding: 30px;
            background: #f8f9fa;
        }

        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-5px);
        }

        .summary-card h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }

        .summary-card .amount {
            font-size: 2rem;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 10px;
        }

        .summary-card .label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .data-section {
            padding: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 1.5rem;
            color: #2c3e50;
            font-weight: 600;
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th {
            background: linear-gradient(135deg, #34495e 0%, #2c3e50 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .amount {
            text-align: right;
            font-weight: 600;
        }

        .positive {
            color: #27ae60;
        }

        .negative {
            color: #e74c3c;
        }

        .neutral {
            color: #6c757d;
        }

        .member-name {
            font-weight: 600;
            color: #2c3e50;
        }

        .account-type {
            background: #e3f2fd;
            color: #1976d2;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
        }

        .activity-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }

        .activity-high {
            background: #27ae60;
        }

        .activity-medium {
            background: #f39c12;
        }

        .activity-low {
            background: #e74c3c;
        }

        .search-box {
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding: 15px;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            font-size: 16px;
            background: white;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-data i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .filter-row {
                flex-direction: column;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: stretch;
            }
            
            table {
                font-size: 14px;
            }
            
            th, td {
                padding: 8px;
            }
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #6c757d;
        }

        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Member Contribution Summary Report</h1>
            <p>Track deposits, withdrawals, and loan repayments across all members</p>
        </div>

        <div class="filters">
            <form method="GET" id="filterForm">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="date_from">From Date</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="date_to">To Date</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    <div class="filter-group">
                        <label for="member_id">Member</label>
                        <select id="member_id" name="member_id">
                            <option value="">All Members</option>
                            <?php foreach ($members_list as $member): ?>
                                <option value="<?php echo $member['id']; ?>" <?php echo $member_id == $member['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($member['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="account_type_id">Account Type</label>
                        <select id="account_type_id" name="account_type_id">
                            <option value="">All Account Types</option>
                            <?php foreach ($account_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" <?php echo $account_type_id == $type['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['account_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <button type="submit" class="btn">🔍 Filter</button>
                        <button type="button" class="btn btn-export" onclick="exportToCSV()">📥 Export CSV</button>
                    </div>
                </div>
            </form>
        </div>

        <div class="summary-cards">
            <div class="summary-card">
                <h3>Total Deposits</h3>
                <div class="amount">$<?php echo number_format($total_contributions, 2); ?></div>
                <div class="label">All member deposits</div>
            </div>
            <div class="summary-card">
                <h3>Total Withdrawals</h3>
                <div class="amount">$<?php echo number_format($total_withdrawals, 2); ?></div>
                <div class="label">All member withdrawals</div>
            </div>
            <div class="summary-card">
                <h3>Loan Repayments</h3>
                <div class="amount">$<?php echo number_format($total_loan_payments, 2); ?></div>
                <div class="label">Total loan payments</div>
            </div>
            <div class="summary-card">
                <h3>Active Members</h3>
                <div class="amount"><?php echo $active_members; ?></div>
                <div class="label">Active in last 30 days</div>
            </div>
        </div>

        <div class="data-section">
            <div class="section-header">
                <h2 class="section-title">Member Details</h2>
            </div>

            <div class="search-box">
                <input type="text" id="searchInput" placeholder="🔍 Search by member name, email, or phone...">
            </div>

            <?php if (empty($members)): ?>
                <div class="no-data">
                    <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;">📊</div>
                    <h3>No Data Available</h3>
                    <p>No member contributions found for the selected criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table id="membersTable">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th>Account Type</th>
                                <th>Contact</th>
                                <th>Deposits</th>
                                <th>Withdrawals</th>
                                <th>Net Savings</th>
                                <th>Loan Payments</th>
                                <th>Current Balance</th>
                                <th>Activity</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($members as $member): 
                                $net_savings = $member['total_deposits'] - $member['total_withdrawals'];
                                $total_activity = $member['transaction_count'] + $member['loan_payment_count'];
                                $activity_level = $total_activity > 10 ? 'high' : ($total_activity > 5 ? 'medium' : 'low');
                            ?>
                                <tr>
                                    <td>
                                        <div class="member-name"><?php echo htmlspecialchars($member['member_name']); ?></div>
                                        <small>ID: <?php echo $member['member_id']; ?></small>
                                    </td>
                                    <td>
                                        <span class="account-type">
                                            <?php echo htmlspecialchars($member['account_name'] ?? 'N/A'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo htmlspecialchars($member['email']); ?></div>
                                        <small><?php echo htmlspecialchars($member['phone']); ?></small>
                                    </td>
                                    <td class="amount positive">
                                        $<?php echo number_format($member['total_deposits'], 2); ?>
                                    </td>
                                    <td class="amount negative">
                                        $<?php echo number_format($member['total_withdrawals'], 2); ?>
                                    </td>
                                    <td class="amount <?php echo $net_savings >= 0 ? 'positive' : 'negative'; ?>">
                                        $<?php echo number_format($net_savings, 2); ?>
                                    </td>
                                    <td class="amount positive">
                                        $<?php echo number_format($member['total_loan_payments'], 2); ?>
                                    </td>
                                    <td class="amount neutral">
                                        $<?php echo number_format($member['current_balance'] ?? 0, 2); ?>
                                    </td>
                                    <td>
                                        <span class="activity-indicator activity-<?php echo $activity_level; ?>"></span>
                                        <?php echo $total_activity; ?> transactions
                                    </td>
                                    <td>
                                        <?php 
                                        if ($member['last_activity_date'] && $member['last_activity_date'] != '1970-01-01') {
                                            echo date('M j, Y', strtotime($member['last_activity_date']));
                                        } else {
                                            echo 'No activity';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const table = document.getElementById('membersTable');
            const rows = table.getElementsByTagName('tr');

            for (let i = 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.getElementsByTagName('td');
                let match = false;

                for (let j = 0; j < cells.length; j++) {
                    if (cells[j].textContent.toLowerCase().includes(searchTerm)) {
                        match = true;
                        break;
                    }
                }

                row.style.display = match ? '' : 'none';
            }
        });

        // Export to CSV function
        function exportToCSV() {
            const table = document.getElementById('membersTable');
            const rows = table.querySelectorAll('tr');
            let csv = [];

            for (let i = 0; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.querySelectorAll('th, td');
                let csvRow = [];

                for (let j = 0; j < cells.length; j++) {
                    let cellText = cells[j].textContent.trim();
                    // Clean up the text and escape quotes
                    cellText = cellText.replace(/"/g, '""');
                    csvRow.push('"' + cellText + '"');
                }

                csv.push(csvRow.join(','));
            }

            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'member_contribution_report_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Auto-refresh functionality
        function autoRefresh() {
            const currentTime = new Date();
            const lastRefresh = localStorage.getItem('lastRefresh');
            
            if (!lastRefresh || (currentTime - new Date(lastRefresh)) > 300000) { // 5 minutes
                localStorage.setItem('lastRefresh', currentTime.toISOString());
                // Uncomment the next line to enable auto-refresh
                // location.reload();
            }
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Set default date range if not set
            const fromDate = document.getElementById('date_from');
            const toDate = document.getElementById('date_to');
            
            if (!fromDate.value) {
                fromDate.value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
            }
            
            if (!toDate.value) {
                toDate.value = new Date().toISOString().split('T')[0];
            }

            // Auto-refresh check
            setInterval(autoRefresh, 60000); // Check every minute
        });

        // Form validation
        document.getElementById('filterForm').addEventListener('submit', function(e) {
            const fromDate = document.getElementById('date_from').value;
            const toDate = document.getElementById('date_to').value;
            
            if (fromDate && toDate && fromDate > toDate) {
                e.preventDefault();
                alert('From date cannot be later than To date');
            }
        });
    </script>
</body>
</html>