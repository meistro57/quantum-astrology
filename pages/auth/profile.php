<?php // pages/auth/profile.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Database\Connection;
use QuantumAstrology\Support\InputValidator;

Auth::requireLogin();

$user = Auth::user();
$success = Session::getFlash('success') ?? '';
$errors = [];

/**
 * Best-effort parse of "City, ST, ..." or "City, State, ..." into [city, state].
 *
 * @return array{0:string,1:string}
 */
function parseCityStateFromLocation(?string $locationName): array
{
    if ($locationName === null) {
        return ['', ''];
    }

    $parts = array_values(array_filter(array_map('trim', explode(',', $locationName)), static fn ($p) => $p !== ''));
    if (count($parts) < 2) {
        return ['', ''];
    }

    $city = $parts[0];
    $state = $parts[1];

    // Drop country-like values from state slot if string is like "City, USA".
    $stateUpper = strtoupper($state);
    if (in_array($stateUpper, ['US', 'USA', 'UNITED STATES', 'UNITED STATES OF AMERICA'], true)) {
        return [$city, ''];
    }

    return [$city, $state];
}

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
    $birthCityRaw = trim($_POST['birth_city'] ?? '');
    $birthStateRaw = trim($_POST['birth_state'] ?? '');

    $currentPasswordRaw = (string) ($_POST['current_password'] ?? '');
    $newPasswordRaw = (string) ($_POST['new_password'] ?? '');
    $confirmPasswordRaw = (string) ($_POST['confirm_password'] ?? '');
    $wantsPasswordChange = $currentPasswordRaw !== '' || $newPasswordRaw !== '' || $confirmPasswordRaw !== '';
    
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
        if ($birthLocationName === '' && ($birthCityRaw !== '' || $birthStateRaw !== '')) {
            $birthLocationName = trim($birthCityRaw . ($birthCityRaw !== '' && $birthStateRaw !== '' ? ', ' : '') . $birthStateRaw);
        }
        $updateData['birth_location_name'] = $birthLocationName !== '' ? $birthLocationName : null;
    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    }

    if ($wantsPasswordChange) {
        if ($currentPasswordRaw === '' || $newPasswordRaw === '' || $confirmPasswordRaw === '') {
            $errors[] = 'To change your password, provide current password, new password, and confirmation.';
        } elseif (!$user->verifyPassword($currentPasswordRaw)) {
            $errors[] = 'Current password is incorrect.';
        } elseif (strlen($newPasswordRaw) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        } elseif ($newPasswordRaw !== $confirmPasswordRaw) {
            $errors[] = 'New password confirmation does not match.';
        }
    }

    if (empty($errors)) {
        $pdo = Connection::getInstance();
        $useTransaction = !$pdo->inTransaction();

        try {
            if ($useTransaction) {
                $pdo->beginTransaction();
            }

            if (!$user->update($updateData)) {
                throw new RuntimeException('Failed to update profile. Please try again.');
            }

            if ($wantsPasswordChange && !$user->updatePassword($newPasswordRaw)) {
                throw new RuntimeException('Failed to update password. Please try again.');
            }

            if ($useTransaction) {
                $pdo->commit();
            }

            $flashMessage = $wantsPasswordChange
                ? 'Profile and password updated successfully!'
                : 'Profile updated successfully!';
            Session::flash('success', $flashMessage);
            header('Location: /profile');
            exit;
        } catch (Throwable $e) {
            if ($useTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = $e->getMessage();
        }
    }
}

