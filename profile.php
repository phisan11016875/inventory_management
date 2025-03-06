<?php
// หน้าจัดการโปรไฟล์ส่วนตัว
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// ดึงข้อมูลผู้ใช้
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// ตรวจสอบการส่งแบบฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $department = trim($_POST['department']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // ตรวจสอบข้อมูล
    if (empty($name) || empty($email) || empty($department)) {
        $error = "กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "รูปแบบอีเมลไม่ถูกต้อง";
    } else {
        try {
            // ตรวจสอบว่าอีเมลซ้ำหรือไม่ (ถ้ามีการเปลี่ยนอีเมล)
            if ($email != $user['email']) {
                $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error = "อีเมลนี้ถูกใช้งานแล้ว กรุณาใช้อีเมลอื่น";
                }
            }
            
            // ถ้าไม่มีข้อผิดพลาด ทำการอัปเดตข้อมูล
            if (empty($error)) {
                // ถ้ามีการเปลี่ยนรหัสผ่าน
                if (!empty($current_password) && !empty($new_password)) {
                    // ตรวจสอบรหัสผ่านปัจจุบัน
                    if (!password_verify($current_password, $user['password'])) {
                        $error = "รหัสผ่านปัจจุบันไม่ถูกต้อง";
                    } elseif ($new_password != $confirm_password) {
                        $error = "รหัสผ่านใหม่และการยืนยันรหัสผ่านไม่ตรงกัน";
                    } elseif (strlen($new_password) < 6) {
                        $error = "รหัสผ่านใหม่ต้องมีความยาวอย่างน้อย 6 ตัวอักษร";
                    } else {
                        // อัปเดตข้อมูลพร้อมรหัสผ่านใหม่
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, department = ?, phone = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $email, $department, $phone, $hashed_password, $user_id]);
                        $success = "อัปเดตข้อมูลและรหัสผ่านเรียบร้อยแล้ว";
                    }
                } else {
                    // อัปเดตข้อมูลโดยไม่เปลี่ยนรหัสผ่าน
                    $stmt = $conn->prepare("UPDATE users SET name = ?, email = ?, department = ?, phone = ? WHERE id = ?");
                    $stmt->execute([$name, $email, $department, $phone, $user_id]);
                    $success = "อัปเดตข้อมูลเรียบร้อยแล้ว";
                }
                
                // ดึงข้อมูลผู้ใช้ใหม่หลังอัปเดต
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$user_id]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // อัปเดตข้อมูลใน session
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['user_email'] = $user['email'];
            }
        } catch (PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>จัดการโปรไฟล์ส่วนตัว</h4>
                </div>
                <div class="card-body">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success)): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="">
                        <div class="mb-3">
                            <label for="name" class="form-label">ชื่อ-นามสกุล <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">อีเมล <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department" class="form-label">แผนก/ฝ่าย <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="department" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">เบอร์โทรศัพท์</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        
                        <hr>
                        <h5>เปลี่ยนรหัสผ่าน (เว้นว่างไว้หากไม่ต้องการเปลี่ยน)</h5>
                        
                        <div class="mb-3">
                            <label for="current_password" class="form-label">รหัสผ่านปัจจุบัน</label>
                            <input type="password" class="form-control" id="current_password" name="current_password">
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">รหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="new_password" name="new_password">
                            <small class="text-muted">รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">ยืนยันรหัสผ่านใหม่</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                        </div>
                        
                        <div class="mb-3 text-end">
                            <a href="index.php" class="btn btn-secondary me-2">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>