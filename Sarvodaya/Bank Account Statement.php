<?php
// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sarvodaya";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Function to get all members for search
function getAllMembers($pdo) {
    $sql = "SELECT id, name, email FROM members ORDER BY name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to search members
function searchMembers($pdo, $search_term) {
    $sql = "SELECT id, name, email FROM members 
            WHERE name LIKE :search OR email LIKE :search OR id = :id 
            ORDER BY name LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $search_param = '%' . $search_term . '%';
    $id_param = is_numeric($search_term) ? (int)$search_term : 0;
    $stmt->bindParam(':search', $search_param);
    $stmt->bindParam(':id', $id_param, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Enhanced function to get member transactions including interest calculations
function getMemberTransactions($pdo, $member_id, $start_date = null, $end_date = null) {
    $sql = "
        SELECT 
            'receipt' as source_table,
            r.id as transaction_id,
            r.receipt_date as transaction_date,
            r.receipt_type as transaction_type,
            r.amount,
            r.loan_id,
            m.name as member_name,
            'Credit' as entry_type,
            r.receipt_type as description,
            NULL as interest_rate,
            NULL as days_calculated,
            NULL as period_info
        FROM receipts r
        JOIN members m ON r.member_id = m.id
        WHERE r.member_id = :member_id";
    
    if ($start_date) {
        $sql .= " AND r.receipt_date >= :start_date";
    }
    if ($end_date) {
        $sql .= " AND r.receipt_date <= :end_date";
    }
    
    $sql .= "
        UNION ALL
        
        SELECT 
            'payment' as source_table,
            p.id as transaction_id,
            p.payment_date as transaction_date,
            p.payment_type as transaction_type,
            p.amount,
            NULL as loan_id,
            m.name as member_name,
            'Debit' as entry_type,
            CONCAT(p.payment_type, ' - ', COALESCE(p.description, '')) as description,
            NULL as interest_rate,
            NULL as days_calculated,
            NULL as period_info
        FROM payments p
        JOIN members m ON p.member_id = m.id
        WHERE p.member_id = :member_id";
    
    if ($start_date) {
        $sql .= " AND p.payment_date >= :start_date";
    }
    if ($end_date) {
        $sql .= " AND p.payment_date <= :end_date";
    }
    
    $sql .= "
        UNION ALL
        
        SELECT 
            'interest' as source_table,
            ic.id as transaction_id,
            ic.calculation_date as transaction_date,
            CASE 
                WHEN sat.account_name IS NOT NULL THEN CONCAT('Interest - ', sat.account_name)
                ELSE 'Interest Calculation'
            END as transaction_type,
            ic.interest_amount as amount,
            NULL as loan_id,
            m.name as member_name,
            'Credit' as entry_type,
            CONCAT(
                'Interest earned (', ic.interest_rate, '% for ', ic.days_calculated, ' days)',
                CASE WHEN ic.notes IS NOT NULL THEN CONCAT(' - ', ic.notes) ELSE '' END
            ) as description,
            ic.interest_rate,
            ic.days_calculated,
            CONCAT(
                DATE_FORMAT(ic.period_start_date, '%b %d, %Y'), 
                ' to ', 
                DATE_FORMAT(ic.period_end_date, '%b %d, %Y')
            ) as period_info
        FROM interest_calculations ic
        JOIN members m ON ic.member_id = m.id
        LEFT JOIN savings_account_types sat ON ic.account_type_id = sat.id
        WHERE ic.member_id = :member_id 
        AND ic.status = 'POSTED'";
    
    if ($start_date) {
        $sql .= " AND ic.calculation_date >= :start_date";
    }
    if ($end_date) {
        $sql .= " AND ic.calculation_date <= :end_date";
    }
    
    $sql .= " ORDER BY transaction_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    
    if ($start_date) {
        $stmt->bindParam(':start_date', $start_date);
    }
    if ($end_date) {
        $stmt->bindParam(':end_date', $end_date);
    }
    
    $stmt->execute();
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to calculate running balance
function calculateRunningBalance($transactions) {
    $balance = 0;
    $reversedTransactions = array_reverse($transactions);
    
    foreach ($reversedTransactions as &$transaction) {
        if ($transaction['entry_type'] == 'Credit') {
            $balance += $transaction['amount'];
        } else {
            $balance -= $transaction['amount'];
        }
        $transaction['running_balance'] = $balance;
    }
    
    return array_reverse($reversedTransactions);
}

// Function to get member info
function getMemberInfo($pdo, $member_id) {
    $sql = "SELECT * FROM members WHERE id = :member_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to get interest summary for member
function getInterestSummary($pdo, $member_id, $start_date = null, $end_date = null) {
    $sql = "
        SELECT 
            COUNT(*) as total_interest_transactions,
            SUM(interest_amount) as total_interest_earned,
            AVG(interest_rate) as avg_interest_rate,
            MIN(calculation_date) as first_interest_date,
            MAX(calculation_date) as last_interest_date
        FROM interest_calculations 
        WHERE member_id = :member_id 
        AND status = 'POSTED'";
    
    if ($start_date) {
        $sql .= " AND calculation_date >= :start_date";
    }
    if ($end_date) {
        $sql .= " AND calculation_date <= :end_date";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':member_id', $member_id, PDO::PARAM_INT);
    
    if ($start_date) {
        $stmt->bindParam(':start_date', $start_date);
    }
    if ($end_date) {
        $stmt->bindParam(':end_date', $end_date);
    }
    
    $stmt->execute();
    
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Function to generate PDF report
function generatePDFReport($member, $transactions, $total_credits, $total_debits, $net_balance, $total_interest, $interest_transactions, $start_date = null, $end_date = null) {
    require_once('fpdf/fpdf.php');
    
    class PDF extends FPDF {
        function Header() {
            $this->SetFont('Arial','B',16);
            $this->SetTextColor(255, 140, 0);
            $this->Cell(0,10,'Sarvodaya Transaction Statement',0,1,'C');
            $this->Ln(5);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->SetTextColor(128);
            $this->Cell(0,10,'Generated on ' . date('M d, Y H:i A') . ' - Page '.$this->PageNo(),0,0,'C');
        }
    }
    
    $pdf = new PDF();
    $pdf->AddPage();
    $pdf->SetFont('Arial','',10);
    
    // Member Information
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(45, 55, 72);
    $pdf->Cell(0,8,'Member Information',0,1);
    $pdf->Ln(2);
    
    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(0);
    $pdf->Cell(30,6,'Member ID:',0,0);
    $pdf->Cell(0,6,'#' . $member['id'],0,1);
    $pdf->Cell(30,6,'Name:',0,0);
    $pdf->Cell(0,6,$member['name'],0,1);
    $pdf->Cell(30,6,'Email:',0,0);
    $pdf->Cell(0,6,$member['email'],0,1);
    $pdf->Cell(30,6,'Phone:',0,0);
    $pdf->Cell(0,6,$member['phone'],0,1);
    $pdf->Ln(5);
    
    // Date Range
    if ($start_date || $end_date) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0,6,'Report Period: ',0,0);
        $pdf->SetFont('Arial','',10);
        if ($start_date && $end_date) {
            $pdf->Cell(0,6,'From ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)),0,1);
        } elseif ($start_date) {
            $pdf->Cell(0,6,'From ' . date('M d, Y', strtotime($start_date)) . ' onwards',0,1);
        } else {
            $pdf->Cell(0,6,'Up to ' . date('M d, Y', strtotime($end_date)),0,1);
        }
        $pdf->Ln(3);
    }
    
    // Summary
    $pdf->SetFont('Arial','B',12);
    $pdf->SetTextColor(45, 55, 72);
    $pdf->Cell(0,8,'Transaction Summary',0,1);
    $pdf->Ln(2);
    
    $pdf->SetFont('Arial','',10);
    $pdf->SetTextColor(0);
    $pdf->Cell(50,6,'Total Credits:',0,0);
    $pdf->SetTextColor(72, 187, 120);
    $pdf->Cell(40,6,'Rs. ' . number_format($total_credits, 2),0,0,'R');
    $pdf->SetTextColor(0);
    $pdf->Cell(50,6,'Total Debits:',0,0);
    $pdf->SetTextColor(245, 101, 101);
    $pdf->Cell(0,6,'Rs. ' . number_format($total_debits, 2),0,1,'R');
    
    $pdf->SetTextColor(0);
    $pdf->Cell(50,6,'Net Balance:',0,0);
    $pdf->SetTextColor($net_balance >= 0 ? [72, 187, 120] : [245, 101, 101]);
    $pdf->Cell(40,6,'Rs. ' . number_format($net_balance, 2),0,0,'R');
    
    if ($interest_transactions > 0) {
        $pdf->SetTextColor(0);
        $pdf->Cell(50,6,'Interest Earned:',0,0);
        $pdf->SetTextColor(159, 122, 234);
        $pdf->Cell(0,6,'Rs. ' . number_format($total_interest, 2),0,1,'R');
    } else {
        $pdf->Ln(6);
    }
    
    $pdf->SetTextColor(0);
    $pdf->Ln(5);
    
    // Transactions Table
    if (!empty($transactions)) {
        $pdf->SetFont('Arial','B',12);
        $pdf->SetTextColor(45, 55, 72);
        $pdf->Cell(0,8,'Transaction History (' . count($transactions) . ' transactions)',0,1);
        $pdf->Ln(2);
        
        // Table headers
        $pdf->SetFont('Arial','B',8);
        $pdf->SetFillColor(247, 250, 252);
        $pdf->Cell(20,8,'Date',1,0,'C',true);
        $pdf->Cell(25,8,'Type',1,0,'C',true);
        $pdf->Cell(50,8,'Description',1,0,'C',true);
        $pdf->Cell(20,8,'Loan ID',1,0,'C',true);
        $pdf->Cell(25,8,'Debit',1,0,'C',true);
        $pdf->Cell(25,8,'Credit',1,0,'C',true);
        $pdf->Cell(25,8,'Balance',1,1,'C',true);
        
        $pdf->SetFont('Arial','',7);
        $transactionsWithBalance = calculateRunningBalance($transactions);
        
        foreach ($transactionsWithBalance as $transaction) {
            // Check if we need a new page
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
                // Repeat headers
                $pdf->SetFont('Arial','B',8);
                $pdf->SetFillColor(247, 250, 252);
                $pdf->Cell(20,8,'Date',1,0,'C',true);
                $pdf->Cell(25,8,'Type',1,0,'C',true);
                $pdf->Cell(50,8,'Description',1,0,'C',true);
                $pdf->Cell(20,8,'Loan ID',1,0,'C',true);
                $pdf->Cell(25,8,'Debit',1,0,'C',true);
                $pdf->Cell(25,8,'Credit',1,0,'C',true);
                $pdf->Cell(25,8,'Balance',1,1,'C',true);
                $pdf->SetFont('Arial','',7);
            }
            
            $pdf->Cell(20,6,date('m/d/Y', strtotime($transaction['transaction_date'])),1,0,'C');
            $pdf->Cell(25,6,substr(ucfirst(str_replace('_', ' ', $transaction['transaction_type'])), 0, 12),1,0,'C');
            $pdf->Cell(50,6,substr($transaction['description'], 0, 30) . (strlen($transaction['description']) > 30 ? '...' : ''),1,0,'L');
            $pdf->Cell(20,6,$transaction['loan_id'] ? $transaction['loan_id'] : '-',1,0,'C');
            
            // Debit
            if ($transaction['entry_type'] == 'Debit') {
                $pdf->Cell(25,6,number_format($transaction['amount'], 2),1,0,'R');
            } else {
                $pdf->Cell(25,6,'-',1,0,'C');
            }
            
            // Credit
            if ($transaction['entry_type'] == 'Credit') {
                $pdf->Cell(25,6,number_format($transaction['amount'], 2),1,0,'R');
            } else {
                $pdf->Cell(25,6,'-',1,0,'C');
            }
            
            // Balance
            $pdf->Cell(25,6,number_format($transaction['running_balance'], 2),1,1,'R');
        }
    } else {
        $pdf->SetFont('Arial','I',10);
        $pdf->SetTextColor(113, 128, 150);
        $pdf->Cell(0,10,'No transactions found for the selected criteria.',0,1,'C');
    }
    
    $filename = 'transaction_statement_' . $member['id'] . '_' . date('Ymd_His') . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

// Get parameters from request
$member_id = isset($_REQUEST['member_id']) ? (int)$_REQUEST['member_id'] : null;
$start_date = isset($_REQUEST['start_date']) && !empty($_REQUEST['start_date']) ? $_REQUEST['start_date'] : null;
$end_date = isset($_REQUEST['end_date']) && !empty($_REQUEST['end_date']) ? $_REQUEST['end_date'] : null;
$search_term = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';

// Handle PDF download request
if (isset($_GET['action']) && $_GET['action'] === 'download_pdf' && $member_id) {
    $member = getMemberInfo($pdo, $member_id);
    if ($member) {
        $transactions = getMemberTransactions($pdo, $member_id, $start_date, $end_date);
        
        // Calculate totals
        $total_credits = 0;
        $total_debits = 0;
        $total_interest = 0;
        $interest_transactions = 0;
        
        foreach ($transactions as $transaction) {
            if ($transaction['entry_type'] == 'Credit') {
                $total_credits += $transaction['amount'];
                if ($transaction['source_table'] == 'interest') {
                    $interest_transactions++;
                    $total_interest += $transaction['amount'];
                }
            } else {
                $total_debits += $transaction['amount'];
            }
        }
        
        $net_balance = $total_credits - $total_debits;
        
        generatePDFReport($member, $transactions, $total_credits, $total_debits, $net_balance, $total_interest, $interest_transactions, $start_date, $end_date);
    }
}

// Handle AJAX search request
if (isset($_GET['action']) && $_GET['action'] === 'search_members') {
    header('Content-Type: application/json');
    $members = searchMembers($pdo, $search_term);
    echo json_encode($members);
    exit;
}

// Show member selector form first
if (!$member_id) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Transaction Statement - Sarvodaya</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, rgb(255, 140, 0) 0%, rgb(255, 165, 0) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .search-container {
                background: white;
                border-radius: 16px;
                padding: 40px;
                width: 100%;
                max-width: 500px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                text-align: center;
            }

            .logo {
                width: 70px;
                height: 70px;
                background: linear-gradient(135deg, rgb(255, 140, 0), rgb(255, 165, 0));
                border-radius: 16px;
                margin: 0 auto 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.8rem;
            }

            h1 {
                color: #1a202c;
                font-size: 2rem;
                font-weight: 600;
                margin-bottom: 8px;
            }

            .subtitle {
                color: #718096;
                margin-bottom: 32px;
                font-size: 1rem;
            }

            .search-section {
                position: relative;
                margin-bottom: 24px;
            }

            .search-input {
                width: 100%;
                padding: 16px 20px 16px 50px;
                font-size: 1rem;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                background: #f7fafc;
                transition: all 0.2s ease;
                outline: none;
            }

            .search-input:focus {
                border-color: rgb(255, 140, 0);
                background: white;
                box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
            }

            .search-icon {
                position: absolute;
                left: 16px;
                top: 50%;
                transform: translateY(-50%);
                color: #a0aec0;
                font-size: 1.1rem;
            }

            .search-results {
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                border: 2px solid #e2e8f0;
                border-top: none;
                border-radius: 0 0 12px 12px;
                max-height: 250px;
                overflow-y: auto;
                z-index: 1000;
                display: none;
            }

            .search-result-item {
                padding: 12px 16px;
                cursor: pointer;
                transition: background 0.2s ease;
                border-bottom: 1px solid #f7fafc;
                text-align: left;
            }

            .search-result-item:hover {
                background: #f7fafc;
            }

            .search-result-item:last-child {
                border-bottom: none;
            }

            .member-name {
                font-weight: 600;
                color: #2d3748;
                margin-bottom: 4px;
            }

            .member-details {
                font-size: 0.85rem;
                color: #718096;
            }

            .btn {
                background: linear-gradient(135deg, rgb(255, 140, 0), rgb(255, 165, 0));
                color: white;
                padding: 14px 28px;
                font-size: 1rem;
                font-weight: 600;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-width: 140px;
                justify-content: center;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(255, 140, 0, 0.3);
            }

            .btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
                transform: none;
            }

            .loading {
                display: none;
                color: #718096;
                font-size: 0.9rem;
                margin-top: 12px;
            }

            .no-results {
                padding: 20px;
                text-align: center;
                color: #718096;
                font-style: italic;
            }

            @media (max-width: 480px) {
                .search-container {
                    padding: 30px 25px;
                }
                
                h1 {
                    font-size: 1.8rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="search-container">
            <div class="logo">
                <i class="fas fa-chart-line"></i>
            </div>
            <h1>Transaction Statement</h1>
            <p class="subtitle">Search and select a member to view their transaction history including interest calculations</p>
            
            <div class="search-section">
                <i class="fas fa-search search-icon"></i>
                <input type="text" 
                       id="memberSearch" 
                       class="search-input" 
                       placeholder="Search by name, email, or member ID..." 
                       autocomplete="off"
                       autofocus>
                <div id="searchResults" class="search-results"></div>
            </div>
            
            <div class="loading" id="loading">
                <i class="fas fa-spinner fa-spin"></i> Searching...
            </div>
            
            <form method="POST" id="memberForm" style="display: none;">
                <input type="hidden" name="member_id" id="selectedMemberId">
                <button type="submit" class="btn" id="viewTransactionsBtn">
                    <i class="fas fa-eye"></i>
                    View Transactions
                </button>
            </form>
        </div>

        <script>
            let searchTimeout;
            let selectedMember = null;
            
            const searchInput = document.getElementById('memberSearch');
            const searchResults = document.getElementById('searchResults');
            const loading = document.getElementById('loading');
            const memberForm = document.getElementById('memberForm');
            const selectedMemberInput = document.getElementById('selectedMemberId');
            const viewBtn = document.getElementById('viewTransactionsBtn');

            searchInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(searchTimeout);
                searchResults.style.display = 'none';
                
                if (query.length < 2) {
                    memberForm.style.display = 'none';
                    return;
                }
                
                loading.style.display = 'block';
                
                searchTimeout = setTimeout(() => {
                    searchMembers(query);
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const firstResult = searchResults.querySelector('.search-result-item');
                    if (firstResult) {
                        firstResult.click();
                    }
                }
            });

            function searchMembers(query) {
                fetch(`?action=search_members&search=${encodeURIComponent(query)}`)
                    .then(response => response.json())
                    .then(members => {
                        loading.style.display = 'none';
                        displaySearchResults(members);
                    })
                    .catch(error => {
                        console.error('Search error:', error);
                        loading.style.display = 'none';
                        searchResults.innerHTML = '<div class="no-results">Search error occurred</div>';
                        searchResults.style.display = 'block';
                    });
            }

            function displaySearchResults(members) {
                if (members.length === 0) {
                    searchResults.innerHTML = '<div class="no-results">No members found</div>';
                } else {
                    searchResults.innerHTML = members.map(member => `
                        <div class="search-result-item" onclick="selectMember(${member.id}, '${member.name.replace(/'/g, "\\'")}')">
                            <div class="member-name">${escapeHtml(member.name)}</div>
                            <div class="member-details">ID: ${member.id} • ${escapeHtml(member.email)}</div>
                        </div>
                    `).join('');
                }
                searchResults.style.display = 'block';
            }

            function selectMember(id, name) {
                selectedMember = { id, name };
                searchInput.value = name;
                searchResults.style.display = 'none';
                selectedMemberInput.value = id;
                memberForm.style.display = 'block';
                viewBtn.focus();
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Hide results when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.search-section')) {
                    searchResults.style.display = 'none';
                }
            });

            // Form submission handling
            memberForm.addEventListener('submit', function() {
                viewBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                viewBtn.disabled = true;
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// Get member info
$member = getMemberInfo($pdo, $member_id);

if (!$member) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Member Not Found - Sarvodaya</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, rgb(255, 69, 0) 0%, rgb(255, 140, 0) 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }

            .error-container {
                background: white;
                border-radius: 16px;
                padding: 40px;
                width: 100%;
                max-width: 400px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
                text-align: center;
            }

            .error-icon {
                width: 70px;
                height: 70px;
                background: linear-gradient(135deg, rgb(255, 69, 0), rgb(255, 140, 0));
                border-radius: 16px;
                margin: 0 auto 24px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-size: 1.8rem;
            }

            h3 {
                color: #1a202c;
                font-size: 1.5rem;
                font-weight: 600;
                margin-bottom: 12px;
            }

            .error-message {
                color: #718096;
                margin-bottom: 32px;
                font-size: 1rem;
                line-height: 1.5;
            }

            .btn {
                background: linear-gradient(135deg, rgb(255, 140, 0), rgb(255, 165, 0));
                color: white;
                padding: 12px 24px;
                font-size: 0.95rem;
                font-weight: 600;
                border: none;
                border-radius: 10px;
                cursor: pointer;
                transition: all 0.2s ease;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 8px;
            }

            .btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 25px rgba(255, 140, 0, 0.3);
            }
        </style>
    </head>
    <body>
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3>Member Not Found</h3>
            <p class="error-message">Member ID <?php echo htmlspecialchars($member_id); ?> does not exist in our records.</p>
            <button class="btn" onclick="window.location.href = window.location.pathname;">
                <i class="fas fa-arrow-left"></i>
                Try Again
            </button>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Get transactions with date filter and interest summary
$transactions = getMemberTransactions($pdo, $member_id, $start_date, $end_date);
$transactionsWithBalance = calculateRunningBalance($transactions);
$interestSummary = getInterestSummary($pdo, $member_id, $start_date, $end_date);

// Calculate totals
$total_credits = 0;
$total_debits = 0;
$interest_transactions = 0;
$total_interest = 0;

foreach ($transactions as $transaction) {
    if ($transaction['entry_type'] == 'Credit') {
        $total_credits += $transaction['amount'];
        if ($transaction['source_table'] == 'interest') {
            $interest_transactions++;
            $total_interest += $transaction['amount'];
        }
    } else {
        $total_debits += $transaction['amount'];
    }
}

$net_balance = $total_credits - $total_debits;

// Build the current URL for PDF download
$pdf_url_params = ['action' => 'download_pdf', 'member_id' => $member_id];
if ($start_date) $pdf_url_params['start_date'] = $start_date;
if ($end_date) $pdf_url_params['end_date'] = $end_date;
$pdf_download_url = '?' . http_build_query($pdf_url_params);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Statement - <?php echo htmlspecialchars($member['name']); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, rgb(255, 140, 0) 0%, rgb(255, 165, 0) 100%);
            color: white;
            padding: 1.5rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(255, 140, 0, 0.3);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }

        .header h1 {
            font-size: 1.8rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .header-icon {
            background: rgba(255, 255, 255, 0.2);
            padding: 12px;
            border-radius: 12px;
            font-size: 1.3rem;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        .controls {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border-left: 4px solid rgb(255, 140, 0);
        }

        .filter-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
            font-size: 0.875rem;
        }

        .form-group input {
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.95rem;
            transition: border-color 0.2s ease;
            background: #f7fafc;
        }

        .form-group input:focus {
            outline: none;
            border-color: rgb(255, 140, 0);
            background: white;
        }

        .btn-group {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgb(255, 140, 0), rgb(255, 165, 0));
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .btn-primary:hover {
            box-shadow: 0 4px 12px rgba(255, 140, 0, 0.4);
        }

        .btn-success:hover {
            box-shadow: 0 4px 12px rgba(72, 187, 120, 0.4);
        }

        .quick-filters {
            display: flex;
            gap: 8px;
            margin-top: 16px;
            flex-wrap: wrap;
        }

        .quick-filter {
            padding: 6px 12px;
            background: rgba(255, 140, 0, 0.1);
            border: 1px solid rgba(255, 140, 0, 0.3);
            border-radius: 20px;
            color: rgb(255, 140, 0);
            text-decoration: none;
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .quick-filter:hover {
            background: rgba(255, 140, 0, 0.2);
        }

        .member-card {
            background: white;
            padding: 24px;
            border-radius: 12px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border-left: 4px solid rgb(255, 140, 0);
        }

        .member-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .info-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, rgb(255, 140, 0), rgb(255, 165, 0));
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .info-text {
            flex: 1;
        }

        .info-label {
            font-size: 0.8rem;
            color: #718096;
            font-weight: 500;
        }

        .info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            text-align: center;
            border-top: 4px solid;
        }

        .summary-card.credits {
            border-top-color: #48bb78;
        }

        .summary-card.debits {
            border-top-color: #f56565;
        }

        .summary-card.balance {
            border-top-color: rgb(255, 140, 0);
        }

        .summary-card.interest {
            border-top-color: #9f7aea;
        }

        .summary-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin: 0 auto 12px;
        }

        .summary-card.credits .summary-icon {
            background: #48bb78;
        }

        .summary-card.debits .summary-icon {
            background: #f56565;
        }

        .summary-card.balance .summary-icon {
            background: rgb(255, 140, 0);
        }

        .summary-card.interest .summary-icon {
            background: #9f7aea;
        }

        .summary-label {
            font-size: 0.85rem;
            color: #718096;
            margin-bottom: 4px;
            font-weight: 500;
        }

        .summary-value {
            font-size: 1.6rem;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .summary-card.credits .summary-value {
            color: #48bb78;
        }

        .summary-card.debits .summary-value {
            color: #f56565;
        }

        .summary-card.balance .summary-value {
            color: rgb(255, 140, 0);
        }

        .summary-card.interest .summary-value {
            color: #9f7aea;
        }

        .transactions-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            overflow: hidden;
        }

        .transactions-header {
            background: #2d3748;
            color: white;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #4a5568;
            border-bottom: 2px solid #e2e8f0;
            font-size: 0.85rem;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }

        tr:hover {
            background: #f7fafc;
        }

        .amount {
            text-align: right;
            font-family: monospace;
            font-weight: 600;
        }

        .credit {
            color: #48bb78;
        }

        .debit {
            color: #f56565;
        }

        .interest {
            color: #9f7aea;
        }

        .balance-positive {
            color: #48bb78;
            font-weight: 700;
        }

        .balance-negative {
            color: #f56565;
            font-weight: 700;
        }

        .transaction-type {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .transaction-type.receipt {
            background: rgba(72, 187, 120, 0.1);
            border: 1px solid rgba(72, 187, 120, 0.3);
            color: #48bb78;
        }

        .transaction-type.payment {
            background: rgba(245, 101, 101, 0.1);
            border: 1px solid rgba(245, 101, 101, 0.3);
            color: #f56565;
        }

        .transaction-type.interest {
            background: rgba(159, 122, 234, 0.1);
            border: 1px solid rgba(159, 122, 234, 0.3);
            color: #9f7aea;
        }

        .loan-badge {
            background: rgb(255, 140, 0);
            color: white;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .interest-details {
            font-size: 0.75rem;
            color: #718096;
            margin-top: 2px;
        }

        .interest-rate {
            font-weight: 600;
            color: #9f7aea;
        }

        .period-info {
            font-size: 0.75rem;
            color: #a0aec0;
            font-style: italic;
        }

        .no-data {
            text-align: center;
            padding: 40px 20px;
            color: #718096;
        }

        .no-data-icon {
            width: 60px;
            height: 60px;
            background: #edf2f7;
            border-radius: 16px;
            margin: 0 auto 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: #a0aec0;
        }

        .date-range-info {
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1), rgba(255, 165, 0, 0.1));
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid rgba(255, 140, 0, 0.3);
            text-align: center;
            font-size: 0.9rem;
        }

        .date-range-info strong {
            color: rgb(255, 140, 0);
        }

        .interest-summary {
            background: linear-gradient(135deg, rgba(159, 122, 234, 0.1), rgba(159, 122, 234, 0.05));
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 16px;
            border: 1px solid rgba(159, 122, 234, 0.3);
        }

        .interest-summary h4 {
            color: #9f7aea;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .interest-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            font-size: 0.85rem;
        }

        .interest-stat {
            text-align: center;
        }

        .interest-stat-value {
            font-weight: 700;
            color: #9f7aea;
            font-size: 1.1rem;
        }

        .interest-stat-label {
            color: #718096;
            font-size: 0.75rem;
        }

        .download-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #48bb78;
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(72, 187, 120, 0.3);
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.5rem;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .btn-group {
                justify-content: center;
            }
            
            .summary-cards {
                grid-template-columns: 1fr 1fr;
            }
            
            .member-info {
                grid-template-columns: 1fr;
            }
            
            .table-wrapper {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 8px 6px;
            }

            .quick-filters {
                justify-content: center;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .interest-summary-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            .header, .controls, .quick-filters, .date-range-info {
                display: none !important;
            }
            
            .container {
                max-width: none !important;
                padding: 0 !important;
            }
            
            .transactions-card, .member-card, .summary-cards, .interest-summary {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
            
            body {
                background: white !important;
            }
        }

        .fade-in {
            animation: fadeIn 0.5s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div id="downloadNotification" class="download-notification">
        <i class="fas fa-check-circle"></i>
        <span>PDF report is being prepared for download...</span>
    </div>

    <div class="header">
        <div class="header-content">
            <h1>
                <div class="header-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                Transaction Statement
            </h1>
            <div class="header-actions">
                <button class="btn btn-secondary" onclick="window.location.href = window.location.pathname;">
                    <i class="fas fa-search"></i>
                    New Search
                </button>
                <a href="<?php echo htmlspecialchars($pdf_download_url); ?>" class="btn btn-success" onclick="showDownloadNotification()">
                    <i class="fas fa-download"></i>
                    Download PDF
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="controls fade-in">
            <form method="POST" class="filter-form">
                <input type="hidden" name="member_id" value="<?php echo $member_id; ?>">
                
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $start_date; ?>" id="start_date">
                </div>
                
                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" name="end_date" value="<?php echo $end_date; ?>" id="end_date">
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter"></i>
                        Apply Filter
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="clearDateFilter()">
                        <i class="fas fa-times"></i>
                        Clear Dates
                    </button>
                </div>
            </form>

            <div class="quick-filters">
                <a href="?member_id=<?php echo $member_id; ?>&start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="quick-filter">
                    Today
                </a>
                <a href="?member_id=<?php echo $member_id; ?>&start_date=<?php echo date('Y-m-d', strtotime('-7 days')); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="quick-filter">
                    Last 7 Days
                </a>
                <a href="?member_id=<?php echo $member_id; ?>&start_date=<?php echo date('Y-m-01'); ?>&end_date=<?php echo date('Y-m-t'); ?>" class="quick-filter">
                    This Month
                </a>
                <a href="?member_id=<?php echo $member_id; ?>&start_date=<?php echo date('Y-01-01'); ?>&end_date=<?php echo date('Y-12-31'); ?>" class="quick-filter">
                    This Year
                </a>
            </div>
        </div>

        <?php if ($start_date || $end_date): ?>
            <div class="date-range-info fade-in">
                <i class="fas fa-filter" style="margin-right: 6px;"></i>
                <strong>Filtered Results:</strong> 
                <?php if ($start_date && $end_date): ?>
                    From <?php echo date('M d, Y', strtotime($start_date)); ?> to <?php echo date('M d, Y', strtotime($end_date)); ?>
                <?php elseif ($start_date): ?>
                    From <?php echo date('M d, Y', strtotime($start_date)); ?> onwards
                <?php else: ?>
                    Up to <?php echo date('M d, Y', strtotime($end_date)); ?>
                <?php endif; ?>
                (<?php echo count($transactions); ?> transactions)
            </div>
        <?php endif; ?>

        <div class="member-card fade-in">
            <div class="member-info">
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Member ID</div>
                        <div class="info-value">#<?php echo $member['id']; ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Full Name</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['name']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Email Address</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['email']); ?></div>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div class="info-text">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value"><?php echo htmlspecialchars($member['phone']); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($interest_transactions > 0): ?>
            <div class="interest-summary fade-in">
                <h4><i class="fas fa-percentage"></i> Interest Summary</h4>
                <div class="interest-summary-grid">
                    <div class="interest-stat">
                        <div class="interest-stat-value">Rs. <?php echo number_format($total_interest, 2); ?></div>
                        <div class="interest-stat-label">Total Interest Earned</div>
                    </div>
                    <div class="interest-stat">
                        <div class="interest-stat-value"><?php echo $interest_transactions; ?></div>
                        <div class="interest-stat-label">Interest Transactions</div>
                    </div>
                    <?php if ($interestSummary['avg_interest_rate']): ?>
                    <div class="interest-stat">
                        <div class="interest-stat-value"><?php echo number_format($interestSummary['avg_interest_rate'], 2); ?>%</div>
                        <div class="interest-stat-label">Average Rate</div>
                    </div>
                    <?php endif; ?>
                    <?php if ($interestSummary['last_interest_date']): ?>
                    <div class="interest-stat">
                        <div class="interest-stat-value"><?php echo date('M d, Y', strtotime($interestSummary['last_interest_date'])); ?></div>
                        <div class="interest-stat-label">Last Interest</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($transactions)): ?>
            <div class="transactions-card fade-in">
                <div class="no-data">
                    <div class="no-data-icon">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h3 style="margin-bottom: 8px; color: #4a5568; font-weight: 600;">No Transactions Found</h3>
                    <p>
                        <?php if ($start_date || $end_date): ?>
                            No transactions found for the selected date range.
                        <?php else: ?>
                            This member has no transaction history yet.
                        <?php endif; ?>
                    </p>
                    <?php if ($start_date || $end_date): ?>
                        <div style="margin-top: 16px;">
                            <a href="?member_id=<?php echo $member_id; ?>" class="btn btn-outline">
                                <i class="fas fa-eye"></i>
                                View All Transactions
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="summary-cards fade-in">
                <div class="summary-card credits">
                    <div class="summary-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="summary-label">Total Credits</div>
                    <div class="summary-value">Rs. <?php echo number_format($total_credits, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #718096;">
                        <?php echo count(array_filter($transactions, function($t) { return $t['entry_type'] == 'Credit'; })); ?> transactions
                    </div>
                </div>
                <div class="summary-card debits">
                    <div class="summary-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="summary-label">Total Debits</div>
                    <div class="summary-value">Rs. <?php echo number_format($total_debits, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #718096;">
                        <?php echo count(array_filter($transactions, function($t) { return $t['entry_type'] == 'Debit'; })); ?> transactions
                    </div>
                </div>
                <div class="summary-card balance">
                    <div class="summary-icon">
                        <i class="fas fa-balance-scale"></i>
                    </div>
                    <div class="summary-label">Net Balance</div>
                    <div class="summary-value">
                        Rs. <?php echo number_format($net_balance, 2); ?>
                    </div>
                    <div style="font-size: 0.75rem; color: #718096;">
                        <?php if ($start_date || $end_date): ?>For selected period<?php else: ?>All time<?php endif; ?>
                    </div>
                </div>
                <?php if ($interest_transactions > 0): ?>
                <div class="summary-card interest">
                    <div class="summary-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="summary-label">Interest Earned</div>
                    <div class="summary-value">Rs. <?php echo number_format($total_interest, 2); ?></div>
                    <div style="font-size: 0.75rem; color: #718096;">
                        <?php echo $interest_transactions; ?> interest transactions
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="transactions-card fade-in">
                <div class="transactions-header">
                    <i class="fas fa-list"></i>
                    <h3>Transaction History</h3>
                    <span style="margin-left: auto; background: rgba(255,255,255,0.2); padding: 4px 8px; border-radius: 12px; font-size: 0.8rem;">
                        <?php echo count($transactions); ?> transactions
                    </span>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Loan ID</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactionsWithBalance as $transaction): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo date('M d, Y', strtotime($transaction['transaction_date'])); ?></div>
                                            <div style="font-size: 0.75rem; color: #718096;"><?php echo date('H:i A', strtotime($transaction['transaction_date'])); ?></div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="transaction-type <?php echo $transaction['source_table']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $transaction['transaction_type'])); ?>
                                        </span>
                                        <?php if ($transaction['source_table'] == 'interest' && $transaction['interest_rate']): ?>
                                            <div class="interest-details">
                                                <span class="interest-rate"><?php echo $transaction['interest_rate']; ?>%</span> 
                                                for <?php echo $transaction['days_calculated']; ?> days
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 200px; word-wrap: break-word;">
                                        <?php echo htmlspecialchars($transaction['description']); ?>
                                        <?php if ($transaction['period_info']): ?>
                                            <div class="period-info"><?php echo htmlspecialchars($transaction['period_info']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($transaction['loan_id']): ?>
                                            <span class="loan-badge">
                                                <?php echo $transaction['loan_id']; ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #a0aec0;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="amount">
                                        <?php if ($transaction['entry_type'] == 'Debit'): ?>
                                            <span class="debit">
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #a0aec0;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="amount">
                                        <?php if ($transaction['entry_type'] == 'Credit'): ?>
                                            <span class="<?php echo $transaction['source_table'] == 'interest' ? 'interest' : 'credit'; ?>">
                                                <?php echo number_format($transaction['amount'], 2); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #a0aec0;">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="amount <?php echo $transaction['running_balance'] >= 0 ? 'balance-positive' : 'balance-negative'; ?>">
                                        <?php echo number_format($transaction['running_balance'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
        
        <div style="margin-top: 32px; text-align: center; padding: 16px; color: #718096; font-size: 0.85rem;">
            <i class="fas fa-shield-alt" style="margin-right: 6px; color: rgb(255, 140, 0);"></i>
            Sarvodaya Transaction Management System
            <?php if ($start_date || $end_date): ?>
                <div style="margin-top: 6px; font-size: 0.75rem;">
                    Report generated on <?php echo date('M d, Y H:i A'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function clearDateFilter() {
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            const form = document.querySelector('.filter-form');
            form.submit();
        }

        function showDownloadNotification() {
            const notification = document.getElementById('downloadNotification');
            notification.style.display = 'flex';
            
            setTimeout(() => {
                notification.style.display = 'none';
            }, 3000);
        }

        // Date validation
        document.addEventListener('DOMContentLoaded', function() {
            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            
            if (startDateInput && endDateInput) {
                startDateInput.addEventListener('change', function() {
                    if (endDateInput.value && this.value > endDateInput.value) {
                        endDateInput.value = this.value;
                    }
                });
                
                endDateInput.addEventListener('change', function() {
                    if (startDateInput.value && this.value < startDateInput.value) {
                        startDateInput.value = this.value;
                    }
                });
            }

            // Add fade-in animation to table rows
            const rows = document.querySelectorAll('tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.03}s`;
                row.classList.add('fade-in');
            });
        });
    </script>
</body>
</html>