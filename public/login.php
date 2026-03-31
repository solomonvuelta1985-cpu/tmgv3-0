<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/session_manager.php';

// Redirect if already logged in
if (is_logged_in()) {
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'lto_staff') {
        header('Location: lto_search.php');
    } elseif (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'pnp') {
        header('Location: citations.php');
    } else {
        header('Location: index.php');
    }
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
            try {
                $user = authenticate($username, $password);
                if ($user) {
                    // Check session limit for admin users
                    $sessionCheck = check_session_limit($user['user_id'], $user['role']);

                    if (!$sessionCheck['allowed']) {
                        $error = $sessionCheck['message'];
                        error_log("Login blocked for user {$user['username']}: {$error}");
                    } else {
                        // Create PHP session
                        create_session($user);

                        // Create session tracking record in database
                        $sessionToken = $_SESSION['session_token'] ?? session_id();
                        create_session_record($user['user_id'], $sessionToken);

                        set_flash('Welcome back, ' . $user['full_name'] . '!', 'success');

                        // Redirect based on user role
                        if ($user['role'] === 'lto_staff') {
                            header('Location: lto_search.php');
                        } elseif ($user['role'] === 'pnp') {
                            header('Location: citations.php');
                        } else {
                            header('Location: index.php');
                        }
                        exit;
                    }
                } else {
                    $error = 'Invalid username or password.';
                }
            } catch (Exception $e) {
                // Handle database errors gracefully
                error_log("Login error: " . $e->getMessage());
                $error = 'System temporarily unavailable. Please contact your administrator if this persists.';
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* --- 1. Reset & Layout --- */
        :root {
            --primary: #4f46e5;
            --primary-light: #818cf8;
            --primary-dark: #3730a3;
            --accent: #06b6d4;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', 'Segoe UI', sans-serif; }

        body {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        /* Animated background orbs */
        body::before,
        body::after {
            content: '';
            position: fixed;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.3;
            z-index: 0;
        }
        body::before {
            width: 500px; height: 500px;
            background: var(--primary);
            top: -150px; right: -100px;
            animation: orbFloat1 8s ease-in-out infinite;
        }
        body::after {
            width: 400px; height: 400px;
            background: var(--accent);
            bottom: -100px; left: -100px;
            animation: orbFloat2 10s ease-in-out infinite;
        }
        @keyframes orbFloat1 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(-60px, 40px); }
        }
        @keyframes orbFloat2 {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(50px, -30px); }
        }

        .login-card {
            display: flex;
            width: 1000px;
            max-width: 95%;
            min-height: 620px;
            background: var(--white);
            border-radius: 24px;
            box-shadow: 0 25px 80px rgba(0,0,0,0.3), 0 0 0 1px rgba(255,255,255,0.05);
            overflow: hidden;
            position: relative;
            z-index: 1;
            animation: cardEntry 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cardEntry {
            from { opacity: 0; transform: translateY(30px) scale(0.98); }
            to { opacity: 1; transform: translateY(0) scale(1); }
        }

        /* --- 2. Left Panel --- */
        .left-panel {
            flex: 1.2;
            background: linear-gradient(160deg, #0f172a 0%, #1e293b 40%, #1a2744 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            overflow: hidden;
            perspective: 800px;
        }

        /* Subtle grid overlay */
        .left-panel::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            z-index: 0;
        }

        /* Glow accent */
        .left-panel::after {
            content: '';
            position: absolute;
            width: 300px; height: 300px;
            background: radial-gradient(circle, rgba(79, 70, 229, 0.15), transparent 70%);
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            z-index: 0;
        }

        .left-logo {
            width: 72px;
            height: 72px;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
            transition: transform 0.3s ease;
        }
        .left-logo:hover { transform: scale(1.08); }

        .stage-header {
            position: absolute;
            top: 32px;
            left: 0;
            right: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            z-index: 10;
        }

        .stage-title {
            color: rgba(255,255,255,0.9);
            letter-spacing: 3px;
            text-transform: uppercase;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .tmg-logo {
            width: 72px;
            height: 72px;
            object-fit: contain;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.4));
            transition: transform 0.3s ease;
        }
        .tmg-logo:hover { transform: scale(1.08); }

        .scene-container {
            width: 100%;
            height: 300px;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
            z-index: 1;
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
            transform: translateY(20px) scale(0.95);
            transition: all 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .scene.active {
            display: flex;
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .caption {
            margin-top: 50px;
            color: rgba(255,255,255,0.85);
            font-size: 1rem;
            font-weight: 400;
            letter-spacing: 1.5px;
            text-transform: uppercase;
        }

        /* Scene indicator dots */
        .scene-dots {
            position: absolute;
            bottom: 28px;
            display: flex;
            gap: 8px;
            z-index: 10;
        }
        .scene-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.25);
            transition: all 0.4s ease;
        }
        .scene-dot.active {
            background: var(--primary-light);
            box-shadow: 0 0 8px rgba(129, 140, 248, 0.5);
            width: 24px;
            border-radius: 4px;
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
            padding: 50px 48px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            background: var(--white);
        }

        .header h1 {
            font-size: 2rem;
            color: var(--gray-900);
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: -0.5px;
        }
        .header p {
            color: var(--gray-400);
            margin-bottom: 28px;
            font-size: 0.95rem;
        }

        /* Notices container */
        .notices {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }
        .input-group label {
            display: block;
            font-size: 0.8rem;
            color: var(--gray-500);
            font-weight: 600;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .input-group .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }
        .input-group .input-icon {
            position: absolute;
            left: 14px;
            color: var(--gray-300);
            font-size: 1rem;
            transition: color 0.3s;
            z-index: 2;
            pointer-events: none;
        }
        .input-group input {
            width: 100%;
            padding: 13px 15px 13px 42px;
            border: 2px solid var(--gray-200);
            border-radius: 12px;
            font-size: 0.95rem;
            color: var(--gray-800);
            background: var(--gray-50);
            transition: all 0.3s ease;
        }
        .input-group input::placeholder {
            color: var(--gray-300);
        }
        .input-group input:focus {
            outline: none;
            border-color: var(--primary);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.08);
        }
        .input-group input:focus ~ .input-icon,
        .input-group input:focus + .input-icon {
            color: var(--primary);
        }
        .input-group:focus-within .input-icon {
            color: var(--primary);
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            padding-right: 48px !important;
        }

        .password-toggle {
            position: absolute;
            right: 4px;
            background: none;
            border: none;
            color: var(--gray-400);
            cursor: pointer;
            padding: 10px;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
            border-radius: 8px;
        }

        .password-toggle:hover {
            color: var(--primary);
            background: rgba(79, 70, 229, 0.05);
        }

        .password-toggle i {
            font-size: 1rem;
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.25s ease;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
            letter-spacing: 0.3px;
        }
        .btn-login::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, transparent, rgba(255,255,255,0.1));
            opacity: 0;
            transition: opacity 0.3s;
        }
        .btn-login:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.35);
        }
        .btn-login:hover::before { opacity: 1; }
        .btn-login:active { transform: translateY(0); }
        .btn-login:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

        .alert {
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 0.82rem;
            line-height: 1.4;
            animation: alertSlide 0.3s ease;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-danger i { color: var(--danger); }

        .alert-success {
            background: #ecfdf5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-info {
            background: var(--gray-50);
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .alert-info i {
            font-size: 0.9rem;
            color: var(--primary-light);
            margin-top: 2px;
            flex-shrink: 0;
        }

        .alert-info strong {
            color: var(--gray-700);
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

        /* Footer text */
        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.78rem;
            color: var(--gray-300);
        }

        /* Responsive */
        @media (max-width: 900px) {
            .login-card {
                flex-direction: column;
                height: auto;
                width: 92%;
                min-height: auto;
            }

            .left-panel {
                min-height: 280px;
            }

            .right-panel {
                padding: 32px 28px;
            }

            .left-logo, .tmg-logo {
                width: 50px;
                height: 50px;
            }

            .stage-header {
                gap: 10px;
                top: 20px;
            }

            .header h1 {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 480px) {
            .login-card {
                width: 100%;
                border-radius: 0;
                min-height: 100vh;
            }

            .left-panel {
                min-height: 220px;
                border-radius: 0;
            }

            .right-panel {
                padding: 24px 20px;
            }

            .stage-header {
                flex-direction: row;
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

            <div class="scene-dots">
                <div class="scene-dot active"></div>
                <div class="scene-dot"></div>
                <div class="scene-dot"></div>
                <div class="scene-dot"></div>
                <div class="scene-dot"></div>
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

            <div class="notices">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Security Notice:</strong> Auto logout after <strong>15 min</strong> of inactivity.
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fas fa-shield-alt"></i>
                    <div>
                        <strong>Device Limit:</strong> Admin accounts limited to <strong>2 devices</strong>.
                    </div>
                </div>
            </div>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo generate_token(); ?>">

                <div class="input-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user input-icon"></i>
                        <input type="text" name="username" id="username"
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="input-group">
                    <label>Password</label>
                    <div class="input-wrapper password-wrapper">
                        <i class="fas fa-lock input-icon"></i>
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

            <div class="login-footer">
                B-TRACS &mdash; Baggao Traffic Citation System
            </div>
        </div>

    </div>

    <script>
        // Cycle Animations with dot indicators
        const scenes = document.querySelectorAll('.scene');
        const dots = document.querySelectorAll('.scene-dot');
        let index = 0;

        function cycleScenes() {
            scenes[index].classList.remove('active');
            dots[index].classList.remove('active');
            index = (index + 1) % scenes.length;
            scenes[index].classList.add('active');
            dots[index].classList.add('active');
        }

        setInterval(cycleScenes, 3000);

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

        // Disable button on submit and mark for page loader
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing In...';

            // Set flag for showing page loader after redirect
            sessionStorage.setItem('showPageLoader', 'true');
        });
    </script>
</body>
</html>
