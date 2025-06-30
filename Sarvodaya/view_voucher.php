<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filterMemberNumber = isset($_GET['filter_member_number']) ? trim($_GET['filter_member_number']) : '';

// Base query for all payments
$baseQuery = "
    SELECT 
        payments.id AS payment_id,
        members.id AS member_id,
        members.name AS member_name,
        payments.payment_type,
        payments.amount,
        payments.description,
        payments.payment_date
    FROM payments
    JOIN members ON payments.member_id = members.id
";

// Default query with no filters
$query = $baseQuery . " ORDER BY payments.payment_date DESC";
$result = $conn->query($query);

// Apply filters if provided
if (!empty($filterType) || !empty($filterMemberNumber)) {
    $filterQuery = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $filterQuery .= " AND payments.payment_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        // Assuming member.id is the member number - adjust if your system uses a different field
        $filterQuery .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    $filterQuery .= " ORDER BY payments.payment_date DESC";
    $result = $conn->query($filterQuery);
}

// Get payment types for filter dropdown
$typesQuery = "SELECT DISTINCT payment_type FROM payments ORDER BY payment_type";
$typesResult = $conn->query($typesQuery);
$paymentTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $paymentTypes[] = $type['payment_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Payments - Sarvodaya Shramadhana Society</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
            font-family: 'Arial', sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Header Styles */
        .header-section {
            background-color: #fff;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            border: 2px solid #ffa726;
        }
        .organization-title {
            font-size: 2.2rem;
            font-weight: bold;
            color: #e65100;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .organization-subtitle {
            font-size: 1.2rem;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        .organization-contact {
            font-size: 1rem;
            color: #666;
            margin-bottom: 8px;
        }
        .organization-reg {
            font-size: 0.9rem;
            color: #888;
            font-style: italic;
        }
        .header-divider {
            border: 1px solid #ffa726;
            margin: 15px auto;
            width: 80%;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table-custom {
            width: 100%;
            border-collapse: collapse;
        }
        .table-custom th,
        .table-custom td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table-custom th {
            background-color: #ffa726;
            color: white;
        }
        .table-custom tbody tr:hover {
            background-color: #ffe0b2;
        }
        .btn-action {
            background-color: #ffa726; /* Orange color */
            color: white;
            border-radius: 5px;
            border: none;
            padding: 8px 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            margin-right: 5px;
            display: inline-block;
        }
        .btn-action:hover {
            background-color: #fb8c00; /* Darker orange on hover */
            transform: scale(1.05);
            color: white;
        }
        .btn-print {
            background-color: #28a745; /* Green color */
            color: white;
        }
        .btn-print:hover {
            background-color: #218838; /* Darker green on hover */
        }
        .filter-section {
            background-color: #fff;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .total-section {
            background-color: #fff8e1;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            font-weight: bold;
            text-align: right;
            font-size: 1.1em;
        }
        .description-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .description-cell:hover {
            white-space: normal;
            overflow: visible;
        }
        /* Highlight active filters */
        .active-filter {
            border: 2px solid #ffa726;
            background-color: #fff8e1;
        }
        /* Action buttons spacing and style */
        .action-buttons {
            display: flex;
            gap: 10px; /* Increased space between buttons */
        }
        .action-btn {
            padding: 6px 12px;
            display: inline-block;
            text-align: center;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            .filter-section {
                display: none;
            }
            body {
                padding: 10px;
                background-color: white;
                font-size: 12px;
            }
            .container {
                max-width: 100%;
                margin: 0;
            }
            .card {
                box-shadow: none;
                padding: 10px;
            }
            .header-section {
                box-shadow: none;
                padding: 15px;
                margin-bottom: 20px;
                page-break-inside: avoid;
            }
            .organization-title {
                font-size: 1.8rem;
            }
            .organization-subtitle {
                font-size: 1rem;
            }
            .organization-contact {
                font-size: 0.9rem;
            }
            .table-custom {
                font-size: 11px;
            }
            .table-custom th,
            .table-custom td {
                padding: 6px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Organization Header -->
        <div class="header-section">
            <div class="organization-title">SARVODAYA SHRAMADHANA SOCIETY</div>
            <div class="organization-subtitle">Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda</div>
            <div class="organization-contact">077 690 6605 | info@sarvodayabank.com</div>
            <div class="organization-reg">Reg. No: 12345/SS/2020</div>
            <hr class="header-divider">
            <h2 style="color: #ffa726; margin-top: 15px; margin-bottom: 0;">Payment Records Report</h2>
        </div>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="" class="row g-3">
                <!-- Payment Type Filter -->
                <div class="col-md-4">
                    <label for="filter_type" class="form-label">Filter by Payment Type:</label>
                    <select name="filter_type" id="filter_type" class="form-select <?php echo (!empty($filterType)) ? 'active-filter' : ''; ?>">
                        <option value="">All Payment Types</option>
                        <?php foreach ($paymentTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type); ?>" <?php echo ($filterType == $type) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $type))); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Direct Member Number Input -->
                <div class="col-md-4">
                    <label for="filter_member_number" class="form-label">Filter by Member Number:</label>
                    <input type="text" name="filter_member_number" id="filter_member_number" 
                           class="form-control <?php echo (!empty($filterMemberNumber)) ? 'active-filter' : ''; ?>" 
                           placeholder="Enter Member ID" 
                           value="<?php echo htmlspecialchars($filterMemberNumber); ?>">
                </div>
                
                <!-- Filter Buttons -->
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn-action me-2">
                        <i class="bi bi-filter"></i> Apply Filters
                    </button>
                    <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn-action btn-print">
                        <i class="bi bi-x-circle"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Active Filters Display -->
        <?php if (!empty($filterType) || !empty($filterMemberNumber)): ?>
        <div class="alert alert-info mb-3 no-print">
            <strong>Active Filters:</strong> 
            <?php 
            $appliedFilters = [];
            if (!empty($filterType)) {
                $appliedFilters[] = "Payment Type: " . ucfirst(str_replace('_', ' ', $filterType));
            }
            if (!empty($filterMemberNumber)) {
                $appliedFilters[] = "Member Number: " . htmlspecialchars($filterMemberNumber);
            }
            echo implode(' | ', $appliedFilters);
            ?>
        </div>
        <?php endif; ?>

        <!-- Payments Table -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3 no-print">
                <h3 style="color: #333; margin: 0;">Payment Details</h3>
                <div>
                    <a href="#" onclick="window.print();" class="btn-action btn-print">
                        <i class="bi bi-printer"></i> Print Report
                    </a>
                </div>
            </div>
            
            <!-- Print-only date and time -->
            <div style="display: none;">
                <div class="print-only" style="text-align: right; margin-bottom: 15px; font-size: 0.9rem; color: #666;">
                    Generated on: <?php echo date('d M Y, h:i A'); ?>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Payment ID</th>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Payment Type</th>
                            <th>Amount (Rs.)</th>
                            <th>Description</th>
                            <th>Payment Date</th>
                            <th class="no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $totalAmount = 0;
                        if ($result && $result->num_rows > 0): 
                        ?>
                            <?php while ($row = $result->fetch_assoc()): 
                                $totalAmount += $row['amount'];
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['payment_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['member_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['payment_type']))); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($row['amount'], 2)); ?></td>
                                    <td class="description-cell" title="<?php echo htmlspecialchars($row['description']); ?>">
                                        <?php echo htmlspecialchars($row['description']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['payment_date']))); ?></td>
                                    <td class="no-print">
                                        <div class="action-buttons">
                                            <a href="view_payment_detail.php?payment_id=<?php echo htmlspecialchars($row['payment_id']); ?>" class="btn-action action-btn" onclick="event.stopPropagation();">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="generate_payment_receipt.php?payment_id=<?php echo htmlspecialchars($row['payment_id']); ?>" class="btn-action action-btn" onclick="event.stopPropagation();">
                                                <i class="bi bi-file-earmark-pdf"></i> Receipt
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No payments found matching your filter criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Total Section -->
            <div class="total-section">
                <div class="row">
                    <div class="col-md-6 text-start">
                        <?php if ($result): ?>
                            <span>Total Records: <?php echo $result->num_rows; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 text-end">
                        Total Amount: Rs. <?php echo number_format($totalAmount, 2); ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Print Footer -->
        <div style="display: none;">
            <div class="print-only" style="margin-top: 30px; text-align: center; font-size: 0.8rem; color: #666; border-top: 1px solid #ddd; padding-top: 10px;">
                This is a computer-generated report from Sarvodaya Shramadhana Society Management System
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make table rows clickable to view payment details
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-custom tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Only navigate if the click wasn't on a button or link
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const paymentId = this.querySelector('td:first-child').textContent;
                        window.location.href = 'view_payment_detail.php?payment_id=' + paymentId;
                    }
                });
                row.style.cursor = 'pointer';
            });
            
            // Show print-only elements when printing
            window.addEventListener('beforeprint', function() {
                const printOnlyElements = document.querySelectorAll('.print-only');
                printOnlyElements.forEach(element => {
                    element.style.display = 'block';
                });
            });
            
            window.addEventListener('afterprint', function() {
                const printOnlyElements = document.querySelectorAll('.print-only');
                printOnlyElements.forEach(element => {
                    element.style.display = 'none';
                });
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>