$pageTitle = 'Profile Settings - Quantum Astrology';
[$parsedBirthCity, $parsedBirthState] = parseCityStateFromLocation($user->getBirthLocationName());
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
        .location-status {
            margin-top: 0.35rem;
            margin-bottom: 0;
        }
        .location-status.success { color: #7dd87d; }
        .location-status.error { color: #ff6b6b; }
        .city-state-row {
            display: flex;
            gap: 0.75rem;
            align-items: center;
        }
        .city-state-row .form-input {
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

            .city-state-row {
                flex-direction: column;
                align-items: stretch;
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
            <button type="button" class="back-button" onclick="(function(){const ref=document.referrer||''; if(ref.startsWith(window.location.origin) && !ref.includes('/profile')){ window.location.href=ref; } else { window.location.href='/dashboard'; } })();">
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
                           value="<?= htmlspecialchars($_POST['username'] ?? $user->getUsername()) ?>"
                           required>
                </div>

                <div class="form-group">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" 
                           id="email" 
                           name="email" 
                           class="form-input"
                           value="<?= htmlspecialchars($_POST['email'] ?? $user->getEmail()) ?>"
                           required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name" class="form-label">First Name</label>
                        <input type="text" 
                               id="first_name" 
                               name="first_name" 
                               class="form-input"
                               value="<?= htmlspecialchars($_POST['first_name'] ?? ($user->getFirstName() ?? '')) ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_name" class="form-label">Last Name</label>
                        <input type="text" 
                               id="last_name" 
                               name="last_name" 
                               class="form-input"
                               value="<?= htmlspecialchars($_POST['last_name'] ?? ($user->getLastName() ?? '')) ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="timezone" class="form-label">Timezone</label>
                    <select id="timezone" name="timezone" class="form-select">
                        <?php $savedTimezone = $_POST['timezone'] ?? $user->getTimezone(); ?>
                        <option value="UTC" <?= $savedTimezone === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                        <option value="America/New_York" <?= $savedTimezone === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                        <option value="America/Chicago" <?= $savedTimezone === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                        <option value="America/Denver" <?= $savedTimezone === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                        <option value="America/Los_Angeles" <?= $savedTimezone === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                        <option value="Europe/London" <?= $savedTimezone === 'Europe/London' ? 'selected' : '' ?>>London</option>
                        <option value="Europe/Paris" <?= $savedTimezone === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                        <option value="Europe/Berlin" <?= $savedTimezone === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin</option>
                        <option value="Asia/Tokyo" <?= $savedTimezone === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                        <option value="Australia/Sydney" <?= $savedTimezone === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
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

                    <div class="form-group">
                        <label class="form-label">City and State</label>
                        <div class="city-state-row">
                            <input type="text"
                                   id="birth_city"
                                   name="birth_city"
                                   class="form-input"
                                   placeholder="City (e.g., New York)"
                                   autocomplete="address-level2"
                                   value="<?= htmlspecialchars($_POST['birth_city'] ?? $parsedBirthCity) ?>">
                            <input type="text"
                                   id="birth_state"
                                   name="birth_state"
                                   class="form-input"
                                   placeholder="State (e.g., NY)"
                                   autocomplete="address-level1"
                                   value="<?= htmlspecialchars($_POST['birth_state'] ?? $parsedBirthState) ?>">
                            <button type="button" id="city_state_button" class="btn btn-secondary">Find Coordinates</button>
                        </div>
                        <p id="city_state_status" class="helper-text location-status">Enter city and state to auto-fill birth latitude and longitude.</p>
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

                <div class="form-section">
                    <h3 class="section-title">Change Password (optional)</h3>
                    <p class="helper-text">Leave blank to keep your current password unchanged.</p>

                    <div class="form-group">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password"
                               id="current_password"
                               name="current_password"
                               class="form-input"
                               autocomplete="current-password">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password"
                                   id="new_password"
                                   name="new_password"
                                   class="form-input"
                                   minlength="8"
                                   autocomplete="new-password">
                        </div>

                        <div class="form-group">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password"
                                   id="confirm_password"
                                   name="confirm_password"
                                   class="form-input"
                                   minlength="8"
                                   autocomplete="new-password">
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

        document.addEventListener('DOMContentLoaded', function() {
            const cityInput = document.getElementById('birth_city');
            const stateInput = document.getElementById('birth_state');
            const latInput = document.getElementById('birth_latitude');
            const lonInput = document.getElementById('birth_longitude');
            const locationInput = document.getElementById('birth_location_name');
            const cityStateButton = document.getElementById('city_state_button');
            const cityStateStatus = document.getElementById('city_state_status');

            if (!cityInput || !stateInput || !latInput || !lonInput || !cityStateButton || !cityStateStatus) {
                return;
            }

            const setCityStateStatus = (message, variant = 'info') => {
                cityStateStatus.textContent = message;
                cityStateStatus.classList.remove('success', 'error');
                if (variant === 'success' || variant === 'error') {
                    cityStateStatus.classList.add(variant);
                }
            };

            const setLocationNameFromCityState = (city, state) => {
                if (!locationInput || locationInput.value.trim() !== '') {
                    return;
                }
                if (city && state) {
                    locationInput.value = `${city}, ${state}, USA`;
                }
            };

            let lastLookupKey = '';
            let lookupTimer = null;

            const resolveCityState = async (manual = false) => {
                const city = cityInput.value.trim();
                const state = stateInput.value.trim();
                if (!city || !state) {
                    if (manual) {
                        setCityStateStatus('Please enter both city and state.', 'error');
                    }
                    return;
                }

                const lookupKey = `${city.toLowerCase()}|${state.toLowerCase()}`;
                if (!manual && lookupKey === lastLookupKey) {
                    return;
                }
                lastLookupKey = lookupKey;

                setCityStateStatus('Finding coordinates...');
                cityStateButton.disabled = true;
                cityStateButton.textContent = 'Finding...';

                try {
                    const response = await fetch('/api/resolve_location.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ city, state }),
                    });
                    const payload = await response.json();
                    if (!response.ok || !payload?.ok) {
                        const message = payload?.error?.message ?? 'Could not resolve the location.';
                        throw new Error(message);
                    }

                    latInput.value = payload.latitude;
                    lonInput.value = payload.longitude;
                    setLocationNameFromCityState(city, state);
                    setCityStateStatus('Coordinates found and filled in.', 'success');
                } catch (error) {
                    if (manual) {
                        const message = error instanceof Error ? error.message : 'Could not resolve the location.';
                        setCityStateStatus(message, 'error');
                    } else {
                        setCityStateStatus('Could not auto-resolve this city/state. You can click Find Coordinates and try again.');
                    }
                } finally {
                    cityStateButton.disabled = false;
                    cityStateButton.textContent = 'Find Coordinates';
                }
            };

            const scheduleLookup = () => {
                if (lookupTimer) {
                    clearTimeout(lookupTimer);
                }
                lookupTimer = setTimeout(() => {
                    const hasCoords = latInput.value.trim() !== '' && lonInput.value.trim() !== '';
                    if (!hasCoords) {
                        resolveCityState(false);
                    }
                }, 600);
            };

            cityInput.addEventListener('input', scheduleLookup);
            cityInput.addEventListener('blur', () => resolveCityState(false));

            stateInput.addEventListener('input', scheduleLookup);
            stateInput.addEventListener('blur', () => resolveCityState(false));
            stateInput.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    resolveCityState(true);
                }
            });

            cityStateButton.addEventListener('click', () => resolveCityState(true));
        });
    </script>
</body>
</html>
