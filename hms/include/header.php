<?php error_reporting(0); ?>
<header class="main-header">
    <!-- Navbar Container -->
    <nav class="navbar" style="
        background: linear-gradient(90deg, #4c69ba 0%, #1e2f5c 100%);
        padding: 0.75rem 2rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    ">
        <div class="navbar-container" style="
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        ">
            <!-- Left Side - Brand and Toggle -->
            <div class="navbar-left" style="display: flex; align-items: center;">
                <!-- Mobile Toggle Button -->
                <button class="mobile-toggle" style="
                    background: none;
                    border: none;
                    color: white;
                    font-size: 1.5rem;
                    display: none;
                    padding: 0.5rem;
                    cursor: pointer;
                    margin-right: 1rem;
                    @media (max-width: 768px) {
                        display: block;
                    }
                ">
                    <i class="ti-align-justify"></i>
                </button>

                <!-- Brand Logo/Name -->
                <a href="#" class="brand-logo" style="
                    color: white;
                    text-decoration: none;
                    font-size: 1.5rem;
                    font-weight: 400;
                ">
                    Glycowave
                </a>
            </div>

            <!-- Right Side - Title and User Profile -->
            <div class="navbar-right" style="
                display: flex;
                align-items: center;
                gap: 2rem;
            ">
                <!-- System Title -->
                <h2 style="
                    color: white;
                    margin: 0;
                    font-size: 1.5rem;
                    font-weight: 400;
                ">
                    Diabetes Management System
                </h2>

                <!-- User Profile -->
                <div class="user-profile dropdown" style="position: relative;">
                    <a href="#" class="dropdown-toggle" style="
                        color: white;
                        text-decoration: none;
                        display: flex;
                        align-items: center;
                        gap: 0.5rem;
                        padding: 0.5rem;
                        cursor: pointer;
                    ">
                        <img src="assets/images/images.jpg" style="
                            width: 32px;
                            height: 32px;
                            border-radius: 50%;
                            border: 2px solid rgba(255,255,255,0.2);
                        ">
                        <span class="username" style="color: white;">
                            <?php 
                            $query = mysqli_query($con, "select fullName from users where id='".$_SESSION['id']."'");
                            while($row = mysqli_fetch_array($query)) {
                                echo $row['fullName'];
                            }
                            ?>
                        </span>
                        <i class="ti-angle-down"></i>
                    </a>

                    <!-- Dropdown Menu -->
                    <ul class="dropdown-menu" style="
                        position: absolute;
                        right: 0;
                        top: 100%;
                        background: white;
                        border-radius: 4px;
                        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                        min-width: 200px;
                        display: none;
                        margin: 0.5rem 0 0 0;
                        padding: 0;
                        list-style: none;
                    ">
                        <li>
                            <a href="edit-profile.php" style="
                                display: block;
                                padding: 0.75rem 1rem;
                                color: #333;
                                text-decoration: none;
                                transition: background 0.2s;
                            ">My Profile</a>
                        </li>
                        <li>
                            <a href="change-password.php" style="
                                display: block;
                                padding: 0.75rem 1rem;
                                color: #333;
                                text-decoration: none;
                                transition: background 0.2s;
                            ">Change Password</a>
                        </li>
                        <li>
                            <a href="logout.php" style="
                                display: block;
                                padding: 0.75rem 1rem;
                                color: #333;
                                text-decoration: none;
                                transition: background 0.2s;
                            ">Log Out</a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
</header>

<!-- Add this JavaScript for dropdown functionality -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropdownToggle = document.querySelector('.dropdown-toggle');
    const dropdownMenu = document.querySelector('.dropdown-menu');
    
    dropdownToggle.addEventListener('click', function(e) {
        e.preventDefault();
        dropdownMenu.style.display = 
            dropdownMenu.style.display === 'none' || dropdownMenu.style.display === '' 
            ? 'block' 
            : 'none';
    });

    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown')) {
            dropdownMenu.style.display = 'none';
        }
    });
});
</script>