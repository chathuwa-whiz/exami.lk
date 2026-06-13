<?php
// Basic CLI PHP script to attempt login to Sakya Dashboard.
// NOTE: Field names are guesses; confirm via browser DevTools Network panel.
// Usage (PowerShell): php login_test.php --student=25C18379 --password=Hashini@987

function parseArgs(array $argv): array {
    $out = [];
    foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $eq = strpos($arg, '=');
            if ($eq !== false) {
                $key = substr($arg, 2, $eq - 2);
                $val = substr($arg, $eq + 1);
                $out[$key] = $val;
            } else {
                $out[substr($arg, 2)] = true;
            }
        }
    }
    return $out;
}

$args = parseArgs($argv);
$studentId = $args['student'] ?? getenv('SAKYA_STUDENT_ID');
$password   = $args['password'] ?? getenv('SAKYA_PASSWORD');

if (!$studentId || !$password) {
    fwrite(STDERR, "Missing credentials. Provide --student and --password or set env vars SAKYA_STUDENT_ID / SAKYA_PASSWORD.\n");
    exit(1);
}

// Potential form field names (adjust after inspecting actual request):
// - student_id OR studentId OR id
// - password
// - remember (optional) value 'on'
// Replace keys below once confirmed.
$postFields = [
    'student_id' => $studentId,
    'password'   => $password,
    'remember'   => 'on'
];

$url = 'https://dash.sakya.edu.lk/auth/login'; // Page likely accepts POST for authentication.

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HEADER => true, // capture headers to detect redirect or set-cookie
    CURLOPT_POSTFIELDS => http_build_query($postFields),
    CURLOPT_TIMEOUT => 30,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER => [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) PHP-Test',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Content-Type: application/x-www-form-urlencoded'
    ],
]);

$response = curl_exec($ch);
if ($response === false) {
    fwrite(STDERR, "cURL error: " . curl_error($ch) . "\n");
    curl_close($ch);
    exit(1);
}

$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$rawHeaders = substr($response, 0, $headerSize);
$body       = substr($response, $headerSize);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Extract Set-Cookie values (session indicators)
preg_match_all('/^Set-Cookie:\s*(.*?)$/mi', $rawHeaders, $cookieMatches);
$cookies = $cookieMatches[1] ?? [];

$successHeuristic = false;
// Heuristics: redirect (302), presence of dashboard text, or new cookie.
if ($statusCode >= 300 && $statusCode < 400) {
    $successHeuristic = true; // Often a redirect on successful auth.
}
if (stripos($body, 'Dashboard') !== false || stripos($body, 'Logout') !== false) {
    $successHeuristic = true;
}
if (!empty($cookies)) {
    $successHeuristic = true;
}

echo "HTTP Status: $statusCode\n";
echo "Cookies Received: " . count($cookies) . "\n";
foreach ($cookies as $c) {
    echo "  $c\n";
}

echo "Heuristic Login Success?: " . ($successHeuristic ? 'POSSIBLE' : 'UNCERTAIN/FAILED') . "\n";

// Optional: Save cookies for subsequent authenticated requests.
if (!empty($cookies)) {
    $cookieFile = __DIR__ . '/sakya_cookie.txt';
    file_put_contents($cookieFile, implode("\n", $cookies));
    echo "Saved raw cookie lines to: $cookieFile\n";
}

// Write body snapshot for manual inspection.
file_put_contents(__DIR__ . '/sakya_login_response.html', $body);
echo "Saved response body to sakya_login_response.html\n";

echo "\nNEXT STEPS:\n - Open sakya_login_response.html to inspect content.\n - Use browser DevTools to confirm exact form field names.\n - Adjust $postFields keys if needed.\n - Remove plaintext password; prefer environment variables.\n";
?>