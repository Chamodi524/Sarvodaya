<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch loan details for editing
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $result = $conn->query("SELECT * FROM loan_types WHERE id = $id");
    $row = $result->fetch_assoc();
}

// Update loan details
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $loan_name = $_POST['loan_name'];
    $maximum_amount = $_POST['maximum_amount'];
    $interest_rate = $_POST['interest_rate'];
    $late_fee = $_POST['late_fee'];  // Added late fee
    $max_period = $_POST['max_period'];
    $description = $_POST['description'];

    // Prepare and execute the update query with late_fee
    $stmt = $conn->prepare("UPDATE loan_types SET loan_name = ?, maximum_amount = ?, interest_rate = ?, late_fee = ?, max_period = ?, description = ? WHERE id = ?");
    $stmt->bind_param("sdddssi", $loan_name, $maximum_amount, $interest_rate, $late_fee, $max_period, $description, $id);

    if ($stmt->execute()) {
        header("Location: loanTypes.php"); // Redirect back to the main page
    } else {
        echo "Error: " . $stmt->error;
    }

    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Loan Type | Sarvodaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: rgb(251, 140, 0);
            --primary-hover: #f76707;
            --light-orange: #fff3e0;
            --input-focus: #fff8e1;
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Navbar Styles */
        .navbar-custom {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            padding: 15px 0;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .navbar-brand {
            display: flex;
            align-items: center;
            font-weight: 700;
            font-size: 1.4rem;
            color: white !important;
        }
        
        .logo-img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.7);
            margin-right: 15px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .logo-img:hover {
            transform: scale(1.05);
        }
        
        /* Main Container */
        .main-container {
            margin-top: 50px;
            margin-bottom: 50px;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(to right, var(--primary-color), var(--primary-hover));
            color: white;
            border-bottom: none;
            padding: 20px 25px;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 30px;
        }
        
        /* Form Styles */
        .form-label {
            font-weight: 600;
            color: #555;
            margin-bottom: 8px;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 12px 15px;
            border: 1px solid #ced4da;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            box-shadow: 0 0 0 0.25rem rgba(251, 140, 0, 0.25);
            border-color: var(--primary-color);
            background-color: var(--input-focus);
        }
        
        textarea.form-control {
            resize: none;
        }
        
        /* Button Styles */
        .btn-group {
            display: flex;
            gap: 15px;
        }
        
        .btn-custom {
            background-color: var(--primary-color);
            color: white;
            border-radius: 30px;
            padding: 12px 30px;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(251, 140, 0, 0.3);
        }
        
        .btn-custom:hover {
            background-color: var(--primary-hover);
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(251, 140, 0, 0.4);
            color: white;
        }
        
        .btn-custom:active {
            transform: translateY(-1px);
        }
        
        .btn-outline-secondary {
            border-radius: 30px;
            padding: 12px 30px;
            font-weight: 600;
        }
        
        /* Card Section Styles */
        .card-section {
            background-color: var(--light-orange);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--primary-hover);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 10px;
        }
        
        /* Footer */
        footer {
            background-color: #6c757d;
            color: white;
            padding: 20px 0;
            margin-top: 50px;
        }
        
        /* Input Group */
        .input-group-text {
            background-color: var(--light-orange);
            border-color: #ced4da;
            color: var(--primary-hover);
            font-weight: 600;
        }
        
        /* Breadcrumb */
        .breadcrumb {
            margin-bottom: 30px;
        }
        
        .breadcrumb-item a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }
        
        .breadcrumb-item.active {
            font-weight: 600;
        }
        
        /* Responsive styles */
        @media (max-width: 768px) {
            .card-body {
                padding: 20px;
            }
            
            .btn-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo" class="logo-img">
                <span>Sarvodaya Shramadhana Society</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <!-- Navigation links could go here -->
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container main-container">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="loanTypes.php">Loan Types</a></li>
                <li class="breadcrumb-item active" aria-current="page">Edit Loan Type</li>
            </ol>
        </nav>
        
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <i class="bi bi-pencil-square me-2"></i>
                Edit Loan Type Details
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    
                    <!-- Basic Info Section -->
                    <div class="card-section">
                        <div class="section-title">
                            <i class="bi bi-info-circle"></i>
                            Basic Information
                        </div>
                        <div class="mb-3">
                            <label for="loan_name" class="form-label">Loan Name</label>
                            <input type="text" class="form-control" name="loan_name" id="loan_name" value="<?php echo $row['loan_name']; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="description" rows="3"><?php echo $row['description']; ?></textarea>
                            <div class="form-text text-muted">Provide a brief description of the loan type and its purpose.</div>
                        </div>
                    </div>
                    
                    <!-- Financial Details Section -->
                    <div class="card-section">
                        <div class="section-title">
                            <i class="bi bi-cash-coin"></i>
                            Financial Details
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="maximum_amount" class="form-label">Maximum Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" class="form-control" name="maximum_amount" id="maximum_amount" value="<?php echo $row['maximum_amount']; ?>" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="interest_rate" class="form-label">Interest Rate</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="interest_rate" id="interest_rate" value="<?php echo $row['interest_rate']; ?>" step="0.01" required>
                                    <span class="input-group-text">% p.m.</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="late_fee" class="form-label">Late Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">Rs.</span>
                                    <input type="number" class="form-control" name="late_fee" id="late_fee" value="<?php echo isset($row['late_fee']) ? $row['late_fee'] : '0'; ?>" step="0.01" required>
                                </div>
                                <div class="form-text text-muted">Fixed amount charged for late payments.</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Terms Section -->
                    <div class="card-section">
                        <div class="section-title">
                            <i class="bi bi-calendar-date"></i>
                            Loan Terms
                        </div>
                        <div class="mb-3">
                            <label for="max_period" class="form-label">Maximum Period</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="max_period" id="max_period" value="<?php echo $row['max_period']; ?>" required>
                                <span class="input-group-text">Months</span>
                            </div>
                            <div class="form-text text-muted">The maximum duration for this loan type.</div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="mt-4 text-end btn-group">
                        <a href="loanTypes.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x-circle me-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-custom">
                            <i class="bi bi-check-circle me-2"></i> Update Loan Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="py-4">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Sarvodaya Shramadhana Society. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>