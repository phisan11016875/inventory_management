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

// ดึงข้อมูลหมวดหมู่เพื่อแสดงในฟอร์ม
$sql = "SELECT * FROM categories ORDER BY category_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($_POST['item_name']) || empty($_POST['category_id'])) {
            throw new Exception("กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน");
        }

        // ตรวจสอบการอัปโหลดรูปภาพ (ถ้ามี)
        $image_path = null;
        if (isset($_FILES['item_image']) && $_FILES['item_image']['size'] > 0) {
            $target_dir = "uploads/items/";
            
            // สร้างไดเรกทอรีถ้ายังไม่มี
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            // สร้างชื่อไฟล์ใหม่ด้วยเวลาปัจจุบัน
            $file_extension = pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION);
            $new_filename = time() . '_' . uniqid() . '.' . $file_extension;
            $target_file = $target_dir . $new_filename;
            
            // ตรวจสอบชนิดของไฟล์
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['item_image']['type'], $allowed_types)) {
                throw new Exception("เฉพาะไฟล์รูปภาพเท่านั้นที่อนุญาตให้อัปโหลด (JPEG, JPG, PNG, GIF)");
            }
            
            // ตรวจสอบขนาดไฟล์ (ไม่เกิน 5MB)
            if ($_FILES['item_image']['size'] > 5000000) {
                throw new Exception("ขนาดไฟล์ต้องไม่เกิน 5MB");
            }
            
            // อัปโหลดไฟล์
            if (!move_uploaded_file($_FILES['item_image']['tmp_name'], $target_file)) {
                throw new Exception("เกิดข้อผิดพลาดในการอัปโหลดไฟล์");
            }
            
            $image_path = $target_file;
        }

        // เตรียมข้อมูลสำหรับบันทึก
        $item_code = $_POST['item_code'] ?? '';
        $item_name = $_POST['item_name'];
        $category_id = $_POST['category_id'];
        $description = $_POST['description'] ?? '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
        $unit = $_POST['unit'] ?? 'ชิ้น';
        $status = $_POST['status'] ?? 'available';
        $acquisition_date = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
        $acquisition_method = $_POST['acquisition_method'] ?? '';
        $price = !empty($_POST['price']) ? floatval($_POST['price']) : 0.00;
        $location = $_POST['location'] ?? '';
        $supplier = $_POST['supplier'] ?? '';
        $warranty_period = !empty($_POST['warranty_period']) ? $_POST['warranty_period'] : null;
        $notes = $_POST['notes'] ?? '';
        
        // สร้างรหัสพัสดุอัตโนมัติถ้าไม่ได้ระบุ
        if (empty($item_code)) {
            // ดึงค่าล่าสุดจากฐานข้อมูล
            $stmt = $conn->prepare("SELECT MAX(CAST(SUBSTRING(item_code, 5) AS UNSIGNED)) as max_code FROM items WHERE item_code LIKE 'ITEM%'");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $next_number = 1;
            if ($result && $result['max_code']) {
                $next_number = $result['max_code'] + 1;
            }
            
            $item_code = 'ITEM' . str_pad($next_number, 5, '0', STR_PAD_LEFT);
        }
        
        // บันทึกข้อมูลลงฐานข้อมูล
        $sql = "INSERT INTO items (item_code, item_name, category_id, description, quantity, unit, status, 
                                  acquisition_date, acquisition_method, price, location, supplier, 
                                  warranty_period, image_path, notes, created_by, created_at) 
                VALUES (:item_code, :item_name, :category_id, :description, :quantity, :unit, :status, 
                        :acquisition_date, :acquisition_method, :price, :location, :supplier, 
                        :warranty_period, :image_path, :notes, :created_by, NOW())";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':item_code', $item_code);
        $stmt->bindParam(':item_name', $item_name);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':unit', $unit);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':acquisition_date', $acquisition_date);
        $stmt->bindParam(':acquisition_method', $acquisition_method);
        $stmt->bindParam(':price', $price);
        $stmt->bindParam(':location', $location);
        $stmt->bindParam(':supplier', $supplier);
        $stmt->bindParam(':warranty_period', $warranty_period);
        $stmt->bindParam(':image_path', $image_path);
        $stmt->bindParam(':notes', $notes);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        
        $stmt->execute();
        
        // บันทึกล็อกการทำงาน
        $log_message = "เพิ่มพัสดุ/ครุภัณฑ์: " . $item_name . " (รหัส: " . $item_code . ")";
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'add_item', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // แสดงข้อความสำเร็จและกลับไปยังหน้ารายการพัสดุ
        header('Location: items.php?success=เพิ่มพัสดุ/ครุภัณฑ์เรียบร้อยแล้ว');
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
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h4>เพิ่มพัสดุ/ครุภัณฑ์ใหม่</h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="item_code" class="form-label">รหัสพัสดุ/ครุภัณฑ์</label>
                                    <input type="text" class="form-control" id="item_code" name="item_code" placeholder="ระบบจะสร้างให้อัตโนมัติถ้าปล่อยว่าง">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="item_name" class="form-label">ชื่อพัสดุ/ครุภัณฑ์ <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="item_name" name="item_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_id" class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
                                    <select class="form-select" id="category_id" name="category_id" required>
                                        <option value="">กรุณาเลือกหมวดหมู่</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= $category['category_id'] ?>"><?= $category['category_name'] ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">สถานะ</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="available">พร้อมใช้งาน</option>
                                        <option value="in_use">กำลังใช้งาน</option>
                                        <option value="in_repair">กำลังซ่อมแซม</option>
                                        <option value="reserved">จองแล้ว</option>
                                        <option value="retired">เลิกใช้งาน</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quantity" class="form-label">จำนวน</label>
                                    <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="unit" class="form-label">หน่วยนับ</label>
                                    <input type="text" class="form-control" id="unit" name="unit" value="ชิ้น">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">รายละเอียด</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="acquisition_date" class="form-label">วันที่ได้รับ</label>
                                    <input type="date" class="form-control" id="acquisition_date" name="acquisition_date">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="acquisition_method" class="form-label">วิธีการได้มา</label>
                                    <select class="form-select" id="acquisition_method" name="acquisition_method">
                                        <option value="">กรุณาเลือก</option>
                                        <option value="purchase">ซื้อ</option>
                                        <option value="donation">รับบริจาค</option>
                                        <option value="transfer">รับโอน</option>
                                        <option value="other">อื่นๆ</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="price" class="form-label">ราคา (บาท)</label>
                                    <input type="number" step="0.01" class="form-control" id="price" name="price" min="0">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">สถานที่เก็บ</label>
                                    <input type="text" class="form-control" id="location" name="location">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="supplier" class="form-label">ผู้จำหน่าย/แหล่งที่มา</label>
                                    <input type="text" class="form-control" id="supplier" name="supplier">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="warranty_period" class="form-label">วันหมดประกัน</label>
                                    <input type="date" class="form-control" id="warranty_period" name="warranty_period">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="item_image" class="form-label">รูปภาพ</label>
                                    <input type="file" class="form-control" id="item_image" name="item_image">
                                    <small class="text-muted">รองรับไฟล์ JPEG, JPG, PNG, GIF ขนาดไม่เกิน 5MB</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>
                        
                        <div class="mb-3 d-flex justify-content-between">
                            <a href="items.php" class="btn btn-secondary">ยกเลิก</a>
                            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>