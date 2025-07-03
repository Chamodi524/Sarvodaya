<?php 
session_start();

// Include the date alert system
$alertScript = '';
$alertDivs = '';

if (isset($_SESSION['show_alerts']) && $_SESSION['show_alerts'] === true) {
    $alerts = $_SESSION['date_alerts'] ?? [];
    
    if (!empty($alerts)) {
        $alertScript = '<script>';
        $alertScript .= 'document.addEventListener("DOMContentLoaded", function() {';
        
        foreach ($alerts as $alert) {
            $alertScript .= 'alert("' . addslashes($alert['message']) . '");';
        }
        
        $alertScript .= '});';
        $alertScript .= '</script>';
        
        // Create visual alert divs
        $alertDivs = '<div id="dateAlerts" style="position: fixed; top: 20px; right: 20px; z-index: 9999; max-width: 400px;">';
        
        foreach ($alerts as $index => $alert) {
            $alertDivs .= '<div class="alert alert-warning alert-dismissible fade show" role="alert" style="margin-bottom: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); background-color: #fff3cd; border-left: 4px solid #ffc107; color: #856404; padding: 15px; border-radius: 8px;">';
            $alertDivs .= '<strong>📅 Date Alert!</strong><br>';
            $alertDivs .= htmlspecialchars($alert['message']);
            $alertDivs .= '<button type="button" onclick="this.parentElement.style.display=\'none\'" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 18px; cursor: pointer; color: #856404;">&times;</button>';
            $alertDivs .= '</div>';
        }
        
        $alertDivs .= '</div>';
    }
    
    // Clear the alerts after displaying
    unset($_SESSION['show_alerts']);
    unset($_SESSION['date_alerts']);
}
?>
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

        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 10px 20px;
            border: none;
            border-radius: 30px;
            background: #e67300;
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
            text-transform: uppercase;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .logout-btn:hover {
            background: #cc6600;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }

        /* Alert styles */
        .alert {
            border-radius: 8px;
            border: none;
            font-size: 14px;
            position: relative;
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
            
            #dateAlerts {
                position: fixed !important;
                top: 10px !important;
                left: 10px !important;
                right: 10px !important;
                max-width: none !important;
            }
        }
    </style>
    
    <?php echo $alertScript; ?>
</head>
<body>
    <?php echo $alertDivs; ?>
    
    <div class="container">
        <button class="logout-btn" onclick="location.href='logout.php'">Logout</button>
        
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