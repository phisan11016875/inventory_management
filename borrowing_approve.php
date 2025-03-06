<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินและเจ้าหน้าที่)
if ($_SESSION['role'] != 'admin' && $_SESSION['role'] != 'staff') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการอนุมัติการยืม');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: borrowings.php?error=ไม่พบรหัสการยืมที่ต้องการอนุมัติ');
    exit();
}

// ตรวจสอบว่ามีการระบุ action หรือไม่
if (!isset($_GET['action']) || ($_GET['action'] != 'approve' && $_GET['action'] != 'reject')) {
    header('Location: borrowings.php?error=การดำเนินการไม่ถูกต้อง');
    exit();
}

$borrowing_id = $_GET['id'];
$action = $_GET['action'];

// ดึงข้อมูลการยืม
$sql = "SELECT b.*, CONCAT(u.first_name, ' ', u.last_name) as borrower_name
        FROM borrowings b
        JOIN users u ON b.borrower_id = u.user_id
        WHERE b.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$borrowing) {
    header('Location: borrowings.php?error=ไม่พบข้อมูลการยืมที่ต้องการอนุมัติ');
    exit();
}

// ตรวจสอบว่าสถานะเป็น pending หรือไม่
if ($borrowing['status'] != 'pending') {
    header('Location: borrowing_detail.php?id=' . $borrowing_id . '&error=รายการยืมนี้ไม่อยู่ในสถานะรออนุมัติ');
    exit();
}

