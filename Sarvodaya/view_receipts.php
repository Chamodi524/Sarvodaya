<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Initialize variables
$filterType = isset($_GET['filter_type']) ? $_GET['filter_type'] : '';
$filterMemberNumber = isset($_GET['filter_member_number']) ? trim($_GET['filter_member_number']) : '';

// Base query for all receipts
$baseQuery = "
    SELECT 
        receipts.id AS receipt_id,
        members.id AS member_id,
        members.name AS member_name,
        receipts.receipt_type,
        receipts.amount,
        receipts.receipt_date
    FROM receipts
    JOIN members ON receipts.member_id = members.id
";

// Default query with no filters
$query = $baseQuery . " ORDER BY receipts.receipt_date DESC";
$result = $conn->query($query);

// Apply filters if provided
if (!empty($filterType) || !empty($filterMemberNumber)) {
    $filterQuery = $baseQuery . " WHERE 1=1";
    
    if (!empty($filterType)) {
        $filterQuery .= " AND receipts.receipt_type = '" . $conn->real_escape_string($filterType) . "'";
    }
    
    if (!empty($filterMemberNumber)) {
        // Assuming member.id is the member number
        $filterQuery .= " AND members.id = " . (int)$filterMemberNumber;
    }
    
    $filterQuery .= " ORDER BY receipts.receipt_date DESC";
    $result = $conn->query($filterQuery);
}

// Get receipt types for filter dropdown
$typesQuery = "SELECT DISTINCT receipt_type FROM receipts ORDER BY receipt_type";
$typesResult = $conn->query($typesQuery);
$receiptTypes = [];
while ($type = $typesResult->fetch_assoc()) {
    $receiptTypes[] = $type['receipt_type'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Receipts - Sarvodaya Shramadhana Society</title>
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
        .organization-header {
            background: linear-gradient(135deg, #ffa726 0%, #ff9800 100%);
            color: white;
            padding: 25px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .organization-header h1 {
            font-size: 2.2rem;
            font-weight: bold;
            margin-bottom: 8px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .organization-header .subtitle {
            font-size: 1.1rem;
            margin-bottom: 15px;
            opacity: 0.95;
        }
        .organization-header .contact-info {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-bottom: 8px;
        }
        .organization-header .reg-info {
            font-size: 0.9rem;
            opacity: 0.85;
            font-style: italic;
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
            .print-only {
                display: block !important;
            }
            .screen-only {
                display: none !important;
            }
            body {
                padding: 0;
                background-color: white;
            }
            .card {
                box-shadow: none;
            }
            .organization-header {
                background: #ffa726 !important;
                -webkit-print-color-adjust: exact;
                color-adjust: exact;
            }
        }
        
        /* Signature Section Styles */
        .signature-section {
            display: none; /* Hidden on screen */
            background-color: #fff;
            padding: 30px 20px;
            margin-top: 30px;
            border-top: 2px solid #ffa726;
        }
        .signature-section .date-section p {
            margin-bottom: 10px;
            font-size: 1rem;
        }
        .signature-section .signature-section-content p {
            margin-bottom: 8px;
            font-size: 1rem;
        }
        .signature-line {
            height: 60px;
        }
        
        /* Screen only styles */
        .screen-only {
            display: block;
        }
        .print-only {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Organization Header -->
        <div class="organization-header">
            <h1>SARVODAYA SHRAMADHANA SOCIETY</h1>
            <div class="subtitle">Samaghi Sarvodaya Shramadhana Society, Kubaloluwa, Veyangoda</div>
            <div class="contact-info">
                <i class="bi bi-telephone"></i> 077 690 6605 | 
                <i class="bi bi-envelope"></i> info@sarvodayabank.com
            </div>
            <div class="reg-info">Reg. No: 12345/SS/2020</div>
        </div>

        <h2 class="text-center mb-4 screen-only" style="color: #ffa726;">View Receipts</h2>
        <h2 class="text-center mb-4 print-only" style="color: #ffa726;">Receipt Details Report</h2>

        <!-- Filter Section -->
        <div class="filter-section no-print">
            <form method="GET" action="" class="row g-3">
                <!-- Receipt Type Filter -->
                <div class="col-md-4">
                    <label for="filter_type" class="form-label">Filter by Receipt Type:</label>
                    <select name="filter_type" id="filter_type" class="form-select <?php echo (!empty($filterType)) ? 'active-filter' : ''; ?>">
                        <option value="">All Receipt Types</option>
                        <?php foreach ($receiptTypes as $type): ?>
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
                $appliedFilters[] = "Receipt Type: " . ucfirst(str_replace('_', ' ', $filterType));
            }
            if (!empty($filterMemberNumber)) {
                $appliedFilters[] = "Member Number: " . htmlspecialchars($filterMemberNumber);
            }
            echo implode(' | ', $appliedFilters);
            ?>
        </div>
        <?php endif; ?>

        <!-- Receipts Table -->
        <div class="card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Receipt Records</h3>
                <div class="no-print">
                    <a href="#" onclick="window.print();" class="btn-action btn-print">
                        <i class="bi bi-printer"></i> Print Report
                    </a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table-custom">
                    <thead>
                        <tr>
                            <th>Receipt ID</th>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Receipt Type</th>
                            <th>Amount (Rs.)</th>
                            <th>Receipt Date</th>
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
                                    <td><?php echo htmlspecialchars($row['receipt_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['member_id']); ?></td>
                                    <td><?php echo htmlspecialchars($row['member_name']); ?></td>
                                    <td><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $row['receipt_type']))); ?></td>
                                    <td><?php echo htmlspecialchars(number_format($row['amount'], 2)); ?></td>
                                    <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($row['receipt_date']))); ?></td>
                                    <td class="no-print">
                                        <div class="action-buttons">
                                            <a href="view_receipt_detail.php?receipt_id=<?php echo htmlspecialchars($row['receipt_id']); ?>" class="btn-action action-btn">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="genarate_receipt_receipt.php?receipt_id=<?php echo htmlspecialchars($row['receipt_id']); ?>" class="btn-action action-btn">
                                                <i class="bi bi-file-earmark-pdf"></i> Receipt
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No receipts found matching your filter criteria.</td>
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
                        Total Amount: (Rs.)<?php echo number_format($totalAmount, 2); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Date and Signature Section (Visible only in print) -->
        <div class="signature-section print-only">
            <div class="row mt-5">
                <div class="col-md-6">
                    <div class="date-section">
                        <p><strong>Report Generated Date:</strong></p>
                        <p><?php echo date('d F Y, h:i A'); ?></p>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="signature-section-content">
                        <div class="signature-line">
                            <br><br><br>
                        </div>
                        <p>_________________________</p>
                        <p><small>Manager<br>Sarvodaya Shramadhana Society</small></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Make table rows clickable to view receipt details
        document.addEventListener('DOMContentLoaded', function() {
            const tableRows = document.querySelectorAll('.table-custom tbody tr');
            tableRows.forEach(row => {
                row.addEventListener('click', function(e) {
                    // Only navigate if the click wasn't on a button or link
                    if (!e.target.closest('a') && !e.target.closest('button')) {
                        const receiptId = this.querySelector('td:first-child').textContent;
                        window.location.href = 'view_receipt_detail.php?receipt_id=' + receiptId;
                    }
                });
                row.style.cursor = 'pointer';
            });
        });
    </script>
</body>
</html>

<?php
// Close the database connection
$conn->close();
?>