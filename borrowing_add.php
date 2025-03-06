<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (ยกเว้น viewer ที่ไม่สามารถยืมได้)
if ($_SESSION['role'] == 'viewer') {
    header('Location: borrowings.php?error=คุณไม่มีสิทธิ์ในการยืมพัสดุ/ครุภัณฑ์');
    exit();
}

// รับค่า item_id ถ้ามีการส่งมาจากหน้าอื่น
$preselected_item_id = isset($_GET['item_id']) ? intval($_GET['item_id']) : null;

// ดึงข้อมูลผู้ใช้ทั้งหมดสำหรับเลือกผู้ยืม (เฉพาะแอดมินและเจ้าหน้าที่ที่สามารถเลือกผู้ยืมได้)
$users = [];
if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') {
    $sql = "SELECT user_id, first_name, last_name, role FROM users WHERE active = 1 ORDER BY role, first_name, last_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// ดึงข้อมูลพัสดุที่สามารถยืมได้ (สถานะ available)
$sql = "SELECT i.item_id, i.item_code, i.item_name, c.category_name, i.quantity, i.unit, i.status, i.image_path
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE i.status = 'available' AND i.quantity > 0
        ORDER BY i.item_name";
$stmt = $conn->prepare($sql);
$stmt->execute();
$available_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ดึงข้อมูลพัสดุที่เลือกล่วงหน้า (ถ้ามี)
$preselected_item = null;
if ($preselected_item_id) {
    $sql = "SELECT i.item_id, i.item_code, i.item_name, c.category_name, i.quantity, i.unit, i.status, i.image_path
            FROM items i
            LEFT JOIN categories c ON i.category_id = c.category_id
            WHERE i.item_id = :item_id AND i.status = 'available' AND i.quantity > 0";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':item_id', $preselected_item_id);
    $stmt->execute();
    $preselected_item = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$preselected_item) {
        $error_message = "ไม่พบพัสดุที่เลือกหรือพัสดุไม่พร้อมสำหรับการยืม";
    }
}

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($_POST['selected_items']) || !is_array($_POST['selected_items']) || count($_POST['selected_items']) == 0) {
            throw new Exception("กรุณาเลือกพัสดุ/ครุภัณฑ์ที่ต้องการยืมอย่างน้อย 1 รายการ");
        }
        
        if (empty($_POST['borrowing_date'])) {
            throw new Exception("กรุณาระบุวันที่ยืม");
        }
        
        if (empty($_POST['expected_return_date'])) {
            throw new Exception("กรุณาระบุวันที่คาดว่าจะคืน");
        }
        
        // ตรวจสอบวันที่ยืมและวันที่คาดว่าจะคืน
        $borrowing_date = $_POST['borrowing_date'];
        $expected_return_date = $_POST['expected_return_date'];
        
        if (strtotime($expected_return_date) < strtotime($borrowing_date)) {
            throw new Exception("วันที่คาดว่าจะคืนต้องไม่เป็นวันที่ก่อนวันที่ยืม");
        }
        
        // กำหนดผู้ยืม (ถ้าเป็นแอดมินหรือเจ้าหน้าที่สามารถเลือกผู้ยืมได้)
        $borrower_id = $_SESSION['user_id']; // ค่าเริ่มต้นคือผู้ใช้ปัจจุบัน
        if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') && !empty($_POST['borrower_id'])) {
            $borrower_id = $_POST['borrower_id'];
        }
        
        // รายละเอียดการยืม
        $purpose = isset($_POST['purpose']) ? $_POST['purpose'] : '';
        $note = isset($_POST['note']) ? $_POST['note'] : '';
        
        // สถานะเริ่มต้น (รออนุมัติ)
        $status = 'pending';
        
        // ถ้าเป็นแอดมินหรือเจ้าหน้าที่ และเลือกอนุมัติทันที
        if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') && isset($_POST['auto_approve']) && $_POST['auto_approve'] == 1) {
            $status = 'approved';
            $approved_by = $_SESSION['user_id'];
            $approved_at = date('Y-m-d H:i:s');
        } else {
            $approved_by = null;
            $approved_at = null;
        }
        
        // เริ่ม transaction
        $conn->beginTransaction();
        
        // สร้างรหัสการยืม
        $year = date('Y');
        $month = date('m');
        
        // ดึงค่าลำดับล่าสุดในเดือนนี้
        $sql = "SELECT MAX(CAST(SUBSTRING(borrowing_code, 8) AS UNSIGNED)) as max_seq 
                FROM borrowings
                WHERE borrowing_code LIKE :code_prefix";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':code_prefix', "BRW{$year}{$month}%");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $next_seq = 1;
        if ($result && $result['max_seq']) {
            $next_seq = $result['max_seq'] + 1;
        }
        
        $borrowing_code = "BRW{$year}{$month}" . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
        
        // บันทึกข้อมูลการยืมลงตาราง borrowings
        $sql = "INSERT INTO borrowings (borrowing_code, borrower_id, borrowing_date, expected_return_date, 
                                     purpose, note, status, created_by, created_at, approved_by, approved_at) 
                VALUES (:borrowing_code, :borrower_id, :borrowing_date, :expected_return_date, 
                        :purpose, :note, :status, :created_by, NOW(), :approved_by, :approved_at)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':borrowing_code', $borrowing_code);
        $stmt->bindParam(':borrower_id', $borrower_id);
        $stmt->bindParam(':borrowing_date', $borrowing_date);
        $stmt->bindParam(':expected_return_date', $expected_return_date);
        $stmt->bindParam(':purpose', $purpose);
        $stmt->bindParam(':note', $note);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        $stmt->bindParam(':approved_by', $approved_by);
        $stmt->bindParam(':approved_at', $approved_at);
        $stmt->execute();
        
        // ดึง ID ของการยืมที่เพิ่งสร้าง
        $borrowing_id = $conn->lastInsertId();
        
        // บันทึกรายการพัสดุที่ยืม
        $selected_items = $_POST['selected_items'];
        $selected_quantities = isset($_POST['item_quantity']) ? $_POST['item_quantity'] : [];
        
        foreach ($selected_items as $item_id) {
            $item_id = intval($item_id);
            
            // ตรวจสอบว่าพัสดุยังมีอยู่และพร้อมให้ยืมหรือไม่
            $sql = "SELECT item_id, item_name, quantity, status FROM items WHERE item_id = :item_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->execute();
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$item) {
                throw new Exception("ไม่พบพัสดุรหัส {$item_id} ในระบบ");
            }
            
            if ($item['status'] != 'available') {
                throw new Exception("พัสดุ {$item['item_name']} ไม่พร้อมให้ยืมในขณะนี้");
            }
            
            // ตรวจสอบและกำหนดจำนวนที่ยืม
            $quantity = 1; // ค่าเริ่มต้น
            if (isset($selected_quantities[$item_id]) && $selected_quantities[$item_id] > 0) {
                $quantity = intval($selected_quantities[$item_id]);
            }
            
            // ตรวจสอบว่าจำนวนที่ยืมไม่เกินจำนวนที่มีอยู่
            if ($quantity > $item['quantity']) {
                throw new Exception("จำนวนที่ต้องการยืมของ {$item['item_name']} เกินกว่าจำนวนที่มีในระบบ");
            }
            
            // บันทึกรายการยืมพัสดุ
            $sql = "INSERT INTO borrowing_items (borrowing_id, item_id, quantity) 
                    VALUES (:borrowing_id, :item_id, :quantity)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->bindParam(':item_id', $item_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->execute();
            
            // ถ้าอนุมัติทันที ให้อัปเดตสถานะพัสดุเป็น in_use หรือลดจำนวนลง
            if ($status == 'approved') {
                // ถ้ายืมทั้งหมด จะเปลี่ยนสถานะเป็น in_use
                if ($quantity >= $item['quantity']) {
                    $sql = "UPDATE items SET status = 'in_use', updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                    $stmt->bindParam(':item_id', $item_id);
                    $stmt->execute();
                } else {
                    // ถ้ายืมบางส่วน ให้ลดจำนวนลง
                    $new_quantity = $item['quantity'] - $quantity;
                    $sql = "UPDATE items SET quantity = :new_quantity, updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':new_quantity', $new_quantity);
                    $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                    $stmt->bindParam(':item_id', $item_id);
                    $stmt->execute();
                }
            }
        }
        
        // บันทึกล็อกการทำงาน
        $log_message = "สร้างรายการยืม: " . $borrowing_code;
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'create_borrowing', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // ถ้าอนุมัติทันที ให้บันทึกล็อกการอนุมัติ
        if ($status == 'approved') {
            $log_message = "อนุมัติการยืม: " . $borrowing_code . " (อนุมัติอัตโนมัติ)";
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'approve_borrowing', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
        }
        
        // ยืนยัน transaction
        $conn->commit();
        
        // ข้อความแสดงผลเมื่อสำเร็จ
        $success_message = $status == 'approved' 
            ? "สร้างและอนุมัติรายการยืมเรียบร้อยแล้ว (รหัส: {$borrowing_code})"
            : "สร้างรายการยืมเรียบร้อยแล้ว (รหัส: {$borrowing_code}) รอการอนุมัติจากเจ้าหน้าที่";
        
        // ไปยังหน้ารายละเอียดการยืม
        header("Location: borrowing_detail.php?id={$borrowing_id}&success=" . urlencode($success_message));
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
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4><i class="fas fa-share me-2"></i>สร้างรายการยืมใหม่</h4>
                    <a href="borrowings.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-1"></i> กลับไปยังรายการยืม-คืน
                    </a>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle me-2"></i><?= $error_message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" id="borrowingForm">
                        <div class="row">
                            <!-- ข้อมูลการยืม -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">ข้อมูลการยืม</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                        <div class="mb-3">
                                            <label for="borrower_id" class="form-label">ผู้ยืม <span class="text-danger">*</span></label>
                                            <select class="form-select" id="borrower_id" name="borrower_id" required>
                                                <option value="">กรุณาเลือกผู้ยืม</option>
                                                <optgroup label="ผู้ใช้ตนเอง">
                                                    <option value="<?= $_SESSION['user_id'] ?>" selected>
                                                        <?= $_SESSION['first_name'] ?> <?= $_SESSION['last_name'] ?> (ตนเอง)
                                                    </option>
                                                </optgroup>
                                                <optgroup label="ผู้ใช้คนอื่น">
                                                    <?php foreach ($users as $user): ?>
                                                        <?php if ($user['user_id'] != $_SESSION['user_id']): ?>
                                                        <option value="<?= $user['user_id'] ?>">
                                                            <?= htmlspecialchars($user['first_name']) ?> <?= htmlspecialchars($user['last_name']) ?> 
                                                            (<?= $user['role'] == 'admin' ? 'แอดมิน' : ($user['role'] == 'staff' ? 'เจ้าหน้าที่' : 'ผู้ใช้ทั่วไป') ?>)
                                                        </option>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </optgroup>
                                            </select>
                                        </div>
                                        <?php else: ?>
                                        <div class="mb-3">
                                            <label class="form-label">ผู้ยืม</label>
                                            <input type="text" class="form-control" value="<?= $_SESSION['first_name'] ?> <?= $_SESSION['last_name'] ?>" readonly>
                                            <input type="hidden" name="borrower_id" value="<?= $_SESSION['user_id'] ?>">
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="borrowing_date" class="form-label">วันที่ยืม <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="borrowing_date" name="borrowing_date" 
                                                           value="<?= date('Y-m-d') ?>" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="expected_return_date" class="form-label">วันที่คาดว่าจะคืน <span class="text-danger">*</span></label>
                                                    <input type="date" class="form-control" id="expected_return_date" name="expected_return_date" 
                                                           value="<?= date('Y-m-d', strtotime('+7 days')) ?>" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="purpose" class="form-label">วัตถุประสงค์การยืม</label>
                                            <textarea class="form-control" id="purpose" name="purpose" rows="2"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="note" class="form-label">หมายเหตุ</label>
                                            <textarea class="form-control" id="note" name="note" rows="2"></textarea>
                                        </div>
                                        
                                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="auto_approve" name="auto_approve" value="1" checked>
                                            <label class="form-check-label" for="auto_approve">
                                                อนุมัติทันที (ข้ามขั้นตอนการอนุมัติ)
                                            </label>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- รายการพัสดุที่เลือก -->
                            <div class="col-md-6">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0">รายการพัสดุที่ต้องการยืม</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="selected-items-container" class="mb-3">
                                            <?php if ($preselected_item): ?>
                                            <div class="selected-item mb-2 border rounded p-2" data-item-id="<?= $preselected_item['item_id'] ?>">
                                                <div class="row align-items-center">
                                                    <div class="col-md-5">
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($preselected_item['image_path']) && file_exists($preselected_item['image_path'])): ?>
                                                            <img src="<?= $preselected_item['image_path'] ?>" alt="รูปภาพพัสดุ" class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                            <?php else: ?>
                                                            <div class="me-2 bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                <i class="fas fa-box text-muted"></i>
                                                            </div>
                                                            <?php endif; ?>
                                                            <div>
                                                                <div class="fw-bold"><?= htmlspecialchars($preselected_item['item_name']) ?></div>
                                                                <small class="text-muted"><?= htmlspecialchars($preselected_item['item_code']) ?></small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <div class="input-group input-group-sm">
                                                            <span class="input-group-text">จำนวน</span>
                                                            <input type="number" class="form-control item-quantity" name="item_quantity[<?= $preselected_item['item_id'] ?>]" 
                                                                  value="1" min="1" max="<?= $preselected_item['quantity'] ?>">
                                                            <span class="input-group-text"><?= htmlspecialchars($preselected_item['unit']) ?></span>
                                                        </div>
                                                        <small class="text-muted">มีทั้งหมด <?= $preselected_item['quantity'] ?> <?= htmlspecialchars($preselected_item['unit']) ?></small>
                                                    </div>
                                                    <div class="col-md-3 text-end">
                                                        <button type="button" class="btn btn-sm btn-danger remove-item">
                                                            <i class="fas fa-times"></i> นำออก
                                                        </button>
                                                        <input type="hidden" name="selected_items[]" value="<?= $preselected_item['item_id'] ?>">
                                                    </div>
                                                </div>
                                            </div>
                                            <?php else: ?>
                                            <div class="alert alert-info mb-0">
                                                <i class="fas fa-info-circle me-2"></i>ยังไม่มีพัสดุที่เลือก กรุณาเลือกพัสดุที่ต้องการยืมด้านล่าง
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <button type="button" class="btn btn-primary w-100" data-bs-toggle="modal" data-bs-target="#itemSelectorModal">
                                            <i class="fas fa-plus me-1"></i> เลือกพัสดุที่ต้องการยืม
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-3">
                            <a href="borrowings.php" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i> ยกเลิก
                            </a>
                            <button type="submit" class="btn btn-success" id="submit-button">
                                <i class="fas fa-save me-1"></i> บันทึกรายการยืม
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal สำหรับเลือกพัสดุ -->
<div class="modal fade" id="itemSelectorModal" tabindex="-1" aria-labelledby="itemSelectorModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="itemSelectorModalLabel">เลือกพัสดุที่ต้องการยืม</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" class="form-control" id="item-search" placeholder="ค้นหาพัสดุ...">
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover" id="available-items-table">
                        <thead>
                            <tr>
                                <th>รหัสพัสดุ</th>
                                <th>ชื่อพัสดุ</th>
                                <th>หมวดหมู่</th>
                                <th>จำนวนคงเหลือ</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($available_items) > 0): ?>
                                <?php foreach ($available_items as $item): ?>
                                <tr class="item-row" data-item-id="<?= $item['item_id'] ?>" data-item-name="<?= htmlspecialchars($item['item_name']) ?>" 
                                    data-item-code="<?= htmlspecialchars($item['item_code']) ?>" data-item-image="<?= htmlspecialchars($item['image_path']) ?>"
                                    data-item-quantity="<?= $item['quantity'] ?>" data-item-unit="<?= htmlspecialchars($item['unit']) ?>">
                                    <td><?= htmlspecialchars($item['item_code']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                            <img src="<?= $item['image_path'] ?>" alt="รูปภาพพัสดุ" class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="me-2 bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                            <?php endif; ?>
                                            <?= htmlspecialchars($item['item_name']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                                    <td><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                    <td>
                                    <button type="button" class="btn btn-sm btn-primary select-item" <?= $preselected_item && $preselected_item['item_id'] == $item['item_id'] ? 'disabled' : '' ?>>
                                            <i class="fas fa-plus"></i> เลือก
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">ไม่พบพัสดุที่พร้อมให้ยืมในขณะนี้</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ปิด</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const itemSelectorModal = document.getElementById('itemSelectorModal');
        const selectedItemsContainer = document.getElementById('selected-items-container');
        const borrowingForm = document.getElementById('borrowingForm');
        const itemSearch = document.getElementById('item-search');
        
        // ค้นหาพัสดุ
        itemSearch.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const itemRows = document.querySelectorAll('#available-items-table tbody tr.item-row');
            
            itemRows.forEach(row => {
                const itemName = row.getAttribute('data-item-name').toLowerCase();
                const itemCode = row.getAttribute('data-item-code').toLowerCase();
                
                if (itemName.includes(searchText) || itemCode.includes(searchText)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
        
        // เลือกพัสดุจากตาราง
        document.querySelectorAll('.select-item').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('tr');
                const itemId = row.getAttribute('data-item-id');
                const itemName = row.getAttribute('data-item-name');
                const itemCode = row.getAttribute('data-item-code');
                const itemImage = row.getAttribute('data-item-image');
                const itemQuantity = row.getAttribute('data-item-quantity');
                const itemUnit = row.getAttribute('data-item-unit');
                
                // ตรวจสอบว่าเลือกพัสดุนี้แล้วหรือยัง
                if (document.querySelector(`.selected-item[data-item-id="${itemId}"]`)) {
                    alert('คุณได้เลือกพัสดุนี้แล้ว');
                    return;
                }
                
                // สร้าง HTML สำหรับพัสดุที่เลือก
                const itemHtml = `
                    <div class="selected-item mb-2 border rounded p-2" data-item-id="${itemId}">
                        <div class="row align-items-center">
                            <div class="col-md-5">
                                <div class="d-flex align-items-center">
                                    ${itemImage && itemImage !== 'null' ? 
                                        `<img src="${itemImage}" alt="รูปภาพพัสดุ" class="me-2" style="width: 40px; height: 40px; object-fit: cover;">` : 
                                        `<div class="me-2 bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                            <i class="fas fa-box text-muted"></i>
                                         </div>`
                                    }
                                    <div>
                                        <div class="fw-bold">${itemName}</div>
                                        <small class="text-muted">${itemCode}</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text">จำนวน</span>
                                    <input type="number" class="form-control item-quantity" name="item_quantity[${itemId}]" 
                                          value="1" min="1" max="${itemQuantity}">
                                    <span class="input-group-text">${itemUnit}</span>
                                </div>
                                <small class="text-muted">มีทั้งหมด ${itemQuantity} ${itemUnit}</small>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="button" class="btn btn-sm btn-danger remove-item">
                                    <i class="fas fa-times"></i> นำออก
                                </button>
                                <input type="hidden" name="selected_items[]" value="${itemId}">
                            </div>
                        </div>
                    </div>
                `;
                
                // เพิ่มพัสดุที่เลือกลงในรายการ
                if (document.querySelector('#selected-items-container .alert')) {
                    selectedItemsContainer.innerHTML = '';
                }
                selectedItemsContainer.insertAdjacentHTML('beforeend', itemHtml);
                
                // ปิด Modal
                const modal = bootstrap.Modal.getInstance(itemSelectorModal);
                modal.hide();
                
                // ปรับปุ่มให้ disabled
                this.disabled = true;
                
                // เพิ่ม event listener สำหรับปุ่มลบรายการ
                addRemoveItemListeners();
            });
        });
        
        // เพิ่ม event listener สำหรับปุ่มลบรายการ
        function addRemoveItemListeners() {
            document.querySelectorAll('.remove-item').forEach(button => {
                button.addEventListener('click', function() {
                    const selectedItem = this.closest('.selected-item');
                    const itemId = selectedItem.getAttribute('data-item-id');
                    
                    // ลบพัสดุออกจากรายการ
                    selectedItem.remove();
                    
                    // ปรับปุ่มในตารางให้สามารถเลือกได้อีกครั้ง
                    const selectButton = document.querySelector(`#available-items-table tr[data-item-id="${itemId}"] .select-item`);
                    if (selectButton) {
                        selectButton.disabled = false;
                    }
                    
                    // ถ้าไม่มีพัสดุที่เลือกแล้ว ให้แสดงข้อความแจ้งเตือน
                    if (selectedItemsContainer.children.length === 0) {
                        selectedItemsContainer.innerHTML = `
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-info-circle me-2"></i>ยังไม่มีพัสดุที่เลือก กรุณาเลือกพัสดุที่ต้องการยืมด้านล่าง
                            </div>
                        `;
                    }
                });
            });
        }
        
        // เพิ่ม event listener สำหรับปุ่มลบรายการที่มีอยู่แล้ว
        addRemoveItemListeners();
        
        // ตรวจสอบก่อนส่ง form
        borrowingForm.addEventListener('submit', function(e) {
            const selectedItems = document.querySelectorAll('input[name="selected_items[]"]');
            
            if (selectedItems.length === 0) {
                e.preventDefault();
                alert('กรุณาเลือกพัสดุที่ต้องการยืมอย่างน้อย 1 รายการ');
                return false;
            }
            
            const borrowingDate = new Date(document.getElementById('borrowing_date').value);
            const expectedReturnDate = new Date(document.getElementById('expected_return_date').value);
            
            if (expectedReturnDate < borrowingDate) {
                e.preventDefault();
                alert('วันที่คาดว่าจะคืนต้องไม่เป็นวันที่ก่อนวันที่ยืม');
                return false;
            }
            
            // ตรวจสอบจำนวนที่ยืม
            let isQuantityValid = true;
            document.querySelectorAll('.item-quantity').forEach(input => {
                const value = parseInt(input.value);
                const max = parseInt(input.getAttribute('max'));
                
                if (value < 1 || value > max) {
                    e.preventDefault();
                    alert('จำนวนที่ยืมต้องอยู่ระหว่าง 1 ถึง ' + max);
                    isQuantityValid = false;
                    return;
                }
            });
            
            return isQuantityValid;
        });
    });
</script>

<?php include 'footer.php'; ?>