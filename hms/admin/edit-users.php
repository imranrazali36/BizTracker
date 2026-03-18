<?php
session_start();
error_reporting(0);
include('include/config.php');

$adminId = $_SESSION['id'];
$adminQuery = mysqli_query($con, "SELECT username FROM admin WHERE id = '$adminId'");
$adminData = mysqli_fetch_array($adminQuery);
$adminName = $adminData['username'];

if(strlen($_SESSION['id']) == 0) {
    header('location:logout.php');
} else {
    $did = intval($_GET['id']); // Get user ID

    if(isset($_POST['submit'])) {
        $fullName = mysqli_real_escape_string($con, $_POST['fullName']);
        $address = mysqli_real_escape_string($con, $_POST['address']);
        $city = mysqli_real_escape_string($con, $_POST['city']);
        $gender = mysqli_real_escape_string($con, $_POST['gender']);
        $dob = mysqli_real_escape_string($con, $_POST['dob']);
        $contactno = mysqli_real_escape_string($con, $_POST['contactno']);

        // Update query
        $sql = mysqli_query($con, "UPDATE users SET fullName='$fullName', address='$address', city='$city', gender='$gender', dob='$dob', contactno='$contactno', updationDate=NOW() WHERE id='$did'");
        
        if($sql) {
            $msg = "User Details Updated Successfully!";
            $msgClass = "bg-green-100 border-green-400 text-green-700";
        } else {
            $msg = "Error in updating details. Please try again!";
            $msgClass = "bg-red-100 border-red-400 text-red-700";
        }
    }

    // Fetch existing user data
    $sql = mysqli_query($con, "SELECT * FROM users WHERE id='$did'");
    $data = mysqli_fetch_array($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin | Edit User</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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
    select {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-color: white;
        padding-right: 2.5rem;
    }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Sidebar -->
    <div class="sidebar fixed h-full text-white">
        <!-- Logo Section -->
        <div class="p-5 bg-[#182848]">
            <h2 class="text-xl font-bold flex items-center space-x-2">
                <img src="assets/images/biztracker.png" alt="BizTracker Logo" class="w-7 h-7 object-contain">
                <span>BizTracker</span>
            </h2>
        </div>
    
    <!-- Admin Profile Section -->
    <div class="p-4 border-b border-white/10">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center">
                <i class="fas fa-user-shield text-white"></i>
            </div>
            <div class="overflow-hidden">
                <h3 class="font-medium truncate"><?php echo htmlentities($adminName); ?></h3>
                <p class="text-sm text-white/70">Administrator</p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <nav class="mt-4 px-3">
        <div class="mb-2">
            <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">
                Main Menu
            </p>
        </div>

        <a href="dashboard.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span>Dashboard</span>
        </a>

        <a href="manage-users.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span>Users</span>
        </a>

        <a href="user-logs.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
            <i class="fas fa-file-alt w-5 text-center"></i>
            <span>User Session Logs</span>
        </a>

        <a href="manage_feedback.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span>Feedback</span>
        </a>

        <a href="about-us.php" class="nav-link flex items-center space-x-3 px-3 py-3 rounded-lg font-medium text-white/80 hover:text-white">
            <i class="fas fa-tachometer-alt w-5 text-center"></i>
            <span>About Us</span>
        </a>

        <div class="mt-4 mb-2">
            <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">
                Account Settings
            </p>
        </div>

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
                    <h1 class="text-2xl font-semibold">Edit User</h1>
                </div>
                <nav class="flex items-center space-x-4">
                    <div class="flex items-center space-x-2 text-sm">
                        <span class="text-white/70">Admin</span>
                        <span class="text-white/40">/</span>
                        <span class="text-white">Edit User</span>
                    </div>
                </nav>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- User Details Form -->
            <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                <!-- Form Header -->
                <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10">
                    <div class="flex items-center space-x-4">
                        <div class="w-16 h-16 rounded-full bg-gradient-to-r from-[#4b6cb7] to-[#182848] flex items-center justify-center">
                            <i class="fas fa-user text-2xl text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-800"><?php echo htmlentities($data['fullName']); ?>'s Profile</h2>
                            <div class="mt-1 text-sm text-gray-600">
                                <span class="inline-flex items-center">
                                    <i class="far fa-calendar-alt mr-2"></i>
                                    Registered: <?php echo htmlentities($data['regDate']); ?>
                                </span>
                                <?php if ($data['updationDate']) { ?>
                                    <span class="inline-flex items-center ml-4">
                                        <i class="far fa-clock mr-2"></i>
                                        Last Updated: <?php echo htmlentities($data['updationDate']); ?>
                                    </span>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error/Success Message -->
                <?php if(!empty($msg)): ?>
                    <div class="<?php echo $msgClass; ?> px-4 py-3 border-b">
                        <div class="flex items-center">
                            <i class="<?php echo strpos($msg, 'Error') !== false ? 'fas fa-exclamation-circle' : 'fas fa-check-circle'; ?> mr-2"></i>
                            <?php echo htmlentities($msg); ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Form Content -->
                <form role="form" name="updateuser" method="post" class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Personal Information Section -->
                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Personal Information</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="fullName">
                                    Full Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="fullName" id="fullName"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    value="<?php echo htmlentities($data['fullName']); ?>" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="gender">
                                    Gender <span class="text-red-500">*</span>
                                </label>
                                <select name="gender" id="gender"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    required>
                                    <option value="Male" <?php if ($data['gender'] == 'Male') echo 'selected'; ?>>Male</option>
                                    <option value="Female" <?php if ($data['gender'] == 'Female') echo 'selected'; ?>>Female</option>
                                    <option value="Other" <?php if ($data['gender'] == 'Other') echo 'selected'; ?>>Other</option>
                                    <option value="Prefer not to say" <?php if ($data['gender'] == 'Prefer not to say') echo 'selected'; ?>>Prefer not to say</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="dob">
                                    Date of Birth <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="dob" id="dob" max="<?php echo date('Y-m-d'); ?>"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    value="<?php echo htmlentities($data['dob']); ?>" required>
                            </div>
                        </div>

                        <!-- Contact Information Section -->
                        <div class="space-y-6">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Contact Information</h3>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="address">
                                    Address <span class="text-red-500">*</span>
                                </label>
                                <textarea name="address" id="address" rows="3"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    required><?php echo htmlentities($data['address']); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="city">
                                    City <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="city" id="city"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    value="<?php echo htmlentities($data['city']); ?>" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1" for="contactno">
                                    Contact Number <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="contactno" id="contactno"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                    value="<?php echo htmlentities($data['contactno']); ?>" 
                                    pattern="[0-9]{10,15}"
                                    title="Please enter a valid phone number (10-15 digits)"
                                    required>
                                <p class="mt-1 text-sm text-gray-500">10-15 digits only</p>
                            </div>
                        </div>

                        <!-- Email Information Section -->
                        <div class="md:col-span-2 space-y-6">
                            <h3 class="text-lg font-semibold text-gray-800 border-b pb-2">Account Information</h3>
                            <div class="max-w-md">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="uemail">
                                        Email Address
                                    </label>
                                    <div class="relative">
                                        <input type="email" name="uemail" id="uemail"
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50 cursor-not-allowed"
                                            value="<?php echo htmlentities($data['email']); ?>"
                                            readonly>
                                        <span class="absolute right-3 top-2.5 text-gray-500">
                                            <i class="fas fa-lock"></i>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button Section -->
                        <div class="md:col-span-2 pt-4 border-t">
                            <div class="flex items-center justify-end space-x-4">
                                <button type="reset" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                                    <i class="fas fa-undo mr-2"></i>
                                    Reset Changes
                                </button>
                                <button type="submit" name="submit" class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all">
                                    <i class="fas fa-save mr-2"></i>
                                    Update User
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const sidebarToggle = document.getElementById('sidebarToggle');
        const sidebar = document.querySelector('.sidebar');
        const mainContent = document.querySelector('.main-content');

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            mainContent.classList.toggle('active');
        });

        // Client-side validation for contact number
        const contactInput = document.getElementById('contactno');
        contactInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });

        // Client-side validation for date of birth
        const dobInput = document.getElementById('dob');
        dobInput.addEventListener('change', function() {
            const selectedDate = new Date(this.value);
            const today = new Date();
            if (selectedDate > today) {
                alert('Date of birth cannot be in the future');
                this.value = '';
            }
        });
    });

    function confirmLogout() {
        return confirm('Are you sure you want to log out?');
    }
</script>
</body>
</html>
<?php } ?>