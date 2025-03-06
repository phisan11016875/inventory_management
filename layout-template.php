
<?php
// footer.php - ส่วนท้ายของเว็บไซต์
?>
            </section>
            <!-- /.content -->
        </div>
        <!-- /.content-wrapper -->

        <!-- Main Footer -->
        <footer class="main-footer">
            <!-- To the right -->
            <div class="pull-right hidden-xs">
                เวอร์ชัน 1.0.0
            </div>
            <!-- Default to the left -->
            <strong>Copyright &copy; <?php echo date('Y'); ?> <a href="#">ระบบบริหารจัดการพัสดุ/ครุภัณฑ์</a>.</strong> สงวนลิขสิทธิ์.
        </footer>
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/js/bootstrap.min.js"></script>
    <!-- AdminLTE App -->
    <script src="https://cdn.jsdelivr.net/npm/admin-lte@2.4.18/dist/js/adminlte.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap.min.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    <!-- ตั้งค่า DataTables -->
    <script>
        $(document).ready(function() {
            $('.dataTable').DataTable({
                "language": {
                    "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
                    "zeroRecords": "ไม่พบข้อมูล",
                    "info": "แสดงหน้า _PAGE_ จาก _PAGES_",
                    "infoEmpty": "ไม่มีข้อมูล",
                    "infoFiltered": "(กรองข้อมูลจาก _MAX_ รายการทั้งหมด)",
                    "search": "ค้นหา:",
                    "paginate": {
                        "first": "หน้าแรก",
                        "last": "หน้าสุดท้าย",
                        "next": "ถัดไป",
                        "previous": "ก่อนหน้า"
                    }
                },
                "responsive": true
            });
        });
    </script>
    
    <?php if (isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'ข้อผิดพลาด',
            text: '<?php echo $_SESSION['error']; ?>',
            confirmButtonText: 'ตกลง'
        });
    </script>
    <?php unset($_SESSION['error']); endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ',
            text: '<?php echo $_SESSION['success']; ?>',
            confirmButtonText: 'ตกลง'
        });
    </script>
    <?php unset($_SESSION['success']); endif; ?>
</body>
</html>

