<?php
declare(strict_types=1);

// Debug tool for login issues
// Save this as debug-login.php in your root directory

require_once __DIR__ . '/config.php';

// Simple autoloader
spl_autoload_register(function ($class) {
    $prefix = 'QuantumAstrology\\';
    $baseDir = __DIR__ . '/classes/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

use QuantumAstrology\Database\Connection;
use QuantumAstrology\Core\Session;
use QuantumAstrology\Core\User;

echo "<!DOCTYPE html>";
echo "<html><head><title>Quantum Astrology - Login Debug</title>";
echo "<style>
    body { font-family: Arial, sans-serif; background: #0a0a0f; color: #fff; padding: 20px; }
    .debug-section { background: rgba(255,255,255,0.1); padding: 20px; margin: 20px 0; border-radius: 10px; }
    .success { color: #4ade80; }
    .error { color: #ff6b6b; }
    .warning { color: #fbbf24; }
    pre { background: rgba(0,0,0,0.3); padding: 10px; border-radius: 5px; overflow-x: auto; }
    .btn { background: #4A90E2; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin: 5px; }
    .btn:hover { background: #357abd; }
    input[type='text'], input[type='password'] { padding: 8px; margin: 5px; border-radius: 5px; border: 1px solid #ccc; }
</style></head><body>";

echo "<h1>🌟 Quantum Astrology - Login Debug Tool</h1>";

// 1. Check Database Connection
echo "<div class='debug-section'>";
echo "<h2>1. Database Connection Test</h2>";
try {
    $pdo = Connection::getInstance();
    echo "<span class='success'>✅ Database connection successful</span><br>";
    
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<span class='success'>✅ Users table exists</span><br>";
        
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $count = $stmt->fetch()['count'];
        echo "<span class='success'>✅ Found {$count} users in database</span><br>";
        
        if ($count === 0) {
            echo "<span class='warning'>⚠️ No users found - you need to register first!</span><br>";
        }
    } else {
        echo "<span class='error'>❌ Users table does not exist</span><br>";
        echo "<span class='warning'>Run: php tools/migrate.php</span><br>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
}
echo "</div>";

// 2. Check Session Configuration
echo "<div class='debug-section'>";
echo "<h2>2. Session Configuration</h2>";
echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "<span class='success'>Active</span>" : "<span class='warning'>Inactive</span>") . "<br>";
echo "Session name: " . session_name() . "<br>";
echo "Session ID: " . (session_id() ?: 'None') . "<br>";
echo "Cookie settings:<br>";
echo "<pre>";
echo "  httponly: " . (ini_get('session.cookie_httponly') ? 'Yes' : 'No') . "\n";
echo "  secure: " . (ini_get('session.cookie_secure') ? 'Yes' : 'No') . "\n";
echo "  samesite: " . (ini_get('session.cookie_samesite') ?: 'Not set') . "\n";
echo "</pre>";
echo "</div>";

// 3. Test User Creation (if no users exist)
echo "<div class='debug-section'>";
echo "<h2>3. Quick User Creation</h2>";

if ($_POST['create_user'] ?? false) {
    try {
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if ($username && $email && $password) {
            $userData = [
                'username' => $username,
                'email' => $email,
                'password' => $password,
                'first_name' => 'Test',
                'last_name' => 'User'
            ];
            
            $user = User::create($userData);
            
            if ($user instanceof User) {
                echo "<span class='success'>✅ User created successfully! User ID: " . $user->getId() . "</span><br>";
            } elseif ($user === User::ERROR_DUPLICATE) {
                echo "<span class='warning'>⚠️ User already exists with that username/email</span><br>";
            } else {
                echo "<span class='error'>❌ Failed to create user</span><br>";
            }
        } else {
            echo "<span class='error'>❌ Please fill all fields</span><br>";
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Error creating user: " . $e->getMessage() . "</span><br>";
    }
}

echo "<form method='POST'>";
echo "<h3>Create Test User:</h3>";
echo "Username: <input type='text' name='username' value='mark' required><br>";
echo "Email: <input type='text' name='email' value='mark@quantummindsunited.com' required><br>";
echo "Password: <input type='password' name='password' value='quantum123' required><br>";
echo "<button type='submit' name='create_user' value='1' class='btn'>Create User</button>";
echo "</form>";
echo "</div>";

// 4. Test Login
echo "<div class='debug-section'>";
echo "<h2>4. Login Test</h2>";

if ($_POST['test_login'] ?? false) {
    try {
        Session::start();
        
        $emailOrUsername = $_POST['login_email'] ?? '';
        $password = $_POST['login_password'] ?? '';
        
        if ($emailOrUsername && $password) {
            $user = User::authenticate($emailOrUsername, $password);
            
            if ($user) {
                Session::login($user);
                echo "<span class='success'>✅ Login successful! User: " . $user->getUsername() . "</span><br>";
                echo "<span class='success'>✅ Session started successfully</span><br>";
                echo "<a href='/dashboard' class='btn'>Go to Dashboard</a>";
            } else {
                echo "<span class='error'>❌ Invalid credentials</span><br>";
                
                // Check if user exists
                $checkUser = User::findByEmail($emailOrUsername) ?: User::findByUsername($emailOrUsername);
                if ($checkUser) {
                    echo "<span class='warning'>⚠️ User found, but password is incorrect</span><br>";
                } else {
                    echo "<span class='warning'>⚠️ User not found with that email/username</span><br>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<span class='error'>❌ Login error: " . $e->getMessage() . "</span><br>";
    }
}

echo "<form method='POST'>";
echo "<h3>Test Login:</h3>";
echo "Email/Username: <input type='text' name='login_email' value='mark' required><br>";
echo "Password: <input type='password' name='login_password' value='quantum123' required><br>";
echo "<button type='submit' name='test_login' value='1' class='btn'>Test Login</button>";
echo "</form>";
echo "</div>";

// 5. List Existing Users
echo "<div class='debug-section'>";
echo "<h2>5. Existing Users</h2>";
try {
    $pdo = Connection::getInstance();
    $stmt = $pdo->query("SELECT id, username, email, created_at FROM users ORDER BY created_at DESC LIMIT 10");
    $users = $stmt->fetchAll();
    
    if ($users) {
        echo "<table style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='border-bottom: 1px solid #333;'><th>ID</th><th>Username</th><th>Email</th><th>Created</th></tr>";
        foreach ($users as $user) {
            echo "<tr style='border-bottom: 1px solid #222;'>";
            echo "<td>" . $user['id'] . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['email']) . "</td>";
            echo "<td>" . $user['created_at'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<span class='warning'>⚠️ No users found in database</span>";
    }
} catch (Exception $e) {
    echo "<span class='error'>❌ Error fetching users: " . $e->getMessage() . "</span>";
}
echo "</div>";

// 6. Quick Actions
echo "<div class='debug-section'>";
echo "<h2>6. Quick Actions</h2>";
echo "<a href='/login' class='btn'>Go to Login Page</a>";
echo "<a href='/register' class='btn'>Go to Register Page</a>";
echo "<a href='/' class='btn'>Go to Dashboard</a>";
echo "<a href='debug-login.php' class='btn'>Refresh Debug</a>";
echo "</div>";

// 7. Environment Check
echo "<div class='debug-section'>";
echo "<h2>7. Environment Info</h2>";
echo "<pre>";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "App Debug: " . (APP_DEBUG ? 'Enabled' : 'Disabled') . "\n";
echo "App Environment: " . APP_ENV . "\n";
echo "Database Host: " . DB_HOST . "\n";
echo "Database Name: " . DB_NAME . "\n";
echo "HTTPS: " . (HTTPS ? 'Yes' : 'No') . "\n";
echo "Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'Not set') . "\n";
echo "</pre>";
echo "</div>";

echo "</body></html>";
?>
