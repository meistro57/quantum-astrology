<?php
declare(strict_types=1);

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Core\Logger;

Auth::requireLogin();

$user = Auth::user();
$errors = [];
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'name' => trim($_POST['name'] ?? ''),
        'birth_datetime' => trim($_POST['birth_date'] ?? '') . ' ' . trim($_POST['birth_time'] ?? ''),
        'birth_timezone' => $_POST['birth_timezone'] ?? 'UTC',
        'birth_latitude' => (float) ($_POST['birth_latitude'] ?? 0),
        'birth_longitude' => (float) ($_POST['birth_longitude'] ?? 0),
        'birth_location_name' => trim($_POST['birth_location_name'] ?? ''),
        'house_system' => $_POST['house_system'] ?? 'P',
        'is_public' => isset($_POST['is_public']) ? 1 : 0,
        'user_id' => $user->getId()
    ];
    
    // Validate form data
    if (empty($formData['name'])) {
        $errors[] = 'Chart name is required';
    }
    
    if (empty($_POST['birth_date']) || empty($_POST['birth_time'])) {
        $errors[] = 'Birth date and time are required';
    }
    
    if ($formData['birth_latitude'] === 0.0 && $formData['birth_longitude'] === 0.0) {
        $errors[] = 'Birth location (latitude/longitude) is required';
    }
    
    // Validate datetime
    try {
        $datetime = new DateTime($formData['birth_datetime']);
    } catch (Exception $e) {
        $errors[] = 'Invalid birth date or time format';
    }
    
    if (empty($errors)) {
        $chart = Chart::generateNatalChart($formData);
        
        if ($chart) {
            Session::flash('success', 'Natal chart created successfully!');
            header('Location: /charts/view?id=' . $chart->getId());
            exit;
        } else {
            Logger::error('Chart creation failed', [
                'user_id' => $user->getId(),
                'chart_name' => $formData['name'] ?? null
            ]);
            $errors[] = 'Failed to create chart. Please try again.';
        }
    }
}

$swissEph = new SwissEphemeris();
$houseSystems = $swissEph->getSupportedHouseSystems();

