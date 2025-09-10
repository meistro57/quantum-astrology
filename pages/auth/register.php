<?php
declare(strict_types=1);

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Core\Csrf;
use QuantumAstrology\Core\User;

Auth::requireGuest();

$errors = [];
$formData = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'password' => $_POST['password'] ?? '',
        'password_confirm' => $_POST['password_confirm'] ?? '',
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'timezone' => $_POST['timezone'] ?? 'UTC'
    ];

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (!Csrf::validateToken($csrfToken)) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $errors = Auth::getValidationErrors($formData);

        if (empty($errors)) {
            $result = Auth::register($formData);

            if ($result instanceof User) {
                Csrf::clearToken();
                Session::flash('success', 'Welcome to Quantum Astrology! Your account has been created.');
                header('Location: /dashboard');
                exit;
            }

            if ($result === Auth::ERROR_DUPLICATE) {
                $errors[] = 'Email or username already exists.';
            } else {
                $errors[] = 'Registration failed. Please try again.';
            }
        }
    }
}

$pageTitle = 'Create Account - Quantum Astrology';
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
            max-width: 500px;
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

        .form-row {
            display: flex;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--quantum-text);
        }

        .form-input, .form-select {
            width: 100%;
            padding: 0.875rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--quantum-text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus {
            outline: none;
            border-color: var(--quantum-primary);
            box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-select option {
            background: var(--quantum-dark);
            color: var(--quantum-text);
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

        .error-messages {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            padding: 0.875rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .error-messages ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        .error-messages li {
            margin-bottom: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="auth-container">
        <div class="auth-card">
            <h1 class="auth-title">Create Account</h1>
            <p class="auth-subtitle">Join Quantum Astrology and explore the cosmos</p>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::getToken()) ?>">
                <div class="form-group">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input"
                           placeholder="Choose a unique username"
                           value="<?= htmlspecialchars($formData['username'] ?? '') ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address *</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input"
                           placeholder="Enter your email address"
                           value="<?= htmlspecialchars($formData['email'] ?? '') ?>"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-input"
                               placeholder="Your first name"
                               value="<?= htmlspecialchars($formData['first_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="form-input"
                               placeholder="Your last name"
                               value="<?= htmlspecialchars($formData['last_name'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select">
                        <option value="UTC" <?= ($formData['timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                        <option value="America/New_York" <?= ($formData['timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                        <option value="America/Chicago" <?= ($formData['timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                        <option value="America/Denver" <?= ($formData['timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                        <option value="America/Los_Angeles" <?= ($formData['timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                        <option value="Europe/London" <?= ($formData['timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                        <option value="Europe/Paris" <?= ($formData['timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                        <option value="Europe/Berlin" <?= ($formData['timezone'] ?? '') === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin</option>
                        <option value="Asia/Tokyo" <?= ($formData['timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                        <option value="Australia/Sydney" <?= ($formData['timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           class="form-input"
                           placeholder="Create a secure password (min. 8 characters)"
                           required>
                </div>

                <div class="form-group">
                    <label for="password_confirm" class="form-label">Confirm Password *</label>
                    <input type="password" 
                           id="password_confirm" 
                           name="password_confirm" 
                           class="form-input"
                           placeholder="Repeat your password"
                           required>
                </div>

                <button type="submit" class="auth-button">Create Account</button>
            </form>

            <div class="auth-links">
                <p>Already have an account? <a href="/login">Sign in here</a></p>
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
