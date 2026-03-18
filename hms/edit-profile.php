<?php
session_start();
include('include/config.php');
include('include/checklogin.php');
check_login();

$errorMsg = '';
$successMsg = '';

if (isset($_POST['submit'])) {
    $fname = trim($_POST['fname']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $gender = $_POST['gender'];
    $contactNumber = trim($_POST['contactnumber']);
    $dob = $_POST['dob'];

    // Validation checks
    if (empty($fname) || empty($contactNumber)) {
        $errorMsg = "Please fill in all required fields";
    } elseif (!preg_match('/^[0-9]{10,15}$/', $contactNumber)) {
        $errorMsg = "Enter a valid contact number (10-15 digits)";
    } elseif (strtotime($dob) > time()) {
        $errorMsg = "Date of birth cannot be in the future";
    } else {
        // Check if any changes were made
        $stmt = $con->prepare("SELECT fullName, address, city, gender, contactno, dob FROM users WHERE id = ?");
        $stmt->bind_param("i", $_SESSION['id']);
        $stmt->execute();
        $result = $stmt->get_result();
        $currentData = $result->fetch_assoc();
        $stmt->close();

        if ($fname === $currentData['fullName'] && 
            $address === $currentData['address'] && 
            $city === $currentData['city'] && 
            $gender === $currentData['gender'] && 
            $contactNumber === $currentData['contactno'] && 
            $dob === $currentData['dob']) {
            $successMsg = "No changes detected";
        } else {
            // Update profile if changes were made
            $stmt = $con->prepare("UPDATE users 
                SET fullName = ?, address = ?, city = ?, gender = ?, contactno = ?, dob = ?, updationDate = NOW() 
                WHERE id = ?");
            
            if (!$stmt) {
                die("Prepare failed: " . $con->error);
            }

            $stmt->bind_param("ssssssi", $fname, $address, $city, $gender, $contactNumber, $dob, $_SESSION['id']);
            
            if ($stmt->execute()) {
                $successMsg = "Your profile updated successfully!";
            } else {
                $errorMsg = "Error updating profile: " . $stmt->error;
            }

            $stmt->close();
        }
    }
}

// Fetch user details
$userId = $_SESSION['id'];
$userQuery = mysqli_query($con, "SELECT fullName FROM users WHERE id = '$userId'");
$userData = mysqli_fetch_array($userQuery);
$userName = $userData['fullName'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User | Edit Profile</title>
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

    /* ── Input icon wrapper ── */
    .input-icon-wrap { position: relative; }
    .input-icon-wrap .field-icon {
        position: absolute;
        left: 13px;
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af;
        font-size: 14px;
        pointer-events: none;
    }
    .input-icon-wrap .field-icon-top {
        position: absolute;
        left: 13px;
        top: 14px;
        color: #9ca3af;
        font-size: 14px;
        pointer-events: none;
    }
    .input-icon-wrap input,
    .input-icon-wrap select,
    .input-icon-wrap textarea { padding-left: 2.4rem !important; }

    /* ── Section badge ── */
    .section-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #4b6cb7;
        background: #eff6ff;
        padding: 3px 10px;
        border-radius: 999px;
        margin-bottom: 8px;
    }

    /* ── Avatar initials ── */
    .avatar-initials {
        width: 64px; height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, #4b6cb7, #182848);
        display: flex; align-items: center; justify-content: center;
        font-size: 1.5rem; font-weight: 700;
        color: white; flex-shrink: 0;
        letter-spacing: -0.02em;
        box-shadow: 0 4px 14px rgba(75,108,183,0.35);
    }

    /* ── Char counter ── */
    .char-counter { font-size: 11px; color: #9ca3af; text-align: right; margin-top: 2px; }
    .char-counter.warn { color: #f97316; }
    .char-counter.limit { color: #ef4444; }

    /* ── Field hint ── */
    .field-hint { font-size: 11.5px; color: #6b7280; margin-top: 3px; display: flex; align-items: center; gap: 4px; }
    .field-hint i { color: #9ca3af; font-size: 10px; }

    /* ── Readonly badge ── */
    .readonly-badge {
        display: inline-flex; align-items: center; gap: 4px;
        font-size: 10px; font-weight: 600; color: #6b7280;
        background: #f3f4f6; border: 1px solid #e5e7eb;
        padding: 2px 8px; border-radius: 999px; margin-left: 6px;
    }
    </style>
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

            <a href="edit-profile.php" class="nav-link active flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white">
                <i class="fas fa-user-edit w-5 text-center"></i>
                <span>My Profile</span>
            </a>
            <a href="change-password.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
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
                        <h1 class="text-2xl font-semibold">Edit Profile</h1>
                    </div>
                    <nav class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2 text-sm">
                            <span class="text-white/70">User</span>
                            <span class="text-white/40">/</span>
                            <span class="text-white">Edit Profile</span>
                        </div>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <?php
            $stmt = $con->prepare("SELECT fullName, address, city, gender, contactno, dob, email, regDate, updationDate FROM users WHERE id = ?");
            $stmt->bind_param("i", $_SESSION['id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();

            // Generate initials for avatar
            $nameParts = explode(' ', trim($data['fullName']));
            $initials  = strtoupper(
                (isset($nameParts[0]) ? $nameParts[0][0] : '') .
                (isset($nameParts[1]) ? $nameParts[1][0] : '')
            );
            ?>

            <!-- Error Message -->
            <?php if (!empty($errorMsg)): ?>
                <div class="mb-6 p-4 bg-red-50 border border-red-300 text-red-700 rounded-lg shadow-sm flex items-start gap-3">
                    <i class="fas fa-exclamation-circle mt-0.5 flex-shrink-0 text-red-500"></i>
                    <div>
                        <p class="font-semibold text-sm">Please fix the following:</p>
                        <p class="text-sm mt-0.5"><?php echo htmlentities($errorMsg); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Success Message -->
            <?php if (!empty($successMsg)): ?>
                <div class="mb-6 p-4 bg-green-50 border border-green-300 text-green-700 rounded-lg shadow-sm flex items-start gap-3">
                    <i class="fas fa-check-circle mt-0.5 flex-shrink-0 text-green-500"></i>
                    <div>
                        <p class="font-semibold text-sm"><?php echo htmlentities($successMsg); ?></p>
                        <?php if (strpos($successMsg, 'successfully') !== false): ?>
                            <p class="text-xs mt-0.5 text-green-600">Your changes have been saved and are now active.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Profile Card -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">

                <!-- Profile Header -->
                <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10 border-b border-gray-100">
                    <div class="flex items-center space-x-4">
                        <!-- Avatar with initials -->
                        <div class="avatar-initials"><?php echo htmlspecialchars($initials ?: '?', ENT_QUOTES, 'UTF-8'); ?></div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800">
                                <?php echo htmlentities($data['fullName']); ?>'s Profile
                            </h2>
                            <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500">
                                <span class="inline-flex items-center gap-1">
                                    <i class="far fa-calendar-alt text-[#4b6cb7]"></i>
                                    Registered: <?php echo htmlentities($data['regDate']); ?>
                                </span>
                                <?php if ($data['updationDate']): ?>
                                    <span class="inline-flex items-center gap-1">
                                        <i class="far fa-clock text-[#4b6cb7]"></i>
                                        Last Updated: <?php echo htmlentities($data['updationDate']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Form -->
                <form method="post" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

                        <!-- ── Personal Information ── -->
                        <div class="space-y-5">
                            <div>
                                <span class="section-badge"><i class="fas fa-user"></i> Personal Information</span>
                                <h3 class="text-base font-semibold text-gray-800 border-b pb-2">Who you are</h3>
                            </div>

                            <!-- Full Name -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="fname">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-id-card field-icon"></i>
                                    <input type="text" name="fname" id="fname" required maxlength="100"
                                        class="w-full px-4 py-2 border <?php echo (!empty($errorMsg) && empty($fname)) ? 'border-red-400 bg-red-50' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent transition"
                                        value="<?php echo htmlentities($data['fullName']); ?>"
                                        placeholder="e.g. Ahmad bin Ali"
                                        oninput="countChars(this, 'fname-count', 100)">
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="field-hint"><i class="fas fa-info-circle"></i> Letters and spaces only, up to 100 characters</span>
                                    <span class="char-counter" id="fname-count"></span>
                                </div>
                            </div>

                            <!-- Gender -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="gender">
                                    Gender
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-venus-mars field-icon"></i>
                                    <select name="gender" id="gender"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent bg-white transition">
                                        <option value="<?php echo htmlentities($data['gender']); ?>" selected>
                                            <?php echo htmlentities($data['gender']); ?>
                                        </option>
                                        <option value="male">Male</option>
                                        <option value="female">Female</option>
                                        <option value="other">Other</option>
                                        <option value="prefer not to say">Prefer not to say</option>
                                    </select>
                                </div>
                                <p class="field-hint mt-1"><i class="fas fa-info-circle"></i> Select the option that best describes you</p>
                            </div>

                            <!-- Date of Birth -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="dob">
                                    Date of Birth
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-birthday-cake field-icon"></i>
                                    <input type="date" name="dob" id="dob"
                                        max="<?php echo date('Y-m-d'); ?>"
                                        class="w-full px-4 py-2 border <?php echo (!empty($errorMsg) && strtotime($dob) > time()) ? 'border-red-400 bg-red-50' : 'border-gray-300'; ?> rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent transition"
                                        value="<?php echo htmlentities($data['dob']); ?>">
                                </div>
                                <p class="field-hint mt-1"><i class="fas fa-info-circle"></i> Cannot be a future date</p>
                            </div>
                        </div>

                        <!-- ── Contact Information ── -->
                        <div class="space-y-5">
                            <div>
                                <span class="section-badge"><i class="fas fa-address-book"></i> Contact Information</span>
                                <h3 class="text-base font-semibold text-gray-800 border-b pb-2">How to reach you</h3>
                            </div>

                            <!-- Address -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="address">
                                    Address
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-map-marker-alt field-icon-top"></i>
                                    <textarea name="address" id="address" rows="3" maxlength="255"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent transition resize-none"
                                        placeholder="e.g. No. 12, Jalan Maju, Taman Bahagia"
                                        oninput="countChars(this, 'addr-count', 255)"
                                    ><?php echo htmlentities($data['address']); ?></textarea>
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="field-hint"><i class="fas fa-info-circle"></i> Your full street address</span>
                                    <span class="char-counter" id="addr-count"></span>
                                </div>
                            </div>

                            <!-- City -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="city">
                                    City
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-city field-icon"></i>
                                    <input type="text" name="city" id="city" required maxlength="100"
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent transition"
                                        value="<?php echo htmlentities($data['city']); ?>"
                                        placeholder="e.g. Kuala Lumpur"
                                        oninput="countChars(this, 'city-count', 100)">
                                </div>
                                <div class="flex justify-between items-center mt-1">
                                    <span class="field-hint"><i class="fas fa-info-circle"></i> Town or city name</span>
                                    <span class="char-counter" id="city-count"></span>
                                </div>
                            </div>

                            <!-- Contact Number -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="contactno">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-phone field-icon"></i>
                                    <input type="text" name="contactnumber" id="contactno" required
                                        class="w-full px-4 py-2 border <?php echo (!empty($errorMsg) && (!preg_match('/^[0-9]{10,15}$/', $contactNumber) || empty($contactNumber)) ? 'border-red-400 bg-red-50' : 'border-gray-300'); ?> rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent transition"
                                        value="<?php echo htmlentities($data['contactno']); ?>"
                                        placeholder="e.g. 0123456789"
                                        pattern="[0-9]{10,15}"
                                        maxlength="15"
                                        title="Please enter a valid phone number (10-15 digits)">
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <p class="field-hint"><i class="fas fa-info-circle"></i> Digits only, 10–15 numbers (e.g. 0123456789)</p>
                                    <span id="contactStatus" class="hidden text-xs font-medium px-2 py-0.5 rounded-full"></span>
                                </div>
                            </div>
                        </div>

                        <!-- ── Email Information ── -->
                        <div class="md:col-span-2 space-y-4">
                            <div>
                                <span class="section-badge"><i class="fas fa-envelope"></i> Email Information</span>
                                <h3 class="text-base font-semibold text-gray-800 border-b pb-2">Login email address</h3>
                            </div>

                            <div class="max-w-md">
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="uemail">
                                    Email Address
                                    <span class="readonly-badge"><i class="fas fa-lock"></i> Read only</span>
                                </label>
                                <div class="input-icon-wrap">
                                    <i class="fas fa-envelope field-icon"></i>
                                    <input type="email" name="uemail" id="uemail"
                                        class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-50 cursor-not-allowed text-gray-500"
                                        value="<?php echo htmlentities($data['email']); ?>"
                                        readonly>
                                </div>
                                <div class="mt-2 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
                                    <i class="fas fa-exclamation-triangle text-amber-500 mt-0.5 flex-shrink-0 text-sm"></i>
                                    <p class="text-xs text-amber-700">
                                        Your email is used to log in and cannot be changed here.
                                        <a href="change-emaild.php" class="font-semibold underline hover:text-amber-900">
                                            Click here to update your email address.
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- ── Profile tips ── -->
                        <div class="md:col-span-2">
                            <div class="p-4 bg-blue-50 border border-blue-100 rounded-lg flex items-start gap-3">
                                <i class="fas fa-lightbulb text-blue-400 mt-0.5 flex-shrink-0"></i>
                                <div class="text-xs text-blue-700 space-y-1">
                                    <p class="font-semibold text-blue-800">Tips for a complete profile</p>
                                    <ul class="space-y-0.5 list-disc list-inside">
                                        <li>Use your full legal name as it appears on official documents</li>
                                        <li>Keep your contact number up to date so we can reach you</li>
                                        <li>Your profile information is kept private and secure</li>
                                        <li>To change your password, visit <a href="change-password.php" class="font-semibold underline hover:text-blue-900">Change Password</a></li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- ── Submit ── -->
                        <div class="md:col-span-2 pt-4 border-t">
                            <div class="flex items-center justify-between">
                                <p class="text-xs text-gray-400">
                                    <i class="fas fa-asterisk text-red-400 mr-1" style="font-size:8px;"></i>
                                    Required fields must be filled before saving
                                </p>
                                <div class="flex items-center space-x-4">
                                    <button type="reset"
                                            class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                                        <i class="fas fa-undo mr-2"></i>Reset Changes
                                    </button>
                                    <button type="submit" name="submit"
                                            class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all flex items-center gap-2">
                                        <i class="fas fa-save"></i> Update Profile
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </form>
                <?php $stmt->close(); ?>
            </div>

        </main>
    </div><!-- end .main-content -->

    <!-- ============================================================
         SCRIPTS
         ============================================================ -->
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar       = document.querySelector('.sidebar');
            const mainContent   = document.querySelector('.main-content');

            sidebarToggle.addEventListener('click', function () {
                sidebar.classList.toggle('active');
                mainContent.classList.toggle('active');
            });

            // Client-side validation for contact number — digits only
            const contactInput = document.getElementById('contactno');
            contactInput.addEventListener('input', function () {
                this.value = this.value.replace(/[^0-9]/g, '');
                validateContact(this.value);
            });
            // Run on load to set initial state
            validateContact(contactInput.value);

            // Client-side validation for date of birth
            const dobInput = document.getElementById('dob');
            dobInput.addEventListener('change', function () {
                const selectedDate = new Date(this.value);
                const today = new Date();
                if (selectedDate > today) {
                    alert('Date of birth cannot be in the future');
                    this.value = '';
                }
            });

            // Initialise char counters on load
            const fname = document.getElementById('fname');
            if (fname.value) countChars(fname, 'fname-count', 100);

            const addr = document.getElementById('address');
            if (addr.value) countChars(addr, 'addr-count', 255);

            const city = document.getElementById('city');
            if (city.value) countChars(city, 'city-count', 100);
        });

        function confirmLogout() {
            return confirm('Are you sure you want to log out?');
        }

        /* ── Character counter ── */
        function countChars(el, counterId, max) {
            const counter = document.getElementById(counterId);
            if (!counter) return;
            const len = el.value.length;
            counter.textContent = len + ' / ' + max;
            counter.className = 'char-counter';
            if (len >= max)          counter.classList.add('limit');
            else if (len >= max * 0.85) counter.classList.add('warn');
        }

        /* ── Contact number live validation ── */
        function validateContact(val) {
            const badge = document.getElementById('contactStatus');
            if (!val) { badge.classList.add('hidden'); return; }
            badge.classList.remove('hidden');
            const ok = /^[0-9]{10,15}$/.test(val);
            if (ok) {
                badge.textContent = '✓ Valid';
                badge.className   = 'text-xs font-medium px-2 py-0.5 rounded-full bg-green-100 text-green-700';
            } else {
                badge.textContent = val.length < 10 ? 'Too short' : val.length > 15 ? 'Too long' : 'Digits only';
                badge.className   = 'text-xs font-medium px-2 py-0.5 rounded-full bg-red-100 text-red-600';
            }
        }
    </script>
</body>
</html>