$pageTitle = 'Create New Chart - Quantum Astrology';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/quantum-dashboard.css">
    <style>
        .chart-creator {
            max-width: 900px;
            margin: 0 auto;
            padding: 2rem;
        }

        .creator-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .creator-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .creator-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 1.1rem;
        }

        .chart-form {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 2.5rem;
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--quantum-gold);
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid rgba(255, 215, 0, 0.2);
        }

        .form-row {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            flex: 1;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--quantum-text);
        }

        .required {
            color: var(--quantum-gold);
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 0.875rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: var(--quantum-text);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
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

        .coordinate-inputs {
            display: flex;
            gap: 1rem;
        }

        .coordinate-group {
            flex: 1;
            position: relative;
        }

        .coordinate-label {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.5);
            font-size: 0.85rem;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-checkbox {
            width: 18px;
            height: 18px;
            accent-color: var(--quantum-primary);
        }

        .location-helper {
            background: rgba(74, 144, 226, 0.1);
            border: 1px solid rgba(74, 144, 226, 0.3);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.875rem 2rem;
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

        .error-messages {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid rgba(220, 53, 69, 0.3);
            color: #ff6b6b;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }

        .error-messages ul {
            margin: 0;
            padding-left: 1.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .coordinate-inputs {
                flex-direction: column;
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="particles-container"></div>
    
    <div class="chart-creator">
        <div class="creator-header">
            <h1 class="creator-title">Create New Chart</h1>
            <p class="creator-subtitle">Generate a precise natal chart using Swiss Ephemeris calculations</p>
        </div>

        <form method="POST" class="chart-form">
            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Chart Information Section -->
            <div class="form-section">
                <h3 class="section-title">Chart Information</h3>
                
                <div class="form-group">
                    <label for="name" class="form-label">Chart Name <span class="required">*</span></label>
                    <input type="text" 
                           id="name" 
                           name="name" 
                           class="form-input"
                           placeholder="e.g., John's Natal Chart"
                           value="<?= htmlspecialchars($formData['name'] ?? '') ?>"
                           required>
                </div>
            </div>

            <!-- Birth Information Section -->
            <div class="form-section">
                <h3 class="section-title">Birth Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="birth_date" class="form-label">Birth Date <span class="required">*</span></label>
                        <input type="date" 
                               id="birth_date" 
                               name="birth_date" 
                               class="form-input"
                               value="<?= htmlspecialchars($_POST['birth_date'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="birth_time" class="form-label">Birth Time <span class="required">*</span></label>
                        <input type="time" 
                               id="birth_time" 
                               name="birth_time" 
                               class="form-input"
                               step="60"
                               value="<?= htmlspecialchars($_POST['birth_time'] ?? '') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label for="birth_timezone" class="form-label">Timezone</label>
                        <select id="birth_timezone" name="birth_timezone" class="form-select">
                            <option value="UTC" <?= ($formData['birth_timezone'] ?? 'UTC') === 'UTC' ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                            <option value="America/New_York" <?= ($formData['birth_timezone'] ?? '') === 'America/New_York' ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                            <option value="America/Chicago" <?= ($formData['birth_timezone'] ?? '') === 'America/Chicago' ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                            <option value="America/Denver" <?= ($formData['birth_timezone'] ?? '') === 'America/Denver' ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                            <option value="America/Los_Angeles" <?= ($formData['birth_timezone'] ?? '') === 'America/Los_Angeles' ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                            <option value="Europe/London" <?= ($formData['birth_timezone'] ?? '') === 'Europe/London' ? 'selected' : '' ?>>London</option>
                            <option value="Europe/Paris" <?= ($formData['birth_timezone'] ?? '') === 'Europe/Paris' ? 'selected' : '' ?>>Paris</option>
                            <option value="Europe/Berlin" <?= ($formData['birth_timezone'] ?? '') === 'Europe/Berlin' ? 'selected' : '' ?>>Berlin</option>
                            <option value="Asia/Tokyo" <?= ($formData['birth_timezone'] ?? '') === 'Asia/Tokyo' ? 'selected' : '' ?>>Tokyo</option>
                            <option value="Australia/Sydney" <?= ($formData['birth_timezone'] ?? '') === 'Australia/Sydney' ? 'selected' : '' ?>>Sydney</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Location Information Section -->
            <div class="form-section">
                <h3 class="section-title">Birth Location</h3>
                
                <div class="form-group">
                    <label for="birth_location_name" class="form-label">Location Name</label>
                    <input type="text" 
                           id="birth_location_name" 
                           name="birth_location_name" 
                           class="form-input"
                           placeholder="e.g., New York, NY, USA"
                           value="<?= htmlspecialchars($formData['birth_location_name'] ?? '') ?>">
                </div>

                <div class="coordinate-inputs">
                    <div class="coordinate-group">
                        <label for="birth_latitude" class="form-label">Latitude <span class="required">*</span></label>
                        <input type="number" 
                               id="birth_latitude" 
                               name="birth_latitude" 
                               class="form-input"
                               placeholder="40.7128"
                               step="0.000001"
                               min="-90"
                               max="90"
                               value="<?= htmlspecialchars((string)($formData['birth_latitude'] ?? '')) ?>"
                               required>
                        <span class="coordinate-label">°N</span>
                    </div>

                    <div class="coordinate-group">
                        <label for="birth_longitude" class="form-label">Longitude <span class="required">*</span></label>
                        <input type="number" 
                               id="birth_longitude" 
                               name="birth_longitude" 
                               class="form-input"
                               placeholder="-74.0060"
                               step="0.000001"
                               min="-180"
                               max="180"
                               value="<?= htmlspecialchars((string)($formData['birth_longitude'] ?? '')) ?>"
                               required>
                        <span class="coordinate-label">°E</span>
                    </div>
                </div>

                <div class="location-helper">
                    <strong>Need coordinates?</strong> Use online tools like Google Maps or geographic coordinate finders. 
                    Right-click on a location in Google Maps to get precise latitude and longitude values.
                </div>
            </div>

            <!-- Chart Settings Section -->
            <div class="form-section">
                <h3 class="section-title">Chart Settings</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="house_system" class="form-label">House System</label>
                        <select id="house_system" name="house_system" class="form-select">
                            <?php foreach ($houseSystems as $code => $name): ?>
                                <option value="<?= htmlspecialchars($code) ?>" 
                                        <?= ($formData['house_system'] ?? 'P') === $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" 
                                   id="is_public" 
                                   name="is_public" 
                                   class="form-checkbox"
                                   <?= ($formData['is_public'] ?? false) ? 'checked' : '' ?>>
                            <label for="is_public" class="form-label">Make this chart public</label>
                        </div>
                        <small style="color: rgba(255, 255, 255, 0.6);">
                            Public charts can be viewed by other users (birth data remains private)
                        </small>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="/dashboard" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Chart</button>
            </div>
        </form>
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

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.chart-form');
            const latInput = document.getElementById('birth_latitude');
            const lngInput = document.getElementById('birth_longitude');

            // Update coordinate labels based on values
            function updateCoordinateLabels() {
                const latLabel = document.querySelector('[for="birth_latitude"] + input + .coordinate-label');
                const lngLabel = document.querySelector('[for="birth_longitude"] + input + .coordinate-label');
                
                const lat = parseFloat(latInput.value);
                const lng = parseFloat(lngInput.value);
                
                if (latLabel) latLabel.textContent = lat >= 0 ? '°N' : '°S';
                if (lngLabel) lngLabel.textContent = lng >= 0 ? '°E' : '°W';
            }

            latInput.addEventListener('input', updateCoordinateLabels);
            lngInput.addEventListener('input', updateCoordinateLabels);

            // Initialize labels
            updateCoordinateLabels();
        });
    </script>
</body>
</html>