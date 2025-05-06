<?php
// Database connection parameters
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'sarvodaya';

// Establish connection
$conn = new mysqli($host, $username, $password, $database);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get member by membership number from form POST, URL parameter, or set to empty if none provided
$membership_number = '';
$member_id = 0;

if (isset($_POST['membership_number']) && !empty($_POST['membership_number'])) {
    $membership_number = trim($_POST['membership_number']);
} elseif (isset($_GET['membership_number']) && !empty($_GET['membership_number'])) {
    $membership_number = trim($_GET['membership_number']);
}

// If membership number is provided, find the member_id
if (!empty($membership_number)) {
    $stmt = $conn->prepare("SELECT id FROM members WHERE id = ?");
    $stmt->bind_param("s", $membership_number);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $member_id = $row['id'];
    }
}

// Default date range (all history to current date)
$default_start_date = '2000-01-01'; // Historical starting point
$default_end_date = date('Y-m-d'); // Current date

// Get date range from form if submitted
$start_date = isset($_POST['start_date']) && !empty($_POST['start_date']) ? $_POST['start_date'] : $default_start_date;
$end_date = isset($_POST['end_date']) && !empty($_POST['end_date']) ? $_POST['end_date'] : $default_end_date;

// Function to fetch member name
function getMemberName($conn, $member_id) {
    if ($member_id <= 0) {
        return "No Member Selected";
    }
    
    $stmt = $conn->prepare("SELECT name FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['name'];
    }
    return "Unknown Member";
}

// Function to get member join date
function getMemberJoinDate($conn, $member_id) {
    if ($member_id <= 0) {
        return "2000-01-01"; // Default historical date
    }
    
    $stmt = $conn->prepare("SELECT created_at FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return date('Y-m-d', strtotime($row['created_at']));
    }
    return "2000-01-01"; // Default historical date
}

$member_name = getMemberName($conn, $member_id);

// If member is found, use their join date as start date if we're using the default historical view
if ($member_id > 0 && $start_date == $default_start_date) {
    $member_join_date = getMemberJoinDate($conn, $member_id);
    $start_date = $member_join_date;
}

// Get transactions for the member and date range
function getTransactions($conn, $member_id, $start_date, $end_date) {
    if ($member_id <= 0) {
        return [];
    }
    
    $query = "SELECT t.*, at.account_name as account_type_name 
              FROM savings_transactions t
              JOIN savings_account_types at ON t.account_type_id = at.id
              WHERE t.member_id = ? 
              AND DATE(t.transaction_date) BETWEEN ? AND ?
              ORDER BY t.transaction_date ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $member_id, $start_date, $end_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $transactions = [];
    while ($row = $result->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    return $transactions;
}

$transactions = getTransactions($conn, $member_id, $start_date, $end_date);

// Get member details
$member_details = [];
if ($member_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $member_details = $row;
    }
}

// Get account types for filtering
$account_types = [];
if ($member_id > 0) {
    $query = "SELECT DISTINCT at.id, at.account_name 
            FROM savings_account_types at
            JOIN savings_transactions t ON at.id = t.account_type_id
            WHERE t.member_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $member_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $account_types[$row['id']] = $row['account_name'];
    }
}

// Calculate totals
$total_deposits = 0;
$total_withdrawals = 0;
foreach ($transactions as $transaction) {
    if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST') {
        $total_deposits += $transaction['amount'];
    } else {
        $total_withdrawals += $transaction['amount'];
    }
}

