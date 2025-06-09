<?php

// WIC Portal Database Connection
// Environment variables for secure configuration

// Load environment variables from .env file
function load_env($file_path) {
    if (!file_exists($file_path)) {
        die("Environment file not found: $file_path");
    }
    
    $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // Skip comments
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // Parse key=value pairs
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            
            // Remove quotes if present
            if (preg_match('/^"(.*)"$/', $value, $matches)) {
                $value = $matches[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                $value = $matches[1];
            }
            
            // Set environment variable
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

// Get environment variable with optional default value
function env($key, $default = null) {
    $value = getenv($key);
    if ($value === false) {
        return $default;
    }
    
    // Convert string booleans to actual booleans
    if (strtolower($value) === 'true') return true;
    if (strtolower($value) === 'false') return false;
    
    return $value;
}

// Connects to the database and returns the connection object
// Uses environment variables for configuration
function connect_to_database() {
    // Try multiple possible locations for .env file
    $possible_env_files = [
        __DIR__ . '/../.env',  // One level up from database folder
        __DIR__ . '/../../.env',  // Two levels up (if in src/database/)
        $_SERVER['DOCUMENT_ROOT'] . '/../.env',  // Document root parent
        dirname($_SERVER['SCRIPT_FILENAME']) . '/.env',  // Same as current script
        '/Applications/MAMP/htdocs/Project/.env'  // Your specific MAMP path
    ];
    
    $env_file = null;
    foreach ($possible_env_files as $file) {
        if (file_exists($file)) {
            $env_file = $file;
            break;
        }
    }
    
    if ($env_file === null) {
        die("Environment file not found. Please create .env file in your project root. Tried: " . implode(', ', $possible_env_files));
    }
    
    load_env($env_file);
    
    // Get database configuration from environment
    $host = env('DB_HOST', 'localhost');
    $user = env('DB_USER', 'root');
    $password = env('DB_PASSWORD', '');
    $database = env('DB_NAME', 'project_db');
    
    // Validate required environment variables
    if (empty($database)) {
        die("Database configuration error: DB_NAME is required");
    }
    
    // Create connection with error handling
    try {
        $dbc = mysqli_connect($host, $user, $password, $database);
        
        // Check connection
        if (!$dbc) {
            throw new Exception("Connection failed: " . mysqli_connect_error());
        }
        
        // Set charset for proper unicode handling
        if (!mysqli_set_charset($dbc, 'utf8mb4')) {
            throw new Exception("Error setting charset: " . mysqli_error($dbc));
        }
        
        return $dbc;
        
    } catch (Exception $e) {
        // Log error (in production, log to file instead of displaying)
        if (env('APP_DEBUG', false)) {
            die("Database connection error: " . $e->getMessage());
        } else {
            die("Database connection failed. Please try again later.");
        }
    }
}

// Get application configuration value
function config($key, $default = null) {
    // Ensure environment is loaded
    static $env_loaded = false;
    if (!$env_loaded) {
        $env_file = __DIR__ . '/../.env';
        if (file_exists($env_file)) {
            load_env($env_file);
        }
        $env_loaded = true;
    }
    
    return env($key, $default);
}

// Check if we're in development mode
function is_development() {
    return env('APP_ENV', 'production') === 'development';
}

// Check if debug mode is enabled
function is_debug() {
    return env('APP_DEBUG', false);
}

?>