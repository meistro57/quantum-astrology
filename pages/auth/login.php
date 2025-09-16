<?php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;

Auth::requireGuest();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailOrUsername = trim($_POST['email_or_username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($emailOrUsername) || empty($password)) {
        $error = 'Please enter both email/username and password.';
    } else {
        if (Auth::attempt($emailOrUsername, $password)) {
            Session::flash('success', 'Welcome back to Quantum Astrology!');
            header('Location: /dashboard');
            exit;
        } else {
            $error = 'Invalid credentials. Please try again.';
        }
    }
}

$pageTitle = 'Sign In - Quantum Astrology';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: var(--quantum-darker);
            position: relative;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .auth-title {
            font-size: 2rem;
            font-weight: 700;
            text-align: center;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .auth-subtitle {
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--quantum-text);
        }

        .form-input {
            width: 100%;
            padding: 0.875rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--quantum-text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--quantum-primary);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .auth-button {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .auth-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .auth-links {
            text-align: center;
        }

        .auth-links a {
            color: var(--quantum-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .auth-links a:hover {
            color: var(--quantum-gold);
        }

        .error-message {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            padding: 0.875rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Welcome Back</h1>
            <p class="auth-subtitle">Sign in to your Quantum Astrology account</p>

            <?php if ($error): ?>
                <div class="error-message">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="email_or_username" class="form-label">Email or Username</label>
                    <input type="text" 
                           id="email_or_username" 
                           name="email_or_username" 
                           class="form-input"
                           placeholder="Enter your email or username"
                           value="<?= htmlspecialchars($_POST['email_or_username'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input"
                           placeholder="Enter your password"
                           required>
                </div>

                <button type="submit" class="auth-button">Sign In</button>
            </form>

            <div class="auth-links">
                <p>Don't have an account? <a href="/register">Create one here</a></p>
            </div>
        </div>
    </div>

    <script>
        // Add particle animation
        const particlesContainer = document.querySelector('.particles-container');
        
        function createParticle() {
            const particle = document.createElement('div');
            particle.className = 'particle';
            particle.style.left = Math.random() * 100 + '%';
            particle.style.animationDelay = Math.random() * 15 + 's';
            particle.style.animationDuration = (Math.random() * 10 + 10) + 's';
            particlesContainer.appendChild(particle);
            
            setTimeout(() => {
                particle.remove();
            }, 20000);
        }
        
        for (let i = 0; i < 50; i++) {
            setTimeout(createParticle, i * 200);
        }
        
        setInterval(() => {
            createParticle();
        }, 1000);
    </script>
</body>
</html>