</head>
<body class="hold-transition skin-blue sidebar-mini thai-font">
    <div class="wrapper">
        <!-- Main Header -->
        <header class="main-header">
            <!-- Logo -->
            <a href="index.php" class="logo">
                <!-- mini logo for sidebar mini 50x50 pixels -->
                <span class="logo-mini"><b>INV</b></span>
                <!-- logo for regular state and mobile devices -->
                <span class="logo-lg"><b>พัสดุ</b>ครุภัณฑ์</span>
            </a>

            <!-- Header Navbar -->
            <nav class="navbar navbar-static-top" role="navigation">
                <!-- Sidebar toggle button-->
                <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                    <span class="sr-only">Toggle navigation</span>
                </a>
                <!-- Navbar Right Menu -->
                <div class="navbar-custom-menu">
                    <ul class="nav navbar-nav">
                        <!-- User Account Menu -->
                        <li class="dropdown user user-menu">
                            <!-- Menu Toggle Button -->
                            <a href="#" class="dropdown-toggle" data-toggle="dropdown">
                                <!-- The user image in the navbar-->
                                <img src="<?php echo isset($current_user['profile_image']) ? 'uploads/' . $current_user['profile_image'] : 'dist/img/default-user.png'; ?>" class="user-image" alt="User Image">
                                <!-- hidden-xs hides the username on small devices so only the image appears. -->
                                <span class="hidden-xs"><?php echo $_SESSION['fullname']; ?></span>
                            </a>
                            <ul class="dropdown-menu">
                                <!-- The user image in the menu -->
                                <li class="user-header">
                                    <img src="<?php echo isset($current_user['profile_image']) ? 'uploads/' . $current_user['profile_image'] : 'dist/img/default-user.png'; ?>" class="img-circle" alt="User Image">
                                    <p>
                                        <?php echo $_SESSION['fullname']; ?>
                                        <small><?php echo $_SESSION['role'] == 'admin' ? 'ผู้ดูแลระบบ' : 'เจ้าหน้าที่พัสดุ'; ?></small>
                                    </p>
                                </li>
                                <!-- Menu Footer-->
                                <li class="user-footer">
                                    <div class="pull-left">
                                        <a href="profile.php" class="btn btn-default btn-flat">โปรไฟล์</a>
                                    </div>
                                    <div class="pull-right">
                                        <a href="logout.php" class="btn btn-default btn-flat">ออกจากระบบ</a>
                                    </div>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </nav>
        </header>

        <!-- Left side column. contains the logo and sidebar -->
        <aside class="main-sidebar">
            <!-- sidebar: style can be found in sidebar.less -->
            <section class="sidebar">
                <!-- Sidebar user panel -->
                <div class="user-panel">
                    <div class="pull-left image">
                        <img src="<?php echo isset($current_user['profile_image']) ? 'uploads/' . $current_user['profile_image'] : 'dist/img/default-user.png'; ?>" class="img-circle" alt="User Image">
                    </div>
                    <div class="pull-left info">
                        <p><?php echo $_SESSION['fullname']; ?></p>
                        <a href="#"><i class="fa fa-circle text-success"></i> ออนไลน์</a>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <ul class="sidebar-menu" data-widget="tree">
                    <li class="header">เมนูหลัก</li>
                    <!-- แดชบอร์ด -->
                    <li <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'class="active"' : ''; ?>>
                        <a href="index.php">
                            <i class="fa fa-dashboard"></i> <span>แดชบอร์ด</span>
                        </a>
                    </li>
                    
                    <!-- จัดการพัสดุ/ครุภัณฑ์ -->
                    <li class="treeview <?php echo in_array(basename($_SERVER['PHP_SELF']), ['items.php', 'item_add.php', 'item_edit.php', 'categories.php']) ? 'active' : ''; ?>">
                        <a href="#">
                            <i class="fa fa-archive"></i> <span>จัดการพัสดุ/ครุภัณฑ์</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'items.php' ? 'class="active"' : ''; ?>>
                                <a href="items.php"><i class="fa fa-circle-o"></i> รายการพัสดุ/ครุภัณฑ์</a>
                            </li>
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'categories.php' ? 'class="active"' : ''; ?>>
                                <a href="categories.php"><i class="fa fa-circle-o"></i> ประเภทพัสดุ/ครุภัณฑ์</a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- จัดการการเบิก/คืนพัสดุ -->
                    <li class="treeview <?php echo in_array(basename($_SERVER['PHP_SELF']), ['borrowings.php', 'borrowing_add.php', 'borrowing_edit.php', 'borrowing_return.php']) ? 'active' : ''; ?>">
                        <a href="#">
                            <i class="fa fa-exchange-alt"></i> <span>จัดการการเบิก/คืนพัสดุ</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'borrowings.php' ? 'class="active"' : ''; ?>>
                                <a href="borrowings.php"><i class="fa fa-circle-o"></i> รายการเบิก/คืนพัสดุ</a>
                            </li>
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'borrowing_add.php' ? 'class="active"' : ''; ?>>
                                <a href="borrowing_add.php"><i class="fa fa-circle-o"></i> เพิ่มรายการเบิกพัสดุ</a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- จัดการซ่อมบำรุง -->
                    <li <?php echo basename($_SERVER['PHP_SELF']) == 'maintenance.php' ? 'class="active"' : ''; ?>>
                        <a href="maintenance.php">
                            <i class="fa fa-tools"></i> <span>จัดการซ่อมบำรุง</span>
                        </a>
                    </li>
                    
                    <!-- รายงาน -->
                    <li class="treeview <?php echo in_array(basename($_SERVER['PHP_SELF']), ['report_items.php', 'report_borrowings.php', 'report_maintenance.php']) ? 'active' : ''; ?>">
                        <a href="#">
                            <i class="fa fa-chart-bar"></i> <span>รายงาน</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'report_items.php' ? 'class="active"' : ''; ?>>
                                <a href="report_items.php"><i class="fa fa-circle-o"></i> รายงานพัสดุ/ครุภัณฑ์</a>
                            </li>
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'report_borrowings.php' ? 'class="active"' : ''; ?>>
                                <a href="report_borrowings.php"><i class="fa fa-circle-o"></i> รายงานการเบิก/คืนพัสดุ</a>
                            </li>
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'report_maintenance.php' ? 'class="active"' : ''; ?>>
                                <a href="report_maintenance.php"><i class="fa fa-circle-o"></i> รายงานการซ่อมบำรุง</a>
                            </li>
                        </ul>
                    </li>
                    
                    <?php if (isAdmin()): ?>
                    <!-- จัดการผู้ใช้ (เฉพาะแอดมิน) -->
                    <li class="treeview <?php echo in_array(basename($_SERVER['PHP_SELF']), ['users.php', 'user_add.php', 'user_edit.php']) ? 'active' : ''; ?>">
                        <a href="#">
                            <i class="fa fa-users"></i> <span>จัดการผู้ใช้</span>
                            <span class="pull-right-container">
                                <i class="fa fa-angle-left pull-right"></i>
                            </span>
                        </a>
                        <ul class="treeview-menu">
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'class="active"' : ''; ?>>
                                <a href="users.php"><i class="fa fa-circle-o"></i> รายชื่อผู้ใช้</a>
                            </li>
                            <li <?php echo basename($_SERVER['PHP_SELF']) == 'user_add.php' ? 'class="active"' : ''; ?>>
                                <a href="user_add.php"><i class="fa fa-circle-o"></i> เพิ่มผู้ใช้</a>
                            </li>
                        </ul>
                    </li>
                    
                    <!-- บันทึกกิจกรรม (เฉพาะแอดมิน) -->
                    <li <?php echo basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'class="active"' : ''; ?>>
                        <a href="logs.php">
                            <i class="fa fa-history"></i> <span>บันทึกกิจกรรม</span>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
                <!-- /.sidebar-menu -->
            </section>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <h1>
                    <?php echo isset($page_title) ? $page_title : 'ระบบบริหารจัดการพัสดุ/ครุภัณฑ์'; ?>
                    <small><?php echo isset($page_subtitle) ? $page_subtitle : ''; ?></small>
                </h1>
                <ol class="breadcrumb">
                    <li><a href="index.php"><i class="fa fa-dashboard"></i> หน้าหลัก</a></li>
                    <?php if (isset($breadcrumb)): ?>
                        <?php foreach ($breadcrumb as $item): ?>
                            <?php if (isset($item['active']) && $item['active']): ?>
                                <li class="active"><?php echo $item['title']; ?></li>
                            <?php else: ?>
                                <li><a href="<?php echo $item['url']; ?>"><?php echo $item['title']; ?></a></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ol>
            </section>

            <!-- Main content -->
            <section class="content container-fluid">
