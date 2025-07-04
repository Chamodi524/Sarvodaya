<?php 
session_start();

// Include the date alert system
$alertDivs = '';

if (isset($_SESSION['show_alerts']) && $_SESSION['show_alerts'] === true) {
    $alerts = $_SESSION['date_alerts'] ?? [];
    
    if (!empty($alerts)) {
        // Create visual alert divs only (no JavaScript alerts)
        $alertDivs = '<div id="dateAlerts" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">';
        
        foreach ($alerts as $index => $alert) {
            $alertDivs .= '<div class="alert alert-warning alert-dismissible fade show" role="alert" style="margin-bottom: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background-color: #fff3cd; border-left: 4px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px;">';
            $alertDivs .= '<strong>📅 Date Alert!</strong><br>';
            $alertDivs .= htmlspecialchars($alert['message']);
            $alertDivs .= '<button type="button" onclick="this.parentElement.style.display=\'none\'" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer; color: #856404;">&times;</button>';
            $alertDivs .= '</div>';
        }
        
        $alertDivs .= '</div>';
    }
    
    // Clear the alerts after displaying
    unset($_SESSION['show_alerts']);
    unset($_SESSION['date_alerts']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Handling - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: white;
        }
        .sidebar {
            background-color: #ffa726;
            height: 100vh;
            width: 250px;
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid white;
            display: block;
            margin: 0 auto 20px;
        }
        .sidebar .nav-link {
            color: white;
            font-size: 18px;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s ease;
            text-align: left;
            padding-left: 10px;
            background-color:rgb(251, 140, 0);
            border: 1px solid white;
        }
        .sidebar .nav-link.active {
            background-color: #fb8c00;
            border: 1px solid #fb8c00;
        }
        .sidebar .nav-link:hover {
            background-color: #fb8c00;
            transform: scale(1.05);
        }
        .content {
            margin-left: 250px;
            padding: 40px;
            color: #333;
        }
        .content h1 {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #ffa726;
        }
        .content p {
            font-size: 20px;
            line-height: 1.6;
        }
        .content .btn-explore {
            background-color: #ffa726;
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 18px;
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-top: 20px;
        }
        .content .btn-explore:hover {
            background-color: #fb8c00;
            transform: scale(1.05);
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            background: #e67300;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .logout-btn:hover {
            background: #cc6600;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        /* Alert styles */
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 14px;
            position: relative;
        }

        @media (max-width: 768px) {
            #dateAlerts {
                position: fixed !important;
                top: 10px !important;
                left: 10px !important;
                right: 10px !important;
                max-width: none !important;
            }
        }
    </style>
</head>
<body>
    <?php echo $alertDivs; ?>
    
    <!-- Sidebar -->
    <div class="sidebar">
        <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
        <nav class="nav flex-column">
            <a class="nav-link" href="Savings_account_management.php">Account information</a>
            <a class="nav-link" href="receipts_options.php">Receipts</a>
            <a class="nav-link" href="payment_options.php">Payments</a>
            <a class="nav-link" href="loan_installment_status.php">View Loan Installments</a>
            <a class="nav-link" href="savings_interest.php">Interest handling</a>
            <a class="nav-link" href="passbook.php">Member Pass Book</a>
            <a class="nav-link" href="General_ledger.php">Ledger page for savings</a>
            <a class="nav-link" href="ledger_for_loans.php">Ledger page for loans</a>
            <a class="nav-link" href="Reports.php">Reports</a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content">
        <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
        <h1>Welcome to Sarvodaya Bank</h1>
        <p>Your trusted partner in financial growth and prosperity. Select an option from the sidebar to get started or explore more about our services.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>