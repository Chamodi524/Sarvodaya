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
    <title>Date Selection App</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            text-align: center;
            color: #2c3e50;
        }
        
        .date-selection {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .date-item {
            flex: 1 1 200px;
            min-width: 200px;
        }
        
        input[type="date"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }
        
        button {
            background-color: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            display: block;
            margin: 20px auto;
            transition: background-color 0.3s;
        }
        
        button:hover {
            background-color: #2980b9;
        }
        
        .saved-dates {
            margin-top: 30px;
        }
        
        .date-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 10px;
        }
        
        .date-card {
            background: #eaf2f8;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #3498db;
            position: relative;
        }
        
        .delete-btn {
            position: absolute;
            top: 5px;
            right: 5px;
            background: #e74c3c;
            color: white;
            border: none;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .delete-btn:hover {
            background: #c0392b;
        }
        
        .status-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
            text-align: center;
        }
        
        .success {
            background-color: #d5f5e3;
            color: #27ae60;
        }
        
        .error {
            background-color: #fadbd8;
            color: #e74c3c;
        }
        
        .date-info {
            margin-right: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Select Dates for reminders</h1>
        
        <?php if (!empty($success_message)): ?>
            <div class="status-message success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="date-selection">
                <?php for ($i = 1; $i <= 12; $i++): ?>
                    <div class="date-item">
                        <label for="date<?php echo $i; ?>">Date <?php echo $i; ?>:</label>
                        <input type="date" 
                               id="date<?php echo $i; ?>" 
                               name="dates[<?php echo $i; ?>]" 
                               value="<?php echo isset($saved_dates[$i]) ? $saved_dates[$i] : ''; ?>">
                    </div>
                <?php endfor; ?>
            </div>
            
            <button type="submit">Save Dates</button>
        </form>
        
        <div class="saved-dates">
            <h2>Saved Dates</h2>
            <div class="date-list">
                <?php foreach ($saved_dates as $num => $date): ?>
                    <div class="date-card">
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="delete" value="<?php echo $num; ?>" class="delete-btn" 
                                    onclick="return confirm('Are you sure you want to delete this date?')">×</button>
                        </form>
                        <div class="date-info">
                            <strong>Date <?php echo $num; ?>:</strong><br>
                            <?php echo date('F j, Y', strtotime($date)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($saved_dates)): ?>
                    <div>No dates saved yet</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Client-side validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Only validate the main form (not delete forms)
            if (!e.target.querySelector('[name="delete"]')) {
                let allFilled = true;
                const inputs = document.querySelectorAll('input[type="date"]');
                
                inputs.forEach(input => {
                    if (!input.value) {
                        input.style.borderColor = 'red';
                        allFilled = false;
                    } else {
                        input.style.borderColor = '#ddd';
                    }
                });
                
                if (!allFilled) {
                    e.preventDefault();
                    alert('Please select all 12 dates');
                }
            }
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>