<?php
// header.php - ส่วนหัวของเว็บไซต์
require_once 'config.php';
requireLogin(); // บังคับให้ล็อกอินก่อนเข้าใช้งาน
$current_user = getCurrentUser($conn);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบบริหารจัดการพัสดุ/ครุภัณฑ์</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap.min.css">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- AdminLTE CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@2.4.18/dist/css/AdminLTE.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@2.4.18/dist/css/skins/skin-blue.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <style>
        .thai-font {
            font-family: 'Sarabun', 'TH Sarabun New', sans-serif;
        }
        .content-wrapper {
            min-height: calc(100vh - 101px);
        }
        .main-footer {
            margin-left: 230px;
        }
        @media (max-width: 767px) {
            .main-footer {
                margin-left: 0;
            }
        }
        .box-title {
            font-size: 18px;
        }
        .pagination>.active>a, 
        .pagination>.active>a:focus, 
        .pagination>.active>a:hover, 
        .pagination>.active>span, 
        .pagination>.active>span:focus, 
        .pagination>.active>span:hover {
            background-color: #3c8dbc;
            border-color: #367fa9;
        }
        .alert-success {
            background-color: #dff0d8;
            border-color: #d6e9c6;
            color: #3c763d;
        }
        .alert-danger {
            background-color: #f2dede;
            border-color: #ebccd1;
            color: #a94442;
        }
        .table-responsive {
            overflow-x: auto;
        }
        /* เพิ่มฟอนต์ไทย */
        @font-face {
            font-family: 'Sarabun';
            src: url('fonts/Sarabun-Regular.ttf') format('truetype');
            font-weight: normal;
            font-style: normal;
        }
        /* สถานะของพัสดุ */
        .label-available {
            background-color: #00a65a;
        }
        .label-borrowed {
            background-color: #f39c12;
        }
        .label-maintenance {
            background-color: #605ca8;
        }
        .label-disposed {
            background-color: #dd4b39;
        }
        .dashboard-box {
            border-radius: 3px;
            background: #ffffff;
            border-top: 3px solid #d2d6de;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            padding: 15px;
        }
        .dashboard-box.primary {
            border-top-color: #3c8dbc;
        }
        .dashboard-box.success {
            border-top-color: #00a65a;
        }
        .dashboard-box.warning {
            border-top-color: #f39c12;
        }
        .dashboard-box.danger {
            border-top-color: #dd4b39;
        }
    </style>