<?php
session_start();
include('includes/db.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND password = ? AND LOWER(username) = 'admin'");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $_SESSION['username'] = $user['username'];
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['is_admin'] = true;
        header("Location: admin_dashboard.php");
        exit();
    } else {
        $error = "Invalid admin username or password";
    }

    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="auth-page admin-auth-page">
    <h1 class="auth-page-title">Welcome to One Scoreboard</h1>
    <div class="login-container">
        <div class="auth-header">
            <h2>Admin Login</h2>
        </div>
        <?php if (isset($error)) { echo "<p class='error'>" . htmlspecialchars($error) . "</p>"; } ?>
        <form method="POST" action="admin_login.php">
            <label for="admin-username">Name</label>
            <input type="text" id="admin-username" name="username" placeholder="Enter admin name" required>
            <label for="admin-password">Password</label>
            <input type="password" id="admin-password" name="password" placeholder="Enter admin password" required>
            <button type="submit">Login as Admin</button>
        </form>
        <p class="auth-switch">Back to <a href="login.php">User Login</a></p>
    </div>
</body>
</html>
