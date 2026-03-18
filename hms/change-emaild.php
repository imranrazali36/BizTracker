<?php
session_start();
error_reporting(0);
include('include/config.php');
include('include/checklogin.php');
check_login();

if(isset($_POST['submit']))
{
    $email=$_POST['email'];
    $sql=mysqli_query($con,"Update users set email='$email' where id='".$_SESSION['id']."'");
    if($sql)
    {
        $msg="Your email updated Successfully";
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
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">
                    Main Menu
                </p>
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
                <p class="px-3 text-xs font-semibold text-white/70 uppercase tracking-wider">
                    Account Settings
                </p>
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

    <!-- Main Content Area -->
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
            <div class="max-w-4xl mx-auto">
                <?php if($msg) { ?>
                <div class="mb-6 p-4 rounded-lg bg-green-100 text-green-700">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlentities($msg); ?>
                    </div>
                </div>
                <?php } ?>

                <!-- Edit Profile Form -->
                <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <!-- Form Header -->
                    <div class="p-6 bg-gradient-to-r from-[#4b6cb7]/10 to-[#182848]/10">
                        <div class="flex items-center space-x-4">
                            <div class="w-16 h-16 rounded-full bg-gradient-to-r from-[#4b6cb7] to-[#182848] flex items-center justify-center">
                                <i class="fas fa-user-edit text-2xl text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">Edit Your Email</h2>
                                <p class="mt-1 text-sm text-gray-600">Update your email address</p>
                            </div>
                        </div>
                    </div>

                    <!-- Form Content -->
                    <form name="registration" id="updatemail" method="post" class="p-6">
                        <div class="max-w-xl">
                            <div class="space-y-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1" for="email">
                                        Email Address
                                    </label>
                                    <div class="relative">
                                        <input type="email" name="email" id="email" 
                                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-[#4b6cb7] focus:border-transparent"
                                            placeholder="Enter your email address"
                                            onBlur="userAvailability()" required>
                                        <span class="absolute right-3 top-2.5 text-gray-400">
                                            <i class="fas fa-envelope"></i>
                                        </span>
                                    </div>
                                    <span id="user-availability-status1" class="mt-1 text-sm"></span>
                                </div>
                            </div>

                            <!-- Submit Button Section -->
                            <div class="mt-8 pt-4 border-t">
                                <div class="flex items-center justify-end space-x-4">
                                    <button type="reset" class="px-6 py-2.5 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-gray-300 transition-colors">
                                        <i class="fas fa-undo mr-2"></i>
                                        Reset
                                    </button>
                                    <button type="submit" name="submit" class="px-6 py-2.5 bg-gradient-to-r from-[#4b6cb7] to-[#182848] text-white rounded-lg hover:opacity-90 focus:outline-none focus:ring-2 focus:ring-[#4b6cb7] focus:ring-offset-2 transition-all">
                                        <i class="fas fa-save mr-2"></i>
                                        Update Profile
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
        });

        function userAvailability() {
            $("#loaderIcon").show();
            jQuery.ajax({
                url: "check_availability.php",
                data:'email='+$("#email").val(),
                type: "POST",
                success:function(data){
                    $("#user-availability-status1").html(data);
                    $("#loaderIcon").hide();
                },
                error:function (){}
            });
        }
    </script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>