<?php
// api/index.php - Serverless Router & Front Controller for Vercel Deployment

$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = ltrim($path, '/');

// Base directory is the project root (one level above /api)
$baseDir = dirname(__DIR__);

// Route root to index.php
if ($path === '' || $path === 'index.php') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $baseDir . '/index.php';
    chdir($baseDir);
    require $baseDir . '/index.php';
    exit;
}

// Special shorthand redirects
if ($path === 'leadership' || $path === 'leadership/') {
    header('Location: /leadership/dashboard.php');
    exit;
}

if ($path === 'student' || $path === 'student/') {
    header('Location: /student/index.php');
    exit;
}

// Check direct file match
$targetFile = $baseDir . '/' . $path;

if (is_dir($targetFile)) {
    if (file_exists($targetFile . '/index.php')) {
        $targetFile .= '/index.php';
    } elseif (file_exists($targetFile . '/dashboard.php')) {
        $targetFile .= '/dashboard.php';
    }
} elseif (!file_exists($targetFile) && file_exists($targetFile . '.php')) {
    $targetFile .= '.php';
}

if (file_exists($targetFile) && substr($targetFile, -4) === '.php') {
    $_SERVER['SCRIPT_FILENAME'] = realpath($targetFile);
    $_SERVER['SCRIPT_NAME'] = '/' . ltrim(str_replace('\\', '/', substr(realpath($targetFile), strlen(realpath($baseDir)))), '/');
    chdir(dirname($targetFile));
    require $targetFile;
    exit;
}

// If file not found, return 404
http_response_code(404);
echo "<!DOCTYPE html><html><head><title>404 Not Found</title></head><body style='font-family: sans-serif; text-align: center; padding: 50px;'>";
echo "<h2>404 - Page Not Found</h2><p>The requested page <code>" . htmlspecialchars($requestUri) . "</code> was not found.</p>";
echo "<a href='/'>Return to Home</a></body></html>";
