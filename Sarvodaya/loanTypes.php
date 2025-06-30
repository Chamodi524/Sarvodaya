<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch all loan types
$result = $conn->query("SELECT * FROM loan_types");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #ff9800;
            --primary-dark: #f57c00;
            --primary-light: #ffe0b2;
            --accent-color: #4CAF50;
            --text-color: #424242;
            --bg-color: #f5f5f5;
        }
        
        body {
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container {
            margin-top: 30px;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 25px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
        }
        
        .card-header {
            background-color: var(--primary-color);
            color: white;
            border-top-left-radius: 15px !important;
            border-top-right-radius: 15px !important;
            padding: 15px 20px;
        }
        
        .section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 20px;
            border-left: 5px solid var(--primary-color);
            padding-left: 15px;
        }
        
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 30px;
            padding: 8px 25px;
            font-weight: 500;
            border: none;
            transition: all 0.3s ease;
        }
        
        .btn-custom:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 152, 0, 0.3);
            color: white;
        }
        
        .btn-custom-secondary {
            background-color: var(--accent-color);
            color: white;
        }
        
        .btn-custom-secondary:hover {
            background-color: #388E3C;
            box-shadow: 0 5px 15px rgba(76, 175, 80, 0.3);
        }
        
        .table-custom {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .table-custom th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 500;
            border: none;
            padding: 15px;
        }
        
        .table-custom td {
            vertical-align: middle;
            padding: 12px 15px;
        }
        
        .table-custom tbody tr {
            transition: all 0.2s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .table-custom tbody tr:hover {
            background-color: var(--primary-light);
            transform: scale(1.01);
        }
        
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 15px 0;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-custom .navbar-brand,
        .navbar-custom .nav-link {
            color: white !important;
        }
        
        .navbar-custom .nav-link {
            font-weight: 500;
            transition: all 0.3s ease;
            margin: 0 10px;
            position: relative;
        }
        
        .navbar-custom .nav-link:after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            background: white;
            bottom: -3px;
            left: 0;
            transition: width 0.3s ease;
        }
        
        .navbar-custom .nav-link:hover:after {
            width: 100%;
        }
        
        .logo-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            display: inline-flex;
            justify-content: center;
            align-items: center;
            margin-right: 15px;
            border: 3px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
            background: white;
        }
        
        .logo-circle:hover {
            transform: rotate(5deg) scale(1.05);
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.3);
        }
        
        .logo-circle img {
            width: 90%;
            height: 90%;
            object-fit: cover;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 10px 15px;
            border: 1px solid #ddd;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 152, 0, 0.25);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--text-color);
            margin-bottom: 8px;
        }
        
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn-sm {
            border-radius: 20px;
            padding: 5px 15px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
            position: relative;
        }
        
        .page-header:after {
            content: '';
            display: block;
            width: 100px;
            height: 3px;
            background: var(--primary-color);
            margin: 15px auto 0;
            border-radius: 3px;
        }
        
        .card-body {
            padding: 25px;
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
        
        /* Animation for new elements */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-in {
            animation: fadeIn 0.5s ease forwards;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="#">
                <div class="logo-circle">
                    <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
                </div>
                <span class="fs-4 fw-bold" style="font-size: 1.5rem;">Sarvodaya Loan Management</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="loan_handing_home.php"><i class="fas fa-home me-1" style="font-size: 1.5rem;"></i> Home</a>
                    </li>
                    
                </ul>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header animate-in">
            <h1 class="display-5 fw-bold" style="color: var(--primary-color);" style="font-size: 1.5rem;">Loan Management System</h1>
        </div>
        
        <div class="card animate-in" style="animation-delay: 0.1s;">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-plus-circle me-2" style="font-size: 1.5rem;"></i> Add New Loan Type</h3>
            </div>
            <div class="card-body">
                <form action="add_loan.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="loan_name" class="form-label" style="font-size: 1.5rem;">Loan Name</label>
                            <input type="text" class="form-control" id="loan_name" name="loan_name" style="font-size: 1.5rem;" placeholder="Enter loan name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="maximum_amount" class="form-label" style="font-size: 1.5rem;">Maximum Amount (Rs.)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-rupee-sign" style="font-size: 1.5rem;"></i></span>
                                <input type="number" class="form-control" id="maximum_amount" name="maximum_amount" step="0.01" style="font-size: 1.5rem;" placeholder="Enter maximum amount" required>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="interest_rate" class="form-label" style="font-size: 1.5rem;">Interest Rate (%)</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="interest_rate" name="interest_rate" step="0.01" style="font-size: 1.5rem;" placeholder="Enter interest rate" required>
                                <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="late_fee" class="form-label" style="font-size: 1.5rem;">Late Fee (Rs.)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-rupee-sign" ></i></span>
                                <input type="number" class="form-control" id="late_fee" name="late_fee" step="0.01" style="font-size: 1.5rem;" placeholder="Enter late fee amount" required>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="max_period" class="form-label" style="font-size: 1.5rem;">Maximum Period (Months)</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                                <input type="number" class="form-control" id="max_period" name="max_period" style="font-size: 1.5rem;" placeholder="Enter maximum period" required>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label" style="font-size: 1.5rem;">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3" style="font-size: 1.5rem;" placeholder="Enter loan description"></textarea>
                    </div>
                    <button type="submit" class="btn btn-custom"><i class="fas fa-save me-2" style="font-size: 1.5rem;"></i>Add Loan Type</button>
                </form>
            </div>
        </div>

        <div class="card animate-in mt-4" style="animation-delay: 0.2s;">
            <div class="card-header">
                <h3 class="mb-0"><i class="fas fa-list-alt me-2"></i> Loan Types List</h3>
            </div>
            <div class="card-body">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th style="font-size: 1.5rem;"><i class="fas fa-hashtag me-1"></i> ID</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-tag me-1"></i> Loan Name</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-rupee-sign me-1"></i> Maximum Amount</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-percentage me-1"></i> Interest Rate</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-exclamation-circle me-1"></i> Late Fee (Rs.)</th>
                            <th style="font-size: 1.5rem;"><i class="far fa-calendar-alt me-1"></i> Max Period</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-align-left me-1"></i> Description</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-toggle-on me-1"></i> Status</th>
                            <th style="font-size: 1.5rem;"><i class="fas fa-tools me-1"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td style="font-size: 1.5rem;"><?php echo $row['id']; ?></td>
                                <td style="font-size: 1.5rem;"><strong><?php echo $row['loan_name']; ?></strong></td>
                                <td style="font-size: 1.5rem;">Rs.<?php echo number_format($row['maximum_amount'], 2); ?></td>
                                <td style="font-size: 1.5rem;"><?php echo $row['interest_rate']; ?>%</td>
                                <td style="font-size: 1.5rem;"><?php echo isset($row['late_fee']) ? 'Rs.' . number_format($row['late_fee'], 2) : 'N/A'; ?></td>
                                <td style="font-size: 1.5rem;"><?php echo $row['max_period']; ?> months</td>
                                <td style="font-size: 1.5rem;"><?php echo $row['description']; ?></td>
                                <td>
                                    <?php if(isset($row['status']) && $row['status'] === 'closed'): ?>
                                        <span class="status-closed"><i class="fas fa-lock me-1" style="font-size: 1.5rem;"></i> Closed</span>
                                    <?php else: ?>
                                        <span class="status-active"><i class="fas fa-check-circle me-1" style="font-size: 1.5rem;"></i> Active</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="edit_loan.php?id=<?php echo $row['id']; ?>" class="btn btn-sm btn-warning" style="font-size: 1.5rem;">
                                            <i class="fas fa-edit" style="font-size: 1.5rem;"></i> Edit
                                        </a>
                                        <a href="toggle_status.php?id=<?php echo $row['id']; ?>&status=<?php echo isset($row['status']) && $row['status'] === 'active' ? 'closed' : 'active';  ?>" class="btn btn-sm <?php echo isset($row['status']) && $row['status'] === 'active' ? 'btn-danger' : 'btn-success'; ?>" style="font-size: 1.5rem;">
                                            <i class="fas fa-<?php echo isset($row['status']) && $row['status'] === 'active' ? 'lock' : 'unlock'; ?>" style="font-size: 1.5rem;"></i> 
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
    </div>

    <footer class="bg-dark text-white text-center py-3 mt-5">
        <div class="container">
            <p class="mb-0" style="font-size: 1.5rem;">© 2025 Sarvodaya Loan Management System. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add fade-in effect when page loads
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(function() {
                document.querySelectorAll('.animate-in').forEach(function(element, index) {
                    element.style.animationDelay = (0.1 * index) + 's';
                    element.style.opacity = '1';
                });
            }, 100);
        });
    </script>
</body>
</html>