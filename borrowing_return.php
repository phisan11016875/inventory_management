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
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการบันทึกการคืน');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: borrowings.php?error=ไม่พบรหัสการยืมที่ต้องการบันทึกการคืน');
    exit();
}

$borrowing_id = $_GET['id'];

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
    header('Location: borrowings.php?error=ไม่พบข้อมูลการยืมที่ต้องการบันทึกการคืน');
    exit();
}

// ตรวจสอบว่าสถานะเป็น approved หรือ borrowed หรือ overdue หรือไม่
if ($borrowing['status'] != 'approved' && $borrowing['status'] != 'borrowed' && $borrowing['status'] != 'overdue') {
    header('Location: borrowing_detail.php?id=' . $borrowing_id . '&error=รายการยืมนี้ไม่อยู่ในสถานะที่สามารถบันทึกการคืนได้');
    exit();
}

// กำหนดการทำงาน (บันทึกการยืมหรือบันทึกการคืน)
$action_type = ($borrowing['status'] == 'approved') ? 'borrow' : 'return';
$page_title = ($action_type == 'borrow') ? 'บันทึกการยืม' : 'บันทึกการคืน';

// ดึงรายการพัสดุที่ยืม
$sql = "SELECT bi.borrowing_item_id, bi.item_id, bi.quantity, bi.return_quantity, 
               i.item_code, i.item_name, i.unit, i.status as item_status
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
        
        if ($action_type == 'borrow') {
            // บันทึกการยืม
            $note = isset($_POST['note']) ? $_POST['note'] : '';
            
            // อัปเดตสถานะเป็น borrowed
            $sql = "UPDATE borrowings SET 
                    status = 'borrowed',
                    updated_by = :updated_by,
                    updated_at = NOW(),
                    note = CONCAT(IFNULL(note, ''), '\n\nบันทึกการยืม: ', :note)
                    WHERE borrowing_id = :borrowing_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':updated_by', $_SESSION['user_id']);
            $stmt->bindParam(':note', $note);
            $stmt->bindParam(':borrowing_id', $borrowing_id);
            $stmt->execute();
            
            // บันทึกล็อกการยืม
            $log_message = "บันทึกการยืม: " . $borrowing['borrowing_code'];
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'borrow_items', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
            
            // ข้อความแจ้งเตือน
            $success_message = "บันทึกการยืมเรียบร้อยแล้ว";
            
        } else {
            // บันทึกการคืน
            if (!isset($_POST['return_items']) || !is_array($_POST['return_items']) || count($_POST['return_items']) == 0) {
                throw new Exception("กรุณาเลือกรายการที่ต้องการคืน");
            }
            
            // ข้อมูลการคืน
            $return_date = isset($_POST['return_date']) ? $_POST['return_date'] : date('Y-m-d');
            $return_condition = isset($_POST['return_condition']) ? $_POST['return_condition'] : '';
            $note = isset($_POST['note']) ? $_POST['note'] : '';
            
            // ตรวจสอบจำนวนที่คืน
            $return_items = $_POST['return_items'];
            $return_quantities = isset($_POST['return_quantity']) ? $_POST['return_quantity'] : [];
            
            $all_returned = true; // ตรวจสอบว่าคืนครบทุกรายการหรือไม่
            
            foreach ($borrowing_items as $item) {
                $borrowing_item_id = $item['borrowing_item_id'];
                
                // ถ้ารายการนี้ถูกเลือกให้คืน
                if (in_array($borrowing_item_id, $return_items)) {
                    $return_quantity = isset($return_quantities[$borrowing_item_id]) ? intval($return_quantities[$borrowing_item_id]) : 0;
                    
                    // ตรวจสอบจำนวนที่คืน
                    if ($return_quantity <= 0) {
                        throw new Exception("จำนวนที่คืนต้องมากกว่า 0");
                    }
                    
                    if ($return_quantity > $item['quantity']) {
                        throw new Exception("จำนวนที่คืนของ {$item['item_name']} เกินกว่าจำนวนที่ยืม");
                    }
                    
                    // บันทึกจำนวนที่คืน
                    $sql = "UPDATE borrowing_items SET 
                            return_quantity = :return_quantity,
                            return_date = :return_date,
                            return_condition = :return_condition
                            WHERE borrowing_item_id = :borrowing_item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':return_quantity', $return_quantity);
                    $stmt->bindParam(':return_date', $return_date);
                    $stmt->bindParam(':return_condition', $return_condition);
                    $stmt->bindParam(':borrowing_item_id', $borrowing_item_id);
                    $stmt->execute();
                    
                    // อัปเดตสถานะและจำนวนพัสดุ
                    $sql = "SELECT quantity, status FROM items WHERE item_id = :item_id";
                    $stmt = $conn->prepare($sql);
                    $stmt->bindParam(':item_id', $item['item_id']);
                    $stmt->execute();
                    $current_item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($current_item['status'] == 'in_use') {
                        // ถ้าพัสดุอยู่ในสถานะกำลังใช้งาน ให้เปลี่ยนกลับเป็นพร้อมใช้งาน
                        $sql = "UPDATE items SET status = 'available', quantity = :quantity, updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':quantity', $return_quantity);
                        $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                        $stmt->bindParam(':item_id', $item['item_id']);
                        $stmt->execute();
                    } else {
                        // ถ้าพัสดุอยู่ในสถานะอื่น ให้เพิ่มจำนวน
                        $new_quantity = $current_item['quantity'] + $return_quantity;
                        $sql = "UPDATE items SET quantity = :quantity, updated_by = :updated_by, updated_at = NOW() WHERE item_id = :item_id";
                        $stmt = $conn->prepare($sql);
                        $stmt->bindParam(':quantity', $new_quantity);
                        $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                        $stmt->bindParam(':item_id', $item['item_id']);
                        $stmt->execute();
                    }
                    
                    // ตรวจสอบว่าคืนครบจำนวนหรือไม่
                    if ($return_quantity < $item['quantity']) {
                        $all_returned = false;
                    }
                } else {
                    // ถ้ารายการนี้ไม่ได้ถูกเลือกให้คืน
                    $all_returned = false;
                }
            }
            
            // อัปเดตสถานะการยืม
            if ($all_returned) {
                // ถ้าคืนครบทุกรายการ ให้เปลี่ยนสถานะเป็น returned
                $sql = "UPDATE borrowings SET 
                        status = 'returned',
                        actual_return_date = :return_date,
                        updated_by = :updated_by,
                        updated_at = NOW(),
                        note = CONCAT(IFNULL(note, ''), '\n\nบันทึกการคืน: ', :note)
                        WHERE borrowing_id = :borrowing_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':return_date', $return_date);
                $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                $stmt->bindParam(':note', $note);
                $stmt->bindParam(':borrowing_id', $borrowing_id);
                $stmt->execute();
            } else {
                // ถ้าคืนบางส่วน ให้อัปเดตเฉพาะหมายเหตุ
                $sql = "UPDATE borrowings SET 
                        updated_by = :updated_by,
                        updated_at = NOW(),
                        note = CONCAT(IFNULL(note, ''), '\n\nบันทึกการคืนบางส่วน: ', :note)
                        WHERE borrowing_id = :borrowing_id";
                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':updated_by', $_SESSION['user_id']);
                $stmt->bindParam(':note', $note);
                $stmt->bindParam(':borrowing_id', $borrowing_id);
                $stmt->execute();
            }
            
            // บันทึกล็อกการคืน
            $log_message = ($all_returned ? "บันทึกการคืนทั้งหมด: " : "บันทึกการคืนบางส่วน: ") . $borrowing['borrowing_code'];
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'return_items', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
            
            // ข้อความแจ้งเตือน
            $success_message = $all_returned ? "บันทึกการคืนทั้งหมดเรียบร้อยแล้ว" : "บันทึกการคืนบางส่วนเรียบร้อยแล้ว";
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
        <div class="col-md-10">
            <div class="card">
                <div class="card-header <?= $action_type == 'borrow' ? 'bg-primary' : 'bg-success' ?> text-white">
                    <h4>
                        <i class="fas <?= $action_type == 'borrow' ? 'fa-share' : 'fa-reply' ?> me-2"></i>
                        <?= $page_title ?>
                    </h4>
                </div>
                <div class="card-body">
                    <?php if (isset($error_message)): ?>
                        <div class="alert alert-danger"><?= $error_message ?></div>
                    <?php endif; ?>
                    
                    <div class="alert alert-info">
                        <h5>ข้อมูลการยืม</h5>
                        <div class="row mt-3">
                            <div class="col-md-3 fw-bold">รหัสการยืม:</div>
                            <div class="col-md-9"><?= htmlspecialchars($borrowing['borrowing_code']) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 fw-bold">ผู้ยืม:</div>
                            <div class="col-md-9"><?= htmlspecialchars($borrowing['borrower_name']) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 fw-bold">วันที่ยืม:</div>
                            <div class="col-md-9"><?= date('d/m/Y', strtotime($borrowing['borrowing_date'])) ?></div>
                        </div>
                        <div class="row mt-2">
                            <div class="col-md-3 fw-bold">วันที่คาดว่าจะคืน:</div>
                            <div class="col-md-9"><?= date('d/m/Y', strtotime($borrowing['expected_return_date'])) ?></div>
                        </div>
                        <?php if (!empty($borrowing['purpose'])): ?>
                        <div class="row mt-2">
                            <div class="col-md-3 fw-bold">วัตถุประสงค์:</div>
                            <div class="col-md-9"><?= nl2br(htmlspecialchars($borrowing['purpose'])) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <form method="POST" id="returnForm">
                        <?php if ($action_type == 'return'): ?>
                        <div class="row mb-3 mt-4">
                            <div class="col-md-4">
                                <label for="return_date" class="form-label">วันที่คืน <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="return_date" name="return_date" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label for="return_condition" class="form-label">สภาพพัสดุที่คืน</label>
                                <select class="form-select" id="return_condition" name="return_condition">
                                    <option value="good">สภาพดี</option>
                                    <option value="damaged">ชำรุดเล็กน้อย</option>
                                    <option value="heavily_damaged">ชำรุดมาก</option>
                                    <option value="lost">สูญหาย</option>
                                </select>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="table-responsive mt-4">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <?php if ($action_type == 'return'): ?>
                                        <th width="5%">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="select-all" checked>
                                            </div>
                                        </th>
                                        <?php endif; ?>
                                        <th width="15%">รหัสพัสดุ</th>
                                        <th width="30%">ชื่อพัสดุ</th>
                                        <th width="15%">จำนวนที่ยืม</th>
                                        <?php if ($action_type == 'return'): ?>
                                        <th width="20%">จำนวนที่คืน</th>
                                        <?php endif; ?>
                                        <th width="15%">สถานะพัสดุ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($borrowing_items as $item): ?>
                                    <tr>
                                        <?php if ($action_type == 'return'): ?>
                                        <td>
                                            <div class="form-check">
                                                <input class="form-check-input item-checkbox" type="checkbox" 
                                                       name="return_items[]" value="<?= $item['borrowing_item_id'] ?>" 
                                                       id="item-<?= $item['borrowing_item_id'] ?>" checked
                                                       data-quantity="<?= $item['quantity'] ?>">
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars($item['item_code']) ?></td>
                                        <td><?= htmlspecialchars($item['item_name']) ?></td>
                                        <td><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                        <?php if ($action_type == 'return'): ?>
                                        <td>
                                            <input type="number" class="form-control return-quantity" 
                                                   name="return_quantity[<?= $item['borrowing_item_id'] ?>]" 
                                                   value="<?= $item['quantity'] ?>" min="1" max="<?= $item['quantity'] ?>"
                                                   <?= $item['return_quantity'] > 0 ? 'disabled' : '' ?>>
                                        </td>
                                        <?php endif; ?>
                                        <td>
                                            <span class="badge <?= $item['item_status'] == 'available' ? 'bg-success' : 'bg-primary' ?>">
                                                <?= $item['item_status'] == 'available' ? 'พร้อมใช้งาน' : 'กำลังใช้งาน' ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mb-3 mt-4">
                            <label for="note" class="form-label">หมายเหตุ</label>
                            <textarea class="form-control" id="note" name="note" rows="3"></textarea>
                        </div>
                        
                        <div class="d-flex justify-content-between mt-4">
                            <a href="borrowing_detail.php?id=<?= $borrowing_id ?>" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-1"></i> ยกเลิกและกลับ
                            </a>
                            <button type="submit" class="btn <?= $action_type == 'borrow' ? 'btn-primary' : 'btn-success' ?>">
                                <i class="fas <?= $action_type == 'borrow' ? 'fa-share' : 'fa-reply' ?> me-1"></i>
                                <?= $action_type == 'borrow' ? 'บันทึกการยืม' : 'บันทึกการคืน' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($action_type == 'return'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // จัดการการเลือกทั้งหมด
    const selectAllCheckbox = document.getElementById('select-all');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    
    selectAllCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        itemCheckboxes.forEach(checkbox => {
            checkbox.checked = isChecked;
            toggleReturnQuantityField(checkbox);
        });
    });
    
    // จัดการการเลือกรายการแต่ละรายการ
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            toggleReturnQuantityField(this);
            
            // ตรวจสอบว่าเลือกทั้งหมดหรือไม่
            const allChecked = Array.from(itemCheckboxes).every(cb => cb.checked);
            selectAllCheckbox.checked = allChecked;
        });
    });
    
    // เปิด/ปิดช่องกรอกจำนวนคืนตามการเลือก
    function toggleReturnQuantityField(checkbox) {
        const id = checkbox.value;
        const quantityField = document.querySelector(`input[name="return_quantity[${id}]"]`);
        
        if (quantityField) {
            if (checkbox.checked) {
                quantityField.disabled = false;
                quantityField.required = true;
            } else {
                quantityField.disabled = true;
                quantityField.required = false;
            }
        }
    }
    
    // ตรวจสอบฟอร์มก่อนส่ง
    document.getElementById('returnForm').addEventListener('submit', function(event) {
        // ตรวจสอบว่ามีการเลือกรายการที่คืนหรือไม่
        const checkedItems = document.querySelectorAll('.item-checkbox:checked');
        if (checkedItems.length === 0) {
            event.preventDefault();
            alert('กรุณาเลือกอย่างน้อย 1 รายการที่ต้องการคืน');
            return;
        }
        
        // ตรวจสอบจำนวนที่คืน
        let isValid = true;
        checkedItems.forEach(checkbox => {
            const id = checkbox.value;
            const quantityField = document.querySelector(`input[name="return_quantity[${id}]"]`);
            const maxQuantity = parseInt(checkbox.dataset.quantity);
            
            if (quantityField) {
                const returnQuantity = parseInt(quantityField.value);
                
                if (isNaN(returnQuantity) || returnQuantity <= 0) {
                    isValid = false;
                    alert('จำนวนที่คืนต้องมากกว่า 0');
                } else if (returnQuantity > maxQuantity) {
                    isValid = false;
                    alert('จำนวนที่คืนต้องไม่เกินจำนวนที่ยืม');
                }
            }
        });
        
        if (!isValid) {
            event.preventDefault();
        }
    });
});
</script>
<?php endif; ?>

<?php include 'footer.php'; ?>