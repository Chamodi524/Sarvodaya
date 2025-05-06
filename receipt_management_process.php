<?php
// Start session if not already started (for user_id)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON for AJAX response
header('Content-Type: application/json');

// Database connection
$conn = new mysqli('localhost', 'root', '', 'sarvodaya');
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => "Connection failed: " . $conn->connect_error
    ]));
}

// Function to convert number to words for Indian Rupees
function numberToWords($number) {
    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen",
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );

    // Format the number with 2 decimal places
    $number = (float)$number;
    $number_parts = explode('.', number_format($number, 2, '.', ''));

    $wholenum = $number_parts[0];
    $decnum = $number_parts[1];

    // Handle the whole number portion
    $result = "";

    // Process crores (if any)
    $crores = (int)($wholenum / 10000000);
    if ($crores > 0) {
        $result .= numberToWordsIndian($crores) . " Crore ";
        $wholenum %= 10000000;
    }

    // Process lakhs (if any)
    $lakhs = (int)($wholenum / 100000);
    if ($lakhs > 0) {
        $result .= numberToWordsIndian($lakhs) . " Lakh ";
        $wholenum %= 100000;
    }

    // Process thousands (if any)
    $thousands = (int)($wholenum / 1000);
    if ($thousands > 0) {
        $result .= numberToWordsIndian($thousands) . " Thousand ";
        $wholenum %= 1000;
    }

    // Process hundreds (if any)
    $hundreds = (int)($wholenum / 100);
    if ($hundreds > 0) {
        $result .= numberToWordsIndian($hundreds) . " Hundred ";
        $wholenum %= 100;
    }

    // Process tens and ones
    if ($wholenum > 0) {
        if ($result != "") {
            $result .= "and ";
        }
        $result .= numberToWordsIndian($wholenum);
    }

    // Add "Rupees" text
    if ($result == "") {
        $result = "Zero";
    }
    $result .= " Rupees";

    // Process decimal part (paise)
    if ((int)$decnum > 0) {
        $result .= " and " . numberToWordsIndian((int)$decnum) . " Paise";
    }

    return $result . " Only";
}

