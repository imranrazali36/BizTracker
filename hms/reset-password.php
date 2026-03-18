<?php
session_start();
//error_reporting(0);
include("include/config.php");
// Code for updating Password
if(isset($_POST['change'])) {
    $name=$_SESSION['name'];
    $email=$_SESSION['email'];
    $newpassword=md5($_POST['password']);
    $query=mysqli_query($con,"update users set password='$newpassword' where fullName='$name' and email='$email'");
    if ($query) {
        echo "<script>alert('Password successfully updated.');</script>";
        echo "<script>window.location.href ='user-login.php'</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Password Reset</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
        :root {
            --primary-color: #4b6cb7;
            --secondary-color: #182848;
            --accent-color: #ff9800;
            --background-color: #f8f9fa;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            display: flex;
            max-width: 900px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }

        .login-image {
            flex: 1;
            background: linear-gradient(90deg, var(--primary-color), var(--secondary-color));
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 2rem;
            flex-direction: column;
        }

        .login-image span:first-child {
            font-size: 2.5rem;
            font-weight: bold;
        }

        .login-image span:last-child {
            font-size: 1.2rem;
            font-weight: normal;
        }

        .login-form {
            flex: 1;
            padding: 3rem;
        }

        .form-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 2rem;
            text-align: center;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            transition: 0.3s ease;
            padding-left: 2.5rem;
        }

        .form-control:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(255, 152, 0, 0.2);
            outline: none;
        }

        .input-icon {
            position: relative;
            display: block;
        }

        .input-icon i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #718096;
        }

        .btn-reset {
            width: 100%;
            padding: 1rem;
            background: linear-gradient(90deg, var(--accent-color), #ff5722);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
        }

        .btn-reset:hover {
            background: linear-gradient(90deg, #ff5722, var(--accent-color));
            transform: scale(1.05);
        }

        .login-footer {
            margin-top: 2rem;
            text-align: center;
            color: #4a5568;
        }

        .login-footer a {
            color: var(--primary-color);
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .login-footer a:hover {
            color: var(--secondary-color);
        }

        .copyright {
            text-align: center;
            margin-top: 2rem;
            color: #718096;
            font-size: 0.9rem;
        }

        .error-message {
            color: #c53030;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
    <script type="text/javascript">
    function valid() {
        if(document.passwordreset.password.value!= document.passwordreset.password_again.value) {
            alert("Password and Confirm Password Field do not match  !!");
            document.passwordreset.password_again.focus();
            return false;
        }
        return true;
    }
    </script>
</head>
<body>
    <div class="login-container">
        <div class="login-image">
            <span>Reset Password</span>
            <span>Create a new password to secure your account.</span>
        </div>
        <div class="login-form">
            <h2 class="form-title">Create New Password</h2>
            <form name="passwordreset" method="post" onSubmit="return valid();">
                <?php if(isset($_SESSION['errmsg']) && $_SESSION['errmsg']) { ?>
                    <div class="error-message">
                        <?php echo $_SESSION['errmsg']; ?>
                        <?php unset($_SESSION['errmsg']); ?>
                    </div>
                <?php } ?>
                <div class="form-group">
                    <span class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Enter new password" required>
                    </span>
                </div>
                <div class="form-group">
                    <span class="input-icon">
                        <i class="fas fa-lock"></i>
                        <input type="password" class="form-control" id="password_again" name="password_again" placeholder="Confirm new password" required>
                    </span>
                </div>
                <button type="submit" class="btn-reset" name="change">
                    Reset Password <i class="fas fa-arrow-right ml-2"></i>
                </button>
                <div class="login-footer">
                    <p>Remember your password? <a href="user-login.php">Login here</a></p>
                    <p><a href="../index.php"><i class="fas fa-home"></i> Back to Home</a></p>
                </div>
            </form>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> Diabetes Management System
            </div>
        </div>
    </div>

    <!-- Keep the essential JavaScript -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/jquery-validation/jquery.validate.min.js"></script>
</body>
</html>