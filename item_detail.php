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
    header('Location: items.php?error=ไม่พบรหัสพัสดุ/ครุภัณฑ์ที่ต้องการดู');
    exit();
}

$item_id = $_GET['id'];

// ดึงข้อมูลพัสดุ/ครุภัณฑ์
$sql = "SELECT i.*, c.category_name, 
               CONCAT(uc.first_name, ' ', uc.last_name) as created_by_name,
               CONCAT(uu.first_name, ' ', uu.last_name) as updated_by_name
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.category_id
        LEFT JOIN users uc ON i.created_by = uc.user_id
        LEFT JOIN users uu ON i.updated_by = uu.user_id
        WHERE i.item_id = :item_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':item_id', $item_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$item) {
    header('Location: items.php?error=ไม่พบพัสดุ/ครุภัณฑ์ที่ต้องการดู');
    exit();
}

// ดึงข้อมูลการยืม-คืนของพัสดุนี้
$sql = "SELECT b.borrowing_id, b.borrowing_code, b.borrowing_date, b.expected_return_date, 
               b.actual_return_date, b.status as borrowing_status,
               CONCAT(u.first_name, ' ', u.last_name) as borrower_name
        FROM borrowing_items bi
        JOIN borrowings b ON bi.borrowing_id = b.borrowing_id
        JOIN users u ON b.borrower_id = u.user_id
        WHERE bi.item_id = :item_id
        ORDER BY b.borrowing_date DESC
        LIMIT 10";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':item_id', $item_id);
$stmt->execute();
$borrowing_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ฟังก์ชันแปลงสถานะเป็นภาษาไทย
function getStatusText($status) {
    $statusText = [
        'available' => 'พร้อมใช้งาน',
        'in_use' => 'กำลังใช้งาน',
        'in_repair' => 'กำลังซ่อมแซม',
        'reserved' => 'จองแล้ว',
        'retired' => 'เลิกใช้งาน'
    ];
    
    return isset($statusText[$status]) ? $statusText[$status] : $status;
}

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

