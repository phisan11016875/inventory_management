<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินเท่านั้นที่สามารถลบได้)
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการลบพัสดุ/ครุภัณฑ์');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: items.php?error=ไม่พบรหัสพัสดุ/ครุภัณฑ์ที่ต้องการลบ');
    exit();
}

$item_id = $_GET['id'];

// ดึงข้อมูลพัสดุ/ครุภัณฑ์ที่ต้องการลบ
$sql = "SELECT item_id, item_code, item_name, image_path FROM items WHERE item_id = :item_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':item_id', $item_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$item) {
    header('Location: items.php?error=ไม่พบพัสดุ/ครุภัณฑ์ที่ต้องการลบ');
    exit();
}

// ตรวจสอบว่ามีการยืนยันการลบหรือไม่
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 1) {
    try {
        // เริ่ม transaction
        $conn->beginTransaction();
        
        // ตรวจสอบว่าพัสดุ/ครุภัณฑ์นี้มีประวัติการยืมหรือไม่
        $sql = "SELECT COUNT(*) as borrow_count FROM borrowing_items WHERE item_id = :item_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // ถ้ามีการยืมและไม่ได้เลือกลบประวัติการยืม
        if ($result['borrow_count'] > 0 && (!isset($_POST['delete_borrowing_history']) || $_POST['delete_borrowing_history'] != 1)) {
            throw new Exception("พัสดุ/ครุภัณฑ์นี้มีประวัติการยืม-คืน กรุณายืนยันการลบประวัติด้วย");
        }
        
        // ลบประวัติการยืมถ้าเลือกลบ
        if (isset($_POST['delete_borrowing_history']) && $_POST['delete_borrowing_history'] == 1) {
            $sql = "DELETE FROM borrowing_items WHERE item_id = :item_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();
        }
        
        // ลบพัสดุ/ครุภัณฑ์
        $sql = "DELETE FROM items WHERE item_id = :item_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':item_id', $item_id);
        $stmt->execute();
        
        // ลบรูปภาพถ้ามี
        if ($item['image_path'] && file_exists($item['image_path'])) {
            unlink($item['image_path']);
        }
        
        // บันทึกล็อกการทำงาน
        $log_message = "ลบพัสดุ/ครุภัณฑ์: " . $item['item_name'] . " (รหัส: " . $item['item_code'] . ")";
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'delete_item', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // ยืนยัน transaction
        $conn->commit();
        
        // แสดงข้อความสำเร็จและกลับไปยังหน้ารายการพัสดุ
        header('Location: items.php?success=ลบพัสดุ/ครุภัณฑ์เรียบร้อยแล้ว');
        exit();
        
    } catch (Exception $e) {
        // ยกเลิก transaction เมื่อเกิดข้อผิดพลาด
        $conn->rollBack();
        $error_message = $e->getMessage();
    }
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card border-danger">
                <div class="card-header bg-danger text-white">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบพัสดุ/ครุภัณฑ์</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning">
                        <p class="mb-0"><strong>คำเตือน:</strong> การลบพัสดุ/ครุภัณฑ์จะไม่สามารถกู้คืนได้ กรุณาตรวจสอบข้อมูลให้แน่ใจก่อนดำเนินการ</p>
                    </div>
                    
                    <div class="mb-4">
                        <h5>ข้อมูลพัสดุ/ครุภัณฑ์ที่จะลบ:</h5>
                        <div class="row mt-3">
                            <div class="col-md-4 fw-bold">รหัสพัสดุ/ครุภัณฑ์:</div>
                            <div class="col-md-8"><?= htmlspecialchars($item['item_code']) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">ชื่อพัสดุ/ครุภัณฑ์:</div>
                            <div class="col-md-8"><?= htmlspecialchars($item['item_name']) ?></div>
                        </div>
                        
                        <?php 
                        // ตรวจสอบว่ามีประวัติการยืมหรือไม่
                        $sql = "SELECT COUNT(*) as borrow_count FROM borrowing_items WHERE item_id = :item_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':item_id', $item_id);
                        $stmt->execute();
                        $result = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($result['borrow_count'] > 0):
                        ?>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">ประวัติการยืม-คืน:</div>
                            <div class="col-md-8">
                                <span class="text-danger">มีประวัติการยืม-คืนจำนวน <?= $result['borrow_count'] ?> รายการ</span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" onsubmit="return confirm('คุณแน่ใจหรือไม่ที่จะลบพัสดุ/ครุภัณฑ์นี้?');">
                        <input type="hidden" name="confirm_delete" value="1">
                        
                        <?php if ($result['borrow_count'] > 0): ?>
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="delete_borrowing_history" name="delete_borrowing_history" value="1" required>
                            <label class="form-check-label text-danger" for="delete_borrowing_history">
                                ฉันยืนยันที่จะลบประวัติการยืม-คืนของพัสดุ/ครุภัณฑ์นี้ทั้งหมด (จำเป็นต้องยืนยัน)
                            </label>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between">
                            <a href="items.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt me-1"></i> ยืนยันการลบ
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>