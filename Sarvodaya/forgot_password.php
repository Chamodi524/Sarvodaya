<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Sarvodaya</title>
    <style>
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
        
        .header img {
            width: 120px;
            margin-bottom: 20px;
        }
        
        .header h2 {
            color: #FF6600;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
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
        
        .back-link {
            text-align: center;
            margin-top: 25px;
            color: #666;
            font-size: 15px;
        }
        
        .back-link a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            padding: 12px;
            margin-bottom: 20px;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .alert-success {
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .alert-error {
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
            <h2>Forgot Password</h2>
            <p>Enter your email address and we'll send you a link to reset your password.</p>
            <div class="divider"></div>
        </div>
        
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['msg_type']; ?>">
                <?php 
                echo $_SESSION['message']; 
                unset($_SESSION['message']);
                unset($_SESSION['msg_type']);
                ?>
            </div>
        <?php endif; ?>
        
        <form action="forgot_password_process.php" method="post">
            <div class="form-group">
                <label for="email" style="font-size: 1.25rem;">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" style="font-size: 1.25rem;" required placeholder="Enter your email address">
            </div>
            
            <button type="submit" name="forgot_password" class="btn" style="font-size: 1.25rem;">Send Reset Link</button>
        </form>
        
        <div class="back-link" style="font-size: 1.1rem;">
            Remember your password? <a href="login.php" style="font-size: 1.1rem;">Back to Login</a>
        </div>
    </div>
</body>
</html>