// Helper function to convert small numbers to words
function numberToWordsIndian($num) {
    $ones = array(
        0 => "", 1 => "One", 2 => "Two", 3 => "Three", 4 => "Four", 5 => "Five",
        6 => "Six", 7 => "Seven", 8 => "Eight", 9 => "Nine", 10 => "Ten",
        11 => "Eleven", 12 => "Twelve", 13 => "Thirteen", 14 => "Fourteen", 15 => "Fifteen",
        16 => "Sixteen", 17 => "Seventeen", 18 => "Eighteen", 19 => "Nineteen"
    );
    $tens = array(
        0 => "", 1 => "", 2 => "Twenty", 3 => "Thirty", 4 => "Forty", 5 => "Fifty",
        6 => "Sixty", 7 => "Seventy", 8 => "Eighty", 9 => "Ninety"
    );

    $num = (int)$num;

    if ($num < 20) {
        return $ones[$num];
    } elseif ($num < 100) {
        return $tens[(int)($num/10)] . ($num % 10 ? " " . $ones[$num % 10] : "");
    }

    return ""; // Should not reach here with proper usage
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $member_id = isset($_POST['member_id']) ? intval($_POST['member_id']) : 0;
    $receipt_type = isset($_POST['receipt_type']) ? $_POST['receipt_type'] : '';
    $loan_id = null;

    // Only set loan_id if it's a loan-related receipt and the value is provided
    // Note: Changed from loan_type to loan_id based on your schema
    if ($receipt_type === 'loan_repayment' && isset($_POST['loan_type'])) {
        $loan_id = intval($_POST['loan_type']);  // The form sends loan_id as loan_type
    }

    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;

    // Validate inputs
    if ($member_id <= 0 || empty($receipt_type) || $amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'All fields are required and values must be valid!'
        ]);
        exit;
    }

    // Validate that loan_id is provided for loan-related receipts
    if ($receipt_type === 'loan_repayment' && $loan_id <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Loan selection is required for loan repayments!'
        ]);
        exit;
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Verify the member exists first
        $member_query = "SELECT id, name, account_type FROM members WHERE id = ?";
        $member_stmt = $conn->prepare($member_query);
        $member_stmt->bind_param("i", $member_id);
        $member_stmt->execute();
        $member_result = $member_stmt->get_result();

        if ($member_result->num_rows === 0) {
            throw new Exception("Member ID {$member_id} not found. Please check the ID and try again.");
        }

        $member_data = $member_result->fetch_assoc();
        $member_name = $member_data['name'];
        $account_type = $member_data['account_type']; // Get the member's default account type

        // Special processing for loan repayments
        $loan_data = null;
        $new_remaining = null;
        $loan_type_id = null;
        $loan_type_name = '';
        
        if ($receipt_type === 'loan_repayment') {
            // Check if there's an active loan with the provided loan_id
            $loan_query = "SELECT l.id, l.status, l.total_repayment_amount, l.loan_type_id, lt.loan_name 
                          FROM loans l
                          JOIN loan_types lt ON l.loan_type_id = lt.id
                          WHERE l.id = ? AND l.member_id = ? LIMIT 1";
            $loan_stmt = $conn->prepare($loan_query);
            $loan_stmt->bind_param("ii", $loan_id, $member_id);
            $loan_stmt->execute();
            $loan_result = $loan_stmt->get_result();

            if ($loan_result->num_rows > 0) {
                $loan_data = $loan_result->fetch_assoc();
                $loan_status = $loan_data['status'];
                $remaining_amount = $loan_data['total_repayment_amount'];
                $loan_type_id = $loan_data['loan_type_id'];
                $loan_type_name = $loan_data['loan_name'];

                // Check if loan is already closed or defaulted
                if ($loan_status === 'closed' || $loan_status === 'defaulted') {
                    throw new Exception("Cannot process payment for a loan that is already {$loan_status}.");
                }

                // Prevent overpayment
                if ($amount > $remaining_amount) {
                    throw new Exception("Payment amount ($amount) exceeds the remaining loan balance ($remaining_amount).");
                }

                // Update the total repayment amount
                $new_remaining = $remaining_amount - $amount;
                $update_loan_query = "UPDATE loans SET total_repayment_amount = ? WHERE id = ?";
                $update_loan_stmt = $conn->prepare($update_loan_query);
                $update_loan_stmt->bind_param("di", $new_remaining, $loan_id);

                if (!$update_loan_stmt->execute()) {
                    throw new Exception("Failed to update loan balance: " . $update_loan_stmt->error);
                }

                // If balance becomes zero or negative, mark loan as closed
                if ($new_remaining <= 0) {
                    $close_loan_query = "UPDATE loans SET status = 'closed' WHERE id = ?";
                    $close_loan_stmt = $conn->prepare($close_loan_query);
                    $close_loan_stmt->bind_param("i", $loan_id);

                    if (!$close_loan_stmt->execute()) {
                        throw new Exception("Failed to close the loan: " . $close_loan_stmt->error);
                    }
                }
            } else {
                throw new Exception("No active loan found with ID {$loan_id} for Member ID {$member_id}.");
            }
        }

        // Insert receipt record with loan_id
        $sql = "INSERT INTO receipts (member_id, loan_id, receipt_type, amount, receipt_date)
                VALUES (?, ?, ?, ?, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iisd", $member_id, $loan_id, $receipt_type, $amount);

        // Execute the receipt insertion
        if (!$stmt->execute()) {
            throw new Exception("Failed to create receipt: " . $stmt->error);
        }

        // Get the receipt ID
        $receipt_id = $conn->insert_id;

        // Get current date for receipt
        $receipt_date = date('Y-m-d H:i:s');

        // Process savings transaction for deposit receipt type
        $new_balance = null;
        if ($receipt_type === 'deposit') {
            // Check if account_type is valid
            if ($account_type <= 0) {
                throw new Exception("Member does not have a valid account type assigned.");
            }

            // Get current running balance for this member and account type
            $balance_query = "SELECT running_balance
                              FROM savings_transactions
                              WHERE member_id = ? AND account_type_id = ?
                              ORDER BY id DESC LIMIT 1";
            $balance_stmt = $conn->prepare($balance_query);
            $balance_stmt->bind_param("ii", $member_id, $account_type);
            $balance_stmt->execute();
            $balance_result = $balance_stmt->get_result();

            $current_balance = 0;
            if ($balance_result->num_rows > 0) {
                $balance_data = $balance_result->fetch_assoc();
                $current_balance = $balance_data['running_balance'];
            }

            // Calculate new balance
            $new_balance = $current_balance + $amount;

            // Insert into savings_transactions
            $savings_sql = "INSERT INTO savings_transactions
                           (member_id, account_type_id, transaction_type, amount, running_balance,
                            reference, description, transaction_date, created_by)
                           VALUES (?, ?, 'DEPOSIT', ?, ?, ?, ?, NOW(), ?)";

            $reference = "Receipt #" . $receipt_id;
            $description = "Deposit payment";
            $created_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

            $savings_stmt = $conn->prepare($savings_sql);
            $savings_stmt->bind_param("iiddsis", $member_id, $account_type, $amount, $new_balance,
                                    $reference, $description, $created_by);

            if (!$savings_stmt->execute()) {
                throw new Exception("Failed to record savings transaction: " . $savings_stmt->error);
            }
        }

        // Convert amount to words for receipt display
        $amount_in_words = numberToWords($amount);

        // Commit the transaction
        $conn->commit();

        // Prepare success message with loan-specific information if applicable
        $success_message = "Receipt #$receipt_id added successfully!";
        if ($receipt_type === 'loan_repayment' && isset($new_remaining)) {
            if ($new_remaining <= 0) {
                $success_message .= " The loan has been fully repaid and is now closed.";
            } else {
                $success_message .= " Remaining loan balance: Rs." . number_format($new_remaining, 2);
            }
        } elseif ($receipt_type === 'deposit' && isset($new_balance)) {
            $success_message .= " New account balance: Rs." . number_format($new_balance, 2);
        }

        // Return JSON response with all receipt details for the modal
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'receipt_id' => $receipt_id,
            'receipt_date' => $receipt_date,
            'member_id' => $member_id,
            'member_name' => $member_name,
            'receipt_type' => $receipt_type,
            'receipt_type_text' => ucfirst(str_replace('_', ' ', $receipt_type)),
            'loan_type' => $loan_type_id,  // This is the loan_type_id from loans table
            'loan_id' => $loan_id,         // This is the loan_id (loans.id)
            'loan_type_name' => $loan_type_name,
            'amount' => $amount,
            'amount_in_words' => $amount_in_words,
            'new_balance' => $new_balance,
            'remaining_loan' => $new_remaining
        ]);

    } catch (Exception $e) {
        // Roll back the transaction on error
        $conn->rollback();
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    } finally {
        // Close all prepared statements
        if (isset($member_stmt)) $member_stmt->close();
        if (isset($stmt)) $stmt->close();
        if (isset($loan_stmt)) $loan_stmt->close();
        if (isset($update_loan_stmt)) $update_loan_stmt->close();
        if (isset($close_loan_stmt)) $close_loan_stmt->close();
        if (isset($balance_stmt)) $balance_stmt->close();
        if (isset($savings_stmt)) $savings_stmt->close();
    }
} else {
    // If not a POST request
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method. POST method required.'
    ]);
}

$conn->close();
?>