<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Services | Sarvodaya</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background: url('jeshoots-com-LtNvQHdKkmw-unsplash.jpg') no-repeat center center fixed;
            background-size: cover; /* Makes the image cover the entire background */
            overflow: hidden;
            color: white;
        }

        header {
            text-align: center;
            padding: 20px;
            background: rgba(255, 140, 0, 0.8); /* Orange with transparency */
            color: white;
            position: relative;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        header img {
            width: 100px;
            margin-bottom: 10px;
        }

        header h1 {
            font-size: 2.2rem;
            margin: 0;
        }

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            background: #e67300; /* Darker orange for logout button */
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .logout-btn:hover {
            background: #cc6600; /* Even darker orange for hover effect */
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        .container {
            margin: 120px auto;
            max-width: 600px;
            text-align: center;
            background: rgba(255, 255, 255, 0.9); /* Semi-transparent white */
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
        }

        .container h2 {
            font-size: 1.8rem;
            color: #FF8C00; /* Orange for headings */
        }

        .container p {
            font-size: 1rem;
            color: #333;
            margin: 10px 0 30px;
        }

        .buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
        }

        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 30px;
            background: #FF8C00; /* Orange buttons */
            color: white;
            font-size: 1rem;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .btn:hover {
            background: #e67300; /* Darker orange for hover effect */
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }
    </style>
</head>
<body>
    <header>
        <img src="Sarwodaya logo.jpg" alt="Sarvodaya Logo">
        <h1>Welcome to Sarvodaya Loan Services</h1>
        <!-- Logout Button -->
        <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
    </header>

    <div class="container">
        <h2>Your Financial Partner</h2>
        <p>Choose the best loan option that suits your financial goals and get started!</p>
        <div class="buttons">
            <button class="btn" onclick="location.href='member_loans.php'">Apply a loan</button>
            <button class="btn" onclick="location.href='view_loans.php'">Application forms</button>
            <button class="btn" onclick="location.href='change_status.php'">Approving</button>
            <button class="btn" onclick="location.href='loan_payement_handling.php'">Record loan</button>
        </div>
    </div>
</body>
</html>