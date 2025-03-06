<?php
// ไฟล์ header.php - ส่วนหัวของเว็บไซต์
// ถ้ายังไม่มีการเริ่ม session ให้เริ่ม session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการพัสดุ/ครุภัณฑ์</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 5 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.8/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <style>
        body {
            font-family: 'Sarabun', sans-serif;
            background-color: #f8f9fa;
            font-size: 0.95rem;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
        }
        .navbar-brand .logo-text {
            background-color: #0d6efd;
            color: white;
            padding: 5px 10px;
            border-radius: 5px;
            margin-right: 5px;
        }
        .sidebar {
            min-height: calc(100vh - 56px);
            background-color: #343a40;
            color: white;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
        }
        .sidebar .nav-link:hover {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .sidebar .nav-link.active {
            color: white;
            background-color: #0d6efd;
        }
        .sidebar .nav-link i {
            margin-right: 8px;
            width: 20px;
            text-align: center;
        }
        .content {
            padding: 20px;
        }
        .user-profile {
            display: flex;
            align-items: center;
            color: white;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 15px;
        }
        .user-profile img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            margin-right: 10px;
        }
        .user-profile .role {
            font-size: 0.8rem;
            opacity: 0.8;
        }
        .table-responsive {
            overflow-x: auto;
        }
        /* ปรับแต่ง DataTables */
        .dataTables_wrapper .dataTables_length, 
        .dataTables_wrapper .dataTables_filter, 
        .dataTables_wrapper .dataTables_info, 
        .dataTables_wrapper .dataTables_processing, 
        .dataTables_wrapper .dataTables_paginate {
            margin-bottom: 10px;
            color: #333;
        }
        /* Badge ปรับแต่ง */
        .badge {
            font-weight: 500;
            padding: 6px 8px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <span class="logo-text">INV</span> ระบบบริหารจัดการพัสดุ/ครุภัณฑ์
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if (isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-1"></i> 
                                <?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-id-card me-2"></i>โปรไฟล์</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>ออกจากระบบ</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <div class="container-fluid">
        <div class="row">
            <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Sidebar -->
                <div class="col-md-3 col-lg-2 d-md-block sidebar collapse" id="sidebar">
                    <div class="user-profile">
                        <div class="avatar">
                            <i class="fas fa-user-circle fa-2x"></i>
                        </div>
                        <div class="user-info ms-2">
                            <div class="name"><?= htmlspecialchars($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></div>
                            <div class="role">
                                <?php
                                $role_text = '';
                                switch ($_SESSION['role']) {
                                    case 'admin':
                                        $role_text = 'ผู้ดูแลระบบ';
                                        break;
                                    case 'staff':
                                        $role_text = 'เจ้าหน้าที่';
                                        break;
                                    case 'user':
                                        $role_text = 'ผู้ใช้งานทั่วไป';
                                        break;
                                    case 'viewer':
                                        $role_text = 'ผู้ดูข้อมูล';
                                        break;
                                }
                                echo $role_text;
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <ul class="nav flex-column">
                        <!-- รายการเมนู -->
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                                <i class="fas fa-tachometer-alt"></i> แดชบอร์ด
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'items.php' || basename($_SERVER['PHP_SELF']) == 'item_add.php' || basename($_SERVER['PHP_SELF']) == 'item_edit.php' || basename($_SERVER['PHP_SELF']) == 'item_detail.php' ? 'active' : '' ?>" href="items.php">
                                <i class="fas fa-boxes"></i> พัสดุ/ครุภัณฑ์
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'categories.php' || basename($_SERVER['PHP_SELF']) == 'category_edit.php' ? 'active' : '' ?>" href="categories.php">
                                <i class="fas fa-tags"></i> หมวดหมู่
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'borrowings.php' || basename($_SERVER['PHP_SELF']) == 'borrowing_add.php' || basename($_SERVER['PHP_SELF']) == 'borrowing_detail.php' ? 'active' : '' ?>" href="borrowings.php">
                                <i class="fas fa-exchange-alt"></i> การยืม-คืน
                            </a>
                        </li>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' || basename($_SERVER['PHP_SELF']) == 'user_add.php' || basename($_SERVER['PHP_SELF']) == 'user_edit.php' ? 'active' : '' ?>" href="users.php">
                                    <i class="fas fa-users"></i> ผู้ใช้งาน
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : '' ?>" href="reports.php">
                                <i class="fas fa-chart-bar"></i> รายงาน
                            </a>
                        </li>
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <li class="nav-item">
                                <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                                    <i class="fas fa-cog"></i> ตั้งค่าระบบ
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
                
                <!-- เนื้อหา -->
                <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
            <?php endif; ?>