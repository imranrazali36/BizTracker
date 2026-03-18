<?php
session_start();
error_reporting(0);
ini_set('display_errors', 0);
include('include/config.php');

// ============================================================
// ADMIN AUTH — fixed broken strlen() check
// ============================================================
$adminId = (int) ($_SESSION['id'] ?? 0);
if ($adminId <= 0) {
    header('Location: logout.php');
    exit;
}

$adminChk = $con->prepare("SELECT id, username, password FROM admin WHERE id = ?");
$adminChk->bind_param("i", $adminId);
$adminChk->execute();
$adminData = $adminChk->get_result()->fetch_assoc();
$adminChk->close();

if (!$adminData) {
    session_destroy();
    header('Location: logout.php');
    exit;
}
$adminName    = $adminData['username'];
$storedHash   = $adminData['password'];

// ============================================================
// CSRF TOKEN
// ============================================================
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ============================================================
// HELPERS — session flash messages (Post-Redirect-Get)
// ============================================================
function setFlash(string $type, string $msg): void {
    $_SESSION['flash'] = ['type' => $type, 'msg' => $msg];
}
function getFlash(): ?array {
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $f;
    }
    return null;
}

// ============================================================
// UNRESPONDED FEEDBACK COUNT — for sidebar badge
// ============================================================
$unreadStmt = $con->prepare("SELECT COUNT(*) AS c FROM contactus WHERE status = 'Not Responded'");
$unreadStmt->execute();
$totalUnread = (int) $unreadStmt->get_result()->fetch_assoc()['c'];
$unreadStmt->close();

