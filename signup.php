<?php
session_start();
require 'db_connect.php';

$error = '';
$success = '';

// Alamin kung anong step ang kasalukuyang nasa user ('register', 'verify_otp', o 'success')
$step = isset($_SESSION['pending_user']) ? 'verify_otp' : 'register';

// ⚠️ PALITAN ITO NG IYONG DEPLOYED GOOGLE APPS SCRIPT WEB APP URL
$apps_script_url = "https://script.google.com/macros/s/AKfycbzraWE7fbxFfwI8mm5ixTHT9NLQUxLqcjlwfPpkl7yfe3-4F-t44fRosm3EL7sDj1ju4w/exec";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // ==========================================
    // STEP 1: INITIAL SIGN UP & SEND OTP
    // ==========================================
    if (isset($_POST['action_type']) && $_POST['action_type'] === 'send_otp') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];

        if ($password !== $confirm_password) {
            $error = "Passwords do not match! Please try again.";
            $step = 'register';
        } else {
            try {
                // 1. Tignan kung ang email ay naka-rehistro na sa database
                $stmt = $conn->prepare("SELECT id FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                
                if ($stmt->fetch()) {
                    $error = "Email is already registered. Please log in.";
                    $step = 'register';
                } else {
                    // 2. Gumawa ng 6-digit OTP code
                    $otp = sprintf("%06d", mt_rand(100000, 999999));

                    // I-store ang impormasyon sa session (valid ng 5 minuto)
                    $_SESSION['pending_user'] = [
                        'full_name' => $full_name,
                        'email'     => $email,
                        'password'  => password_hash($password, PASSWORD_DEFAULT),
                        'otp'       => $otp,
                        'expires'   => time() + 300 
                    ];

                    // 3. Tawagin ang Google Apps Script API para magpadala ng email
                    $target_url = $apps_script_url . "?action=signup_otp"
                                . "&email=" . urlencode($email)
                                . "&name=" . urlencode($full_name)
                                . "&otp=" . urlencode($otp);

                    $ch = curl_init($target_url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                    $response = curl_exec($ch);
                    curl_close($ch);

                    if ($response !== false && strpos($response, 'SUCCESS') !== false) {
                        $step = 'verify_otp';
                        $success = "Verification code sent to " . htmlspecialchars($email);
                    } else {
                        unset($_SESSION['pending_user']);
                        $error = "Could not send verification email. Please try again later.";
                        $step = 'register';
                    }
                }
            } catch(PDOException $e) {
                $error = "System Error: " . $e->getMessage();
                $step = 'register';
            }
        }
    }

    // ==========================================
    // STEP 2: VERIFY OTP AND SAVE TO DATABASE
    // ==========================================
    else if (isset($_POST['action_type']) && $_POST['action_type'] === 'verify_otp') {
        $step = 'verify_otp';
        $input_otp = trim($_POST['otp_code']);

        if (!isset($_SESSION['pending_user'])) {
            $error = "Session expired. Please try signing up again.";
            $step = 'register';
        } else if (time() > $_SESSION['pending_user']['expires']) {
            $error = "OTP code has expired. Please request a new one.";
            unset($_SESSION['pending_user']);
            $step = 'register';
        } else if ($input_otp !== $_SESSION['pending_user']['otp']) {
            $error = "Incorrect OTP code. Please try again.";
        } else {
            // Tama ang OTP -> I-save ang account sa database
            try {
                $pending = $_SESSION['pending_user'];
                $insert = $conn->prepare("INSERT INTO users (full_name, email, password) VALUES (:full_name, :email, :password)");
                $insert->execute([
                    'full_name' => $pending['full_name'],
                    'email'     => $pending['email'],
                    'password'  => $pending['password']
                ]);

                unset($_SESSION['pending_user']);
                $success = "Account created successfully! Redirecting to login...";
                $step = 'success';
                
                header("refresh:2;url=login.php");
            } catch(PDOException $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        }
    }

    // CANCEL/CHANGE EMAIL ACTION
    else if (isset($_POST['action_type']) && $_POST['action_type'] === 'cancel_otp') {
        unset($_SESSION['pending_user']);
        $step = 'register';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - CherryJoe</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', -apple-system, sans-serif; }
        body { background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(5,150,105,0.2)), url('imagesgallery7.jpg') center/cover fixed; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
        .auth-card { background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(16px); padding: 40px; border-radius: 24px; box-shadow: 0 25px 50px rgba(0,0,0,0.1); width: 100%; max-width: 420px; text-align: center; }
        .logo-icon { font-size: 45px; color: #059669; margin-bottom: 10px; }
        h2 { color: #1e293b; font-size: 26px; font-weight: 800; margin-bottom: 5px; }
        p.subtitle { color: #64748b; font-size: 14px; margin-bottom: 25px; line-height: 1.5; }
        
        .input-group { position: relative; margin-bottom: 18px; text-align: left; }
        .input-group i.left-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #10b981; font-size: 18px; }
        .input-group i.toggle-password { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: #64748b; font-size: 18px; cursor: pointer; transition: 0.3s ease; }
        .input-group i.toggle-password:hover { color: #10b981; }

        .input-group input { width: 100%; padding: 15px 45px; border: 2px solid #cbd5e1; background: #f8fafc; border-radius: 14px; font-size: 15px; color: #1e293b; transition: all 0.3s ease; }
        .input-group input:focus { border-color: #10b981; background: #ffffff; outline: none; box-shadow: 0 0 0 4px rgba(16,185,129,0.15); }
        
        /* Input styling para sa walang right icon */
        .input-group input.no-right-icon { padding-right: 20px; }

        .otp-input { letter-spacing: 10px; font-size: 22px !important; font-weight: 800; text-align: center; color: #059669 !important; }

        .submit-btn { background: linear-gradient(135deg, #10b981, #059669); color: white; border: none; padding: 16px; width: 100%; border-radius: 50px; font-weight: 700; font-size: 16px; cursor: pointer; transition: 0.3s ease; margin-top: 5px; }
        .submit-btn:hover { transform: translateY(-3px); box-shadow: 0 15px 25px rgba(16, 185, 129, 0.4); }
        
        .cancel-btn { background: transparent; color: #64748b; border: 1px solid #cbd5e1; padding: 14px; width: 100%; border-radius: 50px; font-weight: 600; font-size: 14px; cursor: pointer; transition: 0.3s ease; margin-top: 10px; }
        .cancel-btn:hover { background: #f1f5f9; color: #1e293b; }

        .bottom-link { display: block; margin-top: 20px; color: #475569; font-size: 14px; text-decoration: none; transition: 0.3s; }
        .bottom-link span { color: #059669; font-weight: 700; }
        .bottom-link:hover span { text-decoration: underline; }

        .error-msg { background: #fee2e2; color: #ef4444; padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; border: 1px solid #fca5a5; display: flex; align-items: center; gap: 8px; justify-content: center; font-weight: 600;}
        .success-msg { background: #d1fae5; color: #059669; padding: 12px; border-radius: 10px; font-size: 14px; margin-bottom: 20px; border: 1px solid #a7f3d0; display: flex; align-items: center; gap: 8px; justify-content: center; font-weight: 600;}

        .divider { display: flex; align-items: center; text-align: center; margin: 20px 0; color: #94a3b8; font-size: 13px; font-weight: 600; }
        .divider::before, .divider::after { content: ''; flex: 1; border-bottom: 1px solid #cbd5e1; }
        .divider::before { margin-right: 15px; } .divider::after { margin-left: 15px; }

        .google-btn { 
            background: #ffffff; color: #374151; border: 2px solid #cbd5e1; 
            padding: 13px; width: 100%; border-radius: 50px; font-weight: 700; 
            font-size: 15px; cursor: pointer; transition: 0.3s ease; display: flex; 
            align-items: center; justify-content: center; gap: 10px; text-decoration: none; 
        }
        .google-btn:hover { background: #f8fafc; border-color: #94a3b8; transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="auth-card">

        <?php if($step === 'register'): ?>
            <!-- ========================================== -->
            <!-- STEP 1: REGISTRATION FORM                  -->
            <!-- ========================================== -->
            <i class="fas fa-user-plus logo-icon"></i>
            <h2>Create Account</h2>
            <p class="subtitle">Join CherryJoe River Park today</p>
            
            <?php if($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" id="signupForm">
                <input type="hidden" name="action_type" value="send_otp">

                <div class="input-group">
                    <i class="fas fa-user left-icon"></i>
                    <input type="text" name="full_name" class="no-right-icon" required placeholder="Full Name">
                </div>

                <div class="input-group">
                    <i class="fas fa-envelope left-icon"></i>
                    <input type="email" name="email" class="no-right-icon" required placeholder="Email Address">
                </div>
                
                <div class="input-group">
                    <i class="fas fa-lock left-icon"></i>
                    <input type="password" name="password" id="signup_pass" required placeholder="Password">
                    <i class="fas fa-eye toggle-password" onclick="togglePass('signup_pass', this)"></i>
                </div>
                
                <div class="input-group">
                    <i class="fas fa-check-circle left-icon"></i>
                    <input type="password" name="confirm_password" id="signup_confirm" required placeholder="Confirm Password">
                    <i class="fas fa-eye toggle-password" onclick="togglePass('signup_confirm', this)"></i>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">Sign Up</button>
            </form>

            <div class="divider">OR</div>
            
            <a href="google_login.php" class="google-btn">
                <svg width="20" height="20" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                </svg>
                Sign up with Google
            </a>
            
            <a href="login.php" class="bottom-link">Already have an account? <span>Log in</span></a>

        <?php elseif($step === 'verify_otp'): ?>
            <!-- ========================================== -->
            <!-- STEP 2: OTP VERIFICATION FORM             -->
            <!-- ========================================== -->
            <i class="fas fa-shield-alt logo-icon"></i>
            <h2>Enter OTP Code</h2>
            <p class="subtitle">Check your email for the 6-digit verification code.</p>

            <?php if($error): ?>
                <div class="error-msg"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            <?php if($success): ?>
                <div class="success-msg"><i class="fas fa-check-circle"></i> <?php echo $success; ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="action_type" value="verify_otp">
                
                <div class="input-group">
                    <i class="fas fa-key left-icon"></i>
                    <input type="text" name="otp_code" class="otp-input no-right-icon" maxlength="6" pattern="\d{6}" required placeholder="000000" autofocus autocomplete="off">
                </div>

                <button type="submit" class="submit-btn">Verify & Create Account</button>
            </form>

            <form method="POST">
                <input type="hidden" name="action_type" value="cancel_otp">
                <button type="submit" class="cancel-btn"><i class="fas fa-arrow-left"></i> Change Email / Cancel</button>
            </form>

        <?php elseif($step === 'success'): ?>
            <!-- ========================================== -->
            <!-- STEP 3: SUCCESS STATE                      -->
            <!-- ========================================== -->
            <i class="fas fa-check-circle logo-icon" style="color: #059669; font-size: 55px;"></i>
            <h2 style="margin-top: 10px;">Account Verified!</h2>
            <p class="subtitle"><?php echo $success; ?></p>
            <a href="login.php" style="text-decoration:none;"><button class="submit-btn">Go to Login</button></a>
        <?php endif; ?>

    </div>

    <!-- JAVASCRIPT FOR EYE TOGGLE & BUTTON SPINNER -->
    <script>
        function togglePass(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const signupForm = document.getElementById('signupForm');
        if (signupForm) {
            signupForm.addEventListener('submit', function() {
                var btn = document.getElementById('submitBtn');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP Code...';
                btn.style.pointerEvents = 'none';
                btn.style.opacity = '0.8';
            });
        }
    </script>
</body>
</html>
