<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="brand-wrapper">
            <img src="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>assets/img/RALogo.png" alt="Logo" class="sidebar-logo" width="auto" height="24">
            <span class="brand-text">RA Student Database</span>
        </div>
        <div class="sidebar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </div>
    </div>

    <ul class="sidebar-menu">
        <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
            <!-- Student Menu -->
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>student_dashboard.php"
                   title="Dashboard"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>my_training.php"
                   title="My Training"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_training.php' ? 'active' : ''; ?>">
                    <i class="fas fa-graduation-cap"></i> <span class="link-text">My Training</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>scorm_history.php"
                   title="Attempt History"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'scorm_history.php' ? 'active' : ''; ?>">
                    <i class="fas fa-history"></i> <span class="link-text">Attempt History</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>student_reports.php"
                   title="Reports"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'student_reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-line"></i> <span class="link-text">Reports</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>my_profile.php"
                   title="My Profile"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'my_profile.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user"></i> <span class="link-text">My Profile</span>
                </a>
            </li>
        <?php else: ?>
            <!-- Admin/Staff Menu -->
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>dashboard.php"
                   title="Dashboard"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i> <span class="link-text">Dashboard</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>users.php"
                   title="Users"
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                    <i class="fas fa-user-cog"></i> <span class="link-text">Users</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>students.php"
                   title="Candidates"
                    class="<?php echo (basename($_SERVER['PHP_SELF']) == 'students.php' || basename($_SERVER['PHP_SELF']) == 'student_view.php' || basename($_SERVER['PHP_SELF']) == 'student_add.php' || basename($_SERVER['PHP_SELF']) == 'student_edit.php') ? 'active' : ''; ?>">
                    <i class="fas fa-user-graduate"></i> <span class="link-text">Candidates</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>groups.php"
                   title="Groups"
                   class="<?php echo basename($_SERVER['PHP_SELF']) == 'groups.php' ? 'active' : ''; ?>">
                    <i class="fas fa-users"></i> <span class="link-text">Groups</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>courses.php"
                   title="Courses"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'courses.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book-open"></i> <span class="link-text">Courses</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>academic.php"
                   title="Academic"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'academic.php' ? 'active' : ''; ?>">
                    <i class="fas fa-book"></i> <span class="link-text">Academic</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>reports.php"
                   title="Reports"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-bar"></i> <span class="link-text">Reports</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>attendance_sheet.php"
                   title="Attendance Sheet"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'attendance_sheet.php' ? 'active' : ''; ?>">
                    <i class="fas fa-clipboard-check"></i> <span class="link-text">Attendance Sheet</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>group_performance.php"
                   title="Group Performance"
                    class="<?php echo basename($_SERVER['PHP_SELF']) == 'group_performance.php' ? 'active' : ''; ?>">
                    <i class="fas fa-chart-pie"></i> <span class="link-text">Group Performance</span>
                </a>
            </li>
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>scorm_packages.php"
                   title="SCORM Packages"
                    class="<?php echo (basename($_SERVER['PHP_SELF']) == 'scorm_packages.php' || basename($_SERVER['PHP_SELF']) == 'scorm_upload.php') ? 'active' : ''; ?>">
                    <i class="fas fa-box-open"></i> <span class="link-text">SCORM Packages</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <div class="sidebar-footer" style="padding: 10px 0;">
        <ul class="sidebar-menu">
            <li>
                <a href="<?php echo isset($path_to_root) ? $path_to_root : ''; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i> <span class="link-text">Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<div class="main-content" id="mainContent">
    <div class="top-header">
        <div style="display:flex; align-items:center;">
            <div class="menu-toggle-mobile" style="margin-right: 15px; cursor: pointer;">
                <i class="fas fa-bars"></i>
            </div>
            <div class="page-title-display" style="font-weight: 600; font-size: 1.1rem; color: var(--secondary-color);">
                Student Database System
            </div>
        </div>
        <div class="user-info-display" style="font-weight: 600; font-size: 1.1rem; color: var(--secondary-color);">
            <i class="fas fa-user-circle" style="margin-right: 8px;"></i>
            <?php
            // Get student ID
            $display_text = '';
            if (isset($_SESSION['role']) && $_SESSION['role'] === 'student' && isset($_SESSION['username'])) {
                $display_text = htmlspecialchars($_SESSION['username']);
            }
            
            $fname = isset($_SESSION['first_name']) ? $_SESSION['first_name'] : '';
            $lname = isset($_SESSION['last_name']) ? $_SESSION['last_name'] : '';
            $full_name = trim("$fname $lname");
            
            echo $display_text ? "$full_name ($display_text)" : ($full_name ?: 'Admin User');
            ?>
        </div>
    </div>
    <div class="content-container">

