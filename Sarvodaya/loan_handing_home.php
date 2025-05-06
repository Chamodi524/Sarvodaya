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
            background: #e67300; /* Darker orange for logout button */
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        .logout-btn:hover {
            background: #cc6600; /* Even darker orange for hover effect */
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
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
            <a class="nav-link" href="loanTypes.php">Loan Types</a>
            <a class="nav-link" href="member_loans.php">Apply a Loan</a>
            <a class="nav-link" href="view_loans.php">View Loans and Download Application Form</a>
            <a class="nav-link" href="change_status.php">Granting loan approval</a>
            <a class="nav-link" href="loan_payement_handling.php">Loan Payment</a>
            <a class="nav-link" href="loan_repayment-Schedule.php">Loan Repayment Schedule</a>
            
        </nav>
    </div>

    <!-- Main Content -->
    <div class="content">
        <!-- Logout Button -->
        <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>

        <h1>Welcome to Sarvodaya Bank</h1>
        <p>Your trusted partner in financial growth and prosperity. Select an option from the sidebar to get started or explore more about our services.</p>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>