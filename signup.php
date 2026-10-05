<?php
session_start();
require 'db_connect.php';

// Kung walang email sa session, ibalik sa signup form
if (!isset($_SESSION['signup_email'])) {
    header("Location: signup.php");
    exit();
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $entered_otp = trim($_POST['otp_code']);
    
    // Tanggalin ang spaces kung sakaling nag-type ng may space ang user
    $entered_otp = str_replace(' ', '', $entered_otp);

    // I-check kung tama ang OTP
    if (isset($_SESSION['signup_otp']) && $entered_otp == $_SESSION['signup_otp']) {
        
        // --- DITO MO ILALAGAY ANG CODE PARA I-SAVE ANG USER SA DATABASE ---
        // Halimbawa: $stmt = $conn->prepare("INSERT INTO users..."); 
        
        // Kapag successful, i-clear ang session at pumunta sa success page
        unset($_SESSION['signup_otp']);
        header("Location: login.php?status=success");
        exit();
    } else {
        $error = "Invalid OTP verification code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter OTP - CherryJoe</title>
    <link rel="stylesheet" href="https://script.google.com/macros/s/AKfycbzraWE7fbxFfwI8mm5ixTHT9NLQUxLqcjlwfPpkl7yfe3-4F-t44fRosm3EL7sDj1ju4w/exec">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', -apple-system, sans-serif; }
        
        body { 
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.2)), url('imagesgallery7.jpg') center/cover fixed; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            padding: 20px; 
        }

        .auth-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(16px); 
            padding: 40px 30px; 
            border-radius: 28px; 
            box-shadow: 0 25px 50px rgba(0,0,0,0.12); 
            width: 100%; 
            max-width: 420px; 
            text-align: center; 
        }

        .header-icon {
            font-size: 45px;
            color: #10b981;
            margin-bottom: 15px;
        }

        h2 { 
            color: #1e293b; 
            font-size: 26px; 
            font-weight: 800; 
            margin-bottom: 8px; 
        }

        p.subtitle { 
            color: #64748b; 
            font-size: 14px; 
            margin-bottom: 25px; 
            line-height: 1.5; 
        }

        /* Success Banner Style tulad ng nasa picture */
        .success-banner {
            background-color: #d1fae5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 25px;
            text-align: left;
        }

        .success-banner i {
            font-size: 20px;
            color: #059669;
        }

        .success-banner div {
            font-size: 13px;
            color: #047857;
            line-height: 1.4;
        }

        .success-banner strong {
            font-weight: 700;
        }

        /* Input Box */
        .input-group { 
            position: relative; 
            margin-bottom: 20px; 
        }

        .input-group i.left-icon { 
            position: absolute; 
            left: 20px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #10b981; 
            font-size: 20px; 
        }

        .input-group input { 
            width: 100%; 
            padding: 18px 20px 18px 55px; 
            border: 2px solid #10b981; 
            background: #ffffff; 
            border-radius: 16px; 
            font-size: 22px; 
            font-weight: 800;
            color: #475569; 
            letter-spacing: 12px;
            text-align: center;
            outline: none;
            transition: all 0.3s ease; 
        }

        .input-group input::placeholder {
            color: #cbd5e1;
            letter-spacing: 10px;
            font-weight: 700;
        }

        .input-group input:focus { 
            border-color: #059669; 
            box-shadow: 0 0 0 4px rgba(16,185,129,0.15); 
        }

        /* Buttons */
        .submit-btn { 
            background: #10b981; 
            color: white; 
            border: none; 
            padding: 16px; 
            width: 100%; 
            border-radius: 50px; 
            font-weight: 700; 
            font-size: 16px; 
            cursor: pointer; 
            transition: 0.3s ease; 
            margin-bottom: 15px;
        }

        .submit-btn:hover { 
            background: #059669;
            transform: translateY(-2px); 
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3); 
        }

        .cancel-btn {
            display: inline-block;
            width: 100%;
            background: #f8fafc;
            color: #64748b;
            text-decoration: none;
            padding: 15px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            border: 1px solid #e2e8f0;
            transition: 0.3s ease;
        }

        .cancel-btn:hover {
            background: #f1f5f9;
            color: #334155;
        }

        .error-msg { 
            background: #fee2e2; 
            color: #ef4444; 
            padding: 12px; 
            border-radius: 12px; 
            font-size: 14px; 
            margin-bottom: 20px; 
            border: 1px solid #fca5a5; 
            display: flex; 
            align-items: center; 
            gap: 8px; 
            justify-content: center; 
            font-weight: 600;
        }
    </style>
</head>
<body>

    <div class="auth-card">
        <!-- Shield Icon -->
        <i class="fas fa-shield-alt header-icon"></i>
        
        <h2>Enter OTP Code</h2>
        <p class="subtitle">Check your email for the 6-digit verification code.</p>

        <!-- Banner ng Success Message na may email ng user -->
        <div class="success-banner">
            <i class="fas fa-check-circle"></i>
            <div>
                Verification code sent! <br>
                Please check <strong><?php echo htmlspecialchars($_SESSION['signup_email']); ?></strong>
            </div>
        </div>

        <?php if($error): ?>
            <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Form para sa pag-enter ng OTP -->
        <form method="POST">
            <div class="input-group">
                <i class="fas fa-key left-icon"></i>
                <input type="text" name="otp_code" maxlength="11" required placeholder="0 0 0 0 0 0" autocomplete="off">
            </div>
            
            <button type="submit" class="submit-btn">Verify & Create Account</button>
            <a href="signup.php" class="cancel-btn">&larr; Change Email / Cancel</a>
        </form>
    </div>

</body>
</html>
