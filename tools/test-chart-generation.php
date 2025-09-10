<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use QuantumAstrology\Core\SwissEphemeris;
use QuantumAstrology\Charts\Chart;
use QuantumAstrology\Charts\ChartWheel;

echo "=== Quantum Astrology Chart Generation Test ===\n\n";

// Test Swiss Ephemeris integration
echo "1. Testing Swiss Ephemeris Integration...\n";
$swissEph = new SwissEphemeris();

try {
    $datetime = new DateTime('1990-01-01 12:00:00');
    $latitude = 40.7128;
    $longitude = -74.0060;
    
    echo "   - Calculating planetary positions for test date...\n";
    $positions = $swissEph->calculatePlanetaryPositions($datetime, $latitude, $longitude);
    
    if (!empty($positions)) {
        echo "   ✅ Swiss Ephemeris integration working\n";
        echo "   - Found " . count($positions) . " planetary positions\n";
        
        foreach (['sun', 'moon', 'mercury'] as $planet) {
            if (isset($positions[$planet])) {
                echo "   - {$planet}: " . number_format($positions[$planet]['longitude'], 2) . "°\n";
            }
        }
    } else {
        echo "   ⚠️ No planetary positions calculated (check Swiss Ephemeris installation)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test house calculations
echo "\n2. Testing House Calculations...\n";
try {
    $houses = $swissEph->calculateHouses($datetime, $latitude, $longitude, 'P');
    
    if (!empty($houses)) {
        echo "   ✅ House calculations working\n";
        echo "   - House 1 (Ascendant): " . number_format($houses[1]['cusp'] ?? 0, 2) . "° " . ($houses[1]['sign'] ?? '') . "\n";
        echo "   - House 10 (Midheaven): " . number_format($houses[10]['cusp'] ?? 0, 2) . "° " . ($houses[10]['sign'] ?? '') . "\n";
    } else {
        echo "   ⚠️ No house positions calculated\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test chart wheel generation
echo "\n3. Testing Chart Wheel Generation...\n";
try {
    $chartWheel = new ChartWheel(400);
    
    if (!empty($positions)) {
        $svg = $chartWheel->generateWheel($positions, $houses ?? [], []);
        
        if (strlen($svg) > 100) {
            echo "   ✅ Chart wheel generation working\n";
            echo "   - SVG length: " . strlen($svg) . " characters\n";
            
            // Save test chart wheel
            $testFile = __DIR__ . '/../storage/charts/test_wheel.svg';
            @mkdir(dirname($testFile), 0755, true);
            file_put_contents($testFile, $svg);
            echo "   - Test chart saved to: storage/charts/test_wheel.svg\n";
        } else {
            echo "   ⚠️ Chart wheel generation produced minimal output\n";
        }
    } else {
        echo "   ⚠️ Skipping chart wheel (no planetary positions)\n";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test database connectivity
echo "\n4. Testing Database Connectivity...\n";
try {
    $pdo = QuantumAstrology\Database\Connection::getInstance();
    
    // Test tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $expectedTables = ['users', 'charts', 'birth_profiles', 'chart_sessions', 'migrations'];
    $foundTables = array_intersect($expectedTables, $tables);
    
    echo "   ✅ Database connection working\n";
    echo "   - Found tables: " . implode(', ', $foundTables) . "\n";
    
    if (count($foundTables) === count($expectedTables)) {
        echo "   ✅ All required tables present\n";
    } else {
        $missing = array_diff($expectedTables, $foundTables);
        echo "   ⚠️ Missing tables: " . implode(', ', $missing) . "\n";
        echo "   - Run: php tools/migrate.php\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Database error: " . $e->getMessage() . "\n";
    echo "   - Check your database configuration in .env\n";
}

// Test system readiness
echo "\n5. System Readiness Summary...\n";
echo "   - PHP Version: " . PHP_VERSION . "\n";
echo "   - Swiss Ephemeris Path: " . (defined('SWEPH_PATH') ? SWEPH_PATH : 'Not configured') . "\n";
echo "   - Swiss Ephemeris Available: " . (file_exists(SWEPH_PATH ?? '') ? 'Yes' : 'No (analytical fallback will be used)') . "\n";
echo "   - Database Name: " . (defined('DB_NAME') ? DB_NAME : 'Not configured') . "\n";
echo "   - Storage Directory: " . (is_writable(ROOT_PATH . '/storage') ? 'Writable' : 'Not writable') . "\n";

echo "\n=== Test Complete ===\n";
echo "Start the development server: php -S localhost:8080 index.php\n";
echo "Access the application: http://localhost:8080\n";
echo "Create charts at: http://localhost:8080/charts/create\n";
echo "View chart library: http://localhost:8080/charts\n\n";