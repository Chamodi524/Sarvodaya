<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sarvodaya</title>
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
            width: 120px; /* Adjust the size of the logo */
            margin-bottom: 20px;
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
        
        .forgot-password {
            text-align: center;
            margin-top: 15px;
        }
        
        .forgot-password a {
            color: #FF8C00;
            text-decoration: none;
            font-weight: 600;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <!-- Sarvodaya Logo -->
            <img src="Sarwodaya logo.jpg" alt="Sarodaya Logo">
            <h2>Login to Sarvodaya</h2>
            <div class="divider"></div>
        </div>
        
        <form action="login_process.php" method="post">
            <div class="form-group">
                <label for="email">User email</label>
                <input type="text" id="email" name="email" class="form-control" required placeholder="Enter your email">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>
            
            <button type="submit" name="login" class="btn">Login</button>
        </form>
        
        <div class="forgot-password">
            <a href="forgot_password.php">Forgot Password?</a>
        </div>
        
        <div class="login-link">
            Don't have an account? <a href="signup.php">Register here</a>
        </div>
    </div>
</body>
</html>