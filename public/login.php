<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// Redirect if already logged in
if (is_logged_in()) {
    header('Location: index.php');
    exit;
}

// Handle login submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Rate limiting
    if (!check_rate_limit('login_attempts', 5, 300)) {
        $error = 'Too many login attempts. Please try again in 5 minutes.';
    } elseif (!isset($_POST['csrf_token']) || !verify_token($_POST['csrf_token'])) {
        $error = 'Security token validation failed. Please refresh the page.';
    } else {
        $username = sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($username) || empty($password)) {
            $error = 'Please enter both username and password.';
        } else {
            $user = authenticate($username, $password);
            if ($user) {
                create_session($user);
                set_flash('Welcome back, ' . $user['full_name'] . '!', 'success');
                header('Location: index.php');
                exit;
            } else {
                $error = 'Invalid username or password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Traffic Citation System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        /* --- 1. Reset & Layout --- */
        :root {
            --bg-color: #e0e5ec;
            --primary: #4e54c8;
            --secondary: #8f94fb;
            --white: #ffffff;
            --shadow: rgba(0, 0, 0, 0.2);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', sans-serif; }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: #dfe6e9;
        }

        .login-card {
            display: flex;
            width: 950px;
            max-width: 95%;
            height: 600px;
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15);
            overflow: hidden;
        }

        /* --- 2. Left Panel (The 3D Stage) --- */
        .left-panel {
            flex: 1.2;
            background: linear-gradient(135deg, #1e272e, #34495e);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            perspective: 800px;
        }

        .left-logo {
            width: 80px;
            height: 80px;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.3));
        }

        .stage-header {
            position: absolute;
            top: 35px;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            z-index: 10;
        }

        .stage-title {
            color: rgba(255,255,255,0.8);
            letter-spacing: 2px;
            text-transform: uppercase;
            font-size: 0.9rem;
        }

        .tmg-logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.3));
        }

        .scene-container {
            width: 100%;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        /* Shared Scene Styles */
        .scene {
            display: none;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.5s ease;
        }

        .scene.active {
            display: flex;
            opacity: 1;
            transform: translateY(0);
        }

        .caption {
            margin-top: 50px;
            color: white;
            font-size: 1.2rem;
            font-weight: 300;
            letter-spacing: 1px;
            text-shadow: 0 5px 10px rgba(0,0,0,0.3);
        }

        /* --- 3. Animation 1: Isometric Paper Writer --- */
        .iso-paper-stack {
            position: relative;
            transform: rotateX(55deg) rotateZ(-45deg);
            transform-style: preserve-3d;
        }

        .sheet {
            width: 100px; height: 120px;
            background: #ecf0f1;
            position: absolute;
            border-radius: 5px;
            box-shadow: -5px 5px 10px rgba(0,0,0,0.3);
        }
        .sheet:nth-child(1) { top: 0; left: 0; z-index: 1; transform: translateZ(0px); background: #bdc3c7; }
        .sheet:nth-child(2) { top: -5px; left: 5px; z-index: 2; transform: translateZ(10px); background: #dfe6e9; }
        .sheet:nth-child(3) {
            top: -10px; left: 10px; z-index: 3;
            transform: translateZ(20px);
            background: #ffffff;
            display: flex; flex-direction: column; padding: 15px; gap: 8px;
        }
        .line { height: 4px; background: #e0e0e0; border-radius: 2px; width: 100%; }
        .line.short { width: 60%; }

        .pen {
            width: 10px; height: 60px; background: #f1c40f;
            position: absolute; z-index: 10; top: -40px; left: 40px;
            transform: translateZ(40px) rotateX(-90deg);
            box-shadow: 5px 5px 10px rgba(0,0,0,0.4);
            animation: writeIso 2s infinite ease-in-out;
        }
        .pen::before { content: ''; position: absolute; bottom: -10px; left: 0; border-left: 5px solid transparent; border-right: 5px solid transparent; border-top: 10px solid #333; }

        @keyframes writeIso {
            0%, 100% { transform: translateZ(40px) translate(0, 0) rotateX(-90deg); }
            25% { transform: translateZ(40px) translate(20px, 10px) rotateX(-90deg); }
            50% { transform: translateZ(40px) translate(0, 20px) rotateX(-90deg); }
            75% { transform: translateZ(40px) translate(20px, 30px) rotateX(-90deg); }
        }

        /* --- 4. Animation 2: 3D Warning Triangle --- */
        .warning-container {
            position: relative;
            transform-style: preserve-3d;
            animation: float 2s infinite ease-in-out;
        }
        .triangle-3d {
            position: relative; width: 0; height: 0;
            border-left: 50px solid transparent; border-right: 50px solid transparent; border-bottom: 90px solid #f39c12;
            filter: drop-shadow(0 10px 10px rgba(0,0,0,0.5));
            transform: rotateY(0deg); display: flex; justify-content: center;
        }
        .triangle-3d::after {
            content: '!'; position: absolute; top: 25px; left: -10px;
            font-size: 50px; font-weight: bold; color: #333; transform: translateZ(20px);
        }
        .pulse-floor {
            position: absolute; bottom: -30px; left: -40px;
            width: 180px; height: 180px;
            background: radial-gradient(circle, rgba(243, 156, 18, 0.4) 0%, transparent 70%);
            transform: rotateX(70deg); border-radius: 50%;
            animation: pulseWave 1s infinite;
        }
        @keyframes pulseWave {
            0% { transform: rotateX(70deg) scale(0.5); opacity: 1; }
            100% { transform: rotateX(70deg) scale(1.2); opacity: 0; }
        }
        @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-15px); } }

        /* --- 5. Animation 3: Search/Scan --- */
        .search-container {
            position: relative; width: 160px; height: 100px;
            transform: rotateX(20deg); transform-style: preserve-3d;
        }
        .data-card {
            width: 100%; height: 100%; background: #3498db; border-radius: 10px;
            transform: rotateY(15deg); box-shadow: 5px 5px 15px rgba(0,0,0,0.3);
            display: flex; flex-direction: column; justify-content: center; padding: 15px; gap: 10px;
        }
        .data-line { height: 6px; background: rgba(255,255,255,0.4); border-radius: 3px; }
        .data-line:nth-child(1) { width: 80%; } .data-line:nth-child(2) { width: 60%; } .data-line:nth-child(3) { width: 90%; }
        .magnify-glass {
            position: absolute; top: -20px; left: -30px; width: 60px; height: 60px;
            border: 6px solid #ecf0f1; border-radius: 50%; background: rgba(255,255,255,0.2);
            backdrop-filter: blur(2px); box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            transform: translateZ(50px); animation: scanMove 2s infinite linear;
        }
        .magnify-glass::after {
            content: ''; position: absolute; bottom: -20px; right: -20px;
            width: 8px; height: 30px; background: #bdc3c7; transform: rotate(-45deg);
        }
        @keyframes scanMove {
            0% { left: -30px; top: -20px; } 50% { left: 120px; top: 30px; } 100% { left: -30px; top: -20px; }
        }

        /* --- 6. Animation 4: Payment Success --- */
        .payment-container { position: relative; width: 180px; height: 110px; perspective: 600px; }
        .credit-card {
            width: 100%; height: 100%; background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px; transform: rotateY(20deg) rotateX(10deg);
            box-shadow: 15px 15px 30px rgba(0,0,0,0.4); position: relative; padding: 15px;
        }
        .chip { width: 30px; height: 20px; background: #f1c40f; border-radius: 4px; margin-bottom: 30px; }
        .dots { display: flex; gap: 5px; } .dot { width: 30px; height: 6px; background: rgba(255,255,255,0.5); border-radius: 3px; }
        .success-badge {
            position: absolute; top: -15px; right: -15px; width: 50px; height: 50px;
            background: #2ecc71; border-radius: 50%; display: flex; align-items: center; justify-content: center;
            border: 4px solid #fff; box-shadow: 0 10px 20px rgba(46, 204, 113, 0.4);
            transform: translateZ(30px) scale(0);
            animation: badgePop 0.5s 0.5s forwards cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .check { width: 12px; height: 22px; border: solid white; border-width: 0 4px 4px 0; transform: rotate(45deg); margin-top: -4px; }
        @keyframes badgePop { to { transform: translateZ(30px) scale(1); } }

        /* --- 7. Animation 5: Issuing Receipt --- */
        .printer-container {
            position: relative;
            width: 140px;
            height: 100px;
            perspective: 600px;
            transform: rotateX(10deg);
        }

        .printer-head {
            position: absolute;
            top: 0; left: 0;
            width: 100%;
            height: 40px;
            background: #2c3e50;
            border-radius: 8px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .printer-slot {
            width: 80%; height: 6px; background: #1a252f; border-radius: 3px;
        }

        .receipt {
            position: absolute;
            top: 10px;
            left: 15px;
            width: 110px;
            height: 0px;
            background: #fff;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 10px;
            gap: 6px;
            animation: printScroll 2s forwards ease-out;
            background-image:
                linear-gradient(135deg, transparent 5px, #fff 5px),
                linear-gradient(225deg, transparent 5px, #fff 5px);
            background-position: bottom;
            background-size: 10px 10px;
            background-repeat: repeat-x;
            background-color: white;
            padding-bottom: 15px;
        }

        @keyframes printScroll {
            0% { height: 0px; opacity: 0; }
            20% { opacity: 1; }
            100% { height: 160px; }
        }

        .r-line { width: 100%; height: 4px; background: #dfe6e9; border-radius: 2px; }
        .r-line.sm { width: 60%; align-self: flex-start; }
        .r-line.total { width: 100%; height: 6px; background: #2ecc71; margin-top: auto; }

        /* --- Right Panel (Form) --- */
        .right-panel {
            flex: 1;
            padding: 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
        }

        .header h1 { font-size: 2.2rem; color: #2d3436; margin-bottom: 10px; }
        .header p { color: #636e72; margin-bottom: 40px; }

        .input-group { position: relative; margin-bottom: 25px; }
        .input-group label {
            position: absolute; left: 15px; top: -10px; background: white; padding: 0 5px;
            font-size: 0.85rem; color: #4e54c8; font-weight: 600; z-index: 3;
        }
        .input-group input {
            width: 100%; padding: 15px; border: 2px solid #e0e5ec; border-radius: 10px; font-size: 1rem; transition: 0.3s;
        }
        .input-group input:focus { outline: none; border-color: #4e54c8; box-shadow: 0 0 0 4px rgba(78, 84, 200, 0.1); }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            padding-right: 50px !important;
        }

        .password-toggle {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: #636e72;
            cursor: pointer;
            padding: 8px 10px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
        }

        .password-toggle:hover {
            color: #4e54c8;
        }

        .password-toggle i {
            font-size: 1.1rem;
        }

        .btn-login {
            width: 100%; padding: 15px; background: linear-gradient(to right, #4e54c8, #8f94fb);
            color: white; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600;
            cursor: pointer; transition: transform 0.2s; margin-top: 10px;
        }
        .btn-login:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(78, 84, 200, 0.3); }
        .btn-login:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }

        .alert {
            padding: 12px 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }

        .alert-danger {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }

        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }

        .default-creds {
            text-align: center;
            margin-top: 20px;
            font-size: 0.85rem;
            color: #95a5a6;
        }

        .default-creds strong {
            color: #e74c3c;
        }

        /* Responsive */
        @media (max-width: 900px) {
            .login-card {
                flex-direction: column;
                height: auto;
                width: 90%;
            }

            .left-panel {
                min-height: 300px;
            }

            .right-panel {
                padding: 40px 30px;
            }

            .left-logo, .tmg-logo {
                width: 50px;
                height: 50px;
            }

            .stage-header {
                flex-direction: column;
                gap: 10px;
                top: 20px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">

        <!-- LEFT: 3D Animation Carousel -->
        <div class="left-panel">
            <div class="stage-header">
                <img src="../assets/img/LOGO1.png" alt="Baggao Logo" class="left-logo">
                <div class="stage-title">B-TRACS </div>
                <img src="../assets/img/TMG PNG.png" alt="TMG Logo" class="tmg-logo">
            </div>

            <div class="scene-container">

                <!-- 1. Paper Writer -->
                <div class="scene active">
                    <div class="iso-paper-stack">
                        <div class="sheet"></div>
                        <div class="sheet"></div>
                        <div class="sheet"><div class="line"></div><div class="line"></div><div class="line short"></div><div class="line"></div></div>
                        <div class="pen"></div>
                    </div>
                    <div class="caption">Generating Report</div>
                </div>

                <!-- 2. Warning Pulse -->
                <div class="scene">
                    <div class="warning-container">
                        <div class="triangle-3d"></div>
                        <div class="pulse-floor"></div>
                    </div>
                    <div class="caption">Fetching Violation...</div>
                </div>

                <!-- 3. Search / Verify -->
                <div class="scene">
                    <div class="search-container">
                        <div class="data-card"><div class="data-line"></div><div class="data-line"></div><div class="data-line"></div></div>
                        <div class="magnify-glass"></div>
                    </div>
                    <div class="caption">Verifying Details</div>
                </div>

                <!-- 4. Payment Success -->
                <div class="scene">
                    <div class="payment-container">
                        <div class="credit-card">
                            <div class="chip"></div>
                            <div class="dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div>
                        </div>
                        <div class="success-badge"><div class="check"></div></div>
                    </div>
                    <div class="caption">Payment Successful</div>
                </div>

                <!-- 5. Issuing Receipt -->
                <div class="scene">
                    <div class="printer-container">
                        <div class="printer-head">
                            <div class="printer-slot"></div>
                        </div>
                        <div class="receipt">
                            <div class="r-line"></div>
                            <div class="r-line sm"></div>
                            <div class="r-line"></div>
                            <div class="r-line sm"></div>
                            <div class="r-line total"></div>
                        </div>
                    </div>
                    <div class="caption">Issuing Receipt</div>
                </div>

            </div>
        </div>

        <!-- RIGHT: Login Form -->
        <div class="right-panel">
            <div class="header">
                <h1>Welcome Back</h1>
                <p>Please enter your credentials to sign in.</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php echo show_flash(); ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" id="username"
                           value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                           placeholder="Enter your username" required autofocus>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="password-wrapper">
                        <input type="password" name="password" id="password"
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" id="togglePassword">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>
<!-- 
            <div class="default-creds">
                Default admin: admin / admin123<br>
                <strong>Change password after first login!</strong>
            </div> -->
        </div>

    </div>

    <script>
        // Cycle Animations
        const scenes = document.querySelectorAll('.scene');
        let index = 0;

        function cycleScenes() {
            scenes[index].classList.remove('active');
            index = (index + 1) % scenes.length;
            scenes[index].classList.add('active');
        }

        setInterval(cycleScenes, 2500);

        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');

            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Disable button on submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';
        });
    </script>
</body>
</html>
