<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sarvodaya Bank | Your Financial Partner</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background: linear-gradient(135deg, #FFA500, #FF6347);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            perspective: 1000px;
            overflow: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at center, rgba(255,255,255,0.1) 0%, transparent 70%);
            transform: rotate(-45deg);
            z-index: -1;
        }

        .container {
            text-align: center;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
            padding: 40px;
            width: 100%;
            max-width: 600px;
            position: relative;
            overflow: hidden;
            transform: rotateX(10deg) rotateY(-10deg) scale(0.9);
            transition: all 0.5s ease;
        }

        .container:hover {
            transform: rotateX(0) rotateY(0) scale(1);
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        .logo {
            width: 180px;
            height: 180px;
            border-radius: 50%;
            object-fit: cover;
            border: 6px solid #FFA500;
            margin: 0 auto 30px;
            display: block;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.05) rotate(5deg);
        }

        .title {
            color: #FFA500;
            font-size: 2.7rem;
            margin-bottom: 20px;
            font-weight: 700;
            letter-spacing: 1px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            color: #555;
            margin-bottom: 40px;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .button-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .btn {
            background-color: #FFA500;
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            transform: perspective(500px);
        }

        .btn:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.3);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        @media (max-width: 600px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
                transform: none;
            }

            .title {
                font-size: 2rem;
            }

            body {
                background: linear-gradient(135deg, #FFA500, #FF6347);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <img src="Sarwodaya logo.jpg" alt="Sarvodaya Bank Logo" class="logo">
        <h1 class="title">Sarvodaya Shramadhana Society</h1>
        <p class="subtitle" style="font-size: 1.25rem;">Empowering Your Financial Journey with Trust and Innovation</p>
        
        <div class="button-container">
            <button class="btn" onclick="location.href='member_management.php'" style="font-size: 1.25rem;">Memberships</button>
            <button class="btn" onclick="location.href='loan_handing_home.php'" style="font-size: 1.25rem;">Loans</button>
            <button class="btn" onclick="location.href='transaction_handling_home.php'" style="font-size: 1.25rem;">Other Services</button>
        </div>
    </div>
</body>
</html>