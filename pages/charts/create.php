<?php
# create.php
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

use QuantumAstrology\Core\Auth;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Support\InputValidator;

Auth::requireLogin();

$user = Auth::user();
$errors = [];
$success = '';
$formData = [];

/**
 * Helpers
 */
function normaliseDate(string $date): string {
    $date = trim($date);
    // Accept M/D/Y -> convert to Y-m-d
    if (preg_match('#^\d{1,2}/\d{1,2}/\d{2,4}$#', $date)) {
        [$m,$d,$y] = array_map('intval', explode('/', $date));
        if ($y < 100) { $y += 1900; } // adjust if you prefer 2000s
        return sprintf('%04d-%02d-%02d', $y, $m, $d);
    }
    // ISO already?
    if (preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
        return $date;
    }
    $t = strtotime($date);
    if ($t === false) {
        throw new InvalidArgumentException('Unparseable birth date');
    }
    return date('Y-m-d', $t);
}

function normaliseTime(string $time, ?DateTimeZone $tz = null): string {
    $time = trim($time);
    $tz = $tz ?? new DateTimeZone('UTC');
    try {
        $dt = new DateTimeImmutable($time, $tz); // handles "7:30 PM", "19:30", etc.
        return $dt->format('H:i:s');
    } catch (Throwable $e) {
        $t = strtotime($time);
        if ($t === false) throw new InvalidArgumentException('Unparseable birth time');
        return gmdate('H:i:s', $t);
    }
}

function parseLocalToUtc(string $dateIso, string $timeIso, string $tzName): array {
    try {
        $tz = new DateTimeZone($tzName);
    } catch (Throwable $e) {
        throw new InvalidArgumentException("Invalid timezone: {$tzName}");
    }

    $local = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', "{$dateIso} {$timeIso}", $tz);
    if ($local === false) {
        $local = new DateTimeImmutable("{$dateIso} {$timeIso}", $tz); // lenient fallback
    }
    $utc = $local->setTimezone(new DateTimeZone('UTC'));

    $offsetSeconds = $tz->getOffset($local);
    $offsetMinutes = intdiv($offsetSeconds, 60);

    return [
        'birth_utc'        => $utc->format('Y-m-d H:i:s'),
        'birth_date_local' => $local->format('Y-m-d'),
        'birth_time_local' => $local->format('H:i:s'),
        'birth_tz'         => $tz->getName(),
        'birth_offset_min' => $offsetMinutes,
    ];
}

function normaliseLat(mixed $s): ?float {
    return InputValidator::parseLatitude($s, false);
}

