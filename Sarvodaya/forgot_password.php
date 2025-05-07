<?php
// Start session
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Sarvodaya</title>
    <style>
        /* Your existing CSS */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #FFCF40, #FF8C00);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
        }
        
        .container {
            width: 100%;
            max-width: 480px;
            background: rgba(255, 255, 255, 0.92);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(246, 142, 32, 0.35);
            padding: 40px 30px;
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.8s ease-out;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h2 {
            color: #FF6600;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .divider {
            height: 5px;
            width: 80px;
            background: linear-gradient(to right, #FF8C00, #FFCF40);
            margin: 15px auto;
            border-radius: 5px;
        }
        
        .form-group {
            margin-bottom: 22px;
            position: relative;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #444;
            font-size: 14px;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 15px;
            border: 2px solid #ebebeb;
            border-radius: 10px;
            font-size: 15px;
            transition: all 0.3s;
            background-color: #fafafa;
        }
        
        .form-control:focus {
            border-color: #FF8C00;
            box-shadow: 0 0 0 3px rgba(255, 140, 0, 0.25);
            outline: none;
            background-color: #fff;
        }
        
        .btn {
            background: linear-gradient(to right, #FF8C00, #FFCF40);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            width: 100%;
            font-weight: 600;
            transition: all 0.3s;
            margin-top: 10px;
            box-shadow: 0 4px 15px rgba(255, 140, 0, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(255, 140, 0, 0.35);
        }
        
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 15px;
        }
        
        .login-link a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        /* Added styles for messages */
        .error {
            color: #e74c3c;
            font-size: 14px;
            padding: 10px;
            background-color: rgba(231, 76, 60, 0.1);
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .success {
            background-color: rgba(46, 204, 113, 0.2);
            color: #27ae60;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Forgot Password</h2>
            <div class="divider"></div>
            <p>Enter your email to reset your password</p>
        </div>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="error">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="success">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_messages']) && is_array($_SESSION['error_messages'])): ?>
            <div class="error">
                <?php 
                foreach ($_SESSION['error_messages'] as $error) {
                    echo $error . "<br>";
                }
                unset($_SESSION['error_messages']); 
                ?>
            </div>
        <?php endif; ?>
        
        <form action="reset_password.php" method="post">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required placeholder="Enter your email">
            </div>
            
            <button type="submit" name="reset" class="btn">Reset Password</button>
        </form>
        
        <div class="login-link">
            Remember your password? <a href="login.php">Log in here</a>
        </div>
    </div>
</body>
</html>