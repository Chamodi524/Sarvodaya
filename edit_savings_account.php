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
    <title>Edit Savings Account Type</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: #ffa726;
            color: white;
            border-radius: 5px;
            border: none;
        }
        .btn-custom:hover {
            background-color: #fb8c00;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Edit Savings Account Type</h1>
        <div class="card p-4">
            <form action="update_savings_account.php" method="POST">
                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                <div class="mb-3">
                    <label for="account_name" class="form-label">Account Name</label>
                    <input type="text" class="form-control" id="account_name" name="account_name" value="<?php echo $row['account_name']; ?>" required>
                </div>
                <div class="mb-3">
                    <label for="minimum_balance" class="form-label">Minimum Balance</label>
                    <input type="number" class="form-control" id="minimum_balance" name="minimum_balance" value="<?php echo $row['minimum_balance']; ?>" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="interest_rate" class="form-label">Interest Rate (%)</label>
                    <input type="number" class="form-control" id="interest_rate" name="interest_rate" value="<?php echo $row['interest_rate']; ?>" step="0.01" required>
                </div>
                <div class="mb-3">
                    <label for="detail_no" class="form-label">Account Type (You cannot change this. 1 for Child Related Account, 2 for Normal Account)</label>
                    <select class="form-control" id="detail_no" name="detail_no" required>
                        <option value="1" <?php echo ($row['detail_no'] == 1) ? 'selected' : ''; ?>>1</option>
                        <option value="2" <?php echo ($row['detail_no'] == 2) ? 'selected' : ''; ?>>2</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-custom">Update Savings Account Type</button>
            </form>
        </div>
    </div>
</body>
</html>
<?php
// Close the statement and connection
$stmt->close();
$conn->close();
?>