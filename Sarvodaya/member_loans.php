<?php
// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch member details based on membership number
$memberDetails = null;
if (isset($_GET['search'])) {
    $membershipNo = $_GET['membership_no'];
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("i", $membershipNo);
    $stmt->execute();
    $result = $stmt->get_result();
    $memberDetails = $result->fetch_assoc();
    $stmt->close();
}

// Fetch loan types from the database
$loanTypes = [];
$result = $conn->query("SELECT * FROM loan_types");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $loanTypes[] = $row;
    }
}

// Handle AJAX request to fetch interest rate, max period, and maximum amount
if (isset($_GET['loan_type_id'])) {
    $loanTypeId = $_GET['loan_type_id'];
    $stmt = $conn->prepare("SELECT interest_rate, max_period, maximum_amount FROM loan_types WHERE id = ?");
    $stmt->bind_param("i", $loanTypeId);
    $stmt->execute();
    $result = $stmt->get_result();
    $loanType = $result->fetch_assoc();
    $stmt->close();

    if ($loanType) {
        echo json_encode(['interest_rate' => $loanType['interest_rate'], 'max_period' => $loanType['max_period'], 'maximum_amount' => $loanType['maximum_amount']]);
    } else {
        echo json_encode(['interest_rate' => 0, 'max_period' => 0, 'maximum_amount' => 0]);
    }
    exit();
}

// Handle AJAX request to fetch guarantor details
if (isset($_GET['guarantor_id'])) {
    $guarantorId = $_GET['guarantor_id'];
    $stmt = $conn->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->bind_param("i", $guarantorId);
    $stmt->execute();
    $result = $stmt->get_result();
    $guarantorDetails = $result->fetch_assoc();
    $stmt->close();

    if ($guarantorDetails) {
        echo json_encode($guarantorDetails);
    } else {
        echo json_encode(['error' => 'Guarantor not found']);
    }
    exit();
}

