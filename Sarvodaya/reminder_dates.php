<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'sarvodaya';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS selected_dates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_number INT NOT NULL,
    date_value DATE NOT NULL,
    UNIQUE KEY (date_number)
)";
$conn->query($create_table);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        // Handle delete action
        $delete_id = $_POST['delete'];
        $stmt = $conn->prepare("DELETE FROM selected_dates WHERE date_number = ?");
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $stmt->close();
        $success_message = "Date deleted successfully!";
    } else {
        // Handle save/update action
        $stmt = $conn->prepare("INSERT INTO selected_dates (date_number, date_value) 
                               VALUES (?, ?) 
                               ON DUPLICATE KEY UPDATE date_value = VALUES(date_value)");
        
        $stmt->bind_param("is", $date_number, $date_value);
        
        // Process each date
        foreach ($_POST['dates'] as $num => $date) {
            if (!empty($date)) {
                $date_number = $num;
                $date_value = $date;
                $stmt->execute();
            }
        }
        
        $stmt->close();
        $success_message = "Dates saved successfully!";
    }
}

// Fetch saved dates
$saved_dates = array();
$result = $conn->query("SELECT date_number, date_value FROM selected_dates ORDER BY date_number");
while ($row = $result->fetch_assoc()) {
    $saved_dates[$row['date_number']] = $row['date_value'];
}
$result->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Selection App - Sarvodaya Shramadhana Society</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, rgba(255, 140, 0, 0.1) 0%, rgba(255, 140, 0, 0.05) 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 20%, rgba(255, 140, 0, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(255, 140, 0, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 40% 60%, rgba(255, 140, 0, 0.06) 0%, transparent 50%);
            pointer-events: none;
            z-index: -1;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding: 30px;
            background: linear-gradient(135deg, rgb(255, 140, 0), rgba(255, 140, 0, 0.8));
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(255, 140, 0, 0.2);
            color: white;
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .header .organization-name {
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 8px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.2);
            position: relative;
            z-index: 1;
        }

        .header .organization-details {
            font-size: 1rem;
            opacity: 0.95;
            margin-bottom: 5px;
            position: relative;
            z-index: 1;
            line-height: 1.4;
        }

        .header .contact-info {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 3px;
            position: relative;
            z-index: 1;
        }

        .header .reg-info {
            font-size: 0.85rem;
            opacity: 0.85;
            font-style: italic;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
            margin-top: 15px;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        .date-selection-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(255, 140, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .date-selection-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, rgb(255, 140, 0), rgba(255, 140, 0, 0.6));
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: rgb(255, 140, 0);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .date-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .date-item {
            position: relative;
        }

        .date-item label {
            display: block;
            font-weight: 500;
            color: #333;
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .date-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
            background: white;
            color: #333;
        }

        .date-input:focus {
            outline: none;
            border-color: rgb(255, 140, 0);
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.1);
            transform: translateY(-2px);
        }

        .date-input:valid {
            border-color: #4CAF50;
        }

        .save-btn {
            background: linear-gradient(135deg, rgb(255, 140, 0), rgba(255, 140, 0, 0.8));
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 auto;
            box-shadow: 0 10px 25px rgba(255, 140, 0, 0.3);
        }

        .save-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(255, 140, 0, 0.4);
        }

        .save-btn:active {
            transform: translateY(-1px);
        }

        .saved-dates-section {
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border: 2px solid rgba(255, 140, 0, 0.1);
            position: relative;
            overflow: hidden;
        }

        .saved-dates-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, rgb(255, 140, 0), rgba(255, 140, 0, 0.6));
        }

        .date-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            max-height: 400px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .date-cards::-webkit-scrollbar {
            width: 6px;
        }

        .date-cards::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .date-cards::-webkit-scrollbar-thumb {
            background: rgb(255, 140, 0);
            border-radius: 10px;
        }

        .date-card {
            background: linear-gradient(135deg, #fff, #fafafa);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid rgb(255, 140, 0);
            position: relative;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }

        .date-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        .date-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .date-number {
            font-weight: 600;
            color: rgb(255, 140, 0);
            font-size: 1.1rem;
        }

        .delete-btn {
            background: linear-gradient(135deg, #ff6b6b, #ee5a5a);
            color: white;
            border: none;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(255, 107, 107, 0.3);
        }

        .delete-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 8px 20px rgba(255, 107, 107, 0.4);
        }

        .date-display {
            font-size: 1rem;
            color: #555;
            font-weight: 500;
        }

        .status-message {
            padding: 15px 20px;
            margin: 20px 0;
            border-radius: 10px;
            text-align: center;
            font-weight: 500;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from { transform: translateY(-20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .success {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
            font-size: 1.1rem;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: rgba(255, 140, 0, 0.3);
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .header h1 {
                font-size: 2rem;
            }

            .header .organization-name {
                font-size: 1.5rem;
            }

            .header .organization-details {
                font-size: 0.9rem;
            }

            .header .contact-info {
                font-size: 0.8rem;
            }

            .date-grid {
                grid-template-columns: 1fr;
            }

            .date-selection-section,
            .saved-dates-section {
                padding: 20px;
            }

            .section-title {
                font-size: 1.5rem;
            }
        }

        /* Animation for date cards */
        .date-card {
            animation: fadeInUp 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Staggered animation for multiple cards */
        .date-card:nth-child(1) { animation-delay: 0.1s; }
        .date-card:nth-child(2) { animation-delay: 0.2s; }
        .date-card:nth-child(3) { animation-delay: 0.3s; }
        .date-card:nth-child(4) { animation-delay: 0.4s; }
        .date-card:nth-child(5) { animation-delay: 0.5s; }
        .date-card:nth-child(6) { animation-delay: 0.6s; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-calendar-alt"></i> SARVODAYA SHRAMADHANA SOCIETY</h1>
            <div class="organization-name">Samaghi Sarvodaya Shramadhana Society</div>
            <div class="organization-details">Kubaloluwa, Veyangoda</div>
            <div class="contact-info">
                <i class="fas fa-phone"></i> 077 690 6605 | 
                <i class="fas fa-envelope"></i> info@sarvodayabank.com
            </div>
            <div class="reg-info">Reg. No: 12345/SS/2020</div>
            <p style="font-size: 30px;">Select and manage important dates for Interest reminders</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="status-message success">
                <i class="fas fa-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="main-content">
            <div class="date-selection-section">
                <h2 class="section-title">
                    <i class="fas fa-calendar-plus"></i> Select Dates
                </h2>
                
                <form method="POST" action="">
                    <div class="date-grid">
                        <?php for ($i = 1; $i <= 12; $i++): ?>
                            <div class="date-item">
                                <label for="date<?php echo $i; ?>">
                                    <i class="fas fa-calendar-day"></i> Date <?php echo $i; ?>
                                </label>
                                <input type="date" 
                                       id="date<?php echo $i; ?>" 
                                       name="dates[<?php echo $i; ?>]" 
                                       class="date-input"
                                       value="<?php echo isset($saved_dates[$i]) ? $saved_dates[$i] : ''; ?>">
                            </div>
                        <?php endfor; ?>
                    </div>
                    
                    <button type="submit" class="save-btn">
                        <i class="fas fa-save"></i> Save All Dates
                    </button>
                </form>
            </div>

            <div class="saved-dates-section">
                <h2 class="section-title">
                    <i class="fas fa-bookmark"></i> Saved Dates
                </h2>
                
                <div class="date-cards">
                    <?php if (empty($saved_dates)): ?>
                        <div class="empty-state">
                            <i class="fas fa-calendar-times"></i>
                            <p>No dates saved yet</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($saved_dates as $num => $date): ?>
                            <div class="date-card">
                                <div class="date-card-header">
                                    <span class="date-number">Date <?php echo $num; ?></span>
                                    <form method="POST" style="display: inline;">
                                        <button type="submit" 
                                                name="delete" 
                                                value="<?php echo $num; ?>" 
                                                class="delete-btn"
                                                onclick="return confirm('Are you sure you want to delete this date?')"
                                                title="Delete Date">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                                <div class="date-display">
                                    <i class="fas fa-calendar"></i>
                                    <?php echo date('F j, Y', strtotime($date)); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced form validation with better UX
        document.querySelector('form').addEventListener('submit', function(e) {
            // Only validate the main form (not delete forms)
            if (!e.target.querySelector('[name="delete"]')) {
                let emptyCount = 0;
                const inputs = document.querySelectorAll('input[type="date"]');
                
                inputs.forEach(input => {
                    if (!input.value) {
                        input.style.borderColor = '#ff6b6b';
                        input.style.boxShadow = '0 0 0 3px rgba(255, 107, 107, 0.2)';
                        emptyCount++;
                    } else {
                        input.style.borderColor = '#4CAF50';
                        input.style.boxShadow = '0 0 0 3px rgba(76, 175, 80, 0.2)';
                    }
                });
                
                if (emptyCount > 0) {
                    e.preventDefault();
                    
                    // Create and show custom alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'status-message error';
                    alertDiv.innerHTML = `
                        <i class="fas fa-exclamation-triangle"></i> 
                        Please fill in all ${emptyCount} remaining date field${emptyCount > 1 ? 's' : ''}
                    `;
                    
                    const container = document.querySelector('.container');
                    container.insertBefore(alertDiv, container.firstChild);
                    
                    // Remove alert after 5 seconds
                    setTimeout(() => {
                        alertDiv.remove();
                    }, 5000);
                    
                    // Scroll to first empty input
                    const firstEmpty = document.querySelector('input[type="date"]:invalid, input[type="date"][value=""]');
                    if (firstEmpty) {
                        firstEmpty.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstEmpty.focus();
                    }
                }
            }
        });

        // Reset border color on input change
        document.querySelectorAll('input[type="date"]').forEach(input => {
            input.addEventListener('change', function() {
                if (this.value) {
                    this.style.borderColor = '#4CAF50';
                    this.style.boxShadow = '0 0 0 3px rgba(76, 175, 80, 0.2)';
                } else {
                    this.style.borderColor = '#e0e0e0';
                    this.style.boxShadow = 'none';
                }
            });
        });

        // Auto-remove success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successMessage = document.querySelector('.status-message.success');
            if (successMessage) {
                setTimeout(() => {
                    successMessage.style.animation = 'slideOut 0.5s ease';
                    setTimeout(() => {
                        successMessage.remove();
                    }, 500);
                }, 5000);
            }
        });

        // Add slide out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideOut {
                from { transform: translateY(0); opacity: 1; }
                to { transform: translateY(-20px); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php
$conn->close();
?>