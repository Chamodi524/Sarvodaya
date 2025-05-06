<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if member ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Invalid member ID");
}

$member_id = intval($_GET['id']);

// Fetch member details with account type information
$query = "
    SELECT m.*, s.detail_no, s.account_name 
    FROM members m
    JOIN savings_account_types s ON m.account_type = s.id
    WHERE m.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $member_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Member not found");
}

$member = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Details - <?php echo htmlspecialchars($member['name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css" rel="stylesheet">
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
        .details-header {
            background-color: #ffa726;
            color: white;
            padding: 15px;
            border-top-left-radius: 10px;
            border-top-right-radius: 10px;
        }
        .document-preview {
            max-width: 100%;
            max-height: 400px;
            object-fit: contain;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="details-header">
                <h2 class="text-center mb-0">Member Details</h2>
            </div>
            <div class="card-body p-4">
                <div class="row">
                    <div class="col-md-6">
                        <h3 style="color: #ffa726;">Personal Information</h3>
                        <table class="table">
                            <tr>
                                <th>Name</th>
                                <td><?php echo htmlspecialchars($member['name']); ?></td>
                            </tr>
                            <tr>
                                <th>Email</th>
                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Phone</th>
                                <td><?php echo htmlspecialchars($member['phone']); ?></td>
                            </tr>
                            <tr>
                                <th>Address</th>
                                <td><?php echo htmlspecialchars($member['address']); ?></td>
                            </tr>
                            <tr>
                                <th>Account Type</th>
                                <td><?php echo htmlspecialchars($member['account_name']); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <?php if ($member['detail_no'] == 1): ?>
                            <h3 style="color: #ffa726;">Guardian Information</h3>
                            <table class="table">
                                <tr>
                                    <th>Guardian's Name</th>
                                    <td><?php echo htmlspecialchars($member['guardian_name'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Guardian's NIC</th>
                                    <td><?php echo htmlspecialchars($member['guardian_nic'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Guardian's Occupation</th>
                                    <td><?php echo htmlspecialchars($member['guardian_occupation'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        <?php else: ?>
                            <h3 style="color: #ffa726;">Additional Information</h3>
                            <table class="table">
                                <tr>
                                    <th>NIC Number</th>
                                    <td><?php echo htmlspecialchars($member['nic'] ?? 'N/A'); ?></td>
                                </tr>
                                <tr>
                                    <th>Occupation</th>
                                    <td><?php echo htmlspecialchars($member['occupation'] ?? 'N/A'); ?></td>
                                </tr>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($member['file_path'])): ?>
                    <div class="row mt-4">
                        <div class="col-12">
                            <h3 style="color: #ffa726;">Uploaded Document</h3>
                            <?php 
                            $file_extension = strtolower(pathinfo($member['file_path'], PATHINFO_EXTENSION));
                            ?>
                            <?php if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])): ?>
                                <img src="<?php echo htmlspecialchars($member['file_path']); ?>" alt="Uploaded Document" class="document-preview img-fluid">
                            <?php else: ?>
                                <p>
                                    <a href="<?php echo htmlspecialchars($member['file_path']); ?>" target="_blank" class="btn btn-primary">
                                        <i class="bi bi-file-earmark-text"></i> View Document
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="text-center mt-4">
                    <a href="member_management.php" class="btn btn-custom" style="background-color: #ffa726; color: white;">
                        <i class="bi bi-arrow-left"></i> Back to Members List
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

<?php
$stmt->close();
$conn->close();
?>