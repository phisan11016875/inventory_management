<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: borrowings.php?error=ไม่พบรหัสการยืมที่ต้องการดู');
    exit();
}

$borrowing_id = $_GET['id'];

// ดึงข้อมูลการยืม
$sql = "SELECT b.*, 
               CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
               u.email as borrower_email,
               u.phone as borrower_phone,
               CONCAT(a.first_name, ' ', a.last_name) as approved_by_name,
               CONCAT(cb.first_name, ' ', cb.last_name) as created_by_name,
               CONCAT(ub.first_name, ' ', ub.last_name) as updated_by_name
        FROM borrowings b
        JOIN users u ON b.borrower_id = u.user_id
        LEFT JOIN users a ON b.approved_by = a.user_id
        LEFT JOIN users cb ON b.created_by = cb.user_id
        LEFT JOIN users ub ON b.updated_by = ub.user_id
        WHERE b.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$borrowing) {
    header('Location: borrowings.php?error=ไม่พบข้อมูลการยืมที่ต้องการดู');
    exit();
}

// ตรวจสอบสิทธิ์การเข้าถึง (ผู้ใช้ทั่วไปเห็นได้เฉพาะรายการของตนเอง)
if ($_SESSION['role'] == 'user' && $_SESSION['user_id'] != $borrowing['borrower_id']) {
    header('Location: borrowings.php?error=คุณไม่มีสิทธิ์ในการดูรายการยืมนี้');
    exit();
}

// ดึงรายการพัสดุที่ยืม
$sql = "SELECT bi.*, i.item_code, i.item_name, i.unit, i.image_path, c.category_name
        FROM borrowing_items bi
        JOIN items i ON bi.item_id = i.item_id
        LEFT JOIN categories c ON i.category_id = c.category_id
        WHERE bi.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงสถานะการยืมเป็นภาษาไทย
function getBorrowingStatusText($status) {
    $statusText = [
        'pending' => 'รออนุมัติ',
        'approved' => 'อนุมัติแล้ว',
        'rejected' => 'ไม่อนุมัติ',
        'borrowed' => 'กำลังยืม',
        'returned' => 'คืนแล้ว',
        'overdue' => 'เกินกำหนด',
        'canceled' => 'ยกเลิก'
    ];
    
    return isset($statusText[$status]) ? $statusText[$status] : $status;
}

// ฟังก์ชันแปลงสถานะการยืมเป็นสีของแบดจ์
function getBorrowingStatusBadgeClass($status) {
    $badgeClass = [
        'pending' => 'bg-warning text-dark',
        'approved' => 'bg-info text-dark',
        'rejected' => 'bg-danger',
        'borrowed' => 'bg-primary',
        'returned' => 'bg-success',
        'overdue' => 'bg-danger',
        'canceled' => 'bg-secondary'
    ];
    
    return isset($badgeClass[$status]) ? $badgeClass[$status] : 'bg-secondary';
}

