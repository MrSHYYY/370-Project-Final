<?php
function requireLoggedInUser($conn) {
    if (!isset($_SESSION['username'])) {
        header("Location: login.php");
        exit();
    }

    if (!isset($_SESSION['user_id'])) {
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $_SESSION['username']);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$user) {
            header("Location: logout.php");
            exit();
        }

        $_SESSION['user_id'] = $user['user_id'];
    }

    return (int) $_SESSION['user_id'];
}

function getOrCreateTeamId($conn, $sport_id, $team_name) {
    $team_name = trim($team_name);

    $stmt = $conn->prepare("SELECT team_id FROM teams WHERE sport_id = ? AND team_name = ? LIMIT 1");
    $stmt->bind_param("is", $sport_id, $team_name);
    $stmt->execute();
    $team = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($team) {
        return (int) $team['team_id'];
    }

    $stmt = $conn->prepare("INSERT INTO teams (sport_id, team_name) VALUES (?, ?)");
    $stmt->bind_param("is", $sport_id, $team_name);
    $stmt->execute();
    $team_id = $conn->insert_id;
    $stmt->close();

    return (int) $team_id;
}
?>