// ฟังก์ชันแปลงวิธีการได้มาเป็นภาษาไทย
function getAcquisitionMethodText($method) {
    $methodText = [
        'purchase' => 'ซื้อ',
        'donation' => 'รับบริจาค',
        'transfer' => 'รับโอน',
        'other' => 'อื่นๆ'
    ];
    
    return isset($methodText[$method]) ? $methodText[$method] : $method;
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
        
        <!-- รูปภาพและข้อมูลเพิ่มเติม -->
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">รูปภาพ</h5>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($item['image_path']) && file_exists($item['image_path'])): ?>
                    <img src="<?= htmlspecialchars($item['image_path']) ?>" alt="รูปภาพพัสดุ/ครุภัณฑ์" class="img-fluid rounded img-thumbnail" style="max-height: 300px;">
                    <?php else: ?>
                    <div class="alert alert-secondary mb-0 d-flex justify-content-center align-items-center" style="height: 200px;">
                        <div>
                            <i class="fas fa-image fa-4x mb-3 text-muted"></i>
                            <p class="mb-0">ไม่มีรูปภาพ</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- QR Code สำหรับพัสดุ/ครุภัณฑ์ -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <img src="https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl=<?= urlencode($_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>&choe=UTF-8" alt="QR Code" class="img-fluid">
                    <div class="mt-2">
                        <small class="text-muted">สแกนเพื่อดูรายละเอียดพัสดุ/ครุภัณฑ์</small>
                    </div>
                    <div class="mt-3">
                        <a href="javascript:void(0);" onclick="window.print();" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-print me-1"></i> พิมพ์ QR Code
                        </a>
                        <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                        <a href="export_items.php?id=<?= $item_id ?>" class="btn btn-sm btn-outline-success ms-2">
                            <i class="fas fa-file-excel me-1"></i> ส่งออก Excel
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <?php if ($item['status'] == 'available' && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff' || $_SESSION['role'] == 'user')): ?>
            <!-- ทำรายการยืม -->
            <div class="card mb-4 border-success">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0"><i class="fas fa-share me-2"></i>ทำรายการยืม</h5>
                </div>
                <div class="card-body">
                    <p>พัสดุ/ครุภัณฑ์นี้สามารถยืมได้</p>
                    <a href="borrowing_add.php?item_id=<?= $item_id ?>" class="btn btn-success w-100">
                        <i class="fas fa-share me-1"></i> ยืมพัสดุ/ครุภัณฑ์นี้
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-12 mb-3">
            <div class="d-flex justify-content-between align-items-center">
                <h3>รายละเอียดพัสดุ/ครุภัณฑ์</h3>
                <div>
                    <a href="items.php" class="btn btn-secondary">
                        <i class="fas fa-list"></i> รายการพัสดุ/ครุภัณฑ์
                    </a>
                    <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
                    <a href="item_edit.php?id=<?= $item_id ?>" class="btn btn-primary ms-2">
                        <i class="fas fa-edit"></i> แก้ไข
                    </a>
                    <?php if ($item['status'] == 'available'): ?>
                    <a href="borrowing_add.php?item_id=<?= $item_id ?>" class="btn btn-success ms-2">
                        <i class="fas fa-share"></i> ทำรายการยืม
                    </a>
                    <?php endif; ?>
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                    <a href="item_delete.php?id=<?= $item_id ?>" class="btn btn-danger ms-2">
                        <i class="fas fa-trash-alt"></i> ลบ
                    </a>
                    <?php endif; ?>
                    <?php endif; ?>
                </div>
            
            <!-- ประวัติการยืม-คืน -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">ประวัติการยืม-คืน (10 รายการล่าสุด)</h5>
                </div>
                <div class="card-body">
                    <?php if (count($borrowing_history) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>รหัสการยืม</th>
                                    <th>ผู้ยืม</th>
                                    <th>วันที่ยืม</th>
                                    <th>กำหนดคืน</th>
                                    <th>วันที่คืน</th>
                                    <th>สถานะ</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($borrowing_history as $history): ?>
                                <tr>
                                    <td><?= htmlspecialchars($history['borrowing_code']) ?></td>
                                    <td><?= htmlspecialchars($history['borrower_name']) ?></td>
                                    <td><?= date('d/m/Y', strtotime($history['borrowing_date'])) ?></td>
                                    <td><?= !empty($history['expected_return_date']) ? date('d/m/Y', strtotime($history['expected_return_date'])) : '-' ?></td>
                                    <td><?= !empty($history['actual_return_date']) ? date('d/m/Y', strtotime($history['actual_return_date'])) : '-' ?></td>
                                    <td>
                                        <span class="badge <?php 
                                            switch($history['borrowing_status']) {
                                                case 'pending':
                                                    echo 'bg-warning text-dark';
                                                    break;
                                                case 'approved':
                                                    echo 'bg-info text-dark';
                                                    break;
                                                case 'borrowed':
                                                    echo 'bg-primary';
                                                    break;
                                                case 'returned':
                                                    echo 'bg-success';
                                                    break;
                                                case 'overdue':
                                                    echo 'bg-danger';
                                                    break;
                                                case 'rejected':
                                                case 'canceled':
                                                    echo 'bg-secondary';
                                                    break;
                                                default:
                                                    echo 'bg-secondary';
                                            }
                                        ?>">
                                            <?= getBorrowingStatusText($history['borrowing_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="borrowing_detail.php?id=<?= $history['borrowing_id'] ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="fas fa-info-circle me-2"></i>ไม่พบประวัติการยืม-คืนของพัสดุ/ครุภัณฑ์นี้
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">ข้อมูลพื้นฐาน</h5>
                        <span class="badge <?php 
                            switch($item['status']) {
                                case 'available':
                                    echo 'bg-success';
                                    break;
                                case 'in_use':
                                    echo 'bg-primary';
                                    break;
                                case 'in_repair':
                                    echo 'bg-warning text-dark';
                                    break;
                                case 'reserved':
                                    echo 'bg-info text-dark';
                                    break;
                                case 'retired':
                                    echo 'bg-secondary';
                                    break;
                                default:
                                    echo 'bg-secondary';
                            }
                        ?> fs-6">
                            <?= getStatusText($item['status']) ?>
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>รหัสพัสดุ/ครุภัณฑ์:</strong> <?= htmlspecialchars($item['item_code']) ?></p>
                            <p><strong>ชื่อพัสดุ/ครุภัณฑ์:</strong> <?= htmlspecialchars($item['item_name']) ?></p>
                            <p><strong>หมวดหมู่:</strong> <?= htmlspecialchars($item['category_name']) ?></p>
                            <p><strong>จำนวน:</strong> <?= number_format($item['quantity']) ?> <?= htmlspecialchars($item['unit']) ?></p>
                            <p><strong>ราคา:</strong> <?= number_format($item['price'], 2) ?> บาท</p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>สถานที่เก็บ:</strong> <?= !empty($item['location']) ? htmlspecialchars($item['location']) : '-' ?></p>
                            <p><strong>วิธีการได้มา:</strong> <?= !empty($item['acquisition_method']) ? getAcquisitionMethodText($item['acquisition_method']) : '-' ?></p>
                            <p><strong>ผู้จำหน่าย/แหล่งที่มา:</strong> <?= !empty($item['supplier']) ? htmlspecialchars($item['supplier']) : '-' ?></p>
                            <p><strong>วันที่ได้รับ:</strong> <?= !empty($item['acquisition_date']) ? date('d/m/Y', strtotime($item['acquisition_date'])) : '-' ?></p>
                            <p><strong>วันหมดประกัน:</strong> <?= !empty($item['warranty_period']) ? date('d/m/Y', strtotime($item['warranty_period'])) : '-' ?></p>
                        </div>
                    </div>
                    
                    <?php if (!empty($item['description'])): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>รายละเอียด:</strong></p>
                            <div class="border p-2 rounded bg-light">
                                <?= nl2br(htmlspecialchars($item['description'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($item['notes'])): ?>
                    <div class="row mt-3">
                        <div class="col-md-12">
                            <p><strong>หมายเหตุ:</strong></p>
                            <div class="border p-2 rounded bg-light">
                                <?= nl2br(htmlspecialchars($item['notes'])) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer text-muted">
                    <div class="row">
                        <div class="col-md-6">
                            <small>สร้างเมื่อ: <?= date('d/m/Y H:i', strtotime($item['created_at'])) ?> โดย <?= htmlspecialchars($item['created_by_name']) ?></small>
                        </div>
                        <div class="col-md-6 text-end">
                            <?php if (!empty($item['updated_at']) && !empty($item['updated_by_name'])): ?>
                            <small>แก้ไขล่าสุด: <?= date('d/m/Y H:i', strtotime($item['updated_at'])) ?> โดย <?= htmlspecialchars($item['updated_by_name']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php include 'footer.php'; ?>