// ฟังก์ชันแปลงสภาพพัสดุที่คืนเป็นภาษาไทย
function getReturnConditionText($condition) {
    $conditionText = [
        'good' => 'สภาพดี',
        'damaged' => 'ชำรุดเล็กน้อย',
        'heavily_damaged' => 'ชำรุดมาก',
        'lost' => 'สูญหาย'
    ];
    
    return isset($conditionText[$condition]) ? $conditionText[$condition] : $condition;
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container mt-4">
    <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['success']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($_GET['error']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row mb-3">
        <div class="col-md-8">
            <h2>
                <i class="fas fa-exchange-alt me-2"></i>รายละเอียดการยืม 
                <span class="badge <?= getBorrowingStatusBadgeClass($borrowing['status']) ?> fs-6">
                    <?= getBorrowingStatusText($borrowing['status']) ?>
                </span>
            </h2>
        </div>
        <div class="col-md-4 text-end">
            <a href="borrowings.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-1"></i> กลับไปยังรายการยืม-คืน
            </a>
            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
            <a href="export_borrowings.php?id=<?= $borrowing_id ?>" class="btn btn-success ms-2">
                <i class="fas fa-file-excel"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <!-- ข้อมูลการยืม -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">ข้อมูลการยืม</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>รหัสการยืม:</strong> <?= htmlspecialchars($borrowing['borrowing_code']) ?></p>
                            <p><strong>ผู้ยืม:</strong> <?= htmlspecialchars($borrowing['borrower_name']) ?></p>
                            <p><strong>อีเมล:</strong> <?= htmlspecialchars($borrowing['borrower_email']) ?></p>
                            <p><strong>เบอร์โทรศัพท์:</strong> <?= htmlspecialchars($borrowing['borrower_phone']) ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>วันที่ยืม:</strong> <?= date('d/m/Y', strtotime($borrowing['borrowing_date'])) ?></p>
                            <p><strong>วันที่คาดว่าจะคืน:</strong> <?= date('d/m/Y', strtotime($borrowing['expected_return_date'])) ?></p>
                            <p><strong>วันที่คืน:</strong> <?= !empty($borrowing['actual_return_date']) ? date('d/m/Y', strtotime($borrowing['actual_return_date'])) : '-' ?></p>
                            <p><strong>สถานะ:</strong> 
                                <span class="badge <?= getBorrowingStatusBadgeClass($borrowing['status']) ?>">
                                    <?= getBorrowingStatusText($borrowing['status']) ?>
                                </span>
                            </p>
                        </div>
                    </div>
                    
                    <?php if (!empty($borrowing['purpose'])): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>วัตถุประสงค์การยืม:</strong></p>
                            <div class="border p-2 rounded bg-light">
                                <?= nl2br(htmlspecialchars($borrowing['purpose'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($borrowing['note'])): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>หมายเหตุ:</strong></p>
                            <div class="border p-2 rounded bg-light">
                                <?= nl2br(htmlspecialchars($borrowing['note'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- รายการพัสดุที่ยืม -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">รายการพัสดุที่ยืม</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสพัสดุ</th>
                                    <th>ชื่อพัสดุ</th>
                                    <th>หมวดหมู่</th>
                                    <th>จำนวนที่ยืม</th>
                                    <th>จำนวนที่คืน</th>
                                    <th>วันที่คืน</th>
                                    <th>สภาพที่คืน</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowing_items as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['item_code']) ?></td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                                            <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="รูปภาพพัสดุ" class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                            <?php else: ?>
                                            <div class="me-2 bg-light d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-box text-muted"></i>
                                            </div>
                                            <?php endif; ?>
                                            <a href="item_detail.php?id=<?= $item['item_id'] ?>" class="link-primary text-decoration-none">
                                                <?= htmlspecialchars($item['item_name']) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($item['category_name']) ?></td>
                                    <td><?= $item['quantity'] ?> <?= htmlspecialchars($item['unit']) ?></td>
                                    <td>
                                        <?php if ($item['return_quantity'] > 0): ?>
                                            <span class="text-success">
                                                <?= $item['return_quantity'] ?> <?= htmlspecialchars($item['unit']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['return_date'])): ?>
                                            <?= date('d/m/Y', strtotime($item['return_date'])) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($item['return_condition'])): ?>
                                            <?= getReturnConditionText($item['return_condition']) ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- ข้อมูลเพิ่มเติม -->
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">ข้อมูลเพิ่มเติม</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>ผู้สร้างรายการ:</strong> <?= htmlspecialchars($borrowing['created_by_name']) ?></p>
                            <p><strong>วันที่สร้างรายการ:</strong> <?= date('d/m/Y H:i', strtotime($borrowing['created_at'])) ?></p>
                        </div>
                        <div class="col-md-6">
                            <?php if (!empty($borrowing['approved_by'])): ?>
                            <p><strong>ผู้อนุมัติ:</strong> <?= htmlspecialchars($borrowing['approved_by_name']) ?></p>
                            <p><strong>วันที่อนุมัติ:</strong> <?= date('d/m/Y H:i', strtotime($borrowing['approved_at'])) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($borrowing['updated_by'])): ?>
                            <p><strong>แก้ไขล่าสุดโดย:</strong> <?= htmlspecialchars($borrowing['updated_by_name']) ?></p>
                            <p><strong>วันที่แก้ไข:</strong> <?= date('d/m/Y H:i', strtotime($borrowing['updated_at'])) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <!-- การดำเนินการ -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">การดำเนินการ</h5>
                </div>
                <div class="card-body">
                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                        <?php if ($borrowing['status'] == 'pending'): ?>
                            <div class="d-grid gap-2 mb-3">
                                <a href="borrowing_approve.php?id=<?= $borrowing_id ?>&action=approve" class="btn btn-success">
                                    <i class="fas fa-check me-1"></i> อนุมัติ
                                </a>
                                <a href="borrowing_approve.php?id=<?= $borrowing_id ?>&action=reject" class="btn btn-danger">
                                    <i class="fas fa-times me-1"></i> ไม่อนุมัติ
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($borrowing['status'] == 'approved'): ?>
                            <div class="d-grid gap-2 mb-3">
                                <a href="borrowing_return.php?id=<?= $borrowing_id ?>" class="btn btn-primary">
                                    <i class="fas fa-share me-1"></i> บันทึกการยืม
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($borrowing['status'] == 'borrowed' || $borrowing['status'] == 'overdue'): ?>
                            <div class="d-grid gap-2 mb-3">
                                <a href="borrowing_return.php?id=<?= $borrowing_id ?>" class="btn btn-success">
                                    <i class="fas fa-reply me-1"></i> บันทึกการคืน
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php if (($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $borrowing['borrower_id']) && $borrowing['status'] == 'pending'): ?>
                        <div class="d-grid gap-2 mb-3">
                            <a href="borrowing_cancel.php?id=<?= $borrowing_id ?>" class="btn btn-secondary">
                                <i class="fas fa-ban me-1"></i> ยกเลิกการยืม
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($borrowing['status'] == 'borrowed' || $borrowing['status'] == 'overdue'): ?>
                        <div class="alert <?= $borrowing['status'] == 'overdue' ? 'alert-danger' : 'alert-info' ?> mb-3">
                            <h6 class="alert-heading">
                                <i class="fas <?= $borrowing['status'] == 'overdue' ? 'fa-exclamation-circle' : 'fa-info-circle' ?> me-1"></i>
                                <?= $borrowing['status'] == 'overdue' ? 'เกินกำหนดการคืน' : 'กำหนดการคืน' ?>
                            </h6>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>กำหนดคืน:</strong> <?= date('d/m/Y', strtotime($borrowing['expected_return_date'])) ?>
                                </div>
                                <?php if ($borrowing['status'] == 'overdue'): ?>
                                <div class="badge bg-danger">
                                    เกินกำหนด <?= floor((time() - strtotime($borrowing['expected_return_date'])) / (60 * 60 * 24)) ?> วัน
                                </div>
                                <?php else: ?>
                                <div class="badge bg-info text-dark">
                                    เหลือ <?= max(0, floor((strtotime($borrowing['expected_return_date']) - time()) / (60 * 60 * 24))) ?> วัน
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2">
                        <a href="javascript:void(0);" onclick="window.print();" class="btn btn-outline-secondary">
                            <i class="fas fa-print me-1"></i> พิมพ์รายละเอียด
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- QR Code -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?= urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>&choe=UTF-8" alt="QR Code" class="img-fluid">
                    <div class="mt-2">
                        <small class="text-muted">สแกนเพื่อดูรายละเอียดการยืม</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>