// Get current balance (latest running balance)
$current_balance = !empty($transactions) ? end($transactions)['running_balance'] : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Passbook Details</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --orange-primary: rgb(255, 140, 0);
            --orange-light: rgba(255, 140, 0, 0.15);
            --orange-medium: rgba(255, 140, 0, 0.5);
            --orange-dark: rgb(230, 120, 0);
            --text-on-orange: #fff;
        }
        
        body { 
            padding: 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .btn-primary {
            background-color: var(--orange-primary);
            border-color: var(--orange-dark);
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--orange-dark);
            border-color: var(--orange-dark);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #5c636a;
        }
        
        .passbook-header { 
            background-color: var(--orange-light);
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px;
            border-left: 5px solid var(--orange-primary);
        }
        
        .table-light {
            background-color: var(--orange-light);
        }
        
        .table>thead {
            background-color: var(--orange-primary);
            color: var(--text-on-orange);
        }
        
        .transaction-row:nth-child(even) { 
            background-color: rgba(0, 0, 0, 0.02);
        }
        
        .transaction-row:hover { 
            background-color: var(--orange-light); 
        }
        
        .deposit { 
            color: #28a745; 
        }
        
        .withdrawal { 
            color: #dc3545; 
        }
        
        .summary-box { 
            background-color: var(--orange-light);
            padding: 20px; 
            border-radius: 8px; 
            margin-top: 20px;
            border-left: 5px solid var(--orange-primary);
        }
        
        .card {
            border-left: 5px solid var(--orange-primary);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
        }
        
        h1, h4, h5 {
            color: var(--orange-dark);
        }
        
        .alert-info {
            background-color: var(--orange-light);
            border-color: var(--orange-primary);
            color: #664d03;
        }
        
        .alert-danger {
            border-left: 5px solid #dc3545;
        }
        
        .form-control:focus {
            border-color: var(--orange-primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 140, 0, 0.25);
        }
        
        /* Custom pagination styles */
        .pagination .page-item.active .page-link {
            background-color: var(--orange-primary);
            border-color: var(--orange-primary);
        }
        
        .pagination .page-link {
            color: var(--orange-primary);
        }
        
        .pagination .page-link:hover {
            color: var(--orange-dark);
        }
        
        /* Custom table styles */
        .table {
            border-collapse: separate;
            border-spacing: 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .table-bordered {
            border: none;
        }
        
        @media print {
            body { 
                padding: 0; 
                font-size: 12pt; 
            }
            
            .no-print { 
                display: none !important; 
            }
            
            .print-visible { 
                display: block !important;
                visibility: visible !important;
            }
            
            .container {
                width: 100%;
                max-width: 100%;
                padding: 0;
                margin: 0;
            }
            
            .passbook-header {
                background-color: transparent !important;
                border-left: 2px solid var(--orange-primary);
                padding: 10px;
                margin-bottom: 15px;
                box-shadow: none;
            }
            
            .table {
                width: 100% !important;
                max-width: 100% !important;
                border: 1px solid #dee2e6 !important;
                box-shadow: none !important;
                font-size: 10pt;
                page-break-inside: auto;
            }
            
            .table>thead {
                background-color: #f0f0f0 !important;
                color: black !important;
                border-bottom: 2px solid var(--orange-primary);
            }
            
            tr { page-break-inside: avoid; }
            thead { display: table-header-group; }
            
            .summary-box {
                background-color: transparent !important;
                border-left: 2px solid var(--orange-primary);
                padding: 10px;
                margin-top: 15px;
                box-shadow: none;
            }
            
            .transaction-row:nth-child(even) {
                background-color: #f9f9f9 !important;
            }
            
            .deposit { 
                color: #28a745 !important; 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            .withdrawal { 
                color: #dc3545 !important; 
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            
            h1, h4, h5 {
                color: black !important;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row no-print">
            <div class="col-12">
                <h1 class="mb-4">Member Passbook</h1>
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="post" class="row g-3">
                            <div class="col-md-4">
                                <label for="membership_number" class="form-label">Membership Number</label>
                                <input type="text" class="form-control" id="membership_number" name="membership_number" 
                                       value="<?php echo htmlspecialchars($membership_number); ?>" placeholder="Enter membership number" required>
                            </div>
                            <div class="col-md-3">
                                <label for="start_date" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="end_date" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">View Transactions</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($member_id > 0): ?>
        <div class="row">
            <div class="col-12">
                <div class="passbook-header">
                    <div class="row">
                        <div class="col-md-6">
                            <h4>Member: <?php echo htmlspecialchars($member_name); ?></h4>
                            <p><strong>Membership Number:</strong> <?php echo $member_id; ?></p>
                            <?php if (!empty($member_details)): ?>
                            <p><strong>NIC:</strong> <?php echo htmlspecialchars($member_details['nic']); ?></p>
                            <p><strong>Contact:</strong> <?php echo htmlspecialchars($member_details['phone']); ?></p>
                            <p><strong>Member Since:</strong> <?php echo date('d M Y', strtotime($member_details['created_at'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <h5>Statement Period</h5>
                            <p><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
                            <?php if (!empty($transactions)): ?>
                            <div class="mt-3 no-print">
                                <button type="button" class="btn btn-secondary" onclick="window.print()">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer me-1" viewBox="0 0 16 16">
                                        <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                                        <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
                                    </svg>
                                    Print Passbook
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <?php if (empty($transactions)): ?>
                    <div class="alert alert-info">No transactions found for the selected period.</div>
                <?php else: ?>
                    <!-- Print-only header that repeats on each page -->
                    <div class="d-none print-visible" style="display:none;">
                        <div style="padding: 10px 0; border-bottom: 1px solid #ddd; margin-bottom: 10px;">
                            <h4 style="margin:0;">Member: <?php echo htmlspecialchars($member_name); ?> (ID: <?php echo $member_id; ?>)</h4>
                            <p style="margin:0;">Statement: <?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></p>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width: 20%;">Date</th>
                                    <th style="width: 20%;">Type</th>
                                    <th style="width: 20%;" class="text-end">Deposit</th>
                                    <th style="width: 20%;" class="text-end">Withdrawal</th>
                                    <th style="width: 20%;" class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <tr class="transaction-row">
                                        <td><?php echo date('Y-m-d H:i', strtotime($transaction['transaction_date'])); ?></td>
                                        <td><?php echo $transaction['transaction_type']; ?></td>
                                        <td class="text-end deposit">
                                            <?php if ($transaction['transaction_type'] == 'DEPOSIT' || $transaction['transaction_type'] == 'INTEREST'): ?>
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end withdrawal">
                                            <?php if ($transaction['transaction_type'] == 'WITHDRAWAL' || $transaction['transaction_type'] == 'ADJUSTMENT' || $transaction['transaction_type'] == 'FEE'): ?>
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($transaction['running_balance'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="summary-box">
                        <div class="row">
                            <div class="col-md-4">
                                <h5>Total Deposits</h5>
                                <p class="deposit fw-bold fs-4"><?php echo number_format($total_deposits, 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h5>Total Withdrawals</h5>
                                <p class="withdrawal fw-bold fs-4"><?php echo number_format($total_withdrawals, 2); ?></p>
                            </div>
                            <div class="col-md-4">
                                <h5>Current Balance</h5>
                                <p class="fw-bold fs-4"><?php echo number_format($current_balance, 2); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php elseif (!empty($membership_number)): ?>
            <div class="alert alert-danger">No member found with membership number: <?php echo htmlspecialchars($membership_number); ?></div>
        <?php else: ?>
            <div class="alert alert-info">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" class="bi bi-info-circle me-2" viewBox="0 0 16 16">
                    <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14zm0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16z"/>
                    <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533L8.93 6.588zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0z"/>
                </svg>
                Please enter a membership number to view the passbook.
            </div>
        <?php endif; ?>
        
        <footer class="mt-5 pt-3 border-top text-muted text-center no-print">
            <p>Sarvodaya Member Services &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>