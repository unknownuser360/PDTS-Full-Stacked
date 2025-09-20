<?php
session_start();

// Collect errors from session
$errors = [
    'login' => $_SESSION['login_error'] ?? '',
    'register' => $_SESSION['register_error'] ?? ''
];
$success = $_SESSION['register_success'] ?? '';
$activeForm = $_SESSION['active_form'] ?? 'login';

// Clear session errors so they don’t persist
session_unset();

function showError($error) {
    return !empty($error) ? "<p class='error-message'>$error</p>" : '';
}
function showSuccess($msg) {
    return !empty($msg) ? "<p class='success-message'>$msg</p>" : '';
}
function isActiveForm($formName, $activeForm) {
    return $formName == $activeForm ? 'active' : '';
}
?>
<!DOCTYPE html>
<html lang="en">  
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Politeknik Dormitory Ticketing System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">

        <!-- LOGIN FORM -->
        <div class="form-box <?= isActiveForm('login',$activeForm); ?>" id="login-form">
            <form action="login_register.php" method="post"> 
                <h2>Login</h2>
                <?= showError($errors['login']); ?>
                <?= showSuccess($success); ?>

                <input type="email" name="email" placeholder="Email" required>
                <div class="password-container">
                    <input type="password" name="password" placeholder="Password" required>
                    
                </div>

                <button type="submit" name="login">Login</button>
                <p>Don't have an account? 
                    <a href="#" onclick="showForm('register-form')">Register</a>
                </p>
            </form>
        </div>

        <!-- REGISTER FORM -->
        <div class="form-box <?= isActiveForm('register',$activeForm); ?>" id="register-form">
            <form action="login_register.php" method="post">
                <h2>Register</h2>
                <?= showError($errors['register']); ?>
                <?= showSuccess($success); ?>

                <input type="text" name="name" placeholder="Full Name" required>
                <small>Name must be in UPPERCASE</small>

                <input type="email" name="email" placeholder="Email" required>
                <small>Please enter a valid email address</small>

                <input type="text" name="phone" placeholder="Phone Number" required>
                
                <select name="gender" required>
                    <option value="">--Select Gender--</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>

                <!-- Force only student registration -->
                <input type="hidden" name="role" value="student">

                <!-- Student-specific fields -->
                <div id="student-fields">
                    <input type="text" name="student_id" placeholder="Student ID" required>
                    <small>Your official Student ID (e.g:05DDT2000)</small>
                    <input type="text" name="room_number" placeholder="Room Number" required>
                    <small>Only 2 students allowed per room</small>
                </div>

                <div class="password-container">
                    <input type="password" name="password" placeholder="Password" required>
  
                </div>
                <div class="password-container">
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>

                </div>

                <button type="submit" name="register">Register</button>
                <p>Already have an account? 
                    <a href="#" onclick="showForm('login-form')">Login</a>
                </p>
            </form>
        </div>
    </div>

<script>
// Toggle between login/register forms
function showForm(formId) {
    document.getElementById('login-form').classList.remove('active');
    document.getElementById('register-form').classList.remove('active');
    document.getElementById(formId).classList.add('active');
}

// Password toggle
document.querySelectorAll('.toggle-password').forEach(btn => {
    btn.addEventListener('click', function() {
        const input = this.previousElementSibling;
        input.type = input.type === "password" ? "text" : "password";
    });
});
</script>
</body>
</html>


