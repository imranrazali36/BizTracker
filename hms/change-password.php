<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();
date_default_timezone_set('Asia/Kolkata');
$currentTime = date('d-m-Y h:i:s A', time());

if (isset($_POST['submit'])) {
    // Validate old password
    $sql = mysqli_query($con, "SELECT password FROM users WHERE password='" . md5($_POST['cpass']) . "' AND id='" . $_SESSION['id'] . "'");
    $num = mysqli_fetch_array($sql);
    
    if ($num > 0) {
        // Update password
        $updateQuery = mysqli_query($con, "UPDATE users SET password='" . md5($_POST['npass']) . "', updationDate='$currentTime' WHERE id='" . $_SESSION['id'] . "'");
        
        if ($updateQuery) {
            $_SESSION['msg1'] = "Password Changed Successfully !!";
        } else {
            // If the update query failed
            $_SESSION['msg1'] = "Error updating password. Please try again.";
        }
    } else {
        $_SESSION['msg1'] = "Old Password does not match !!";
    }
}

// Fetch user details
$userId = $_SESSION['id'];
$userQuery = mysqli_query($con, "SELECT fullName FROM users WHERE id = '$userId'");

if ($userQuery) {
    $userData = mysqli_fetch_array($userQuery);
    $userName = $userData['fullName'];
} else {
    // Handle query failure (optional)
    $_SESSION['msg1'] = "Error fetching user details.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User | Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body {
        font-family: 'Inter', sans-serif;
    }
    .sidebar {
        width: 280px;
        transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
        background: rgb(235, 235, 235);
    }
    .nav-link {
        transition: all 0.3s ease;
    }
    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
    }
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 4px solid #fff;
    }
    @media (max-width: 768px) {
        .sidebar {
            margin-left: -280px;
        }
        .sidebar.active {
            margin-left: 0;
        }
        .main-content {
            margin-left: 0;
        }
        .main-content.active {
            margin-left: 280px;
        }
    }

    /* ── Show/hide toggle ── */
    .pw-toggle {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        cursor: pointer;
        background: none;
        border: none;
        padding: 0;
        line-height: 1;
        font-size: 15px;
    }
    .pw-toggle:hover { color: #4b6cb7; }

    /* ── Strength bar ── */
    .strength-track {
        height: 5px;
        background: #e5e7eb;
        border-radius: 999px;
        overflow: hidden;
        margin-top: 6px;
    }
    .strength-fill {
        height: 100%;
        border-radius: 999px;
        width: 0%;
        transition: width 0.35s ease, background-color 0.35s ease;
    }

    /* ── Requirement dots ── */
    .req-row {
        display: flex;
        align-items: center;
        gap: 7px;
        font-size: 12px;
        color: #9ca3af;
        transition: color 0.2s;
    }
    .req-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #d1d5db;
        flex-shrink: 0;
        transition: background 0.2s;
    }
    .req-row.met { color: #16a34a; }
    .req-row.met .req-dot { background: #22c55e; }

    /* ── Match indicator ── */
    #matchMsg { font-size: 12px; margin-top: 4px; min-height: 16px; }
    </style>

    <script type="text/javascript">
        function valid() {
            if(document.chngpwd.cpass.value=="") {
                alert("Current Password Filed is Empty !!");
                document.chngpwd.cpass.focus();
                return false;
            }
            else if(document.chngpwd.npass.value=="") {
                alert("New Password Filed is Empty !!");
                document.chngpwd.npass.focus();
                return false;
            }
            else if(document.chngpwd.cfpass.value=="") {
                alert("Confirm Password Filed is Empty !!");
                document.chngpwd.cfpass.focus();
                return false;
            }
            else if(document.chngpwd.npass.value!= document.chngpwd.cfpass.value) {
                alert("Password and Confirm Password Field do not match !!");
                document.chngpwd.cfpass.focus();
                return false;
            }
            return true;
        }
    </script>
</head>
<body class="bg-gray-50">

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <div class="sidebar fixed h-full text-white">
        <!-- Logo Section -->
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>

        <!-- User Profile Section -->
        <div class="p-4 border-b border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                    <i class="fas fa-user text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-medium truncate"><?php echo htmlentities($userName); ?></h3>
                    <p class="text-sm text-white/70">User</p>
                </div>
            </div>
        </div>

        <!-- Navigation Menu -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>

            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="expense.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-comments-dollar w-5 text-center"></i>
                <span>Expense Management</span>
            </a>
            <a href="budget.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-dollar-sign w-5 text-center"></i>
                <span>Income Management</span>
            </a>
            <a href="category.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-list w-5 text-center"></i>
                <span>Category Management</span>
            </a>
            <a href="prediction.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-gear w-5 text-center"></i>
                <span>Prediction Management</span>
            </a>
            <a href="feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-solid fa-envelope w-5 text-center"></i>
                <span>Feedback Management</span>
            </a>

            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>

            <a href="edit-profile.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-user-edit w-5 text-center"></i>
                <span>My Profile</span>
            </a>
            <a href="change-password.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-lock w-5 text-center"></i>
                <span>Change Password</span>
            </a>
            <a href="logout.php" onclick="return confirmLogout()" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-sign-out-alt w-5 text-center"></i>
                <span>Log Out</span>
            </a>
        </nav>
    </div>

    <!-- ============================================================
         MAIN CONTENT
         ============================================================ -->
    <div class="main-content min-h-screen">

        <!-- Header -->
        <header class="bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white">
            <div class="h-1 bg-white/10"></div>
            <div class="container mx-auto px-4 sm:px-6 lg:px-8 py-4">
                <div class="flex items-center justify-between">
                    <button id="sidebarToggle" class="md:hidden text-white">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                    <div class="flex items-center space-x-4">
                        <h1 class="text-2xl font-semibold">Change Password</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Change Password</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">

                <!-- Flash message -->
                <?php if (!empty($_SESSION['msg1'])): ?>
                    <?php
                        $isSuccess = strpos($_SESSION['msg1'], 'Successfully') !== false;
                        $msgClass  = $isSuccess
                            ? 'bg-green-50 border border-green-200 text-green-800'
                            : 'bg-red-50 border border-red-200 text-red-800';
                        $msgIcon   = $isSuccess ? 'fa-check-circle text-green-500' : 'fa-exclamation-circle text-red-500';
                    ?>
                    <div class="mb-6 p-4 rounded-lg flex items-center gap-3 <?php echo $msgClass; ?>">
                        <i class="fas <?php echo $msgIcon; ?> flex-shrink-0 text-lg"></i>
                        <span class="font-medium"><?php echo htmlspecialchars($_SESSION['msg1'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <?php unset($_SESSION['msg1']); ?>
                <?php endif; ?>

                <!-- Password Change Card -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">

                    <!-- Card Header -->
                    <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10 border-b border-gray-100">
                        <div class="flex items-center space-x-4">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-[#4b6cb7] to-[#182848] flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-lock text-2xl text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Change Your Password</h2>
                                <p class="mt-1 text-sm text-gray-500">Please fill in all fields to update your password</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form -->
                    <form role="form" name="chngpwd" method="post" onSubmit="return valid();" class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                            <!-- ── Left: Current Password ── -->
                            <div>
                                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider border-b pb-2 mb-5">
                                    Current Password
                                </h3>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="cpass">
                                        Enter Your Current Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="cpass" id="cpass"
                                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                            placeholder="Enter your current password">
                                        <button type="button" class="pw-toggle" onclick="togglePw('cpass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <!-- Security tip -->
                                <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                                    <p class="text-xs font-semibold text-blue-700 mb-2 flex items-center gap-1">
                                        <i class="fas fa-shield-alt"></i> Password Security Tips
                                    </p>
                                    <ul class="text-xs text-blue-600 space-y-1 leading-relaxed">
                                        <li>• Use a password you don't use anywhere else</li>
                                        <li>• Mix letters, numbers and special characters</li>
                                        <li>• Avoid names, dates or common words</li>
                                        <li>• Longer passwords are always stronger</li>
                                    </ul>
                                </div>
                            </div>

                            <!-- ── Right: New Password ── -->
                            <div>
                                <h3 class="text-sm font-bold text-gray-500 uppercase tracking-wider border-b pb-2 mb-5">
                                    New Password
                                </h3>

                                <!-- New password field -->
                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="npass">
                                        Enter New Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="npass" id="npass"
                                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                            placeholder="Enter new password"
                                            oninput="evalStrength(this.value); checkMatch();">
                                        <button type="button" class="pw-toggle" onclick="togglePw('npass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>

                                    <!-- Strength bar -->
                                    <div class="mt-2">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="text-xs text-gray-400">Password strength</span>
                                            <span id="strengthLabel" class="text-xs font-semibold text-gray-400">—</span>
                                        </div>
                                        <div class="strength-track">
                                            <div class="strength-fill" id="strengthFill"></div>
                                        </div>
                                    </div>

                                    <!-- Requirements checklist -->
                                    <div class="mt-3 grid grid-cols-1 gap-1.5 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                        <p class="text-xs font-semibold text-gray-500 mb-1">Password must contain:</p>
                                        <div class="req-row" id="req-len">
                                            <span class="req-dot"></span>
                                            <span>At least 8 characters</span>
                                        </div>
                                        <div class="req-row" id="req-upper">
                                            <span class="req-dot"></span>
                                            <span>One uppercase letter (A–Z)</span>
                                        </div>
                                        <div class="req-row" id="req-lower">
                                            <span class="req-dot"></span>
                                            <span>One lowercase letter (a–z)</span>
                                        </div>
                                        <div class="req-row" id="req-num">
                                            <span class="req-dot"></span>
                                            <span>One number (0–9)</span>
                                        </div>
                                        <div class="req-row" id="req-sym">
                                            <span class="req-dot"></span>
                                            <span>One special character (!@#$...)</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Confirm password field -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="cfpass">
                                        Confirm New Password
                                    </label>
                                    <div class="relative">
                                        <input type="password" name="cfpass" id="cfpass"
                                            class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                            placeholder="Confirm new password"
                                            oninput="checkMatch()">
                                        <button type="button" class="pw-toggle" onclick="togglePw('cfpass', this)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <p id="matchMsg"></p>
                                </div>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="mt-8 pt-4 border-t">
                            <div class="flex items-center justify-end space-x-4">
                                <button type="reset" onclick="resetForm()"
                                        class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                                    <i class="fas fa-undo mr-2"></i>Reset
                                </button>
                                <button type="submit" name="submit"
                                        class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all">
                                    <i class="fas fa-save mr-2"></i>Update Password
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

        </main>
    </div><!-- end .main-content -->

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        /* ---------- Sidebar toggle ---------- */
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');

            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('active');
            });
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }
    </script>

    <script>
        /* ---------- Show / hide password ---------- */
        function togglePw(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon  = btn.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>

    <script>
        /* ---------- Password strength meter + requirements ---------- */
        function evalStrength(pw) {
            const fill  = document.getElementById('strengthFill');
            const label = document.getElementById('strengthLabel');

            const checks = {
                len   : pw.length >= 8,
                upper : /[A-Z]/.test(pw),
                lower : /[a-z]/.test(pw),
                num   : /[0-9]/.test(pw),
                sym   : /[^A-Za-z0-9]/.test(pw),
            };

            // Update requirement rows
            Object.entries(checks).forEach(([key, met]) => {
                const row = document.getElementById('req-' + key);
                if (row) row.classList.toggle('met', met);
            });

            const score = Object.values(checks).filter(Boolean).length;

            const levels = [
                { pct: '0%',   bg: '#e5e7eb', text: '—',          color: '#9ca3af' },
                { pct: '20%',  bg: '#ef4444', text: 'Very weak',   color: '#ef4444' },
                { pct: '40%',  bg: '#f97316', text: 'Weak',        color: '#f97316' },
                { pct: '60%',  bg: '#eab308', text: 'Fair',        color: '#ca8a04' },
                { pct: '80%',  bg: '#84cc16', text: 'Good',        color: '#65a30d' },
                { pct: '100%', bg: '#22c55e', text: 'Strong',      color: '#16a34a' },
            ];

            const level = pw.length === 0 ? levels[0] : levels[score];
            fill.style.width           = level.pct;
            fill.style.backgroundColor = level.bg;
            label.textContent          = level.text;
            label.style.color          = level.color;
        }

        /* ---------- Password match indicator ---------- */
        function checkMatch() {
            const npass = document.getElementById('npass').value;
            const cfpass = document.getElementById('cfpass').value;
            const msg   = document.getElementById('matchMsg');

            if (cfpass.length === 0) {
                msg.textContent = '';
                return;
            }
            if (npass === cfpass) {
                msg.textContent  = '✓ Passwords match';
                msg.style.color  = '#16a34a';
            } else {
                msg.textContent  = '✗ Passwords do not match';
                msg.style.color  = '#dc2626';
            }
        }

        /* ---------- Reset clears meters too ---------- */
        function resetForm() {
            document.chngpwd.reset();
            evalStrength('');
            document.getElementById('matchMsg').textContent = '';
        }
    </script>

</body>
</html>