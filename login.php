<?php
require_once 'includes/header.php';

// Handle logout
if(isset($_GET['logout'])) {
    logout_user();
    header('Location: index.php');
    exit;
}

// Redirect if already logged in
if(is_logged_in()) {
    header('Location: index.php');
    exit;
}

$loginError = '';
$registerError = '';
$registerSuccess = '';

// Handle login form submission
if(isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if(empty($username) || empty($password)) {
        $loginError = 'Please enter both username and password';
    } else {
        if(login_user($username, $password)) {
            header('Location: index.php');
            exit;
        } else {
            $loginError = 'Invalid username or password';
        }
    }
}

// Handle register form submission
if(isset($_POST['register'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if(empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $registerError = 'Please fill all fields';
    } elseif($password !== $confirm_password) {
        $registerError = 'Passwords do not match';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $registerError = 'Please enter a valid email';
    } else {
        $user_id = register_user($username, $email, $password);
        if($user_id) {
            $registerSuccess = 'Registration successful! You can now login.';
        } else {
            $registerError = 'Username or email already exists';
        }
    }
}
?>

<section class="auth-container">
    <div class="auth-tabs">
        <button class="auth-tab active" data-tab="login">Login</button>
        <button class="auth-tab" data-tab="register">Register</button>
    </div>
    
    <div class="auth-content">
        <div class="auth-form active" id="login">
            <h2>Login</h2>
            <?php if($loginError): ?>
                <div class="alert alert-error"><?= $loginError ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <label for="login-username">Username or Email</label>
                    <input type="text" id="login-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="login-password">Password</label>
                    <input type="password" id="login-password" name="password" required>
                </div>
                <button type="submit" name="login" class="btn btn-primary">Login</button>
            </form>
        </div>
        
        <div class="auth-form" id="register">
            <h2>Register</h2>
            <?php if($registerError): ?>
                <div class="alert alert-error"><?= $registerError ?></div>
            <?php endif; ?>
            <?php if($registerSuccess): ?>
                <div class="alert alert-success"><?= $registerSuccess ?></div>
            <?php endif; ?>
            <form action="" method="post">
                <div class="form-group">
                    <label for="register-username">Username</label>
                    <input type="text" id="register-username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="register-email">Email</label>
                    <input type="email" id="register-email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="register-password">Password</label>
                    <input type="password" id="register-password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="register-confirm-password">Confirm Password</label>
                    <input type="password" id="register-confirm-password" name="confirm_password" required>
                </div>
                <button type="submit" name="register" class="btn btn-primary">Register</button>
            </form>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>