// Handle AJAX request to fetch loan details
if (isset($_GET['fetch_loans'])) {
    $memberId = $_GET['member_id'];
    $stmt = $conn->prepare("
        SELECT l.*, lt.loan_name 
        FROM loans l
        JOIN loan_types lt ON l.loan_type_id = lt.id
        WHERE l.member_id = ?
        ORDER BY l.application_date DESC
    ");
    $stmt->bind_param("i", $memberId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $loans = [];
    while ($row = $result->fetch_assoc()) {
        $row['amount'] = number_format($row['amount'], 2);
        $row['total_repayment_amount'] = number_format($row['total_repayment_amount'], 2);
        $row['application_date'] = date('Y-m-d H:i:s', strtotime($row['application_date']));
        $row['start_date'] = date('Y-m-d', strtotime($row['start_date']));
        $row['end_date'] = date('Y-m-d', strtotime($row['end_date']));
        $loans[] = $row;
    }
    
    $stmt->close();
    echo json_encode($loans);
    exit();
}

// New function to fetch transaction history for a specific member
if (isset($_GET['fetch_transactions'])) {
    $memberId = $_GET['member_id'];
    
    // Fetch payments
    $paymentsStmt = $conn->prepare("
        SELECT 'Payment' as type, payment_type as category, amount, description, payment_date as transaction_date 
        FROM payments 
        WHERE member_id = ?
    ");
    $paymentsStmt->bind_param("i", $memberId);
    $paymentsStmt->execute();
    $paymentsResult = $paymentsStmt->get_result();
    
    // Fetch receipts
    $receiptsStmt = $conn->prepare("
        SELECT 'Receipt' as type, receipt_type as category, amount, receipt_type as description, receipt_date as transaction_date 
        FROM receipts 
        WHERE member_id = ?
    ");
    $receiptsStmt->bind_param("i", $memberId);
    $receiptsStmt->execute();
    $receiptsResult = $receiptsStmt->get_result();
    
    $transactions = [];
    
    // Collect payments
    while ($row = $paymentsResult->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    // Collect receipts
    while ($row = $receiptsResult->fetch_assoc()) {
        $transactions[] = $row;
    }
    
    // Sort transactions by date
    usort($transactions, function($a, $b) {
        return strtotime($b['transaction_date']) - strtotime($a['transaction_date']);
    });
    
    // Format transactions
    foreach ($transactions as &$transaction) {
        $transaction['amount'] = number_format($transaction['amount'], 2);
        $transaction['transaction_date'] = date('Y-m-d H:i:s', strtotime($transaction['transaction_date']));
    }
    
    echo json_encode($transactions);
    exit();
}

// Handle loan application submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $memberId = $_POST['member_id'];
    $loanTypeId = $_POST['loan_type'];
    $amount = $_POST['amount'];
    $totalAmount = $_POST['total_amount'];
    $guarantor1Id = $_POST['guarantor1_id'];
    $guarantor2Id = $_POST['guarantor2_id'];

    // Validate guarantor IDs
    if ($guarantor1Id == $memberId) {
        echo "<script>alert('Guarantor 1 ID cannot be the same as the member ID.');</script>";
    } elseif ($guarantor2Id == $memberId) {
        echo "<script>alert('Guarantor 2 ID cannot be the same as the member ID.');</script>";
    } elseif ($guarantor1Id == $guarantor2Id) {
        echo "<script>alert('Guarantor 1 and Guarantor 2 cannot be the same.');</script>";
    } else {
        // Fetch the loan type details for the selected loan type
        $stmt = $conn->prepare("SELECT maximum_amount, interest_rate, max_period FROM loan_types WHERE id = ?");
        $stmt->bind_param("i", $loanTypeId);
        $stmt->execute();
        $result = $stmt->get_result();
        $loanTypeDetails = $result->fetch_assoc();
        $stmt->close();

        if ($loanTypeDetails) {
            $maximumAmount = $loanTypeDetails['maximum_amount'];
            $interestRate = $loanTypeDetails['interest_rate'];
            $maxPeriod = $loanTypeDetails['max_period'];

            // Calculate start and end dates
            $startDate = date('Y-m-d'); // Today's date
            $endDate = date('Y-m-d', strtotime("+$maxPeriod months", strtotime($startDate)));

            // Validate the loan amount
            if ($amount <= 0) {
                echo "<script>alert('Loan amount must be greater than 0.');</script>";
            } elseif ($amount > $maximumAmount) {
                echo "<script>alert('Loan amount exceeds the maximum allowed amount.');</script>";
            } else {
                // Insert loan application details into the database
                $stmt = $conn->prepare("INSERT INTO loans(member_id, loan_type_id, amount, interest_rate, max_period, total_repayment_amount, start_date, end_date, guarantor1_id, guarantor2_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'defaulted')");
                $stmt->bind_param("iiddiissii", $memberId, $loanTypeId, $amount, $interestRate, $maxPeriod, $totalAmount, $startDate, $endDate, $guarantor1Id, $guarantor2Id);
                $stmt->execute();
                $stmt->close();

                echo "<script>alert('Loan application submitted successfully!');</script>";
            }
        } else {
            echo "<script>alert('Invalid loan type.');</script>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Loan Application</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 20px;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-custom {
            background-color: #ffa726;
            color: white;
            border-radius: 5px;
            border: none;
            padding: 10px 20px;
            transition: all 0.3s ease;
        }
        .btn-custom:hover {
            background-color: #fb8c00;
            transform: scale(1.05);
        }
        .form-control {
            border-radius: 5px;
        }
        .btn-view-loans {
            background-color: rgb(256, 140, 0);
            color: white;
            border-radius: 5px;
            border: none;
            padding: 10px 20px;
            transition: all 0.3s ease;
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }
        .btn-view-loans:hover {
            background-color: #218838;
            transform: scale(1.05);
        }
        .loan-details-table {
            margin-top: 20px;
        }
        .loan-details-table th {
            background-color: #ffa726;
            color: white;
        }
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    

    <div class="container">
        <h1 class="text-center mb-4" style="color: #ffa726;">Member Loan Application</h1>

        <!-- Search Form -->
        <div class="card">
            <h2>Search Member</h2>
            <form method="GET" action="">
                <div class="mb-3">
                    <label for="membership_no" class="form-label">Membership Number</label>
                    <input type="text" class="form-control" id="membership_no" name="membership_no" required>
                </div>
                <button type="submit" name="search" class="btn btn-custom">Search</button>
            </form>
        </div>

        <!-- Member Details -->
        <?php if ($memberDetails): ?>
            <div class="card">
                <h2>Member Details</h2>
                <p><strong>Name:</strong> <?php echo $memberDetails['name']; ?></p>
                <p><strong>Email:</strong> <?php echo $memberDetails['email']; ?></p>
                <p><strong>Phone:</strong> <?php echo $memberDetails['phone']; ?></p>
                <p><strong>Address:</strong> <?php echo $memberDetails['address']; ?></p>
                <p><strong>Account Type:</strong> <?php echo $memberDetails['account_type']; ?></p>
                
                <!-- Loan Details Button and Container -->
                <button type="button" class="btn btn-custom mt-3" id="showLoanDetailsBtn">
                    <i class="bi bi-list-ul"></i> Show Loan Details
                </button>
                
                <div id="loanDetailsContainer" class="mt-3" style="display: none;">
                    <h4 class="mt-4">Loan History</h4>
                    <div id="loadingSpinner" class="loading-spinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading loan details...</p>
                    </div>
                    <div id="loanDetailsContent" class="table-responsive">
                        <!-- Loan details will be loaded here -->
                    </div>
                </div>
            </div>

            <!-- Loan Application Form -->
            <div class="card">
                <h2>Apply for Loan</h2>
                <form method="POST" action="" id="loanForm">
                    <input type="hidden" name="member_id" value="<?php echo $memberDetails['id']; ?>">
                    <div class="mb-3">
                        <label for="loan_type" class="form-label">Loan Type</label>
                        <select class="form-select" id="loan_type" name="loan_type" required>
                            <option value="">Select Loan Type</option>
                            <?php foreach ($loanTypes as $loanType): ?>
                                <option value="<?php echo $loanType['id']; ?>"><?php echo $loanType['loan_name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="amount" class="form-label">Loan Amount (Rs)</label>
                        <input type="number" class="form-control" id="amount" name="amount" required>
                    </div>
                    <div class="mb-3">
                        <label for="total_amount" class="form-label">Total Repayment Amount (Rs.)(Loan + Interest)</label>
                        <input type="text" class="form-control" id="total_amount" name="total_amount" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="guarantor1_id" class="form-label">Guarantor 1 ID</label>
                        <input type="text" class="form-control" id="guarantor1_id" name="guarantor1_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="guarantor1_details" class="form-label">Guarantor 1 Details</label>
                        <textarea class="form-control" id="guarantor1_details" rows="3" readonly></textarea>
                        <button type="button" class="btn btn-custom mt-2" id="guarantor1TransactionsBtn">
                            <i class="bi bi-list-ul"></i> View Transactions
                        </button>
                    </div>
                    <div class="mb-3">
                        <label for="guarantor2_id" class="form-label">Guarantor 2 ID</label>
                        <input type="text" class="form-control" id="guarantor2_id" name="guarantor2_id" required>
                    </div>
                    <div class="mb-3">
                        <label for="guarantor2_details" class="form-label">Guarantor 2 Details</label>
                        <textarea class="form-control" id="guarantor2_details" rows="3" readonly></textarea>
                        <button type="button" class="btn btn-custom mt-2" id="guarantor2TransactionsBtn">
                            <i class="bi bi-list-ul"></i> View Transactions
                        </button>
                    </div>
                    <button type="submit" class="btn btn-custom">Apply for Loan</button>
                </form>
            </div>
        <?php elseif (isset($_GET['search'])): ?>
            <div class="card">
                <p class="text-danger">No member found with the provided membership number.</p>
            </div>
        <?php endif; ?>
    </div>

    <!-- Transaction Modal -->
    <div class="modal fade" id="transactionModal" tabindex="-1" aria-labelledby="transactionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="transactionModalLabel">Transaction History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="transactionModalBody">
                    <!-- Transactions will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('loan_type').addEventListener('change', fetchInterestRate);
        document.getElementById('amount').addEventListener('input', calculateTotalAmount);
        document.getElementById('guarantor1_id').addEventListener('input', fetchGuarantor1Details);
        document.getElementById('guarantor2_id').addEventListener('input', fetchGuarantor2Details);
        
        // Loan details button functionality
        if (document.getElementById('showLoanDetailsBtn')) {
            document.getElementById('showLoanDetailsBtn').addEventListener('click', function() {
                const memberId = <?php echo $memberDetails ? $memberDetails['id'] : 'null'; ?>;
                const container = document.getElementById('loanDetailsContainer');
                const content = document.getElementById('loanDetailsContent');
                const loadingSpinner = document.getElementById('loadingSpinner');
                
                if (container.style.display === 'none') {
                    // Show loading spinner
                    loadingSpinner.style.display = 'block';
                    content.innerHTML = '';
                    
                    // Fetch loan details if not already shown
                    fetchLoanDetails(memberId);
                    
                    container.style.display = 'block';
                    this.innerHTML = '<i class="bi bi-eye-slash"></i> Hide Loan Details';
                } else {
                    container.style.display = 'none';
                    this.innerHTML = '<i class="bi bi-list-ul"></i> Show Loan Details';
                }
            });
        }

        // Transaction view buttons for guarantors
        document.getElementById('guarantor1TransactionsBtn').addEventListener('click', function() {
            const guarantorId = document.getElementById('guarantor1_id').value;
            if (guarantorId) {
                fetchMemberTransactions(guarantorId);
            } else {
                alert('Please enter a valid Guarantor 1 ID first.');
            }
        });

        document.getElementById('guarantor2TransactionsBtn').addEventListener('click', function() {
            const guarantorId = document.getElementById('guarantor2_id').value;
            if (guarantorId) {
                fetchMemberTransactions(guarantorId);
            } else {
                alert('Please enter a valid Guarantor 2 ID first.');
            }
        });

        function fetchLoanDetails(memberId) {
            if (!memberId) {
                document.getElementById('loanDetailsContent').innerHTML = 
                    '<p class="text-danger">No member selected</p>';
                return;
            }

            fetch(`?fetch_loans=1&member_id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    const content = document.getElementById('loanDetailsContent');
                    const loadingSpinner = document.getElementById('loadingSpinner');
                    
                    // Hide loading spinner
                    loadingSpinner.style.display = 'none';
                    
                    if (data.error) {
                        content.innerHTML = `<p class="text-danger">${data.error}</p>`;
                        return;
                    }
                    
                    if (data.length === 0) {
                        content.innerHTML = '<p>No loan history found for this member.</p>';
                        return;
                    }
                    
                    let html = `
                        <table class="table table-striped loan-details-table">
                            <thead>
                                <tr>
                                    <th>Loan ID</th>
                                    <th>Loan Type</th>
                                    <th>Amount (Rs.)</th>
                                    <th>Interest Rate</th>
                                    <th>Period (months)</th>
                                    <th>Total Repayment(Rs.)</th>
                                    <th>Status</th>
                                    <th>Application Date</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                </tr>
                            </thead>
                            <tbody>`;
                    
                    data.forEach(loan => {
                        html += `
                            <tr>
                                <td>${loan.id}</td>
                                <td>${loan.loan_name}</td>
                                <td>${loan.amount}</td>
                                <td>${loan.interest_rate}%</td>
                                <td>${loan.max_period}</td>
                                <td>${loan.total_repayment_amount}</td>
                                <td>
                                    <span class="badge ${getStatusBadgeClass(loan.status)}">
                                        ${loan.status}
                                    </span>
                                </td>
                                <td>${loan.application_date}</td>
                                <td>${loan.start_date}</td>
                                <td>${loan.end_date}</td>
                            </tr>`;
                    });
                    
                    html += `</tbody></table>`;
                    content.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching loan details:', error);
                    document.getElementById('loadingSpinner').style.display = 'none';
                    document.getElementById('loanDetailsContent').innerHTML = 
                        '<p class="text-danger">Error loading loan details. Please try again.</p>';
                });
        }

        function fetchMemberTransactions(memberId) {
            // Show loading spinner
            const transactionModalBody = document.getElementById('transactionModalBody');
            transactionModalBody.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading transactions...</p>
                </div>
            `;
            
            // Open the modal
            var transactionModal = new bootstrap.Modal(document.getElementById('transactionModal'));
            transactionModal.show();

            // Fetch transactions
            fetch(`?fetch_transactions=1&member_id=${memberId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.length === 0) {
                        transactionModalBody.innerHTML = '<p class="text-center">No transactions found.</p>';
                        return;
                    }

                    let html = `
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Amount(Rs.)</th>
                                    <th>Description</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>`;
                    
                    data.forEach(transaction => {
                        let typeClass = transaction.type === 'Payment' ? 'text-danger' : 'text-success';
                        html += `
                            <tr>
                                <td><span class="${typeClass}">${transaction.type}</span></td>
                                <td>${transaction.category}</td>
                                <td class="${typeClass}">${transaction.amount}</td>
                                <td>${transaction.description}</td>
                                <td>${transaction.transaction_date}</td>
                            </tr>`;
                    });
                    
                    html += `</tbody></table>`;
                    transactionModalBody.innerHTML = html;
                })
                .catch(error => {
                    console.error('Error fetching transactions:', error);
                    transactionModalBody.innerHTML = '<p class="text-danger">Error loading transactions. Please try again.</p>';
                });
        }

        function getStatusBadgeClass(status) {
            switch(status.toLowerCase()) {
                case 'active': return 'bg-success';
                case 'closed': return 'bg-secondary';
                case 'defaulted': return 'bg-danger';
                default: return 'bg-primary';
            }
        }

        function fetchInterestRate() {
            const loanTypeId = document.getElementById('loan_type').value;
            if (loanTypeId) {
                fetch('?loan_type_id=' + loanTypeId)
                    .then(response => response.json())
                    .then(data => {
                        document.getElementById('loan_type').setAttribute('data-interest-rate', data.interest_rate);
                        document.getElementById('loan_type').setAttribute('data-max-period', data.max_period);
                        document.getElementById('loan_type').setAttribute('data-maximum-amount', data.maximum_amount);
                        calculateTotalAmount();
                    })
                    .catch(error => console.error('Error fetching interest rate:', error));
            }
        }

        function calculateTotalAmount() {
    const loanType = document.getElementById('loan_type');
    const amount = document.getElementById('amount').value;
    const interestRate = loanType.getAttribute('data-interest-rate');
    const maxPeriod = loanType.getAttribute('data-max-period');
    const maximumAmount = loanType.getAttribute('data-maximum-amount');
    
    if (amount && interestRate && maxPeriod && maximumAmount) {
        const principal = parseFloat(amount);
        const monthlyRate = parseFloat(interestRate) / 100; // Convert to decimal
        const term = parseInt(maxPeriod);
        const maximumAmountNum = parseFloat(maximumAmount);
        
        if (!isNaN(principal) && !isNaN(monthlyRate) && !isNaN(term) && !isNaN(maximumAmountNum)) {
            if (principal <= 0) {
                alert('Loan amount must be greater than 0.');
                document.getElementById('amount').value = '';
                document.getElementById('total_amount').value = '';
                return;
            }
            
            if (principal > maximumAmountNum) {
                alert('Loan amount exceeds the maximum allowed amount of ' + maximumAmountNum + '.');
                document.getElementById('amount').value = '';
                document.getElementById('total_amount').value = '';
                return;
            }
            
            // Calculate monthly payment using amortization formula
            let monthlyPayment;
            if (monthlyRate === 0) {
                monthlyPayment = principal / term;
            } else {
                monthlyPayment = principal * monthlyRate * Math.pow(1 + monthlyRate, term) / (Math.pow(1 + monthlyRate, term) - 1);
            }
            
            // Calculate total amount to repay
            const totalAmount = monthlyPayment * term;
            document.getElementById('total_amount').value = totalAmount.toFixed(2);
        } else {
            console.error('Invalid input values. Amount, interest rate, max period, or maximum amount is not a number.');
            document.getElementById('total_amount').value = '';
        }
    } else {
        console.error('Missing required inputs. Amount, interest rate, max period, or maximum amount is not provided.');
        document.getElementById('total_amount').value = '';
    }
}

        function fetchGuarantor1Details() {
            const guardianId = document.getElementById('guarantor1_id').value;
            if (guardianId) {
                fetchGuarantorDetails(guardianId, 'guarantor1_details');
            } else {
                document.getElementById('guarantor1_details').value = '';
            }
        }

        function fetchGuarantor2Details() {
            const guardianId = document.getElementById('guarantor2_id').value;
            if (guardianId) {
                fetchGuarantorDetails(guardianId, 'guarantor2_details');
            } else {
                document.getElementById('guarantor2_details').value = '';
            }
        }

        function fetchGuarantorDetails(guardianId, targetElementId) {
            fetch('?guarantor_id=' + guardianId)
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById(targetElementId).value = data.error;
                    } else {
                        const details = `Name: ${data.name}\nEmail: ${data.email}\nPhone: ${data.phone}\nAddress: ${data.address}\nNIC: ${data.nic}\nOccupation: ${data.occupation}`;
                        document.getElementById(targetElementId).value = details;
                    }
                })
                .catch(error => {
                    console.error('Error fetching guarantor details:', error);
                    document.getElementById(targetElementId).value = 'Error fetching details.';
                });
        }

        // Add form submission validation
        document.getElementById('loanForm').addEventListener('submit', function(event) {
            const memberId = <?php echo $memberDetails ? $memberDetails['id'] : 'null'; ?>;
            const guarantor1Id = document.getElementById('guarantor1_id').value;
            const guarantor2Id = document.getElementById('guarantor2_id').value;

            if (memberId) {
                // Check if guarantor IDs are the same as member ID
                if (guarantor1Id == memberId) {
                    event.preventDefault();
                    alert('Guarantor 1 ID cannot be the same as the member ID.');
                    return false;
                }

                if (guarantor2Id == memberId) {
                    event.preventDefault();
                    alert('Guarantor 2 ID cannot be the same as the member ID.');
                    return false;
                }

                // Check if guarantor IDs are the same
                if (guarantor1Id == guarantor2Id) {
                    event.preventDefault();
                    alert('Guarantor 1 and Guarantor 2 cannot be the same.');
                    return false;
                }
            }

            return true;
        });
    </script>
</body>
</html>