function normaliseLon(mixed $s): ?float {
    return InputValidator::parseLongitude($s, false);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Raw inputs
    $rawName = trim($_POST['name'] ?? '');
    $rawDate = (string)($_POST['birth_date'] ?? '');
    $rawTime = (string)($_POST['birth_time'] ?? '');
    $rawTz   = (string)($_POST['birth_timezone'] ?? 'UTC');

    $rawLat  = $_POST['birth_latitude']  ?? null;
    $rawLon  = $_POST['birth_longitude'] ?? null;

    $locationStr = trim((string)($_POST['birth_location_name'] ?? ''));
    $houseSystem = $_POST['house_system'] ?? 'P';
    $isPublic    = isset($_POST['is_public']) ? 1 : 0;

    // Basic required fields
    if ($rawName === '') {
        $errors[] = 'Chart name is required';
    }
    if ($rawDate === '' || $rawTime === '') {
        $errors[] = 'Birth date and time are required';
    }

    try {
        // Bail early if already missing requireds
        if (!empty($errors)) {
            throw new RuntimeException('Validation failed');
        }

        // Normalise date/time/timezone
        $dateIso = normaliseDate($rawDate);
        $tzName  = InputValidator::normaliseTimezone($rawTz, false) ?? 'UTC';
        $tzObj   = new DateTimeZone($tzName);
        $timeIso = normaliseTime($rawTime, $tzObj);

        // Lat/Lon
        $lat = normaliseLat($rawLat);
        $lon = normaliseLon($rawLon);
        if ($lat === null || $lon === null) {
            $errors[] = 'Birth location (latitude/longitude) is required';
            throw new RuntimeException('Validation failed');
        }

        // Compute UTC, keep locals
        $dt = parseLocalToUtc($dateIso, $timeIso, $tzName);

        // Build formData for the domain layer
        $formData = [
            'name'                 => $rawName,

            // legacy keys (if your domain/service layer expects them)
            'birth_datetime'       => "{$dateIso} {$timeIso}", // local ISO (clean)
            'birth_timezone'       => $dt['birth_tz'],
            'birth_latitude'       => $lat,
            'birth_longitude'      => $lon,
            'birth_location_name'  => $locationStr,
            'house_system'         => $houseSystem,
            'is_public'            => $isPublic,
            'user_id'              => (int)$user->getId(),

            // clean, DB-safe keys (preferred for inserts)
            'birth_utc'            => $dt['birth_utc'],          // DATETIME UTC
            'birth_date_local'     => $dt['birth_date_local'],   // DATE
            'birth_time_local'     => $dt['birth_time_local'],   // TIME
            'birth_offset_min'     => $dt['birth_offset_min'],   // SMALLINT minutes
        ];

        // Do the thing
        $chart = Chart::generateNatalChart($formData);

        if ($chart) {
            Session::flash('success', 'Natal chart created successfully!');
            header('Location: /quantum-astrology/charts/view?id=' . $chart->getId());
            exit;
        } else {
            $errors[] = 'Failed to create chart. Please try again.';
        }

    } catch (InvalidArgumentException $e) {
        $errors[] = $e->getMessage();
    } catch (RuntimeException $e) {
        // validation errors already added
    } catch (Throwable $e) {
        $errors[] = 'Unexpected error while creating chart.';
    }

    // Keep user-entered values for re-render
    $formData = [
        'name'                => $rawName,
        'birth_timezone'      => $rawTz,
        'birth_location_name' => $locationStr,
        'house_system'        => $houseSystem,
        'is_public'           => (bool)$isPublic,
        'birth_latitude'      => $rawLat,
        'birth_longitude'     => $rawLon,
    ];
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
    <link rel="stylesheet" href="/quantum-astrology/assets/css/quantum-dashboard.css">
    <style>
        .chart-creator { max-width: 900px; margin: 0 auto; padding: 2rem; }
        .creator-header { text-align: center; margin-bottom: 3rem; }
        .creator-title { font-size: 2.5rem; font-weight: 700; background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-gold)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; margin-bottom: 0.5rem; }
        .creator-subtitle { color: rgba(255, 255, 255, 0.7); font-size: 1.1rem; }
        .chart-form { background: rgba(255, 255, 255, 0.05); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 20px; padding: 2.5rem; }
        .form-section { margin-bottom: 2.5rem; }
        .section-title { font-size: 1.3rem; font-weight: 600; color: var(--quantum-gold); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid rgba(255, 215, 0, 0.2); }
        .form-row { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; }
        .form-group { flex: 1; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; color: var(--quantum-text); }
        .required { color: var(--quantum-gold); }
        .form-input, .form-select, .form-textarea { width: 100%; padding: 0.875rem; background: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); border-radius: 10px; color: var(--quantum-text); font-size: 1rem; transition: all 0.3s ease; }
        .form-input:focus, .form-select:focus, .form-textarea:focus { outline: none; border-color: var(--quantum-primary); box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1); }
        .form-input::placeholder { color: rgba(255, 255, 255, 0.5); }
        .form-select option { background: var(--quantum-dark); color: var(--quantum-text); }
        .coordinate-inputs { display: flex; gap: 1rem; }
        .coordinate-group { flex: 1; position: relative; }
        .coordinate-label { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); color: rgba(255, 255, 255, 0.5); font-size: 0.85rem; }
        .checkbox-group { display: flex; align-items: center; gap: 0.5rem; }
        .form-checkbox { width: 18px; height: 18px; accent-color: var(--quantum-primary); }
        .location-helper { background: rgba(74, 144, 226, 0.1); border: 1px solid rgba(74, 144, 226, 0.3); border-radius: 10px; padding: 1rem; margin-top: 1rem; font-size: 0.9rem; color: rgba(255, 255, 255, 0.8); }
        .button-group { display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem; }
        .btn { padding: 0.875rem 2rem; border: none; border-radius: 10px; font-size: 1rem; font-weight: 600; cursor: pointer; transition: all 0.3s ease; text-decoration: none; display: inline-block; text-align: center; }
        .btn-primary { background: linear-gradient(135deg, var(--quantum-primary), var(--quantum-purple)); color: white; }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 25px rgba(74, 144, 226, 0.3); }
        .btn-secondary { background: rgba(255, 255, 255, 0.1); color: var(--quantum-text); border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-secondary:hover { background: rgba(255, 255, 255, 0.2); }
        .error-messages { background: rgba(220, 53, 69, 0.1); border: 1px solid rgba(220, 53, 69, 0.3); color: #ff6b6b; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; }
        .error-messages ul { margin: 0; padding-left: 1.5rem; }
        @media (max-width: 768px) {
            .form-row { flex-direction: column; gap: 0; }
            .coordinate-inputs { flex-direction: column; }
            .button-group { flex-direction: column; }
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
                            <option value="UTC" <?= (($formData['birth_timezone'] ?? 'UTC') === 'UTC') ? 'selected' : '' ?>>UTC (Coordinated Universal Time)</option>
                            <option value="America/New_York" <?= (($formData['birth_timezone'] ?? '') === 'America/New_York') ? 'selected' : '' ?>>Eastern Time (US & Canada)</option>
                            <option value="America/Chicago" <?= (($formData['birth_timezone'] ?? '') === 'America/Chicago') ? 'selected' : '' ?>>Central Time (US & Canada)</option>
                            <option value="America/Denver" <?= (($formData['birth_timezone'] ?? '') === 'America/Denver') ? 'selected' : '' ?>>Mountain Time (US & Canada)</option>
                            <option value="America/Los_Angeles" <?= (($formData['birth_timezone'] ?? '') === 'America/Los_Angeles') ? 'selected' : '' ?>>Pacific Time (US & Canada)</option>
                            <option value="Europe/London" <?= (($formData['birth_timezone'] ?? '') === 'Europe/London') ? 'selected' : '' ?>>London</option>
                            <option value="Europe/Paris" <?= (($formData['birth_timezone'] ?? '') === 'Europe/Paris') ? 'selected' : '' ?>>Paris</option>
                            <option value="Europe/Berlin" <?= (($formData['birth_timezone'] ?? '') === 'Europe/Berlin') ? 'selected' : '' ?>>Berlin</option>
                            <option value="Asia/Tokyo" <?= (($formData['birth_timezone'] ?? '') === 'Asia/Tokyo') ? 'selected' : '' ?>>Tokyo</option>
                            <option value="Australia/Sydney" <?= (($formData['birth_timezone'] ?? '') === 'Australia/Sydney') ? 'selected' : '' ?>>Sydney</option>
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
                        <input type="text"
                               id="birth_latitude"
                               name="birth_latitude"
                               class="form-input"
                               placeholder="40.7128 N"
                               inputmode="decimal"
                               value="<?= htmlspecialchars($formData['birth_latitude'] ?? '') ?>"
                               required>
                        <span class="coordinate-label">°N/S</span>
                    </div>

                    <div class="coordinate-group">
                        <label for="birth_longitude" class="form-label">Longitude <span class="required">*</span></label>
                        <input type="text"
                               id="birth_longitude"
                               name="birth_longitude"
                               class="form-input"
                               placeholder="74.0060 W"
                               inputmode="decimal"
                               value="<?= htmlspecialchars($formData['birth_longitude'] ?? '') ?>"
                               required>
                        <span class="coordinate-label">°E/W</span>
                    </div>
                </div>

                <div class="location-helper">
                    <strong>Need coordinates?</strong> Use online tools like Google Maps or geographic coordinate finders.
                    Right-click on a location in Google Maps to get precise latitude and longitude values. You can enter
                    coordinates with direction letters (e.g., <code>34.05N</code> or <code>118.25W</code>) and we'll convert
                    them automatically.
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
                                        <?= (($formData['house_system'] ?? 'P') === $code) ? 'selected' : '' ?>>
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
                                   <?= (!empty($formData['is_public'])) ? 'checked' : '' ?>>
                            <label for="is_public" class="form-label">Make this chart public</label>
                        </div>
                        <small style="color: rgba(255, 255, 255, 0.6);">
                            Public charts can be viewed by other users (birth data remains private)
                        </small>
                    </div>
                </div>
            </div>

            <div class="button-group">
                <a href="/quantum-astrology/" class="btn btn-secondary">Cancel</a>
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

            setTimeout(() => { particle.remove(); }, 20000);
        }

        for (let i = 0; i < 50; i++) setTimeout(createParticle, i * 200);
        setInterval(createParticle, 1000);

        // Form validation and enhancement
        document.addEventListener('DOMContentLoaded', function() {
            const latInput = document.getElementById('birth_latitude');
            const lngInput = document.getElementById('birth_longitude');

            function parseCoordinate(raw, positive, negative) {
                if (!raw) return NaN;
                let value = raw.trim().toUpperCase();
                if (!value) return NaN;

                let direction = null;
                if (/^[NSEW]/.test(value)) {
                    direction = value.charAt(0);
                    value = value.slice(1).trim();
                } else if (/[NSEW]$/.test(value)) {
                    direction = value.slice(-1);
                    value = value.slice(0, -1).trim();
                }

                value = value.replace(/[°º]/g, '');
                let number = parseFloat(value);
                if (Number.isNaN(number)) {
                    return NaN;
                }

                if (direction) {
                    if (direction === positive) {
                        return Math.abs(number);
                    }
                    if (direction === negative) {
                        return -Math.abs(number);
                    }
                }

                return number;
            }

            function updateCoordinateLabels() {
                const latLabel = document.querySelector('[for="birth_latitude"] + input + .coordinate-label');
                const lngLabel = document.querySelector('[for="birth_longitude"] + input + .coordinate-label');
                const lat = parseCoordinate(latInput.value, 'N', 'S');
                const lng = parseCoordinate(lngInput.value, 'E', 'W');
                if (latLabel) latLabel.textContent = Number.isNaN(lat) ? '°N/S' : (lat >= 0 ? '°N' : '°S');
                if (lngLabel) lngLabel.textContent = Number.isNaN(lng) ? '°E/W' : (lng >= 0 ? '°E' : '°W');
            }
            latInput.addEventListener('input', updateCoordinateLabels);
            lngInput.addEventListener('input', updateCoordinateLabels);
            updateCoordinateLabels();
        });
    </script>
</body>
</html>
