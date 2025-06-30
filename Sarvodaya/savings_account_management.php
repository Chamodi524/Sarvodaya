<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all savings account types
$result = $conn->query("SELECT * FROM savings_account_types");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Savings Account Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 50px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .btn-custom {
            background-color: #ffa726; /* Light orange color */
            color: white;
            border-radius: 5px;
            border: none;
        }
        .btn-custom:hover {
            background-color: #fb8c00; /* Slightly darker orange on hover */
        }
        .table-custom th {
            background-color: #ffa726; /* Light orange header */
            color: white;
        }
        .table-custom tbody tr:hover {
            background-color: #ffe0b2; /* Light orange hover effect */
        }
        .navbar-custom {
            background-color: #ffa726; /* Light orange navbar */
            padding: 15px 0; /* Increased padding for taller navbar */
        }
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: white !important;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: 700; /* Bolder text */
            font-size: 2rem; /* Larger font size */
            letter-spacing: 0.5px; /* Improved readability */
        }
        .logo-container {
            margin-right: 20px; /* More space between logo and text */
            display: flex;
            align-items: center;
        }
        .logo-img {
            height: 110px; /* Larger logo */
            width: 110px; /* Larger logo */
            border-radius: 50%; /* Makes the logo circular */
            object-fit: cover; /* Ensures the image fills the circular container */
            border: 4px solid white; /* Thicker white border */
            box-shadow: 0 3px 8px rgba(0,0,0,0.3); /* Enhanced shadow */
            transition: transform 0.3s ease; /* Smooth transition for hover effect */
        }
        .logo-img:hover {
            transform: scale(1.08); /* Slightly enlarges the logo on hover */
        }
        .status-active {
            background-color: #4CAF50;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .status-closed {
            background-color: #f44336;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        .btn-sm {
            border-radius: 20px;
            padding: 5px 15px;
        }
        .page-title {
            position: relative;
            padding-bottom: 15px;
            margin-bottom: 30px;
        }
        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 150px; /* Wider underline */
            height: 4px; /* Thicker underline */
            background-color: #ffa726;
        }
    </style>
</head>
<body>
    <!-- Navbar with circular logo -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <div class="logo-container">
                    <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo" class="logo-img">
                </div>
                Sarvodaya Savings Account Management
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
    </nav>

    <div class="container">
        <h1 class="text-center page-title" style="color: #ffa726; font-size: 2.5rem; font-weight: 700; margin-top: 30px; margin-bottom: 40px;">Savings Account Management</h1>

        <!-- Add New Savings Account Type Form -->
        <div class="card p-4">
            <h2 style="color: #ffa726;">Add New Savings Account Type</h2>
            <form action="add_savings_account.php" method="POST">
                <div class="mb-3">
                    <label for="account_name" class="form-label" style="font-size: 20px;">Account Name</label>
                    <input type="text" class="form-control" id="account_name" style="font-size: 20px;" name="account_name" required>
                </div>
                <div class="mb-3">
                    <label for="minimum_balance" class="form-label" style="font-size: 20px;">Minimum Balance(Rs.)</label>
                    <input type="number" class="form-control" id="minimum_balance" style="font-size: 20px;" name="minimum_balance" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="interest_rate" class="form-label" style="font-size: 20px;">Interest Rate (%)</label>
                    <input type="number" class="form-control" id="interest_rate" style="font-size: 20px;" name="interest_rate" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="detail_no" class="form-label" style="font-size: 20px;"style="font-size: 20px;">Account Type</label>
                    <select class="form-control" id="detail_no" style="font-size: 20px;" name="detail_no" required>
                        <option value="" style="font-size: 20px;">Select Account Type</option>
                        <option value="1">1.Child Related Account</option>
                        <option value="2">2.Normal Account</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-custom" style="font-size: 20px;">Add Savings Account Type</button>
            </form>
        </div>

        <!-- Savings Account Types List -->
        <div class="card p-4 mt-4">
            <h2 style="color: #ffa726;">Savings Account Types List</h2>
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th style="font-size: 20px;"><i class="fas fa-hashtag me-1"></i> ID</th>
                        <th style="font-size: 20px;"><i class="fas fa-tag me-1"></i> Account Name</th>
                        <th style="font-size: 20px;"><i class="fas fa-rupee-sign me-1"></i> Minimum Balance(Rs.)</th>
                        <th style="font-size: 20px;"><i class="fas fa-percentage me-1"></i> Interest Rate</th>
                        <th style="font-size: 20px;"><i class="fas fa-clipboard-list me-1"></i> Account Type</th>
                        <th style="font-size: 20px;"><i class="fas fa-toggle-on me-1"></i> Status</th>
                        <th style="font-size: 20px;"><i class="fas fa-tools me-1"></i> Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-size: 20px;"><?php echo $row['id']; ?></td>
                            <td style="font-size: 20px;"style="font-size: 20px;"><strong><?php echo $row['account_name']; ?></strong></td>
                            <td style="font-size: 20px;">Rs.<?php echo number_format($row['minimum_balance'], 2); ?></td>
                            <td style="font-size: 20px;"><?php echo $row['interest_rate']; ?>%</td>
                            <td style="font-size: 20px;">
                                <?php 
                                    echo ($row['detail_no'] == 1) ? 'Child Related Account' : 'Normal Account'; 
                                ?>
                            </td>
                            <td style="font-size: 20px;">
                                <?php if(isset($row['status']) && $row['status'] === 'closed'): ?>
                                    <span class="status-closed"><i class="fas fa-lock me-1" style="font-size: 20px;"></i> Closed</span>
                                <?php else: ?>
                                    <span class="status-active"><i class="fas fa-check-circle me-1" style="font-size: 20px;"></i> Active</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 20px;">
                                <div class="action-buttons">
                                    <a href="edit_savings_account.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" style="font-size: 20px;">
                                        <i class="fas fa-edit" style="font-size: 20px;"></i> Edit
                                    </a>
                                    <a href="toggle_savings_status.php?id=<?php echo $row['id']; ?>&status=<?php echo isset($row['status']) && $row['status'] === 'active' ? 'closed' : 'active'; ?>" class="btn btn-sm <?php echo isset($row['status']) && $row['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>" style="font-size: 20px;">
                                        <i class="fas fa-<?php echo isset($row['status']) && $row['status'] === 'active' ? 'lock' : 'unlock'; ?>"></i> 
                                        <?php echo isset($row['status']) && $row['status'] === 'active' ? 'Close' : 'Activate'; ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>