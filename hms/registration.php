<?php
include_once('include/config.php');
if(isset($_POST['submit']))
{
    // Validate full name (no numbers/special chars)
    $fname = $_POST['full_name'];
    if (!preg_match("/^[a-zA-Z ]*$/", $fname)) {
        echo "<script>alert('Name can only contain letters and spaces');</script>";
        exit();
    }

    // Validate email format
    $email = $_POST['email'];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format');</script>";
        exit();
    }

    // Check password strength
    $password = $_POST['password'];
    if (strlen($password) < 8 || 
        !preg_match("#[0-9]+#", $password) || 
        !preg_match("#[A-Z]+#", $password) || 
        !preg_match("#[a-z]+#", $password) ||
        !preg_match("#\W+#", $password)) {
        echo "<script>alert('Password must be at least 8 characters long and contain uppercase, lowercase, number and special character');</script>";
        exit();
    }

    // Check password match
    if ($password != $_POST['password_again']) {
        echo "<script>alert('Passwords do not match');</script>";
        exit();
    }

    // If all validations pass, proceed with registration
    $address = $_POST['address'];
    $city = $_POST['city'];
    $gender = $_POST['gender'];
    $password = md5($password);
    $contactno = $_POST['contactno'];
    $dob = $_POST['dob'];

    $query = mysqli_query($con, "insert into users(fullname,address,city,gender,email,password,contactno,dob) values('$fname','$address','$city','$gender','$email','$password','$contactno','$dob')");
    if($query)
    {
        echo "<script>alert('Successfully Registered. You can login now');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>User Registration</title>
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
            padding: 2rem 0;
        }

        /* ── wider container ── */
        .registration-container {
            display: flex;
            max-width: 1400px;
            width: 100%;
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            margin: 20px;
        }

        /* ── left panel ── */
        .registration-image {
            flex: 0 0 380px;
            background: linear-gradient(160deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 3rem 2.5rem;
            flex-direction: column;
        }

        .registration-image .panel-logo {
            width: 120px;
            height: 120px;
            object-fit: contain;
            margin-bottom: 1.5rem;
            filter: drop-shadow(0 4px 12px rgba(0,0,0,0.25));
        }

        .registration-image .panel-title {
            font-size: 3.8rem;
            font-weight: 700;
            line-height: 1.3;
            margin-bottom: 0.75rem;
        }

        .registration-image .panel-divider {
            width: 48px;
            height: 3px;
            background: var(--accent-color);
            border-radius: 2px;
            margin: 0.75rem auto;
        }

        .registration-image .panel-sub {
            font-size: 2rem;
            color: rgba(255,255,255,0.82);
            line-height: 1.7;
        }

        .registration-image .panel-features {
            margin-top: 2rem;
            text-align: left;
            width: 100%;
        }

        .registration-image .panel-feature {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            font-size: 1.5rem;
            color: rgba(255,255,255,0.85);
            margin-bottom: 0.7rem;
        }

        .registration-image .panel-feature i {
            color: var(--accent-color);
            flex-shrink: 0;
        }

        /* ── right panel ── */
        .registration-form {
            flex: 2;
            padding: 3rem;
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
            margin-bottom: 1.5rem;
        }

        .form-section-title {
            color: var(--primary-color);
            font-weight: 600;
            margin: 1.5rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid var(--accent-color);
        }

        /* ── field labels ── */
        .field-label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 5px;
        }

        .field-label .req { color: #ef4444; margin-left: 2px; }

        /* ── inputs ── */
        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.3s ease;
            margin-bottom: 0;
            background: #fafafa;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(75,108,183,0.15);
            outline: none;
            background: white;
        }

        .form-control::placeholder { color: #9ca3af; }

        /* ── layout ── */
        .form-row {
            display: flex;
            gap: 1.25rem;
            margin-bottom: 1rem;
        }

        .form-row > div { flex: 1; }

        .gender-group {
            display: flex;
            gap: 2rem;
            margin-bottom: 1rem;
        }

        .gender-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* ── strength bar ── */
        .strength-bar {
            height: 6px;
            border-radius: 4px;
            margin-top: 4px;
            transition: background-color 0.3s;
        }

        .strength-weak   { background-color: #fca5a5; width: 33%; }
        .strength-medium { background-color: #fcd34d; width: 66%; }
        .strength-strong { background-color: #86efac; width: 100%; }

        .strength-text { font-weight: bold; margin-left: 10px; }

        /* ── password requirements checklist ── */
        .pw-requirements { font-size: 14px; color: #6b7280; margin-top: 6px; }
        .pw-req { display: flex; align-items: center; gap: 5px; margin-bottom: 3px; }
        .pw-req .req-icon { width: 13px; flex-shrink: 0; }
        .pw-req.met   { color: #16a34a; }
        .pw-req.unmet { color: #9ca3af; }

        /* ── register button ── */
        .btn-register {
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
            margin-top: 1rem;
        }

        .btn-register:hover {
            background: linear-gradient(90deg, #ff5722, var(--accent-color));
            transform: scale(1.02);
        }

        .registration-footer {
            margin-top: 2rem;
            text-align: center;
            color: #4a5568;
        }

        .registration-footer a {
            color: var(--primary-color);
            font-weight: 500;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .registration-footer a:hover { color: var(--secondary-color); }

        /* ── terms modal ── */
        #termsModal {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        #termsModal .modal-box {
            background: white;
            max-width: 780px;
            width: 92%;
            padding: 2.5rem;
            border-radius: 14px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        #termsModal .modal-close {
            position: absolute;
            top: 14px;
            right: 18px;
            background: none;
            border: none;
            font-size: 22px;
            cursor: pointer;
            color: #6b7280;
        }

        #termsModal .modal-body {
            font-size: 15px;
            color: #374151;
            line-height: 1.85;
            max-height: 460px;
            overflow-y: auto;
            margin-top: 1rem;
        }

        #termsModal .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            margin-top: 1.25rem;
        }

        /* ── responsive ── */
        @media (max-width: 900px) {
            .registration-image { display: none; }
            .registration-form  { padding: 1.75rem; }
            .form-row { flex-direction: column; }
        }
    </style>

    <script type="text/javascript">
        function valid() {
            if (document.registration.password.value != document.registration.password_again.value) {
                alert("Password and Confirm Password Field do not match!");
                document.registration.password_again.focus();
                return false;
            }
            return true;
        }
    </script>
</head>
<body>
    <div class="registration-container">

        <!-- ── Left panel ── -->
        <div class="registration-image">
            <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="panel-logo">
            <div class="panel-title">Welcome to<br>BizTracker!</div>
            <div class="panel-divider"></div>
            <div class="panel-sub">Create your account to manage your business journey.</div>
            <div style="margin-top:4rem;"></div>
            <div class="panel-features">
                <div class="panel-feature"><i class="fas fa-chart-line"></i> Income &amp; Expense Tracking</div>
                <div class="panel-feature"><i class="fas fa-brain"></i> AI-Powered Risk Prediction</div>
                <div class="panel-feature"><i class="fas fa-shield-alt"></i> Secure &amp; Private Data</div>
                <div class="panel-feature"><i class="fas fa-file-alt"></i> Financial Reports</div>
            </div>
        </div>

        <!-- ── Right panel ── -->
        <div class="registration-form">
            <h2 class="form-title">User Registration</h2>
            <p class="form-subtitle">Fields marked <span style="color:#ef4444;">*</span> are required.</p>

            <form name="registration" id="registration" method="post" onSubmit="return valid();">

                <h3 class="form-section-title">Personal Information</h3>

                <div class="form-row">
                    <div>
                        <label class="field-label" for="full_name">Full Name <span class="req">*</span></label>
                        <input type="text" class="form-control" name="full_name" id="full_name"
                               placeholder="e.g. Ahmad bin Ali" required>
                    </div>
                    <div>
                        <label class="field-label" for="contactno">Contact Number <span class="req">*</span></label>
                        <input type="text" class="form-control" name="contactno" id="contactno"
                               placeholder="e.g. 012-345 6789" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="field-label" for="address">Address <span class="req">*</span></label>
                        <input type="text" class="form-control" name="address" id="address"
                               placeholder="e.g. No. 12, Jalan Maju" required>
                    </div>
                    <div>
                        <label class="field-label" for="city">City <span class="req">*</span></label>
                        <input type="text" class="form-control" name="city" id="city"
                               placeholder="e.g. Kuala Lumpur" required>
                    </div>
                </div>

                <div class="form-row">
                    <div>
                        <label class="field-label" for="dob">Date of Birth <span class="req">*</span></label>
                        <input type="date" class="form-control" name="dob" id="dob" required>
                    </div>
                    <div>
                        <label class="field-label" for="gender">Gender <span class="req">*</span></label>
                        <select name="gender" id="gender" class="form-control" required>
                            <option value="">-- Select Gender --</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                        </select>
                    </div>
                </div>

                <h3 class="form-section-title">Account Information</h3>

                <div class="form-group">
                    <label class="field-label" for="email">Email Address <span class="req">*</span></label>
                    <input type="email" class="form-control" name="email" id="email"
                           onBlur="userAvailability()"
                           placeholder="e.g. you@example.com" required>
                    <span id="user-availability-status1" style="font-size:12px;"></span>
                </div>

                <div class="form-row space-y-4">
                    <!-- Password Field -->
                    <div class="relative">
                        <label class="field-label" for="password">Password <span class="req">*</span></label>
                        <input type="password" class="form-control pl-10 pr-3 py-2 w-full border rounded"
                               id="password" name="password"
                               placeholder="Create a strong password" required
                               oninput="checkStrength(this.value)">
                        <div id="password-strength" class="strength-bar mt-1"></div>
                        <span id="strength-text" class="strength-text"></span>
                        <div class="pw-requirements">
                            <div class="pw-req unmet" id="req-len"><span class="req-icon"><i class="fas fa-circle fa-xs"></i></span> 8+ characters</div>
                            <div class="pw-req unmet" id="req-upper"><span class="req-icon"><i class="fas fa-circle fa-xs"></i></span> Uppercase letter</div>
                            <div class="pw-req unmet" id="req-lower"><span class="req-icon"><i class="fas fa-circle fa-xs"></i></span> Lowercase letter</div>
                            <div class="pw-req unmet" id="req-num"><span class="req-icon"><i class="fas fa-circle fa-xs"></i></span> Number</div>
                            <div class="pw-req unmet" id="req-special"><span class="req-icon"><i class="fas fa-circle fa-xs"></i></span> Special character</div>
                        </div>
                    </div>

                    <!-- Confirm Password Field -->
                    <div class="relative w-full">
                        <label class="field-label" for="password_again">Confirm Password <span class="req">*</span></label>
                        <input type="password" class="form-control pr-10 pl-3 py-2 w-full border rounded"
                               id="password_again" name="password_again"
                               placeholder="Re-enter your password" required>
                    </div>
                </div>

                <!-- Terms and Conditions Checkbox -->
                <div class="form-group" style="margin-top:1rem;">
                    <div class="flex items-start space-x-2">
                        <input type="checkbox" id="agree" value="agree" required class="mt-1"
                               style="cursor:pointer; accent-color:var(--primary-color);">
                        <label for="agree" style="font-size:14px; color:#374151; cursor:pointer;">
                            I agree to the
                            <a href="javascript:void(0);" onclick="openTermsModal()"
                               style="color:var(--primary-color); text-decoration:underline; font-weight:500;">
                                terms and conditions
                            </a>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn-register" id="submit" name="submit">
                    Create Account <i class="fas fa-arrow-right"></i>
                </button>

                <div class="registration-footer">
                    <p>Already have an account? <a href="user-login.php">Sign in</a></p>
                    <p><a href="../index.php"><i class="fas fa-home"></i> Back to Home</a></p>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Terms and Conditions Modal ── -->
    <div id="termsModal">
        <div class="modal-box">
            <button class="modal-close" onclick="closeTermsModal()" title="Close">&times;</button>
            <h3 style="font-size:1.35rem; font-weight:700; color:#182848;">
                <i class="fas fa-file-contract" style="color:#4b6cb7; margin-right:8px;"></i>Terms and Conditions
            </h3>
            <div class="modal-body">
                <p><strong>1. Account Usage</strong><br>
                Your account is personal and non-transferable. You are responsible for all activity that occurs under your account.</p>
                <p><strong>2. Data Privacy</strong><br>
                We collect your personal information solely to provide the BizTracker service. Your data will not be shared with third parties without your consent.</p>
                <p><strong>3. Financial Data</strong><br>
                All income and expense records you enter are stored securely and used only to power your personal financial dashboard and predictions.</p>
                <p><strong>4. Password Security</strong><br>
                You are responsible for keeping your password secure. We recommend using a strong, unique password.</p>
                <p><strong>5. Termination</strong><br>
                We reserve the right to suspend accounts that violate our usage policy.</p>
            </div>
            <div class="modal-footer">
                <button onclick="acceptTerms()"
                        style="padding:8px 20px; background:linear-gradient(90deg,#4b6cb7,#182848);
                               color:white; border:none; border-radius:8px; cursor:pointer; font-weight:600;">
                    <i class="fas fa-check" style="margin-right:4px;"></i>I Accept
                </button>
                <button onclick="closeTermsModal()"
                        style="padding:8px 20px; background:#e5e7eb; color:#374151;
                               border:none; border-radius:8px; cursor:pointer;">
                    Close
                </button>
            </div>
        </div>
    </div>

    <!-- Keep original scripts -->
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
    <script src="vendor/modernizr/modernizr.js"></script>
    <script src="vendor/jquery-cookie/jquery.cookie.js"></script>
    <script src="vendor/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="vendor/switchery/switchery.min.js"></script>
    <script src="vendor/jquery-validation/jquery.validate.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/login.js"></script>
    <script>
        jQuery(document).ready(function() {
            Main.init();
            Login.init();
        });

        function userAvailability() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "check_availability.php",
                data: 'email=' + $("#email").val(),
                type: "POST",
                success: function(data) {
                    $("#user-availability-status1").html(data);
                    $("#loaderIcon").hide();
                },
                error: function() {}
            });
        }

        function openTermsModal() {
            document.getElementById('termsModal').style.display = 'flex';
        }
        function closeTermsModal() {
            document.getElementById('termsModal').style.display = 'none';
        }
        function acceptTerms() {
            document.getElementById('agree').checked = true;
            closeTermsModal();
        }
        document.getElementById('termsModal').addEventListener('click', function(e) {
            if (e.target === this) closeTermsModal();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeTermsModal();
        });
    </script>

    <script>
    function togglePassword(id, icon) {
        const input = document.getElementById(id);
        const iconElem = icon.querySelector('i');
        if (input.type === "password") {
            input.type = "text";
            iconElem.classList.remove('fa-eye-slash');
            iconElem.classList.add('fa-eye');
        } else {
            input.type = "password";
            iconElem.classList.remove('fa-eye');
            iconElem.classList.add('fa-eye-slash');
        }
    }

    function valid() {
        // Name validation
        var name = document.registration.full_name.value;
        if (!/^[a-zA-Z ]*$/.test(name)) {
            alert("Name can only contain letters and spaces");
            document.registration.full_name.focus();
            return false;
        }

        // Email validation
        var email = document.registration.email.value;
        if (!/^\w+([\.-]?\w+)*@\w+([\.-]?\w+)*(\.\w{2,3})+$/.test(email)) {
            alert("Invalid email format");
            document.registration.email.focus();
            return false;
        }

        // Password match validation
        if (document.registration.password.value != document.registration.password_again.value) {
            alert("Password and Confirm Password Field do not match!");
            document.registration.password_again.focus();
            return false;
        }

        // Password strength validation
        var password = document.registration.password.value;
        if (password.length < 8 || 
            !/[A-Z]/.test(password) || 
            !/[a-z]/.test(password) || 
            !/[0-9]/.test(password) ||
            !/[^A-Za-z0-9]/.test(password)) {
            alert("Password must be at least 8 characters long and contain uppercase, lowercase, number and special character");
            document.registration.password.focus();
            return false;
        }

        // Terms checkbox validation
        if (!document.getElementById('agree').checked) {
            alert("You must agree to the terms and conditions");
            return false;
        }

        return true;
    }

    function checkStrength(password) {
        const strengthBar = document.getElementById('password-strength');
        const strengthText = document.getElementById('strength-text');

        strengthBar.className = 'strength-bar';
        strengthText.textContent = '';
        strengthText.className = 'strength-text';

        const hasLen     = password.length >= 8;
        const hasUpper   = /[A-Z]/.test(password);
        const hasLower   = /[a-z]/.test(password);
        const hasNum     = /[0-9]/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        updateReq('req-len',     hasLen);
        updateReq('req-upper',   hasUpper);
        updateReq('req-lower',   hasLower);
        updateReq('req-num',     hasNum);
        updateReq('req-special', hasSpecial);

        let strength = 0;
        if (password.length >= 8) strength++;
        if (/[A-Z]/.test(password)) strength++;
        if (/[a-z]/.test(password)) strength++;
        if (/[0-9]/.test(password)) strength++;
        if (/[^A-Za-z0-9]/.test(password)) strength++;

        if (strength <= 2) {
            strengthText.textContent = 'Weak Password';
            strengthBar.classList.add('strength-weak');
            strengthText.style.color = 'red';
        } else if (strength === 3 || strength === 4) {
            strengthText.textContent = 'Medium Password';
            strengthBar.classList.add('strength-medium');
            strengthText.style.color = 'orange';
        } else if (strength >= 5) {
            strengthText.textContent = 'Strong Password';
            strengthBar.classList.add('strength-strong');
            strengthText.style.color = 'green';
        }
    }

    function updateReq(id, met) {
        const el   = document.getElementById(id);
        const icon = el.querySelector('i');
        el.className   = met ? 'pw-req met'   : 'pw-req unmet';
        icon.className = met ? 'fas fa-check-circle fa-xs' : 'fas fa-circle fa-xs';
    }
    </script>
</body>
</html>