// ============================================================
// HANDLE PASSWORD CHANGE — POST with CSRF
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {

    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid request. Please refresh and try again.');
    }

    $currentPass = $_POST['cpass']  ?? '';
    $newPass     = $_POST['npass']  ?? '';
    $confirmPass = $_POST['cfpass'] ?? '';

    // Server-side validation (mirrors client-side, can't be bypassed)
    if (empty($currentPass)) {
        setFlash('error', 'Current password is required.');
    } elseif (empty($newPass)) {
        setFlash('error', 'New password is required.');
    } elseif (strlen($newPass) < 8) {
        setFlash('error', 'New password must be at least 8 characters long.');
    } elseif ($newPass !== $confirmPass) {
        setFlash('error', 'New password and confirm password do not match.');
    } elseif ($newPass === $currentPass) {
        setFlash('error', 'New password must be different from your current password.');
    } else {
        // ── Verify current password ──────────────────────────────
        // Supports both hashed (password_verify) and legacy plaintext passwords.
        // Once changed, the new password is always stored as a bcrypt hash.
        $passwordMatches = password_verify($currentPass, $storedHash)
                        || ($currentPass === $storedHash); // legacy plaintext fallback

        if (!$passwordMatches) {
            setFlash('error', 'Current password is incorrect.');
        } else {
            // Hash the new password with bcrypt
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);

            $updStmt = $con->prepare(
                "UPDATE admin SET password = ?, updationDate = NOW() WHERE id = ?"
            );
            $updStmt->bind_param("si", $newHash, $adminId);

            if ($updStmt->execute()) {
                // Regenerate CSRF token after successful password change
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                setFlash('success', 'Password changed successfully.');
            } else {
                setFlash('error', 'Something went wrong. Please try again.');
            }
            $updStmt->close();
        }
    }

    header('Location: change-password.php');
    exit;
}

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Change Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/images/biztracker.png">
    <link rel="shortcut icon" href="assets/images/biztracker.png">
    <style>
    body { font-family: 'Inter', sans-serif; }
    .sidebar {
        width: 280px;
        transition: all 0.3s ease;
        background: linear-gradient(180deg, #4b6cb7 0%, #182848 100%);
    }
    .main-content {
        margin-left: 280px;
        transition: all 0.3s ease;
    }
    .nav-link { transition: all 0.3s ease; }
    .nav-link:hover { background-color: rgba(255, 255, 255, 0.1); }
    .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
        border-left: 4px solid #fff;
    }
    @media (max-width: 768px) {
        .sidebar { margin-left: -280px; }
        .sidebar.active { margin-left: 0; }
        .main-content { margin-left: 0; }
        .main-content.active { margin-left: 280px; }
    }

    /* Password strength bar */
    .strength-bar {
        height: 4px;
        border-radius: 2px;
        transition: width 0.3s ease, background-color 0.3s ease;
    }

    /* Show/hide password toggle */
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
    }
    .pw-toggle:hover { color: #4b6cb7; }

    /* Requirement indicator dots */
    .req-item { display: flex; align-items: center; gap: 6px; font-size: 12px; }
    .req-dot {
        width: 7px; height: 7px; border-radius: 50%;
        background: #d1d5db; flex-shrink: 0;
        transition: background 0.2s;
    }
    .req-dot.met { background: #22c55e; }
    </style>
</head>
<body class="bg-gray-50">

    <!-- ============================================================
         SIDEBAR
         ============================================================ -->
    <div class="sidebar fixed h-full text-white">
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>

        <!-- Admin Profile -->
        <div class="p-4 border-b border-white/10">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                    <i class="fas fa-user-shield text-white"></i>
                </div>
                <div class="overflow-hidden">
                    <h3 class="font-medium truncate"><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></h3>
                    <p class="text-sm text-white/70">Administrator</p>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <nav class="mt-4 px-3">
            <div class="mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Main Menu</p>
            </div>
            <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-tachometer-alt w-5 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-users w-5 text-center"></i>
                <span>Users</span>
            </a>
            <a href="user-logs.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-file-alt w-5 text-center"></i>
                <span>User Session Logs</span>
            </a>
            <a href="manage_feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-envelope w-5 text-center"></i>
                <span>Feedback</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="ml-auto bg-yellow-400 text-yellow-900 text-xs font-bold px-2 py-0.5 rounded-full">
                        <?php echo $totalUnread; ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="about-us.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
                <i class="fas fa-info-circle w-5 text-center"></i>
                <span>About Us</span>
            </a>
            <div class="mt-4 mb-2">
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">Account Settings</p>
            </div>
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
    <div class="main-content min-h-screen bg-gray-100">

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
                            <span class="text-white/70">Admin</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Change Password</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8 max-w-2xl">

            <!-- Flash message -->
            <?php if ($flash): ?>
                <div class="mb-6 p-4 rounded-lg flex items-center gap-3
                    <?php echo $flash['type'] === 'success'
                        ? 'bg-green-100 text-green-800 border border-green-200'
                        : 'bg-red-100 text-red-800 border border-red-200'; ?>">
                    <i class="fas <?php echo $flash['type'] === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> flex-shrink-0"></i>
                    <span><?php echo htmlspecialchars($flash['msg'], ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            <?php endif; ?>

            <!-- Password Change Card -->
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">

                <!-- Card header -->
                <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10 border-b border-gray-100">
                    <div class="flex items-center space-x-4">
                        <div class="w-14 h-14 rounded-full bg-gradient-to-r from-[#4b6cb7] to-[#182848] flex items-center justify-center flex-shrink-0">
                            <i class="fas fa-lock text-xl text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-800">Change Your Password</h2>
                            <p class="text-sm text-gray-500 mt-0.5">
                                Logged in as <strong><?php echo htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8'); ?></strong>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form method="POST" action="change-password.php" class="p-6 space-y-6" id="pwForm">
                    <input type="hidden" name="csrf_token"
                           value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                    <!-- Current Password -->
                    <div>
                        <label for="cpass" class="block text-sm font-medium text-gray-700 mb-1">
                            Current Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="cpass" id="cpass"
                                   class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                   placeholder="Enter your current password"
                                   autocomplete="current-password">
                            <button type="button" class="pw-toggle" onclick="togglePw('cpass', this)" aria-label="Show/hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    <!-- New Password -->
                    <div>
                        <label for="npass" class="block text-sm font-medium text-gray-700 mb-1">
                            New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="npass" id="npass"
                                   class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                   placeholder="Enter new password"
                                   autocomplete="new-password"
                                   oninput="evaluateStrength(this.value); checkMatch();">
                            <button type="button" class="pw-toggle" onclick="togglePw('npass', this)" aria-label="Show/hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>

                        <!-- Strength bar -->
                        <div class="mt-2">
                            <div class="flex justify-between text-xs text-gray-500 mb-1">
                                <span>Password strength</span>
                                <span id="strengthLabel" class="font-medium text-gray-400">—</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-1.5">
                                <div id="strengthBar" class="strength-bar bg-gray-300" style="width:0%"></div>
                            </div>
                        </div>

                        <!-- Requirements checklist -->
                        <div class="mt-3 grid grid-cols-2 gap-1.5" id="reqList">
                            <div class="req-item"><span class="req-dot" id="req-len"></span><span class="text-gray-500">At least 8 characters</span></div>
                            <div class="req-item"><span class="req-dot" id="req-upper"></span><span class="text-gray-500">One uppercase letter</span></div>
                            <div class="req-item"><span class="req-dot" id="req-lower"></span><span class="text-gray-500">One lowercase letter</span></div>
                            <div class="req-item"><span class="req-dot" id="req-num"></span><span class="text-gray-500">One number</span></div>
                            <div class="req-item"><span class="req-dot" id="req-sym"></span><span class="text-gray-500">One special character</span></div>
                        </div>
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="cfpass" class="block text-sm font-medium text-gray-700 mb-1">
                            Confirm New Password <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <input type="password" name="cfpass" id="cfpass"
                                   class="w-full px-4 py-2 pr-10 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                   placeholder="Confirm new password"
                                   autocomplete="new-password"
                                   oninput="checkMatch()">
                            <button type="button" class="pw-toggle" onclick="togglePw('cfpass', this)" aria-label="Show/hide password">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <!-- Match indicator -->
                        <p id="matchMsg" class="mt-1 text-xs hidden"></p>
                    </div>

                    <!-- Actions -->
                    <div class="pt-2 flex items-center justify-end gap-3 border-t border-gray-100">
                        <button type="reset" onclick="resetForm()"
                                class="px-5 py-2.5 border border-gray-300 text-gray-600 rounded-lg hover:bg-gray-50 transition-colors text-sm">
                            <i class="fas fa-undo mr-1"></i> Reset
                        </button>
                        <button type="submit" name="submit" id="submitBtn"
                                class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all text-sm font-medium flex items-center gap-2">
                            <i class="fas fa-save"></i> Update Password
                        </button>
                    </div>
                </form>
            </div>

            <!-- Security tip -->
            <div class="mt-6 bg-white rounded-lg shadow p-4 flex items-start gap-3 text-sm text-gray-600">
                <i class="fas fa-shield-alt text-blue-400 mt-0.5 flex-shrink-0"></i>
                <div>
                    <p class="font-medium text-gray-700 mb-1">Password security tips</p>
                    <p>Use a unique password that you don't use anywhere else. A strong password contains a mix of uppercase and lowercase letters, numbers, and special characters. Avoid using personal information such as names or dates.</p>
                </div>
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
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('active');
                    mainContent.classList.toggle('active');
                });
            }
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }
    </script>

    <script>
        /* ---------- Show / hide password toggle ---------- */
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
        function evaluateStrength(pw) {
            const bar    = document.getElementById('strengthBar');
            const label  = document.getElementById('strengthLabel');

            const checks = {
                len   : pw.length >= 8,
                upper : /[A-Z]/.test(pw),
                lower : /[a-z]/.test(pw),
                num   : /[0-9]/.test(pw),
                sym   : /[^A-Za-z0-9]/.test(pw),
            };

            // Update requirement dots
            Object.entries(checks).forEach(([key, met]) => {
                const dot = document.getElementById('req-' + key);
                if (dot) dot.classList.toggle('met', met);
            });

            // Score 0-5
            const score = Object.values(checks).filter(Boolean).length;

            const levels = [
                { pct: '0%',   color: '#d1d5db', text: '—',         labelColor: '#9ca3af' },
                { pct: '20%',  color: '#ef4444', text: 'Very weak',  labelColor: '#ef4444' },
                { pct: '40%',  color: '#f97316', text: 'Weak',       labelColor: '#f97316' },
                { pct: '60%',  color: '#eab308', text: 'Fair',       labelColor: '#ca8a04' },
                { pct: '80%',  color: '#84cc16', text: 'Good',       labelColor: '#65a30d' },
                { pct: '100%', color: '#22c55e', text: 'Strong',     labelColor: '#16a34a' },
            ];

            const level = pw.length === 0 ? levels[0] : levels[score];
            bar.style.width           = level.pct;
            bar.style.backgroundColor = level.color;
            label.textContent         = level.text;
            label.style.color         = level.labelColor;
        }

        /* ---------- Confirm password match ---------- */
        function checkMatch() {
            const npass  = document.getElementById('npass').value;
            const cfpass = document.getElementById('cfpass').value;
            const msg    = document.getElementById('matchMsg');

            if (cfpass.length === 0) {
                msg.classList.add('hidden');
                return;
            }
            msg.classList.remove('hidden');
            if (npass === cfpass) {
                msg.textContent  = '✓ Passwords match';
                msg.className    = 'mt-1 text-xs text-green-600';
            } else {
                msg.textContent  = '✗ Passwords do not match';
                msg.className    = 'mt-1 text-xs text-red-600';
            }
        }

        /* ---------- Client-side validation before submit ---------- */
        document.getElementById('pwForm').addEventListener('submit', function (e) {
            const cpass  = document.getElementById('cpass').value.trim();
            const npass  = document.getElementById('npass').value;
            const cfpass = document.getElementById('cfpass').value;

            if (!cpass) {
                e.preventDefault();
                alert('Current password is required.');
                document.getElementById('cpass').focus();
                return;
            }
            if (!npass) {
                e.preventDefault();
                alert('New password is required.');
                document.getElementById('npass').focus();
                return;
            }
            if (npass.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                document.getElementById('npass').focus();
                return;
            }
            if (npass !== cfpass) {
                e.preventDefault();
                alert('New password and confirm password do not match.');
                document.getElementById('cfpass').focus();
                return;
            }
        });

        /* ---------- Reset also clears strength meter ---------- */
        function resetForm() {
            document.getElementById('pwForm').reset();
            evaluateStrength('');
            document.getElementById('matchMsg').classList.add('hidden');
        }
    </script>

</body>
</html>