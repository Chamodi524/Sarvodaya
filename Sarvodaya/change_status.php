<?php
// Include database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$member_id = '';
$loans = [];
$error_message = '';
$success_message = '';
$member_info = null;

// Search loans by member ID
if (isset($_GET['member_id']) && !empty($_GET['member_id'])) {
    $member_id = intval($_GET['member_id']);
    
    // First, check if member exists
    $member_sql = "SELECT * FROM members WHERE id = ?";
    $member_stmt = $conn->prepare($member_sql);
    $member_stmt->bind_param("i", $member_id);
    $member_stmt->execute();
    $member_result = $member_stmt->get_result();
    
    if ($member_result->num_rows > 0) {
        $member_info = $member_result->fetch_assoc();
        
        // Now fetch all loans for this member
        $loans_sql = "SELECT l.*, 
                             m1.name as guarantor1_name,
                             m2.name as guarantor2_name,
                             lt.loan_name
                      FROM loans l
                      LEFT JOIN members m1 ON l.guarantor1_id = m1.id
                      LEFT JOIN members m2 ON l.guarantor2_id = m2.id
                      LEFT JOIN loan_types lt ON l.loan_type_id = lt.id
                      WHERE l.member_id = ?
                      ORDER BY l.application_date DESC";
        $loans_stmt = $conn->prepare($loans_sql);
        $loans_stmt->bind_param("i", $member_id);
        $loans_stmt->execute();
        $loans_result = $loans_stmt->get_result();
        
        if ($loans_result->num_rows > 0) {
            while ($row = $loans_result->fetch_assoc()) {
                $loans[] = $row;
            }
        } else {
            $error_message = "No loans found for this member.";
        }
    } else {
        $error_message = "No member found with ID: " . $member_id;
    }
}

// Update loan status if requested
if (isset($_POST['update_status'])) {
    $loan_id = intval($_POST['loan_id']);
    $status = $_POST['status'];
    
    $sql = "UPDATE loans SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $status, $loan_id);
    
    if ($stmt->execute()) {
        $success_message = "Loan status updated successfully!";
        
        // Refresh loans data
        if (!empty($member_id)) {
            header("Location: " . $_SERVER['PHP_SELF'] . "?member_id=" . $member_id . "&status_updated=1");
            exit;
        }
    } else {
        $error_message = "Error updating loan status: " . $stmt->error;
    }
}

