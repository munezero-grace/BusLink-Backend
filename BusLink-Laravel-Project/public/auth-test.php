<?php
// This file is for debugging Laravel authentication issues only

echo "Laravel Authentication Test<br>";
echo "PHP Version: " . phpversion() . "<br>";

// Try to load the Composer autoloader
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
    echo "Composer autoloader loaded successfully<br>";
} else {
    echo "Failed to load Composer autoloader<br>";
    exit(1);
}

// Initialize Laravel app
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

echo "Laravel version: " . \Illuminate\Foundation\Application::VERSION . "<br>";

// Check if Sanctum is installed
echo "Checking Sanctum:<br>";
try {
    if (class_exists('Laravel\Sanctum\Sanctum')) {
        echo "Laravel Sanctum is installed<br>";
    } else {
        echo "Laravel Sanctum class not found<br>";
    }
} catch (\Throwable $e) {
    echo "Error checking Sanctum: " . $e->getMessage() . "<br>";
}

// Check authentication configuration
echo "Auth configuration:<br>";
try {
    $authConfig = config('auth');
    echo "Default guard: " . $authConfig['defaults']['guard'] . "<br>";
    echo "Available guards: " . implode(', ', array_keys($authConfig['guards'])) . "<br>";
} catch (\Throwable $e) {
    echo "Error checking auth config: " . $e->getMessage() . "<br>";
}

echo "<hr>Test complete.";
