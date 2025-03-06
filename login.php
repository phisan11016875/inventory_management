<?php
// เรียกใช้ไฟล์ config.php เพื่อเชื่อมต่อฐานข้อมูล
require_once 'config.php';

// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน หากล็อกอินแล้วให้ redirect ไปหน้า index.php
if (isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/index.php');
}

// ตรวจสอบการ submit form
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $error = '';

    // ตรวจสอบว่าป้อนข้อมูลครบหรือไม่
    if (empty($username) || empty($password)) {
        $error = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else {
        try {
            // ค้นหาผู้ใช้ในฐานข้อมูล
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                // ล็อกอินสำเร็จ
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $fullnameParts = explode(' ', $user['fullname'], 2);
                $_SESSION['first_name'] = $fullnameParts[0];
                $_SESSION['last_name'] = $fullnameParts[1] ?? '';
                
                // บันทึกเวลาเข้าสู่ระบบ
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                redirect(SITE_URL . '/index.php');
            } else {
                $error = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
            }
        } catch (PDOException $e) {
            $error = 'เกิดข้อผิดพลาดในการเข้าสู่ระบบ: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>เข้าสู่ระบบ | <?php echo SITE_NAME; ?></title>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="dist/css/adminlte.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
    <style>
        @font-face {
            font-family: 'Sarabun';
            src: url('dist/fonts/Sarabun-Regular.ttf');
        }
        body {
            font-family: 'Sarabun', sans-serif;
        }
    </style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <h1 class="h1"><?php echo SITE_NAME; ?></h1>
        </div>
        <div class="card-body">
            <p class="login-box-msg">กรุณาเข้าสู่ระบบเพื่อเริ่มใช้งาน</p>

            <?php if (isset($error) && !empty($error)): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
                    <h5><i class="icon fas fa-ban"></i> ผิดพลาด!</h5>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="post">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" placeholder="ชื่อผู้ใช้" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" class="form-control" placeholder="รหัสผ่าน" name="password">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-8">
                        <div class="icheck-primary">
                            <input type="checkbox" id="remember">
                            <label for="remember">
                                จดจำฉัน
                            </label>
                        </div>
                    </div>
                    <!-- /.col -->
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">เข้าสู่ระบบ</button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
<!-- /.login-box -->

<!-- jQuery -->
<script src="plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap 4 -->
<script src="plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="dist/js/adminlte.min.js"></script>
<!-- SweetAlert2 -->
<script src="plugins/sweetalert2/sweetalert2.min.js"></script>
</body>
</html>