<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: categories.php?error=ไม่พบรหัสหมวดหมู่ที่ต้องการแก้ไข');
    exit();
}

$category_id = $_GET['id'];

// ดึงข้อมูลหมวดหมู่ที่ต้องการแก้ไข
$sql = "SELECT * FROM categories WHERE category_id = :category_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$category) {
    header('Location: categories.php?error=ไม่พบหมวดหมู่ที่ต้องการแก้ไข');
    exit();
}

// ตรวจสอบจำนวนพัสดุในหมวดหมู่
$sql = "SELECT COUNT(*) FROM items WHERE category_id = :category_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id);
$stmt->execute();
$item_count = $stmt->fetchColumn();

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($_POST['category_name'])) {
            throw new Exception("กรุณาระบุชื่อหมวดหมู่");
        }

        $category_name = $_POST['category_name'];
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        // ตรวจสอบว่ามีชื่อหมวดหมู่นี้อยู่แล้วหรือไม่ (ยกเว้นหมวดหมู่ปัจจุบัน)
        $sql = "SELECT COUNT(*) FROM categories WHERE category_name = :category_name AND category_id != :category_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_name', $category_name);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("มีหมวดหมู่ชื่อนี้อยู่แล้วในระบบ");
        }

        // อัปเดตข้อมูลหมวดหมู่
        $sql = "UPDATE categories SET 
                category_name = :category_name,
                description = :description,
                updated_by = :updated_by,
                updated_at = NOW()
                WHERE category_id = :category_id";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_name', $category_name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':updated_by', $_SESSION['user_id']);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->execute();
        
        // บันทึกล็อกการทำงาน
        $log_message = "แก้ไขหมวดหมู่: " . $category_name . " (ID: " . $category_id . ")";
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'edit_category', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // กลับไปยังหน้าจัดการหมวดหมู่
        header('Location: categories.php?success=แก้ไขหมวดหมู่เรียบร้อยแล้ว');
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
                    <h4>แก้ไขหมวดหมู่</h4>
                    <a href="categories.php" class="btn btn-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> กลับไปยังรายการหมวดหมู่
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label for="category_name" class="form-label">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="category_name" name="category_name" value="<?= htmlspecialchars($category['category_name']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">รายละเอียด</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= htmlspecialchars($category['description']) ?></textarea>
                        </div>
                        
                        <?php if ($item_count > 0): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            หมวดหมู่นี้มีพัสดุ/ครุภัณฑ์ที่เกี่ยวข้อง <?= number_format($item_count) ?> รายการ
                            <a href="items.php?category_id=<?= $category_id ?>" class="alert-link">คลิกเพื่อดูรายการทั้งหมด</a>
                        </div>
                        <?php endif; ?>
                        
                        <div class="mb-3 d-flex justify-content-between">
                            <a href="categories.php" class="btn btn-secondary">
                                ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> บันทึกข้อมูล
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted">
                    <div class="row">
                        <div class="col-md-6">
                            <small>สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($category['created_at'])) ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if (!empty($category['updated_at'])): ?>
                            <small>แก้ไขล่าสุด: <?= date('d/m/Y H:i', strtotime($category['updated_at'])) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>