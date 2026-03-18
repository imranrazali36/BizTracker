<?php
session_start();
error_reporting(0);
include("include/config.php");
if(isset($_POST['submit']))
{
    $uname    = $_POST['username'];
    $upassword = $_POST['password'];

    // ── Fetch the admin row by username only ──────────────────────
    $ret = mysqli_query($con, "SELECT * FROM admin WHERE username='$uname'");
    $num = mysqli_fetch_array($ret);

    // ── Verify password ──────────────────────────────────────────
    // Supports both bcrypt hashes (password_hash) and legacy plaintext.
    $passwordOk = false;
    if ($num) {
        $storedPassword = $num['password'];
        if (password_verify($upassword, $storedPassword)) {
            // bcrypt hash — matches
            $passwordOk = true;
        } elseif ($upassword === $storedPassword) {
            // legacy plaintext — matches
            $passwordOk = true;
        }
    }

    if($passwordOk)
    {
        $_SESSION['login'] = $_POST['username'];
        $_SESSION['id']    = $num['id'];
        header("location:dashboard.php");
        exit;
    }
    else
    {
        $_SESSION['errmsg'] = "Invalid username or password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Admin Login - BizTracker</title>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

        /* ── Container — wider to show more breathing room ── */
        .login-container {
            display: flex;
            max-width: 1100px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.18);
            margin: 20px;
        }

        /* ── Left panel ── */
        .login-image {
            flex: 0 0 420px;
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

        .panel-logo {
            width: 110px; height: 110px;
            object-fit: contain;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
            position: relative; z-index: 1;
        }

        .panel-title {
            font-size: 5rem;
            font-weight: 700;
            line-height: 1.25;
            margin-bottom: 0.5rem;
            position: relative; z-index: 1;
        }

        .panel-divider {
            width: 48px; height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
            margin: 0.75rem auto 0.75rem;
            position: relative; z-index: 1;
        }

        .panel-sub {
            font-size: 2rem;
            color: rgba(255,255,255,0.80);
            line-height: 1.65;
            position: relative; z-index: 1;
        }

        .panel-features {
            margin-top: 2.5rem;
            text-align: left;
            width: 100%;
            position: relative; z-index: 1;
        }

        .panel-feature {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.5rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.65rem;
        }

        .panel-feature i { color: var(--accent-color); flex-shrink: 0; }

        /* ── Right panel ── */
        .login-form {
            flex: 1;
            padding: 3.5rem 3rem;
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

        /* ── Field label ── */
        .field-label {
            display: block;
            font-size: 13.5px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .form-group {
            margin-bottom: 1.4rem;
            position: relative;
        }

        /* ── Input with icon wrapper ── */
        .input-icon {
            position: relative;
            display: block;
        }

        .input-icon i.icon-left {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
            font-size: 14px;
            pointer-events: none;
        }

        .form-control {
            width: 100%;
            padding: 0.72rem 2.8rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: #fafafa;
            transition: 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(75, 108, 183, 0.15);
            outline: none;
            background: white;
        }

        .form-control::placeholder { color: #9ca3af; }

        /* ── Show/hide toggle ── */
        .toggle-pw {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #9ca3af;
            font-size: 14px;
            padding: 0;
            line-height: 1;
        }
        .toggle-pw:hover { color: var(--primary-color); }

        .btn-login {
            width: 100%;
            padding: 0.85rem;
            background: linear-gradient(90deg, var(--accent-color), #ff5722);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1.5rem;
            cursor: pointer;
            transition: 0.3s ease;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
            margin-top: 0.5rem;
        }

        .btn-login:hover {
            background: linear-gradient(90deg, #ff5722, var(--accent-color));
            transform: scale(1.03);
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

        .copyright {
            text-align: center;
            margin-top: 1.5rem;
            color: #9ca3af;
            font-size: 0.82rem;
        }

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

        /* ── Admin badge ── */
        .admin-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.07em;
            text-transform: uppercase;
            color: #4b6cb7;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            padding: 3px 10px;
            border-radius: 999px;
            margin: 0 auto 1.25rem;
            display: block;
            width: fit-content;
        }

        @media (max-width: 860px) {
            .login-image { display: none; }
            .login-form  { padding: 2rem 1.75rem; }
        }
    </style>
</head>
<body>
    <div class="login-container">

        <!-- ── Left panel ── -->
        <div class="login-image">
            <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="panel-logo">
            <div class="panel-title">Admin<br>Portal</div>
            <div class="panel-divider"></div>
            <div class="panel-sub">Manage your BizTracker system, users, and feedback from one place.</div>
            <div style="margin-top:2.5rem;"></div>
            <div class="panel-features">
                <div class="panel-feature"><i class="fas fa-users-cog"></i> User Management</div>
                <div class="panel-feature"><i class="fas fa-envelope-open-text"></i> Feedback Monitoring</div>
                <div class="panel-feature"><i class="fas fa-file-alt"></i> Session Logs</div>
                <div class="panel-feature"><i class="fas fa-shield-alt"></i> Secure Admin Access</div>
            </div>
        </div>

        <!-- ── Right panel ── -->
        <div class="login-form">
            <span class="admin-badge"><i class="fas fa-user-shield"></i> Administrator</span>
            <h2 class="form-title">Admin Login</h2>
            <p class="form-subtitle">Sign in to access the BizTracker admin dashboard.</p>

            <form method="post">
                <?php if($_SESSION['errmsg']) { ?>
                    <div class="error-message">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlentities($_SESSION['errmsg']); ?>
                        <?php echo htmlentities($_SESSION['errmsg']="");?>
                    </div>
                <?php } ?>

                <div class="form-group">
                    <label class="field-label">Username</label>
                    <span class="input-icon">
                        <i class="fas fa-user icon-left"></i>
                        <input type="text" class="form-control" name="username" placeholder="Enter your username" required>
                    </span>
                </div>

                <div class="form-group">
                    <label class="field-label">Password</label>
                    <span class="input-icon">
                        <i class="fas fa-lock icon-left"></i>
                        <input type="password" class="form-control password" id="adminPassword" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-pw" onclick="toggleAdminPw(this)" title="Show/hide password">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </span>
                </div>

                <button type="submit" class="btn-login" name="submit">
                    <i class="fas fa-sign-in-alt" style="margin-right:6px;"></i>Login
                </button>

                <div class="login-footer">
                    <p><a href="../../index.php"><i class="fas fa-home"></i> Back to Home</a></p>
                </div>
            </form>
            <div class="copyright">
                &copy; <?php echo date('Y'); ?> BizTracker
            </div>
        </div>
    </div>

    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/jquery-validation/jquery.validate.min.js"></script>
    <script>
        function toggleAdminPw(btn) {
            const input = document.getElementById('adminPassword');
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