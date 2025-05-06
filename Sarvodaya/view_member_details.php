<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch member details and transactions based on member ID
$memberDetails = null;
$payments = [];
$receipts = [];
if (isset($_GET['search'])) {
    $memberId = intval($_GET['member_id']);
    if ($memberId > 0) {
        // Fetch member details
        $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        $memberDetails = $result->fetch_assoc();
        $stmt->close();

        // Fetch payment transactions
        $stmt = $conn->prepare("SELECT * FROM payments WHERE member_id = ? ORDER BY payment_date DESC");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $payments[] = $row;
        }
        $stmt->close();

        // Fetch receipt transactions
        $stmt = $conn->prepare("SELECT * FROM receipts WHERE member_id = ? ORDER BY receipt_date DESC");
        $stmt->bind_param("i", $memberId);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $receipts[] = $row;
        }
        $stmt->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Transaction History</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .table th {
            background-color: #ffa726;
            color: white;
        }
        .table tr:hover {
            background-color: #f5f5f5;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Member Transaction History</h1>

        <!-- Search Form -->
        <div class="card">
            <h2>Search Member</h2>
            <form method="GET" action="">
                <div class="mb-3">
                    <label for="member_id" class="form-label">Member ID</label>
                    <input type="number" class="form-control" id="member_id" name="member_id" required>
                </div>
                <button type="submit" name="search" class="btn btn-custom">Search</button>
            </form>
        </div>

        <!-- Member Details -->
        <?php if ($memberDetails): ?>
            <div class="card">
                <h2>Member Details</h2>
                <p><strong>ID:</strong> <?php echo $memberDetails['id']; ?></p>
                <p><strong>Name:</strong> <?php echo $memberDetails['name']; ?></p>
                <p><strong>Email:</strong> <?php echo $memberDetails['email']; ?></p>
                <p><strong>Phone:</strong> <?php echo $memberDetails['phone']; ?></p>
                <p><strong>Address:</strong> <?php echo $memberDetails['address']; ?></p>
                <p><strong>Account Type:</strong> <?php echo $memberDetails['account_type']; ?></p>
                <p><strong>Guardian Name:</strong> <?php echo $memberDetails['guardian_name']; ?></p>
                <p><strong>Guardian NIC:</strong> <?php echo $memberDetails['guardian_nic']; ?></p>
                <p><strong>Guardian Occupation:</strong> <?php echo $memberDetails['guardian_occupation']; ?></p>
                <p><strong>NIC:</strong> <?php echo $memberDetails['nic']; ?></p>
                <p><strong>Occupation:</strong> <?php echo $memberDetails['occupation']; ?></p>
                <p><strong>Created At:</strong> <?php echo $memberDetails['created_at']; ?></p>
            </div>

            <!-- Payment Transactions -->
            <div class="card">
                <h2>Payment Transactions</h2>
                <?php if (!empty($payments)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Payment Type</th>
                                <th>Amount</th>
                                
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo $payment['id']; ?></td>
                                    <td><?php echo $payment['payment_type']; ?></td>
                                    <td><?php echo number_format($payment['amount'], 2); ?></td>
                                    
                                    <td><?php echo $payment['payment_date']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No payment transactions found.</p>
                <?php endif; ?>
            </div>

            <!-- Receipt Transactions -->
            <div class="card">
                <h2>Receipt Transactions</h2>
                <?php if (!empty($receipts)): ?>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Receipt Type</th>
                                <th>Amount</th>
                                <th>Receipt Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($receipts as $receipt): ?>
                                <tr>
                                    <td><?php echo $receipt['id']; ?></td>
                                    <td><?php echo $receipt['receipt_type']; ?></td>
                                    <td><?php echo number_format($receipt['amount'], 2); ?></td>
                                    <td><?php echo $receipt['receipt_date']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p>No receipt transactions found.</p>
                <?php endif; ?>
            </div>
        <?php elseif (isset($_GET['search'])): ?>
            <div class="card">
                <p class="text-danger">No member found with the provided ID.</p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>