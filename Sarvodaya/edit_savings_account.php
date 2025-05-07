<?php 
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get savings account type details using prepared statement
$id = $_GET['id'];
$stmt = $conn->prepare("SELECT * FROM savings_account_types WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Savings Account Type | Sarvodaya</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #ffa726;
            --secondary: #fb8c00;
            --accent: #ff7043;
            --light: #f8f9fa;
            --dark: #343a40;
        }
        
        body {
            background-color: #fff9f0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .container-custom {
            max-width: 800px;
            margin: 2rem auto;
        }
        
        .page-title {
            color: var(--secondary);
            font-weight: 600;
            text-align: center;
            margin-bottom: 1.5rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 25px rgba(255, 167, 38, 0.15);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-bottom: none;
            padding: 1.5rem;
            text-align: center;
        }
        
        .card-header h3 {
            margin-bottom: 0;
            font-weight: 600;
        }
        
        .card-body {
            padding: 2rem;
            background-color: white;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }
        
        .form-control {
            border-radius: 8px;
            padding: 0.75rem 1rem;
            border: 1px solid #ced4da;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(255, 167, 38, 0.25);
        }
        
        .form-text {
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .input-group-text {
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px 0 0 8px;
        }
        
        .btn-custom {
            background: linear-gradient(to right, var(--primary), var(--secondary));
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-custom:hover {
            background: linear-gradient(to right, var(--secondary), var(--accent));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 167, 38, 0.3);
        }
        
        .btn-outline-custom {
            color: var(--secondary);
            border: 1px solid var(--secondary);
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-outline-custom:hover {
            background-color: #fff5e6;
            color: var(--secondary);
            border-color: var(--primary);
        }
        
        .account-type {
            margin-bottom: 1.5rem;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            background-color: #fff5e6;
            border-left: 5px solid var(--primary);
        }
        
        .account-type-text {
            font-weight: 500;
            color: var(--secondary);
            margin: 0;
        }
        
        .footer {
            margin-top: 2rem;
            padding: 1rem 0;
            text-align: center;
            color: #6c757d;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Main Content -->
    <div class="container container-custom">
        <h1 class="page-title">
            <i class="fas fa-edit me-2"></i>Edit Savings Account Type
        </h1>
        
        <div class="card">
            <div class="card-header">
                <h3>Account Details</h3>
            </div>
            <div class="card-body">
                <!-- Account Type Indicator -->
                <div class="account-type">
                    <p class="account-type-text">
                        <?php if($row['detail_no'] == 1): ?>
                            <i class="fas fa-child me-2"></i>Child Related Account
                        <?php else: ?>
                            <i class="fas fa-user me-2"></i>Normal Account
                        <?php endif; ?>
                    </p>
                </div>
                
                <form action="update_savings_account.php" method="POST">
                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                    
                    <div class="mb-4">
                        <label for="account_name" class="form-label">Account Name</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-signature"></i></span>
                            <input type="text" class="form-control" id="account_name" name="account_name" 
                                   value="<?php echo htmlspecialchars($row['account_name']); ?>" required>
                        </div>
                        <div class="form-text">Enter the full name of the savings account type</div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="minimum_balance" class="form-label">Minimum Balance</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-coins"></i></span>
                                <input type="number" class="form-control" id="minimum_balance" name="minimum_balance" 
                                       value="<?php echo htmlspecialchars($row['minimum_balance']); ?>" step="0.01" min="0" required>
                            </div>
                            <div class="form-text">Minimum amount required to maintain</div>
                        </div>
                        
                        <div class="col-md-6 mb-4">
                            <label for="interest_rate" class="form-label">Interest Rate</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-percentage"></i></span>
                                <input type="number" class="form-control" id="interest_rate" name="interest_rate" 
                                       value="<?php echo htmlspecialchars($row['interest_rate']); ?>" step="0.01" min="0" max="100" required>
                                <span class="input-group-text">%</span>
                            </div>
                            <div class="form-text">Annual interest rate percentage</div>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="detail_no" class="form-label">Account Type</label>
                        <select class="form-select" id="detail_no" name="detail_no" required>
                            <option value="1" <?php echo ($row['detail_no'] == 1) ? 'selected' : ''; ?>>Child Related Account</option>
                            <option value="2" <?php echo ($row['detail_no'] == 2) ? 'selected' : ''; ?>>Normal Account</option>
                        </select>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i> Account type classification cannot be changed after creation
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="javascript:history.back()" class="btn btn-outline-custom">
                            <i class="fas fa-arrow-left me-1"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-custom">
                            <i class="fas fa-save me-1"></i> Update Account Type
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> Sarvodaya Bank. All rights reserved.</p>
        </div>
    </div>
    
    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php 
// Close the statement and connection
$stmt->close();
$conn->close(); 
?>