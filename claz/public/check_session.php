<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Session Debug</title>
</head>
<body>
    <h1>Session Debug</h1>
    <h2>Session Status</h2>
    <p>Status: <?= session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'NOT ACTIVE' ?></p>
    <p>Session ID: <?= session_id() ?></p>
    
    <h2>Session Data</h2>
    <pre><?php print_r($_SESSION); ?></pre>
    
    <h2>User ID in Session</h2>
    <p><?= isset($_SESSION['user_id']) ? 'YES - ID: ' . $_SESSION['user_id'] : 'NO' ?></p>
    
    <hr>
    <p><a href="login.php">Back to Login</a></p>
    <p><a href="student/dashboard.php">Try Dashboard</a></p>
</body>
</html>
