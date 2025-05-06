<?php
// Include database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$search_member_id = '';
$loans = [];
$error_message = '';
$success_message = '';

// Process search
if (isset($_POST['search'])) {
    $search_member_id = trim($_POST['member_id']);
    
    if (!empty($search_member_id)) {
        // Query to fetch loan details for the given member ID
        $sql = "SELECT l.*, 
                       m.name as member_name,
                       lt.loan_name as loan_type_name,
                       lt.max_period,
                       lt.interest_rate
                FROM loans l
                LEFT JOIN members m ON l.member_id = m.id
                LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
                WHERE l.member_id = ?
                ORDER BY l.application_date DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $search_member_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $loans = $result->fetch_all(MYSQLI_ASSOC);
        } else {
            $error_message = "No loans found for member ID: " . $search_member_id;
        }
    } else {
        $error_message = "Please enter a member ID";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: rgb(251, 140, 0);
            --primary-hover: #f76707;
            --secondary-color: #045de9;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        main {
            flex: 1 0 auto;
        }
        
        .loan-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .loan-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
            color: white;
            padding: 40px 0;
            margin-bottom: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
            font-weight: 600;
        }
        
        .status-closed {
            background-color: #e3f2fd;
            color: #0d47a1;
            font-weight: 600;
        }
        
        .status-defaulted {
            background-color: #ffebee;
            color: #c62828;
            font-weight: 600;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-hover);
            border-color: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(247, 103, 7, 0.3);
        }
        
        .card-header.bg-primary {
            background: linear-gradient(to right, var(--primary-color), var(--primary-hover)) !important;
        }
        
        .logo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.7);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .card-header {
            border-bottom: none;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
        }
        
        .card-body {
            padding: 25px;
        }
        
        .card-footer {
            background-color: white;
            border-top: 1px solid rgba(0,0,0,0.05);
            padding: 20px;
        }
        
        footer {
            background-color: #6c757d;
            color: #ffffff;
            padding: 20px 0;
            margin-top: 100px;
        }
        
        .brand-text {
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        
        .lead {
            font-weight: 300;
            letter-spacing: 0.3px;
        }
        
        .hero-content {
            display: flex;
            align-items: center;
            gap: 30px;
        }
        
        .hero-text {
            flex: 1;
        }
        
        @media (max-width: 768px) {
            .hero-content {
                flex-direction: column;
                text-align: center;
            }
        }

        .content-wrapper {
            min-height: calc(100vh - 300px); /* Adjust this value as needed */
        }
    </style>
</head>
<body>
    <main>
        <div class="hero-section">
            <div class="container">
                <div class="hero-content">
                    <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo" class="logo">
                    <div class="hero-text">
                        <h1 class="display-4 brand-text">Sarvodaya Shramadhana Society</h1>
                        <p class="lead">Search loan details by member ID and generate application forms</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="container content-wrapper mb-5">
            <div class="row">
                <div class="col-lg-10 col-md-12 mx-auto">
                    <div class="card shadow mb-4">
                        <div class="card-header bg-primary text-white d-flex align-items-center">
                            <i class="bi bi-search me-2"></i>
                            <h3 class="card-title mb-0 fs-4">Search Loans</h3>
                        </div>
                        <div class="card-body">
                            <form method="post" action="" class="row g-3">
                                <div class="col-md-8">
                                    <label for="member_id" class="form-label fw-bold">Member ID</label>
                                    <input type="number" class="form-control form-control-lg" id="member_id" name="member_id" value="<?php echo htmlspecialchars($search_member_id); ?>" placeholder="Enter member ID">
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" name="search" class="btn btn-primary w-100 py-3">
                                        <i class="bi bi-search me-2"></i> Search
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger mt-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <?php echo $error_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success mt-4 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill me-2"></i>
                        <?php echo $success_message; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($loans)): ?>
                    <div class="mt-4">
                        <h2 class="mb-4">Loan Records for Member: <span class="text-primary"><?php echo htmlspecialchars($loans[0]['member_name']); ?></span> <span class="badge bg-secondary">ID: <?php echo htmlspecialchars($search_member_id); ?></span></h2>
                        
                        <div class="row row-cols-1 row-cols-md-2 g-4 mt-2">
                            <?php foreach ($loans as $loan): ?>
                            <div class="col">
                                <div class="card h-100 loan-card">
                                    <div class="card-header d-flex justify-content-between align-items-center bg-light">
                                        <h5 class="mb-0 fw-bold text-dark">Loan #<?php echo $loan['id']; ?></h5>
                                        <span class="badge status-<?php echo $loan['status']; ?> rounded-pill px-3 py-2">
                                            <?php echo ucfirst($loan['status']); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong><i class="bi bi-tag me-2 text-primary"></i>Type:</strong> <?php echo htmlspecialchars($loan['loan_type_name']); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong><i class="bi bi-cash-stack me-2 text-primary"></i>Amount:</strong> Rs. <?php echo number_format($loan['amount'], 2); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong><i class="bi bi-percent me-2 text-primary"></i>Interest Rate:</strong> <?php echo $loan['interest_rate']; ?>%
                                        </div>
                                        <div class="mb-3">
                                            <strong><i class="bi bi-calendar-week me-2 text-primary"></i>Term:</strong> <?php echo $loan['max_period']; ?> months
                                        </div>
                                        <div class="mb-3">
                                            <strong><i class="bi bi-calendar-date me-2 text-primary"></i>Application Date:</strong> <?php echo date('M d, Y', strtotime($loan['application_date'])); ?>
                                        </div>
                                        <div class="mb-3">
                                            <strong><i class="bi bi-calendar-range me-2 text-primary"></i>Period:</strong> 
                                            <div class="ms-4 mt-2">
                                                <div><i class="bi bi-calendar2-check me-2 text-success"></i>From: <?php echo date('M d, Y', strtotime($loan['start_date'])); ?></div>
                                                <div><i class="bi bi-calendar2-x me-2 text-danger"></i>To: <?php echo date('M d, Y', strtotime($loan['end_date'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-footer text-center">
                                        <a href="generate_loan_pdf.php?id=<?php echo $loan['id']; ?>" class="btn btn-primary w-100" target="_blank">
                                            <i class="bi bi-file-earmark-pdf me-2"></i> Generate Loan Application PDF
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (empty($loans) && !empty($search_member_id) && empty($error_message)): ?>
                    <div class="alert alert-info mt-4 d-flex align-items-center">
                        <i class="bi bi-info-circle-fill me-2"></i>
                        No loan records found for the provided member ID.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <footer class="py-4">
        <div class="container text-center">
            <p>&copy; <?php echo date('Y'); ?> Sarvodaya Shramadhana Society. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>