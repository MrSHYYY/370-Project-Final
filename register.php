<?php
session_start();
include('includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $sql = "SELECT * FROM users WHERE username='$username'";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $error = "Username already taken";
    } else {
        $sql = "INSERT INTO users (username, password) VALUES ('$username', '$password')";
        
        if ($conn->query($sql) === TRUE) {
            $_SESSION['username'] = $username;
            $_SESSION['user_id'] = $conn->insert_id;
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page">
    <?php if (isset($_SESSION['username'])) { ?>
        <div class="page-actions">
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    <?php } ?>
    <h1 class="auth-page-title">Welcome to One Scoreboard</h1>
    <div class="register-container">
        <div class="auth-header">
            <h2>Create an account</h2>
        </div>
        <?php if (isset($error)) { echo "<p class='error'>$error</p>"; } ?>
        <form method="POST" action="register.php">
            <label for="signup-username">Name</label>
            <input type="text" id="signup-username" name="username" placeholder="Enter your name" required>
            <label for="signup-password">Password</label>
            <input type="password" id="signup-password" name="password" placeholder="Enter your password" required>
            <button type="submit">Sign Up</button>
        </form>
        <div class="signup-actions">
            <a href="admin_login.php" class="dashboard-action-btn">Admin Login</a>
            <p class="auth-switch">Already have an account? <a href="login.php">Log in</a></p>
        </div>
    </div>
</body>
</html>
