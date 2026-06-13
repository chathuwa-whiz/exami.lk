<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Simple Login Test</title>
</head>
<body>
    <h1>Simple Login Test</h1>
    
    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<div style='background: yellow; padding: 20px; margin: 20px 0;'>";
        echo "<h2>POST RECEIVED!</h2>";
        echo "<p>Email: " . htmlspecialchars($_POST['email'] ?? 'NOT SET') . "</p>";
        echo "<p>Password: " . htmlspecialchars($_POST['password'] ?? 'NOT SET') . "</p>";
        
        require_once __DIR__ . '/../src/config.php';
        
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if ($email && $password) {
            $stmt = db()->prepare('SELECT id, password_hash, name, user_type FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $row = $stmt->fetch();
            
            if ($row) {
                echo "<p>User found: ID={$row['id']}, Type={$row['user_type']}</p>";
                
                if (verify_password($password, $row['password_hash'])) {
                    echo "<p style='color: green; font-weight: bold;'>PASSWORD CORRECT!</p>";
                    $_SESSION['user_id'] = $row['id'];
                    echo "<p>Session set. User ID in session: " . $_SESSION['user_id'] . "</p>";
                    
                    if ($row['user_type'] === 'student') {
                        echo "<p><a href='student/dashboard.php'>Go to Dashboard</a></p>";
                    }
                } else {
                    echo "<p style='color: red;'>Password verification failed</p>";
                }
            } else {
                echo "<p style='color: red;'>User not found</p>";
            }
        }
        echo "</div>";
    }
    ?>
    
    <form method="post" action="test_login.php">
        <div>
            <label>Email:</label><br>
            <input type="email" name="email" value="test@gmail.com" required>
        </div>
        <div>
            <label>Password:</label><br>
            <input type="password" name="password" value="password123" required>
        </div>
        <button type="submit">Test Login</button>
    </form>
</body>
</html>
