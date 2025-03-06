<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินเท่านั้น)
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้');
    exit();
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($_POST['username']) || empty($_POST['password']) || empty($_POST['first_name']) || 
            empty($_POST['last_name']) || empty($_POST['email']) || empty($_POST['role'])) {
            throw new Exception("กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน");
        }
        
        // รับค่าจากฟอร์ม
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $first_name = $_POST['first_name'];
        $last_name = $_POST['last_name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'] ?? '';
        $role = $_POST['role'];
        $department = $_POST['department'] ?? '';
        $position = $_POST['position'] ?? '';
        $active = isset($_POST['active']) ? 1 : 0;
        
        // ตรวจสอบการยืนยันรหัสผ่าน
        if ($password !== $confirm_password) {
            throw new Exception("รหัสผ่านและการยืนยันรหัสผ่านไม่ตรงกัน");
        }
        
        // ตรวจสอบความยาวรหัสผ่าน
        if (strlen($password) < 6) {
            throw new Exception("รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร");
        }
        
        // ตรวจสอบรูปแบบอีเมล
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("รูปแบบอีเมลไม่ถูกต้อง");
        }
        
        // ตรวจสอบว่าชื่อผู้ใช้หรืออีเมลซ้ำหรือไม่
        $sql = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            // ตรวจสอบว่าซ้ำที่ชื่อผู้ใช้หรืออีเมล
            $sql = "SELECT * FROM users WHERE username = :username";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            if ($stmt->rowCount() > 0) {
                throw new Exception("ชื่อผู้ใช้นี้มีอยู่ในระบบแล้ว กรุณาใช้ชื่อผู้ใช้อื่น");
            } else {
                throw new Exception("อีเมลนี้มีอยู่ในระบบแล้ว กรุณาใช้อีเมลอื่น");
            }
        }
        
        // เข้ารหัสรหัสผ่าน
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // เพิ่มผู้ใช้ใหม่ลงฐานข้อมูล
        $sql = "INSERT INTO users (username, password, first_name, last_name, email, phone, role, department, position, active, created_at) 
                VALUES (:username, :password, :first_name, :last_name, :email, :phone, :role, :department, :position, :active, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':first_name', $first_name);
        $stmt->bindParam(':last_name', $last_name);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':role', $role);
        $stmt->bindParam(':department', $department);
        $stmt->bindParam(':position', $position);
        $stmt->bindParam(':active', $active);
        $stmt->execute();
        
        // รับ ID ของผู้ใช้ที่เพิ่งสร้าง
        $user_id = $conn->lastInsertId();
        
        // บันทึกล็อกการทำงาน
        $log_message = "เพิ่มผู้ใช้: " . $username . " (รหัส: " . $user_id . ")";
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'add_user', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // แสดงข้อความสำเร็จและกลับไปยังหน้ารายการผู้ใช้
        header('Location: users.php?success=เพิ่มผู้ใช้เรียบร้อยแล้ว');
        exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-user-plus me-2"></i>เพิ่มผู้ใช้ใหม่</h4>
                    <a href="users.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> กลับไปยังรายการผู้ใช้
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" id="userForm">
                        <!-- ข้อมูลบัญชีผู้ใช้ -->
                        <h5 class="mb-3">ข้อมูลบัญชีผู้ใช้</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">ชื่อผู้ใช้ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="username" name="username" required>
                                <div class="form-text">ใช้สำหรับเข้าสู่ระบบ ห้ามมีช่องว่าง</div>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="password" class="form-label">รหัสผ่าน <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <div class="form-text">อย่างน้อย 6 ตัวอักษร</div>
                            </div>
                            <div class="col-md-6">
                                <label for="confirm_password" class="form-label">ยืนยันรหัสผ่าน <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- ข้อมูลส่วนตัว -->
                        <h5 class="mb-3">ข้อมูลส่วนตัว</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="first_name" class="form-label">ชื่อ <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="first_name" name="first_name" required>
                            </div>
                            <div class="col-md-6">
                                <label for="last_name" class="form-label">นามสกุล <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="last_name" name="last_name" required>
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="col-md-6">
                                <label for="department" class="form-label">แผนก/ฝ่าย</label>
                                <input type="text" class="form-control" id="department" name="department">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="position" class="form-label">ตำแหน่ง</label>
                            <input type="text" class="form-control" id="position" name="position">
                        </div>
                        
                        <hr class="my-4">
                        
                        <!-- ข้อมูลสิทธิ์การใช้งาน -->
                        <h5 class="mb-3">สิทธิ์การใช้งาน</h5>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="role" class="form-label">บทบาท <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">กรุณาเลือกบทบาท</option>
                                    <option value="admin">ผู้ดูแลระบบ</option>
                                    <option value="staff">เจ้าหน้าที่</option>
                                    <option value="user">ผู้ใช้งานทั่วไป</option>
                                    <option value="viewer">ผู้ดูข้อมูล</option>
                                </select>
                                <div class="form-text">สิทธิ์ในการใช้งานระบบ</div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" checked>
                                    <label class="form-check-label" for="active">เปิดใช้งานบัญชีนี้</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card bg-light mb-4">
                            <div class="card-body">
                                <h6>รายละเอียดบทบาท:</h6>
                                <ul class="mb-0">
                                    <li><strong>ผู้ดูแลระบบ</strong> - สามารถจัดการผู้ใช้ ข้อมูลทั้งหมด และการตั้งค่าระบบได้</li>
                                    <li><strong>เจ้าหน้าที่</strong> - สามารถจัดการพัสดุ/ครุภัณฑ์ และการยืม-คืนได้</li>
                                    <li><strong>ผู้ใช้งานทั่วไป</strong> - สามารถยืมพัสดุ/ครุภัณฑ์และดูข้อมูลพื้นฐานได้</li>
                                    <li><strong>ผู้ดูข้อมูล</strong> - สามารถดูข้อมูลได้อย่างเดียว ไม่สามารถแก้ไขหรือเพิ่มข้อมูลได้</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="users.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ตรวจสอบรหัสผ่านและการยืนยันรหัสผ่าน
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    function validatePassword() {
        if (passwordInput.value != confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('รหัสผ่านไม่ตรงกัน');
        } else {
            confirmPasswordInput.setCustomValidity('');
        }
    }
    
    passwordInput.addEventListener('change', validatePassword);
    confirmPasswordInput.addEventListener('keyup', validatePassword);
    
    // ตรวจสอบชื่อผู้ใช้
    const usernameInput = document.getElementById('username');
    
    usernameInput.addEventListener('input', function() {
        this.value = this.value.replace(/\s+/g, ''); // ลบช่องว่างทั้งหมด
    });
    
    // ตรวจสอบฟอร์มก่อนส่ง
    document.getElementById('userForm').addEventListener('submit', function(event) {
        // ตรวจสอบความยาวรหัสผ่าน
        if (passwordInput.value.length < 6) {
            event.preventDefault();
            alert('รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร');
            passwordInput.focus();
        }
    });
});
</script>

<?php include 'footer.php'; ?>