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

                <!-- Full Name + Email -->
                <div class="form-row">
                    <input type="text" name="name" placeholder="Full Name" required>
                    <input type="email" name="email" placeholder="Email" required>
                </div>
                <small>Name must be in UPPERCASE | Please enter a valid email</small>

                <!-- Student ID + Phone -->
                <div class="form-row">
                    <input type="text" name="student_id" placeholder="Student ID" required>
                    <input type="text" name="phone" placeholder="Phone Number" required>
                </div>
                <small>Student ID e.g: 05DDT2000 | Phone Number required</small>

                <!-- Gender -->
                <select name="gender" id="gender" required>
                    <option value="">--Select Gender--</option>
                    <option value="male">Male</option>
                    <option value="female">Female</option>
                </select>

                <!-- Block + Room Number -->
                <div class="form-row">
                    <select name="block" id="block" required disabled>
                        <option value="">--Select Block--</option>
                    </select>
                    <input type="number" name="room_number" id="room_number" placeholder="Room Number" required disabled>
                </div>
                <small>Only numeric Room Number | Must match pre-registered database</small>

                <!-- Password + Confirm -->
                <div class="form-row">
                    <input type="password" name="password" placeholder="Password" required>
                    <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                </div>

                <!-- Force only student registration -->
                <input type="hidden" name="role" value="student">

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

// Gender → Block → Room Number logic
const genderSelect = document.getElementById("gender");
const blockSelect = document.getElementById("block");
const roomInput = document.getElementById("room_number");

genderSelect.addEventListener("change", function() {
    blockSelect.innerHTML = "";
    roomInput.value = "";
    roomInput.disabled = true;

    let defaultOption = document.createElement("option");
    defaultOption.value = "";
    defaultOption.text = "--Select Block--";
    blockSelect.add(defaultOption);

    if (this.value === "male") {
        blockSelect.disabled = false;
        ["A","B","C","D","E","F"].forEach(b => {
            let option = document.createElement("option");
            option.value = b;
            option.text = "Block " + b;
            blockSelect.add(option);
        });
    } else if (this.value === "female") {
        blockSelect.disabled = false;
        ["A","B"].forEach(b => {
            let option = document.createElement("option");
            option.value = b;
            option.text = "Block " + b;
            blockSelect.add(option);
        });
    } else {
        blockSelect.disabled = true;
    }
});

blockSelect.addEventListener("change", function() {
    if (this.value !== "") {
        roomInput.disabled = false;
    } else {
        roomInput.disabled = true;
        roomInput.value = "";
    }
});
</script>
</body>
</html>