// Delete loan if requested
if (isset($_POST['delete_loan'])) {
    $loan_id = intval($_POST['loan_id']);
    
    // Start a transaction to ensure data integrity
    $conn->begin_transaction();
    
    try {
        // First, check if loan exists and belongs to the member
        $check_sql = "SELECT id FROM loans WHERE id = ? AND member_id = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ii", $loan_id, $member_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Delete the loan
            $delete_sql = "DELETE FROM loans WHERE id = ?";
            $delete_stmt = $conn->prepare($delete_sql);
            $delete_stmt->bind_param("i", $loan_id);
            
            if ($delete_stmt->execute()) {
                $conn->commit();
                $success_message = "Loan record deleted successfully!";
                
                // Refresh page to show updated loan list
                header("Location: " . $_SERVER['PHP_SELF'] . "?member_id=" . $member_id . "&loan_deleted=1");
                exit;
            } else {
                throw new Exception("Error deleting loan: " . $delete_stmt->error);
            }
        } else {
            throw new Exception("Loan not found or does not belong to this member.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = $e->getMessage();
    }
}

// For status_updated or loan_deleted parameter
if (isset($_GET['status_updated']) && $_GET['status_updated'] == 1) {
    $success_message = "Loan status updated successfully!";
}
if (isset($_GET['loan_deleted']) && $_GET['loan_deleted'] == 1) {
    $success_message = "Loan record deleted successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Loans Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: rgb(255, 140, 0);
            --primary-dark: rgb(230, 126, 0);
            --primary-light: rgb(255, 165, 51);
            --primary-very-light: rgb(255, 235, 204);
        }
        
        .hero-section {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 30px 0;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        
        
        .bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .card-header.bg-primary {
            color: white;
        }
        
        .status-active {
            background-color: #d1e7dd;
            color: #146c43;
        }
        
        .status-closed {
            background-color: #e2e3e5;
            color: #41464b;
        }
        
        .status-defaulted {
            background-color: #f8d7da;
            color: #b02a37;
        }
        
        .member-info {
            background-color: var(--primary-very-light);
            border-left: 5px solid var(--primary-color);
        }
        
        /* Updated logo container styles - CHANGED TO CIRCLE */
        .logo-container {
            width: 100px;
            height: 100px;
            overflow: hidden;
            border-radius: 50%; /* Changed from 12px to 50% for circle */
            border: 3px solid white;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            background-color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-right: 20px;
        }
        
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        /* Heading styles */
        .header-content {
            display: flex;
            align-items: center;
        }
        
        .header-text h1 {
            font-weight: 700;
            margin-bottom: 0;
            text-shadow: 1px 1px 3px rgba(0,0,0,0.2);
        }
        
        .header-text p {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        /* Add a stylish line separator */
        .header-separator {
            height: 3px;
            background: linear-gradient(to right, white, rgba(255,255,255,0.2));
            margin: 10px 0;
            width: 150px;
        }

        /* Fix for button spacing */
        .action-buttons .btn {
            margin-right: 8px;
        }
        
        .action-buttons .btn:last-child {
            margin-right: 0;
        }

        /* Fix for long email text wrapping */
        .member-info p {
            word-break: break-word;
            overflow-wrap: break-word;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        /* Allow email to show full text on hover */
        .member-info p:hover {
            white-space: normal;
            overflow: visible;
            text-overflow: unset;
        }

                /* Enhanced Actions column */
        .actions-column {
            min-width: 280px !important;
            width: 280px !important;
        }

        .actions-flex {
            display: flex;
            gap: 12px;
            justify-content: flex-start;
            align-items: center;
            flex-wrap: nowrap;
            min-width: 260px;
        }

        .actions-flex .btn {
            flex: 0 0 auto;
            min-width: 100px;
        }
    </style>
</head>
<body>
    <div class="hero-section">
        <div class="container">
            <!-- Updated left-aligned header -->
            <div class="header-content">
                <div class="logo-container">
                    <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
                </div>
                <div class="header-text">
                    <h1 class="display-5">Granting loan approval</h1>
                    <div class="header-separator"></div>
                    <p>Search and manage loans for members</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container mb-5">
        <div class="row">
            <div class="col-lg-10 mx-auto">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">Search by Member ID</h3>
                    </div>
                    <div class="card-body">
                        <form method="get" action="" class="row g-3">
                            <div class="col-md-8">
                                <label for="member_id" class="form-label" style="font-size: 1.5rem;">Member ID</label>
                                <input type="number" class="form-control" id="member_id" name="member_id" style="font-size: 1.5rem;" 
                                    value="<?php echo htmlspecialchars($member_id); ?>" placeholder="Enter member ID">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100" style="font-size: 1.5rem;">
                                    <i class="bi bi-search" style="font-size: 1.5rem;"></i> Search Loans
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger mt-4">
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success mt-4" style="font-size: 1.5rem;">
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>

                <?php if ($member_info): ?>
                <div class="card shadow mt-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">Member Information</h3>
                    </div>
                    <div class="card-body">
                        <div class="p-3 member-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <p style="font-size: 1.5rem;"><strong>Name:</strong> <?php echo htmlspecialchars($member_info['name']); ?></p>
                                    <p style="font-size: 1.5rem;" title="<?php echo htmlspecialchars($member_info['email']); ?>"><strong>Email:</strong> <?php echo htmlspecialchars($member_info['email']); ?></p>
                                    <p style="font-size: 1.5rem;"><strong>Phone:</strong> <?php echo htmlspecialchars($member_info['phone']); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p style="font-size: 1.5rem;"><strong>NIC:</strong> <?php echo htmlspecialchars($member_info['nic'] ?? 'N/A'); ?></p>
                                    <p style="font-size: 1.5rem;"><strong>Account Type:</strong> <?php echo htmlspecialchars($member_info['account_type']); ?></p>
                                    <p style="font-size: 1.5rem;"><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($member_info['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($loans)): ?>
                <div class="card shadow mt-4">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">Loans for Member #<?php echo $member_id; ?></h3>
                    </div>
                    <div class="card-body" >
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr >
                                        <th style="font-size: 1.5rem;">Loan ID</th>
                                        <th style="font-size: 1.5rem;">Loan Type</th>
                                        <th style="font-size: 1.5rem;">Amount(Rs.)</th>
                                        <th style="font-size: 1.5rem;">Interest</th>
                                        <th style="font-size: 1.5rem;">Application Date</th>
                                        <th style="font-size: 1.5rem;">Period</th>
                                        <th style="font-size: 1.5rem;">Status</th>
                                        <th class="actions-column" style="font-size: 1.5rem;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($loans as $loan): ?>
                                    <tr>
                                        <td style="font-size: 1.5rem;"><?php echo htmlspecialchars($loan['id']); ?></td>
                                        <td style="font-size: 1.5rem;"><?php echo htmlspecialchars($loan['loan_name']); ?></td>
                                        <td style="font-size: 1.5rem;"><?php echo number_format($loan['amount'], 2); ?></td>
                                        <td style="font-size: 1.5rem;"><?php echo htmlspecialchars($loan['interest_rate']); ?>%</td>
                                        <td style="font-size: 1.5rem;"><?php echo date('Y-m-d', strtotime($loan['application_date'])); ?></td>
                                        <td style="font-size: 1.5rem;"><?php echo htmlspecialchars($loan['max_period']); ?> months</td>
                                        <td style="font-size: 1.5rem;">
                                            <span class="badge status-<?php echo $loan['status']; ?>">
                                                <?php echo ucfirst($loan['status']); ?>
                                            </span>
                                        </td>
                                        <td class="actions-column" style="font-size: 1.5rem;">
                                            <div class="actions-flex">
                                                <!-- Update Status Button -->
                                                <button type="button" class="btn btn-sm btn-primary" style="font-size: 1.5rem;" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#loanModal-<?php echo $loan['id']; ?>">
                                                    <i class="bi bi-pencil" style="font-size: 1.5rem;"></i> Update
                                                </button>
                                                
                                                <!-- Delete Loan Button -->
                                                <button type="button" class="btn btn-sm btn-danger" style="font-size: 1.5rem;" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#deleteLoanModal-<?php echo $loan['id']; ?>">
                                                    <i class="bi bi-trash" style="font-size: 1.5rem;"></i> Delete
                                                </button>
                                            </div>
                                            
                                            <!-- Update Status Modal -->
                                            <div class="modal fade" id="loanModal-<?php echo $loan['id']; ?>" tabindex="-1" 
                                                aria-labelledby="loanModalLabel-<?php echo $loan['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-primary text-white">
                                                            <h5 class="modal-title" id="loanModalLabel-<?php echo $loan['id']; ?>" style="font-size: 1.5rem;">
                                                                Update Loan #<?php echo $loan['id']; ?> Status
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <form method="post" action="">
                                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                                                                
                                                                <div class="mb-3">
                                                                    <label for="status-<?php echo $loan['id']; ?>" class="form-label" style="font-size: 1.5rem;">Loan Status</label>
                                                                    <select class="form-select" id="status-<?php echo $loan['id']; ?>" name="status" style="font-size: 1.5rem;">
                                                                        <option value="active" style="font-size: 1.5rem;" <?php echo ($loan['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                                                                        <option value="closed" style="font-size: 1.5rem;" <?php echo ($loan['status'] === 'closed') ? 'selected' : ''; ?>>Closed</option>
                                                                        <option value="defaulted" style="font-size: 1.5rem;" <?php echo ($loan['status'] === 'defaulted') ? 'selected' : ''; ?>>Defaulted</option>
                                                                    </select>
                                                                </div>
                                                                
                                                                <div class="d-grid">
                                                                    <button type="submit" name="update_status" class="btn btn-primary" style="font-size: 1.5rem;">
                                                                        Update Status
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Delete Loan Modal -->
                                            <div class="modal fade" id="deleteLoanModal-<?php echo $loan['id']; ?>" tabindex="-1" 
                                                aria-labelledby="deleteLoanModalLabel-<?php echo $loan['id']; ?>" aria-hidden="true">
                                                <div class="modal-dialog">
                                                    <div class="modal-content">
                                                        <div class="modal-header bg-danger text-white">
                                                            <h5 class="modal-title" id="deleteLoanModalLabel-<?php echo $loan['id']; ?>">
                                                                Delete Loan #<?php echo $loan['id']; ?>
                                                            </h5>
                                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                                                        </div>
                                                        <div class="modal-body">
                                                            <p>Are you sure you want to delete this loan record?</p>
                                                            <p><strong>Loan Details:</strong></p>
                                                            <ul>
                                                                <li>Loan ID: <?php echo $loan['id']; ?></li>
                                                                <li>Loan Type: <?php echo htmlspecialchars($loan['loan_name']); ?></li>
                                                                <li>Amount: <?php echo number_format($loan['amount'], 2); ?></li>
                                                                <li>Status: <?php echo ucfirst($loan['status']); ?></li>
                                                            </ul>
                                                            <form method="post" action="">
                                                                <input type="hidden" name="loan_id" value="<?php echo $loan['id']; ?>">
                                                                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                                                                
                                                                <div class="d-grid">
                                                                    <button type="submit" name="delete_loan" class="btn btn-danger">
                                                                        <i class="bi bi-trash"></i> Confirm Delete
                                                                    </button>
                                                                </div>
                                                            </form>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php elseif ($member_info): ?>
                <div class="alert alert-info mt-4">
                    No loans found for this member.
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <footer class="bg-light py-4">
        <div class="container text-center">
            <p style="font-size: 1.5rem;">&copy; <?php echo date('Y'); ?> Sarvodaya Shramadhana Society. All rights reserved.</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>