<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Home - Sarvodaya Bank</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: white; /* White background */
        }
        .sidebar {
            background-color: #ffa726; /* Orange color */
            height: 100vh; /* Full height */
            width: 250px; /* Fixed width */
            position: fixed;
            top: 0;
            left: 0;
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
        }
        .sidebar img {
            width: 100px; /* Smaller size */
            height: 100px;
            border-radius: 50%; /* Make the logo circular */
            border: 4px solid white; /* Add a white outline */
            display: block;
            margin: 0 auto 20px; /* Center the logo */
        }
        .sidebar .nav-link {
            color: white;
            font-size: 18px;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s ease;
            text-align: left; /* Align text to the left */
            padding-left: 10px; /* Add left padding */
            background-color:rgb(251, 140, 0); /* Same as sidebar background */
            border: 1px solid white; /* Add border to make it look like a tab */
        }
        .sidebar .nav-link.active {
            background-color: #fb8c00; /* Darker orange for active tab */
            border: 1px solid #fb8c00; /* Match border color */
        }
        .sidebar .nav-link:hover {
            background-color: #fb8c00; /* Darker orange on hover */
            transform: scale(1.05);
        }
        .content {
            margin-left: 250px; /* Same as sidebar width */
            padding: 40px;
            color: #333; /* Dark text for better readability on white background */
        }
        .content h1 {
            font-size: 48px;
            font-weight: bold;
            margin-bottom: 20px;
            color: #ffa726; /* Orange color */
        }
        .content p {
            font-size: 20px;
            line-height: 1.6;
        }
        .content .btn-explore {
            background-color:rgb(251, 140, 0);
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
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sarvodaya Logo -->
        <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">

        <!-- Vertical Tabs -->
        <nav class="nav flex-column">
        <a class="nav-link" href="account_details_report.php">Accounts Summary Report</a>
        <a class="nav-link" href="balance_Sheet.php">Balance Sheet</a>
        <a class="nav-link" href="payment_report_system.php">Payments Report</a>
        <a class="nav-link" href="receipt_report_system.php">Receipts Report</a>
        <a class="nav-link" href="Account_analysis.php">Account Analysis</a>
        <a class="nav-link" href="loan_analysis.php">Loan Analysis</a>
        </nav>
    </div>

    <div class="content">
        <h1>Welcome to Sarvodaya Bank</h1>
        <p>Your trusted partner in financial growth and prosperity. Select an option from the sidebar to get started or explore more about our services.</p>
        
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>