<?php 
require_once 'includes/functions.php';

// Add the dark mode toggle AJAX handler here
if(isset($_POST['toggle_dark_mode']) && isset($_POST['ajax']) && $_POST['ajax'] == 1) {
    // This is an AJAX request to toggle dark mode
    if(is_logged_in()) {
        $mode = (int)$_POST['toggle_dark_mode'];
        toggle_dark_mode($_SESSION['user_id'], $mode);
        // Return success response
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false]);
    }
    exit; // Stop further execution
}

$darkMode = isset($_SESSION['dark_mode']) && $_SESSION['dark_mode'] ? 'dark-mode' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= SITE_NAME ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body class="<?= $darkMode ?>">
    <header>
        <div class="container">
            <div class="logo">
                <h1><a href="index.php"><?= SITE_NAME ?></a></h1>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="browse.php">Browse</a></li>
                    <?php if(is_logged_in()): ?>
                        <li><a href="profile.php">Profile</a></li>
                        <li><a href="login.php?logout=true">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login/Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </header>
    <main class="container">