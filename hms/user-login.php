<?php session_start();
error_reporting(0);
include("include/config.php");
if(isset($_POST['submit'])) {
    $puname = $_POST['username'];    
    $ppwd = md5($_POST['password']);
    $ret = mysqli_query($con, "SELECT * FROM users WHERE email='$puname' and password='$ppwd'");
    $num = mysqli_fetch_array($ret);
    if($num > 0) {
        $_SESSION['login'] = $_POST['username'];
        $_SESSION['id'] = $num['id'];
        $pid = $num['id'];
        $uip = $_SERVER['REMOTE_ADDR'];
        $status = 1;
        mysqli_query($con, "INSERT INTO userlog(uid, username, userip, status) VALUES ('$pid', '$puname', '$uip', '$status')");
        header("location:dashboard.php");
    } else {
        $_SESSION['login'] = $_POST['username'];    
        $uip = $_SERVER['REMOTE_ADDR'];
        $status = 0;
        mysqli_query($con, "INSERT INTO userlog(username, userip, status) VALUES ('$puname', '$uip', '$status')");
        echo "<script>alert('Invalid username or password');</script>";
        echo "<script>window.location.href='user-login.php'</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Login</title>
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
        :root {
            --primary-color: #4b6cb7;
            --secondary-color: #182848;
            --accent-color: #ff9800;
            --background-color: #f0f2f5;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--background-color);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── container — wider to match registration ── */
        .login-container {
            display: flex;
            max-width: 1400px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.18);
            margin: 20px;
        }

        /* ── left panel ── */
        .login-image {
            flex: 0 0 460px;
            background: linear-gradient(160deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3rem 2.5rem;
            flex-direction: column;
            position: relative;
            overflow: hidden;
        }

        /* Subtle decorative circles on panel */
        .login-image::before,
        .login-image::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255,255,255,0.05);
        }
        .login-image::before {
            width: 320px; height: 320px;
            top: -80px; left: -80px;
        }
        .login-image::after {
            width: 220px; height: 220px;
            bottom: -60px; right: -60px;
        }

        .login-image .panel-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
            position: relative; z-index: 1;
        }

        .login-image .panel-title {
            font-size: 3.8rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 0.75rem;
            position: relative; z-index: 1;
        }

        .login-image .panel-divider {
            width: 48px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
            margin: 0.75rem auto;
            position: relative; z-index: 1;
        }

        .login-image .panel-sub {
            font-size: 1.5rem;
            color: rgba(255,255,255,0.82);
            line-height: 1.7;
            position: relative; z-index: 1;
        }

        .login-image .panel-features {
            margin-top: 2rem;
            text-align: left;
            width: 100%;
            position: relative; z-index: 1;
        }

        .login-image .panel-feature {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.6rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.7rem;
        }

        .login-image .panel-feature i {
            color: var(--accent-color);
            flex-shrink: 0;
        }

        /* ── right panel ── */
        .login-form {
            flex: 1;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-title {
            font-size: 4.5rem;
            font-weight: 700;
            color: var(--secondary-color);
            margin-bottom: 0.25rem;
            text-align: center;
        }

        .form-subtitle {
            font-size: 1.8rem;
            color: #6b7280;
            text-align: center;
            margin-bottom: 2rem;
        }

        /* ── field label ── */
        .field-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        /* ── input with icon ── */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper .input-icon {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 15px;
            pointer-events: none;
        }

        .input-wrapper .form-control {
            padding-left: 3.5rem;
        }

        .input-wrapper .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 15px;
            padding: 0;
        }

        .input-wrapper .toggle-pw:hover { color: #374151; }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.3s ease;
            background: #fafafa;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(75,108,183,0.15);
            outline: none;
            background: white;
        }

        .form-control::placeholder { color: #9ca3af; }

        .btn-login {
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
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #ff5722, var(--accent-color));
            transform: scale(1.05);
        }

        .login-footer {
            margin-top: 1.75rem;
            text-align: center;
            color: #4a5568;
            font-size: 1.4rem;
        }

        .login-footer a {
            color: var(--primary-color);
            font-weight: 500;
            transition: color 0.3s ease;
            text-decoration: none;
        }

        .login-footer a:hover { color: var(--secondary-color); }

        .error-message {
            background: #fff5f5;
            color: #c53030;
            border: 1px solid #fed7d7;
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            text-align: center;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
        }

        /* ── responsive ── */
        @media (max-width: 900px) {
            .login-image { display: none; }
            .login-form  { padding: 1.75rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">

        <!-- ── Left panel ── -->
        <div class="login-image">
            <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="panel-logo">
            <div class="panel-title">Welcome<br>Back!</div>
            <div class="panel-divider"></div>
            <div class="panel-sub">Access your business records and manage your data with ease.</div>
            <div style="margin-top:4rem;"></div>
            <div class="panel-features">
                <div class="panel-feature"><i class="fas fa-chart-line"></i> Income &amp; Expense Tracking</div>
                <div class="panel-feature"><i class="fas fa-brain"></i> AI-Powered Risk Prediction</div>
                <div class="panel-feature"><i class="fas fa-shield-alt"></i> Secure &amp; Private Data</div>
                <div class="panel-feature"><i class="fas fa-file-alt"></i> Financial Reports</div>
            </div>
        </div>

        <!-- ── Right panel ── -->
        <div class="login-form">
            <h2 class="form-title">User Login</h2>
            <p class="form-subtitle">Sign in to your BizTracker account.</p>

            <form method="post">
                <?php if($_SESSION['errmsg']) { ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $_SESSION['errmsg']; ?>
                        <?php $_SESSION['errmsg'] = ""; ?>
                    </div>
                <?php } ?>

                <div class="form-group">
                    <label class="field-label">Email Address</label>
                    <div class="input-wrapper">
                        <i class="fas fa-envelope input-icon"></i>
                        <input type="email" class="form-control" name="username"
                               placeholder="Enter your email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="field-label">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock input-icon"></i>
                        <input type="password" class="form-control" id="loginPassword" name="password"
                               placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw"
                                onclick="toggleLoginPassword(this)" title="Show/hide password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-login" name="submit">
                    <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i>Login
                </button>

                <div class="login-footer">
                    <p><a href="forgot-password.php">Forgot Password?</a></p>
                    <p>Don't have an account? <a href="registration.php">Create an account</a></p>
                    <p><a href="../index.php"><i class="fas fa-home"></i> Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function toggleLoginPassword(btn) {
            const input = document.getElementById('loginPassword');
            const icon  = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            }
        }
    </script>
</body>
</html>