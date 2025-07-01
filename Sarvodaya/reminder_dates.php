<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sarvodaya - Interest Calculation Reminders</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .header {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="20" cy="20" r="2" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="30" r="1.5" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="90" cy="80" r="2.5" fill="rgba(255,255,255,0.1)"/></svg>');
            pointer-events: none;
        }

        .header h1 {
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
            font-weight: 700;
            position: relative;
            z-index: 1;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            padding: 40px 30px;
        }

        .form-section, .reminders-section {
            background: white;
            padding: 35px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(79, 172, 254, 0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .form-section:hover, .reminders-section:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.12);
        }

        .section-title {
            color: #333;
            margin-bottom: 30px;
            font-size: 2rem;
            position: relative;
            font-weight: 600;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -12px;
            left: 0;
            width: 60px;
            height: 4px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            border-radius: 2px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #555;
            font-weight: 600;
            font-size: 1rem;
        }

        .form-group select, .form-group input {
            width: 100%;
            padding: 15px 18px;
            border: 2px solid #e8ecf0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #fafbfc;
            font-family: inherit;
        }

        .form-group select:focus, .form-group input:focus {
            outline: none;
            border-color: #4facfe;
            background: white;
            box-shadow: 0 0 0 4px rgba(79, 172, 254, 0.08);
            transform: translateY(-1px);
        }

        .date-input-group {
            display: flex;
            gap: 20px;
        }

        .date-input-group > div {
            flex: 1;
        }

        .btn {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 15px 35px;
            border: none;
            border-radius: 12px;
            font-size: 1.05rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 25px rgba(79, 172, 254, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .reminder-card {
            background: linear-gradient(135deg, #f8f9ff 0%, #f0f4ff 100%);
            border: 2px solid #e8ecff;
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .reminder-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .reminder-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
            border-color: #4facfe;
        }

        .reminder-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .reminder-month {
            font-size: 1.4rem;
            font-weight: 700;
            color: #333;
        }

        .reminder-date {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 10px 18px;
            border-radius: 25px;
            font-size: 1.1rem;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(79, 172, 254, 0.3);
        }

        .reminder-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn-small {
            padding: 8px 20px;
            font-size: 0.9rem;
            border-radius: 8px;
            width: auto;
            letter-spacing: 0.5px;
        }

        .btn-delete {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b7a 100%);
        }

        .btn-delete:hover {
            box-shadow: 0 10px 20px rgba(255, 71, 87, 0.4);
        }

        .no-reminders {
            text-align: center;
            color: #999;
            font-style: italic;
            padding: 50px 30px;
            background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
            border-radius: 15px;
            border: 3px dashed #dee2e6;
        }

        .no-reminders p:first-child {
            font-size: 1.2rem;
            margin-bottom: 10px;
            color: #666;
        }

        .toast {
            position: fixed;
            top: 30px;
            right: 30px;
            background: linear-gradient(135deg, #4caf50 0%, #81c784 100%);
            color: white;
            padding: 18px 30px;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            transform: translateX(450px);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            z-index: 1000;
            font-weight: 600;
            font-size: 1rem;
        }

        .toast.show {
            transform: translateX(0);
        }

        .toast.error {
            background: linear-gradient(135deg, #ff4757 0%, #ff6b7a 100%);
        }

        .stats-bar {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            background: rgba(79, 172, 254, 0.1);
            border-radius: 15px;
            margin: 20px 30px;
            border: 1px solid rgba(79, 172, 254, 0.2);
        }

        .stat-item {
            text-align: center;
            color: #4facfe;
            font-weight: 600;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
                gap: 25px;
                padding: 25px 20px;
            }
            
            .date-input-group {
                flex-direction: column;
                gap: 15px;
            }

            .header h1 {
                font-size: 2.2rem;
            }

            .container {
                margin: 10px;
                border-radius: 15px;
            }

            .toast {
                right: 20px;
                left: 20px;
                transform: translateY(-100px);
            }

            .toast.show {
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Sarvodaya Finance</h1>
            <p>Interest Calculation Reminder System</p>
        </div>

        <div class="stats-bar">
            <div class="stat-item">
                <span class="stat-number" id="totalReminders">0</span>
                <span>Total Reminders</span>
            </div>
        </div>

        <div class="main-content">
            <div class="form-section">
                <h2 class="section-title">Add Monthly Reminder</h2>
                <form id="reminderForm">
                    <div class="date-input-group">
                        <div class="form-group">
                            <label for="reminderMonth">Month:</label>
                            <select id="reminderMonth" name="reminderMonth" required>
                                <option value="">Select Month</option>
                                <option value="January">January</option>
                                <option value="February">February</option>
                                <option value="March">March</option>
                                <option value="April">April</option>
                                <option value="May">May</option>
                                <option value="June">June</option>
                                <option value="July">July</option>
                                <option value="August">August</option>
                                <option value="September">September</option>
                                <option value="October">October</option>
                                <option value="November">November</option>
                                <option value="December">December</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="reminderDate">Date:</label>
                            <select id="reminderDate" name="reminderDate" required>
                                <option value="">Select Date</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn">Add Reminder</button>
                </form>
            </div>

            <div class="reminders-section">
                <h2 class="section-title">Scheduled Reminders</h2>
                <div id="remindersList">
                    <div class="no-reminders">
                        <p>No reminders scheduled yet.</p>
                        <p>Add your first reminder using the form on the left.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        // Generate date options (1-31)
        function populateDateOptions() {
            const dateSelect = document.getElementById('reminderDate');
            dateSelect.innerHTML = '<option value="">Select Date</option>';
            
            for (let i = 1; i <= 31; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = i;
                dateSelect.appendChild(option);
            }
        }

        // API Service to interact with PHP backend
        class ReminderService {
            static async getReminders() {
                try {
                    const response = await fetch('api.php?action=get');
                    return await response.json();
                } catch (error) {
                    console.error('Error fetching reminders:', error);
                    return [];
                }
            }

            static async addReminder(reminderData) {
                try {
                    const response = await fetch('api.php?action=add', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(reminderData)
                    });
                    return await response.json();
                } catch (error) {
                    console.error('Error adding reminder:', error);
                    return { success: false, message: 'Failed to add reminder' };
                }
            }

            static async deleteReminder(id) {
                try {
                    const response = await fetch('api.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({ id })
                    });
                    return await response.json();
                } catch (error) {
                    console.error('Error deleting reminder:', error);
                    return { success: false, message: 'Failed to delete reminder' };
                }
            }
        }

        // DOM elements
        const reminderForm = document.getElementById('reminderForm');
        const remindersList = document.getElementById('remindersList');
        const toast = document.getElementById('toast');
        const totalRemindersElement = document.getElementById('totalReminders');

        // Toast notification function
        function showToast(message, type = 'success') {
            toast.textContent = message;
            toast.className = `toast ${type} show`;
            setTimeout(() => {
                toast.classList.remove('show');
            }, 4000);
        }

        // Update stats
        function updateStats(count) {
            totalRemindersElement.textContent = count;
        }

        // Create reminder card HTML
        function createReminderCard(reminder) {
            return `
                <div class="reminder-card" data-id="${reminder.id}">
                    <div class="reminder-header">
                        <div class="reminder-month">${reminder.reminder_month}</div>
                        <div class="reminder-date">${reminder.reminder_date}</div>
                    </div>
                    <div class="reminder-actions">
                        <button class="btn btn-small btn-delete" onclick="deleteReminder(${reminder.id})">Delete</button>
                    </div>
                </div>
            `;
        }

        // Render reminders list
        async function renderReminders() {
            try {
                const reminders = await ReminderService.getReminders();
                
                if (reminders.length === 0) {
                    remindersList.innerHTML = `
                        <div class="no-reminders">
                            <p>No reminders scheduled yet.</p>
                            <p>Add your first reminder using the form on the left.</p>
                        </div>
                    `;
                } else {
                    remindersList.innerHTML = reminders.map(createReminderCard).join('');
                }
                
                updateStats(reminders.length);
            } catch (error) {
                console.error('Error rendering reminders:', error);
                showToast('Error loading reminders', 'error');
            }
        }

        // Handle form submission
        reminderForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const reminderData = {
                reminder_month: formData.get('reminderMonth'),
                reminder_date: parseInt(formData.get('reminderDate'))
            };

            if (!reminderData.reminder_month || !reminderData.reminder_date) {
                showToast('Please select both month and date', 'error');
                return;
            }

            const result = await ReminderService.addReminder(reminderData);
            
            if (result.success) {
                showToast(result.message);
                this.reset();
                renderReminders();
            } else {
                showToast(result.message, 'error');
            }
        });

        // Delete reminder function
        async function deleteReminder(id) {
            if (confirm('Are you sure you want to delete this reminder?')) {
                const result = await ReminderService.deleteReminder(id);
                if (result.success) {
                    showToast(result.message);
                    renderReminders();
                } else {
                    showToast(result.message, 'error');
                }
            }
        }

        // Initialize
        populateDateOptions();
        renderReminders();
    </script>

<?php
// PHP Backend API (embedded in the same file for this example)
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Database configuration - UPDATE THESE WITH YOUR CREDENTIALS
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "sarvodaya";
    
    try {
        $conn = new PDO("mysql:host=$host;dbname=$database", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $action = $_GET['action'];
        
        switch ($action) {
            case 'get':
                // Get all active reminders
                $stmt = $conn->prepare("SELECT * FROM interest_reminders WHERE status = 'active' ORDER BY 
                    FIELD(reminder_month, 'January', 'February', 'March', 'April', 'May', 'June', 'July', 
                    'August', 'September', 'October', 'November', 'December'), reminder_date");
                $stmt->execute();
                $reminders = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode($reminders);
                break;
                
            case 'add':
                // Add new reminder
                $data = json_decode(file_get_contents('php://input'), true);
                
                // Validate input
                if (empty($data['reminder_month']) || empty($data['reminder_date'])) {
                    echo json_encode(['success' => false, 'message' => 'Month and date are required']);
                    exit;
                }
                
                // Check if reminder already exists
                $stmt = $conn->prepare("SELECT id FROM interest_reminders 
                                      WHERE reminder_month = :month AND reminder_date = :date AND status = 'active'");
                $stmt->execute([
                    ':month' => $data['reminder_month'],
                    ':date' => $data['reminder_date']
                ]);
                
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Reminder for this month and date already exists']);
                    exit;
                }
                
                // Insert new reminder
                $stmt = $conn->prepare("INSERT INTO interest_reminders (reminder_month, reminder_date) 
                                      VALUES (:month, :date)");
                $stmt->execute([
                    ':month' => $data['reminder_month'],
                    ':date' => $data['reminder_date']
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Reminder added successfully']);
                break;
                
            case 'delete':
                // Soft delete reminder (set status to inactive)
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (empty($data['id'])) {
                    echo json_encode(['success' => false, 'message' => 'Invalid reminder ID']);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE interest_reminders SET status = 'inactive' WHERE id = :id");
                $stmt->execute([':id' => $data['id']]);
                
                if ($stmt->rowCount() > 0) {
                    echo json_encode(['success' => true, 'message' => 'Reminder deleted successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Reminder not found']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}
?>
</body>
</html>