// ดึงรายการพัสดุที่ยืม
$sql = "SELECT bi.*, i.item_code, i.item_name, i.unit, i.quantity as available_quantity, i.status as item_status
        FROM borrowing_items bi
        JOIN items i ON bi.item_id = i.item_id
        WHERE bi.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // เริ่ม transaction
        $conn->beginTransaction();
        
        if ($action == 'approve') {
            // อนุมัติการยืม
            $note = isset($_POST['note']) ? $_POST['note'] : '';
            
            // อัปเดตสถานะเป็น approved
            $sql = "UPDATE borrowings SET 
                    status = 'approved',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    note = CONCAT(IFNULL(note, ''), '\n', :note)
                    WHERE borrowing_id = :borrowing_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':approved_by', $_SESSION['user_id']);
            $stmt->bindParam(':note', $note);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // ตรวจสอบและอัปเดตสถานะพัสดุแต่ละรายการ
            foreach ($borrowing_items as $item) {
                // ตรวจสอบว่าพัสดุยังมีอยู่และพร้อมให้ยืมหรือไม่
                $sql = "SELECT item_id, quantity, status FROM items WHERE item_id = :item_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':item_id', $item['item_id']);
                $stmt->execute();
                $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$current_item) {
                    throw new Exception("ไม่พบพัสดุรหัส {$item['item_id']} ในระบบ");
                }
                
                if ($current_item['status'] != 'available') {
                    throw new Exception("พัสดุ {$item['item_name']} ไม่พร้อมให้ยืมในขณะนี้");
                }
                
                // ตรวจสอบว่าจำนวนที่ยืมไม่เกินจำนวนที่มีอยู่
                if ($item['quantity'] > $current_item['quantity']) {
                    throw new Exception("จำนวนที่ต้องการยืมของ {$item['item_name']} เกินกว่าจำนวนที่มีในระบบ");
                }
                
                // ถ้ายืมทั้งหมด จะเปลี่ยนสถานะเป็น in_use
                if ($item['quantity'] >= $current_item['quantity']) {
                    $sql = "UPDATE items SET status = 'in_use', updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->execute();
                } else {
                    // ถ้ายืมบางส่วน ให้ลดจำนวนลง
                    $new_quantity = $current_item['quantity'] - $item['quantity'];
                    $sql = "UPDATE items SET quantity = :new_quantity, updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':new_quantity', $new_quantity);
                    $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->execute();
                }
            }
            
            // บันทึกล็อกการอนุมัติ
            $log_message = "อนุมัติการยืม: " . $borrowing['borrowing_code'];
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'approve_borrowing', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
            
            // ข้อความแจ้งเตือน
            $success_message = "อนุมัติการยืมเรียบร้อยแล้ว";
            
        } else if ($action == 'reject') {
            // ไม่อนุมัติการยืม
            $reject_reason = isset($_POST['reject_reason']) ? $_POST['reject_reason'] : '';
            
            if (empty($reject_reason)) {
                throw new Exception("กรุณาระบุเหตุผลในการไม่อนุมัติ");
            }
            
            // อัปเดตสถานะเป็น rejected
            $sql = "UPDATE borrowings SET 
                    status = 'rejected',
                    approved_by = :approved_by,
                    approved_at = NOW(),
                    note = CONCAT(IFNULL(note, ''), '\n\nเหตุผลที่ไม่อนุมัติ: ', :reject_reason)
                    WHERE borrowing_id = :borrowing_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':approved_by', $_SESSION['user_id']);
            $stmt->bindParam(':reject_reason', $reject_reason);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // บันทึกล็อกการไม่อนุมัติ
            $log_message = "ไม่อนุมัติการยืม: " . $borrowing['borrowing_code'] . " เหตุผล: " . $reject_reason;
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'reject_borrowing', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
            
            // ข้อความแจ้งเตือน
            $success_message = "บันทึกการไม่อนุมัติเรียบร้อยแล้ว";
        }
        
        // ยืนยัน transaction
        $conn->commit();
        
        // กลับไปยังหน้ารายละเอียดการยืม
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
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header <?= $action == 'approve' ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                    <h4>
                        <i class="fas <?= $action == 'approve' ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                        <?= $action == 'approve' ? 'อนุมัติการยืม' : 'ไม่อนุมัติการยืม' ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h5>ข้อมูลการยืม</h5>
                        <div class="row mt-3">
                            <div class="col-md-4 fw-bold">รหัสการยืม:</div>
                            <div class="col-md-8"><?= htmlspecialchars($borrowing['borrowing_code']) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">ผู้ยืม:</div>
                            <div class="col-md-8"><?= htmlspecialchars($borrowing['borrower_name']) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">วันที่ยืม:</div>
                            <div class="col-md-8"><?= date('d/m/Y', strtotime($borrowing['borrowing_date'])) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">วันที่คาดว่าจะคืน:</div>
                            <div class="col-md-8"><?= date('d/m/Y', strtotime($borrowing['expected_return_date'])) ?></div>
                        </div>
                        <?php if (!empty($borrowing['purpose'])): ?>
                        <div class="row mt-2">
                            <div class="col-md-4 fw-bold">วัตถุประสงค์:</div>
                            <div class="col-md-8"><?= nl2br(htmlspecialchars($borrowing['purpose'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <h5>รายการพัสดุที่ยืม</h5>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>รหัสพัสดุ</th>
                                        <th>ชื่อพัสดุ</th>
                                        <th>จำนวน</th>
                                        <th>สถานะพัสดุ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowing_items as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($item['item_code']) ?></td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                        <td>
                                            <span class="badge <?= $item['item_status'] == 'available' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                                <?= $item['item_status'] == 'available' ? 'พร้อมใช้งาน' : 'ไม่พร้อมใช้งาน' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <form method="POST" id="approvalForm" onsubmit="return validateForm()">
                        <?php if ($action == 'approve'): ?>
                        <div class="mb-3 mt-4">
                            <label for="note" class="form-label">หมายเหตุ (ถ้ามี)</label>
                            <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                        </div>
                        <?php else: ?>
                        <div class="mb-3 mt-4">
                            <label for="reject_reason" class="form-label">เหตุผลในการไม่อนุมัติ <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="reject_reason" name="reject_reason" rows="3" required></textarea>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="borrowing_detail.php?id=<?= $borrowing_id ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> ยกเลิกและกลับ
                            </a>
                            <button type="submit" class="btn <?= $action == 'approve' ? 'btn-success' : 'btn-danger' ?>">
                                <i class="fas <?= $action == 'approve' ? 'fa-check' : 'fa-times' ?> me-1"></i>
                                <?= $action == 'approve' ? 'ยืนยันการอนุมัติ' : 'ยืนยันการไม่อนุมัติ' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function validateForm() {
    <?php if ($action == 'reject'): ?>
    const rejectReason = document.getElementById('reject_reason').value.trim();
    if (rejectReason === '') {
        alert('กรุณาระบุเหตุผลในการไม่อนุมัติ');
        return false;
    }
    <?php endif; ?>
    
    return confirm('คุณแน่ใจหรือไม่ที่จะ<?= $action == 'approve' ? 'อนุมัติ' : 'ไม่อนุมัติ' ?>รายการยืมนี้?');
}
</script>

<?php include 'footer.php'; ?>