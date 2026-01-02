<?php // pages/auth/profile.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Support\InputValidator;

Auth::requireLogin();

$user = Auth::user();
$success = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $updateData = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'first_name' => trim($_POST['first_name'] ?? ''),
        'last_name' => trim($_POST['last_name'] ?? ''),
        'timezone' => $_POST['timezone'] ?? 'UTC',
    ];

    $birthDateRaw = trim($_POST['birth_date'] ?? '');
    $birthTimeRaw = trim($_POST['birth_time'] ?? '');
    $birthTimezoneRaw = trim($_POST['birth_timezone'] ?? '');
    $birthLocationName = trim($_POST['birth_location_name'] ?? '');
    $birthLatitudeRaw = $_POST['birth_latitude'] ?? null;
    $birthLongitudeRaw = $_POST['birth_longitude'] ?? null;
    
    // Basic validation
    if (empty($updateData['username'])) {
        $errors[] = 'Username is required';
    }
    
    if (empty($updateData['email']) || !filter_var($updateData['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Valid email is required';
    }
    
    // Check for existing username/email (excluding current user)
    $existingUserByUsername = \QuantumAstrology\Core\User::findByUsername($updateData['username']);
    if ($existingUserByUsername && $existingUserByUsername->getId() !== $user->getId()) {
        $errors[] = 'Username is already taken';
    }
    
    $existingUserByEmail = \QuantumAstrology\Core\User::findByEmail($updateData['email']);
    if ($existingUserByEmail && $existingUserByEmail->getId() !== $user->getId()) {
        $errors[] = 'Email is already in use';
    }
    
    // Birth data validation (optional but validated when provided)
    try {
        if ($birthDateRaw !== '') {
            $birthDate = DateTimeImmutable::createFromFormat('Y-m-d', $birthDateRaw);
            if (!$birthDate) {
                throw new InvalidArgumentException('Birth date must be in YYYY-MM-DD format.');
            }
            $updateData['birth_date'] = $birthDate->format('Y-m-d');
        } else {
            $updateData['birth_date'] = null;
        }

        if ($birthTimeRaw !== '') {
            $time = DateTimeImmutable::createFromFormat('H:i', $birthTimeRaw)
                ?: DateTimeImmutable::createFromFormat('H:i:s', $birthTimeRaw);

            if (!$time) {
                throw new InvalidArgumentException('Birth time must be in 24-hour HH:MM format.');
            }

            $updateData['birth_time'] = $time->format('H:i:s');
        } else {
            $updateData['birth_time'] = null;
        }

        $birthTimezone = InputValidator::normaliseTimezone($birthTimezoneRaw, false);
        if (($updateData['birth_date'] || $updateData['birth_time']) && $birthTimezone === null) {
            throw new InvalidArgumentException('Birth timezone is required when saving birth date or time.');
        }
        $updateData['birth_timezone'] = $birthTimezone;

        $birthLatitude = InputValidator::parseLatitude($birthLatitudeRaw, false);
        $birthLongitude = InputValidator::parseLongitude($birthLongitudeRaw, false);

        if (($birthLatitude !== null && $birthLongitude === null) || ($birthLatitude === null && $birthLongitude !== null)) {
            throw new InvalidArgumentException('Please provide both latitude and longitude for your birth location.');
        }

        $updateData['birth_latitude'] = $birthLatitude;
        $updateData['birth_longitude'] = $birthLongitude;
        $updateData['birth_location_name'] = $birthLocationName !== '' ? $birthLocationName : null;
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }

    if (empty($errors)) {
        if ($user->update($updateData)) {
            $success = 'Profile updated successfully!';
            $user = Auth::user(); // Refresh user data
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
        }
    }
}

$pageTitle = 'Profile Settings - Quantum Astrology';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 2rem;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .profile-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .profile-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .profile-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
        }

        .form-row {
            display: flex;
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
            flex: 1;
        }

        .form-section {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 0.75rem;
        }

        .helper-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.95rem;
            margin-bottom: 1rem;
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

        .form-select option {
            background: var(--quantum-dark);
            color: var(--quantum-text);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: var(--quantum-text);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .success-message {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #4ade80;
            padding: 0.875rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            text-align: center;
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

        .user-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.6);
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .button-group {
                flex-direction: column;
            }
            
            .user-meta {
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>

    <div class="profile-container">
        <div class="page-actions">
            <button type="button" class="back-button" onclick="window.history.length > 1 ? window.history.back() : window.location.href='/dashboard'">
                <span class="icon" aria-hidden="true">←</span>
                <span>Back</span>
            </button>
        </div>
        <div class="profile-header">
            <h1 class="profile-title">Profile Settings</h1>
            <p class="profile-subtitle">Manage your Quantum Astrology account</p>
        </div>

        <div class="profile-card">
            <?php if ($success): ?>
                <div class="success-message">
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

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
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           class="form-input"
                           value="<?= htmlspecialchars($user->getUsername()) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input"
                           value="<?= htmlspecialchars($user->getEmail()) ?>"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-input"
                               value="<?= htmlspecialchars($user->getFirstName() ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="form-input"
                               value="<?= htmlspecialchars($user->getLastName() ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select">
                        <option value="UTC" <?= $user->getTimezone() === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                        <option value="America/New_York" <?= $user->getTimezone() === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                        <option value="America/Chicago" <?= $user->getTimezone() === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                        <option value="America/Denver" <?= $user->getTimezone() === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                        <option value="America/Los_Angeles" <?= $user->getTimezone() === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                        <option value="Europe/London" <?= $user->getTimezone() === 'Europe/London' ? 'selected' : '' ?>>London</option>
                        <option value="Europe/Paris" <?= $user->getTimezone() === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                        <option value="Europe/Berlin" <?= $user->getTimezone() === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin</option>
                        <option value="Asia/Tokyo" <?= $user->getTimezone() === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                        <option value="Australia/Sydney" <?= $user->getTimezone() === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
                    </select>
                </div>

                <div class="form-section">
                    <h3 class="section-title">Saved Birth Details (optional)</h3>
                    <p class="helper-text">Store your personal birth information to quickly auto-fill the chart creator. Leave blank if you prefer to enter details manually each time.</p>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_date" class="form-label">Birth Date</label>
                            <input type="date"
                                   id="birth_date"
                                   name="birth_date"
                                   class="form-input"
                                   value="<?= htmlspecialchars($_POST['birth_date'] ?? ($user->getBirthDate() ?? '')) ?>">
                        </div>

                        <div class="form-group">
                            <label for="birth_time" class="form-label">Birth Time</label>
                            <input type="time"
                                   id="birth_time"
                                   name="birth_time"
                                   class="form-input"
                                   step="60"
                                   value="<?= htmlspecialchars($_POST['birth_time'] ?? ($user->getBirthTime() ? substr($user->getBirthTime(), 0, 5) : '')) ?>">
                        </div>

                        <div class="form-group">
                            <label for="birth_timezone" class="form-label">Birth Timezone</label>
                            <select id="birth_timezone" name="birth_timezone" class="form-select">
                                <?php $savedBirthTz = $_POST['birth_timezone'] ?? ($user->getBirthTimezone() ?? ''); ?>
                                <option value="" <?= $savedBirthTz === '' ? 'selected' : '' ?>>-- Select timezone --</option>
                                <option value="UTC" <?= $savedBirthTz === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                                <option value="America/New_York" <?= $savedBirthTz === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                                <option value="America/Chicago" <?= $savedBirthTz === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                                <option value="America/Denver" <?= $savedBirthTz === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                                <option value="America/Los_Angeles" <?= $savedBirthTz === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                                <option value="Europe/London" <?= $savedBirthTz === 'Europe/London' ? 'selected' : '' ?>>London</option>
                                <option value="Europe/Paris" <?= $savedBirthTz === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                                <option value="Europe/Berlin" <?= $savedBirthTz === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin</option>
                                <option value="Asia/Tokyo" <?= $savedBirthTz === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                                <option value="Australia/Sydney" <?= $savedBirthTz === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="birth_location_name" class="form-label">Birth Location Name</label>
                        <input type="text"
                               id="birth_location_name"
                               name="birth_location_name"
                               class="form-input"
                               placeholder="e.g., London, UK"
                               value="<?= htmlspecialchars($_POST['birth_location_name'] ?? ($user->getBirthLocationName() ?? '')) ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_latitude" class="form-label">Birth Latitude</label>
                            <input type="text"
                                   id="birth_latitude"
                                   name="birth_latitude"
                                   class="form-input"
                                   placeholder="51.5074 N"
                                   value="<?= htmlspecialchars($_POST['birth_latitude'] ?? ($user->getBirthLatitude() ?? '')) ?>">
                        </div>

                        <div class="form-group">
                            <label for="birth_longitude" class="form-label">Birth Longitude</label>
                            <input type="text"
                                   id="birth_longitude"
                                   name="birth_longitude"
                                   class="form-input"
                                   placeholder="0.1278 W"
                                   value="<?= htmlspecialchars($_POST['birth_longitude'] ?? ($user->getBirthLongitude() ?? '')) ?>">
                        </div>
                    </div>
                </div>

                <div class="button-group">
                    <a href="/dashboard" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </div>
            </form>

            <div class="user-meta">
                <span>Member since: <?= date('F j, Y', strtotime($user->getCreatedAt())) ?></span>
                <span>User ID: <?= $user->getId() ?></span>
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