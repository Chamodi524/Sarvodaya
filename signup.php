<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration | Sarvodaya</title>
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
        
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('sarvodaya.jpg');
            background-size: cover;
            background-position: center;
            opacity: 0.15;
            z-index: -1;
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
        
        .container::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: linear-gradient(135deg, #FF8C00, #FFCF40);
            border-radius: 50%;
            opacity: 0.7;
            z-index: 0;
        }
        
        .container::after {
            content: '';
            position: absolute;
            bottom: -60px;
            left: -60px;
            width: 180px;
            height: 180px;
            background: linear-gradient(135deg, #FFCF40, #FF8C00);
            border-radius: 50%;
            opacity: 0.7;
            z-index: 0;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .form-content {
            position: relative;
            z-index: 1;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            padding-top: 100px; /* Added space for the logo */
        }
        
        .header h2 {
            color: #FF6600;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }
        
        .header p {
            color: #6c6c6c;
            font-size: 16px;
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
            transition: all 0.3s;
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
        
        select.form-control {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23FF8C00' d='M6 8L.5 2h11L6 8z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }
        
        .input-icon {
            position: absolute;
            top: 42px;
            right: 15px;
            color: #FF8C00;
        }
        
        .password-wrapper {
            position: relative;
        }
        
        small {
            display: block;
            color: #888;
            font-size: 12px;
            margin-top: 5px;
        }
        
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
            position: relative;
            overflow: hidden;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 15px rgba(255, 140, 0, 0.35);
        }
        
        .btn:active {
            transform: translateY(1px);
        }
        
        .btn::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }
        
        .btn:hover::after {
            left: 100%;
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
        
        .social-signup {
            margin-top: 30px;
            position: relative;
        }
        
        .social-signup p {
            position: relative;
            text-align: center;
            margin-bottom: 20px;
            color: #666;
            font-size: 14px;
        }
        
        .social-signup p:before,
        .social-signup p:after {
            content: "";
            position: absolute;
            height: 1px;
            width: 35%;
            background-color: #ddd;
            top: 50%;
        }
        
        .social-signup p:before {
            left: 0;
        }
        
        .social-signup p:after {
            right: 0;
        }
        
        .social-icons {
            display: flex;
            justify-content: center;
            gap: 20px;
        }
        
        .social-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }
        
        .social-icon:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
        }
        
        .facebook {
            background: #3b5998;
        }
        
        .google {
            background: #db4437;
        }
        
        .twitter {
            background: #1da1f2;
        }
        
        /* Modified logo style */
        .logo-container {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 100px;
            height: 100px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid #FF8C00;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            z-index: 10;
        }
        
        .logo-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        @media (max-width: 480px) {
            .container {
                padding: 30px 20px;
            }
            
            .header h2 {
                font-size: 26px;
            }
            
            .form-control {
                padding: 12px;
            }
            
            .social-icon {
                width: 40px;
                height: 40px;
            }
            
            .logo-container {
                width: 90px;
                height: 90px;
                top: -10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="form-content">
            <div class="logo-container">
                <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
            </div>
            
            <div class="header">
                <h2>Join Sarvodaya</h2>
                <div class="divider"></div>
                <p>Create your account in just a few steps</p>
            </div>
            
            <form action="signup_process.php" method="post">
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" class="form-control" required placeholder="Choose a unique username">
                        <div class="input-icon">
                            <i class="fas fa-user"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrapper">
                        <input type="email" id="email" name="email" class="form-control" required placeholder="Enter your email address">
                        <div class="input-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="position">Position</label>
                    <div class="input-wrapper">
                        <select id="position" name="position" class="form-control" required>
                            <option value="" disabled selected>Select your position</option>
                            <option value="loan_handling_clerk">Loan Handling Clerk</option>
                            <option value="membership_handling_clerk">Membership Handling Clerk</option>
                            <option value="transaction_handling_clerk">Transaction Handling Clerk</option>
                            <option value="manager">Manager</option>
                        </select>
                        <div class="input-icon">
                            <i class="fas fa-briefcase"></i>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="password" name="password" class="form-control" required placeholder="Create a secure password">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                    <small>Password must be at least 8 characters long with letters and numbers</small>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <div class="password-wrapper">
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" required placeholder="Confirm your password">
                        <div class="input-icon">
                            <i class="fas fa-lock"></i>
                        </div>
                    </div>
                </div>
                
                <button type="submit" name="register" class="btn">Create Account</button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="login.php">Log in now</a>
            </div>
        </div>
    </div>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</body>
</html>