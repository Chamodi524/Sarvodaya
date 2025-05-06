<?php
/**
 * Add a new loan to the database
 * 
 * @param mysqli $conn Database connection
 * @param string $loan_name Name of the loan
 * @param float $maximum_amount Maximum loan amount
 * @param float $interest_rate Interest rate percentage
 * @return bool True if successful, false otherwise
 */
function addLoan($conn, $loan_name, $maximum_amount, $interest_rate) {
    $stmt = $conn->prepare("INSERT INTO loans (loan_name, maximum_amount, interest_rate) VALUES (?, ?, ?)");
    $stmt->bind_param("sdd", $loan_name, $maximum_amount, $interest_rate);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Update an existing loan in the database
 * 
 * @param mysqli $conn Database connection
 * @param int $loan_id ID of the loan to update
 * @param string $loan_name New name of the loan
 * @param float $maximum_amount New maximum loan amount
 * @param float $interest_rate New interest rate percentage
 * @return bool True if successful, false otherwise
 */
function updateLoan($conn, $loan_id, $loan_name, $maximum_amount, $interest_rate) {
    $stmt = $conn->prepare("UPDATE loans SET loan_name = ?, maximum_amount = ?, interest_rate = ? WHERE id = ?");
    $stmt->bind_param("sddi", $loan_name, $maximum_amount, $interest_rate, $loan_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Delete a loan from the database
 * 
 * @param mysqli $conn Database connection
 * @param int $loan_id ID of the loan to delete
 * @return bool True if successful, false otherwise
 */
function deleteLoan($conn, $loan_id) {
    $stmt = $conn->prepare("DELETE FROM loans WHERE id = ?");
    $stmt->bind_param("i", $loan_id);
    $result = $stmt->execute();
    $stmt->close();
    return $result;
}

/**
 * Get all loans from the database
 * 
 * @param mysqli $conn Database connection
 * @return array Array of loan records
 */
function getAllLoans($conn) {
    $result = $conn->query("SELECT * FROM loans ORDER BY id DESC");
    $loans = [];
    
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $loans[] = $row;
        }
    }
    
    return $loans;
}

/**
 * Get a specific loan by ID
 * 
 * @param mysqli $conn Database connection
 * @param int $loan_id ID of the loan to retrieve
 * @return array|null Loan data or null if not found
 */
function getLoanById($conn, $loan_id) {
    $stmt = $conn->prepare("SELECT * FROM loans WHERE id = ?");
    $stmt->bind_param("i", $loan_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $loan = $result->fetch_assoc();
        $stmt->close();
        return $loan;
    } else {
        $stmt->close();
        return null;
    }
}
?>