<?php
// หน้าแดชบอร์ดหลัก
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// กำหนดชื่อหน้า
$page_title = "แดชบอร์ด";

// ดึงข้อมูลสถิติ
$stats = [
    'total_items' => 0,
    'total_borrowed' => 0,
    'total_available' => 0,
    'total_repair' => 0,
    'total_disposed' => 0,
    'pending_borrowings' => 0,
    'approved_borrowings' => 0,
    'returned_borrowings' => 0,
    'overdue_borrowings' => 0,
    'user_borrowings' => 0
];

try {
    // จำนวนพัสดุ/ครุภัณฑ์ทั้งหมด
    $stmt = $conn->query("SELECT COUNT(*) FROM items");
    $stats['total_items'] = $stmt->fetchColumn();
    
    // จำนวนพัสดุ/ครุภัณฑ์ตามสถานะ
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM items GROUP BY status");
    while ($row = $stmt->fetch()) {
        switch ($row['status']) {
            case 'available':
                $stats['total_available'] = $row['count'];
                break;
            case 'borrowed':
                $stats['total_borrowed'] = $row['count'];
                break;
            case 'repair':
                $stats['total_repair'] = $row['count'];
                break;
            case 'disposed':
                $stats['total_disposed'] = $row['count'];
                break;
        }
    }
    
    // จำนวนการยืมตามสถานะ
    $stmt = $conn->query("SELECT status, COUNT(*) as count FROM borrowings GROUP BY status");
    while ($row = $stmt->fetch()) {
        switch ($row['status']) {
            case 'pending':
                $stats['pending_borrowings'] = $row['count'];
                break;
            case 'approved':
                $stats['approved_borrowings'] = $row['count'];
                break;
            case 'returned':
                $stats['returned_borrowings'] = $row['count'];
                break;
            case 'overdue':
                $stats['overdue_borrowings'] = $row['count'];
                break;
        }
    }
    
    // นับรายการยืมที่เกินกำหนด (สถานะ approved แต่เกินวันที่กำหนด)
    $stmt = $conn->query("SELECT COUNT(*) FROM borrowings WHERE status = 'approved' AND due_date < CURDATE()");
    $stats['overdue_borrowings'] = $stmt->fetchColumn();
    
    // จำนวนรายการยืมของผู้ใช้ปัจจุบัน
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $stats['user_borrowings'] = $stmt->fetchColumn();
    
    // ข้อมูลการยืมล่าสุด 5 รายการ
    if (is_staff()) {
        // สำหรับแอดมินและเจ้าหน้าที่แสดงทุกรายการ
        $stmt = $conn->query("SELECT b.*, i.name as item_name, i.code as item_code, u.name as borrower_name 
                              FROM borrowings b 
                              LEFT JOIN items i ON b.item_id = i.id 
                              LEFT JOIN users u ON b.user_id = u.id 
                              ORDER BY b.created_at DESC LIMIT 5");
    } else {
        // สำหรับผู้ใช้ทั่วไปแสดงเฉพาะรายการของตนเอง
        $stmt = $conn->prepare("SELECT b.*, i.name as item_name, i.code as item_code, u.name as borrower_name 
                               FROM borrowings b 
                               LEFT JOIN items i ON b.item_id = i.id 
                               LEFT JOIN users u ON b.user_id = u.id 
                               WHERE b.user_id = ? 
                               ORDER BY b.created_at DESC LIMIT 5");
        $stmt->execute([$_SESSION['user_id']]);
    }
    $recent_borrowings = $stmt->fetchAll();
    
    // ข้อมูลพัสดุ/ครุภัณฑ์ที่มีการยืมบ่อย 5 อันดับ
    $stmt = $conn->query("SELECT i.id, i.name, i.code, COUNT(b.id) as borrow_count 
                         FROM items i 
                         LEFT JOIN borrowings b ON i.id = b.item_id 
                         GROUP BY i.id 
                         ORDER BY borrow_count DESC LIMIT 5");
    $popular_items = $stmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">แดชบอร์ด</h1>
        <?php if (is_staff()): ?>
        <div>
            <a href="export_items.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> รายงานพัสดุ/ครุภัณฑ์
            </a>
            <a href="export_borrowings.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm ms-2">
                <i class="fas fa-download fa-sm text-white-50"></i> รายงานการยืม-คืน
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- พัสดุ/ครุภัณฑ์ทั้งหมด -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                พัสดุ/ครุภัณฑ์ทั้งหมด</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_items']); ?> รายการ</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-boxes fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- พัสดุ/ครุภัณฑ์ที่พร้อมใช้งาน -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-success shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                พร้อมใช้งาน</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_available']); ?> รายการ</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- พัสดุ/ครุภัณฑ์ที่ถูกยืม -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-info shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                กำลังถูกยืม
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['total_borrowed']); ?> รายการ</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-hand-holding fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- รายการยืมที่เกินกำหนด -->
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                เกินกำหนดคืน</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stats['overdue_borrowings']); ?> รายการ</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Row -->
    <div class="row">
        <!-- ข้อมูลการยืม-คืนล่าสุด -->
        <div class="col-lg-8">
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                    <h6 class="m-0 font-weight-bold text-primary">รายการยืม-คืนล่าสุด</h6>
                    <a href="borrowings.php" class="btn btn-sm btn-primary">ดูทั้งหมด</a>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_borrowings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-alt fa-2x mb-3 text-gray-300"></i>
                            <p class="text-gray-500">ไม่พบรายการยืม-คืนล่าสุด</p>
                            <a href="borrowing_add.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> ยืมพัสดุ/ครุภัณฑ์
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>รหัส</th>
                                        <th>รายการ</th>
                                        <th>ผู้ยืม</th>
                                        <th>วันที่ยืม</th>
                                        <th>กำหนดคืน</th>
                                        <th>สถานะ</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_borrowings as $borrowing): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($borrowing['item_code']); ?></td>
                                            <td><?php echo htmlspecialchars($borrowing['item_name']); ?></td>
                                            <td><?php echo htmlspecialchars($borrowing['borrower_name']); ?></td>
                                            <td><?php echo isset($borrowing['borrow_date']) && $borrowing['borrow_date'] ? date('d/m/Y', strtotime($borrowing['borrow_date'])) : '-'; ?></td>
                                            
                                            <td>
                                                <span class="badge bg-<?php echo borrowing_status_color($borrowing['status']); ?>">
                                                    <?php echo borrowing_status_text($borrowing['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="borrowing_detail.php?id=<?php echo $borrowing['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- สถิติและการดำเนินการ -->
        <div class="col-lg-4">
            <!-- สถิติการยืม -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">สถิติการยืม-คืน</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>รออนุมัติ</span>
                            <span class="text-primary"><?php echo $stats['pending_borrowings']; ?> รายการ</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-warning" role="progressbar" style="width: <?php echo ($stats['pending_borrowings'] / max(1, $stats['pending_borrowings'] + $stats['approved_borrowings'] + $stats['returned_borrowings'])) * 100; ?>%" 
                                aria-valuenow="<?php echo $stats['pending_borrowings']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>กำลังยืม</span>
                            <span class="text-primary"><?php echo $stats['approved_borrowings']; ?> รายการ</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo ($stats['approved_borrowings'] / max(1, $stats['pending_borrowings'] + $stats['approved_borrowings'] + $stats['returned_borrowings'])) * 100; ?>%" 
                                aria-valuenow="<?php echo $stats['approved_borrowings']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>คืนแล้ว</span>
                            <span class="text-primary"><?php echo $stats['returned_borrowings']; ?> รายการ</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo ($stats['returned_borrowings'] / max(1, $stats['pending_borrowings'] + $stats['approved_borrowings'] + $stats['returned_borrowings'])) * 100; ?>%" 
                                aria-valuenow="<?php echo $stats['returned_borrowings']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between mb-1">
                            <span>เกินกำหนด</span>
                            <span class="text-primary"><?php echo $stats['overdue_borrowings']; ?> รายการ</span>
                        </div>
                        <div class="progress mb-2">
                            <div class="progress-bar bg-danger" role="progressbar" style="width: <?php echo ($stats['overdue_borrowings'] / max(1, $stats['pending_borrowings'] + $stats['approved_borrowings'] + $stats['returned_borrowings'])) * 100; ?>%" 
                                aria-valuenow="<?php echo $stats['overdue_borrowings']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ดำเนินการด่วน -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">ดำเนินการด่วน</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <a href="borrowing_add.php" class="btn btn-primary btn-block">
                                <i class="fas fa-hand-holding mr-2"></i> ยืมพัสดุ/ครุภัณฑ์
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="items.php" class="btn btn-info btn-block">
                                <i class="fas fa-search mr-2"></i> ค้นหาพัสดุ/ครุภัณฑ์
                            </a>
                        </div>
                        <?php if (is_staff()): ?>
                        <div class="col-6 mb-3">
                            <a href="item_add.php" class="btn btn-success btn-block">
                                <i class="fas fa-plus mr-2"></i> เพิ่มพัสดุ/ครุภัณฑ์
                            </a>
                        </div>
                        <div class="col-6 mb-3">
                            <a href="borrowings.php?status=pending" class="btn btn-warning btn-block">
                                <i class="fas fa-clipboard-check mr-2"></i> อนุมัติการยืม
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- พัสดุ/ครุภัณฑ์ยอดนิยม -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">พัสดุ/ครุภัณฑ์ที่มีการยืมบ่อย</h6>
                </div>
                <div class="card-body">
                    <?php if (empty($popular_items)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-boxes fa-2x mb-3 text-gray-300"></i>
                            <p class="text-gray-500">ยังไม่มีข้อมูลพัสดุ/ครุภัณฑ์ที่มีการยืมบ่อย</p>
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($popular_items as $item): ?>
                                <div class="col-md-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body text-center">
                                            <h5 class="card-title"><?php echo htmlspecialchars($item['name']); ?></h5>
                                            <p class="card-text text-muted"><?php echo htmlspecialchars($item['code']); ?></p>
                                            <div class="badge bg-primary mb-3">ยืมแล้ว <?php echo $item['borrow_count']; ?> ครั้ง</div>
                                            <div>
                                                <a href="item_detail.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-info-circle"></i> รายละเอียด
                                                </a>
                                                <a href="borrowing_add.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-hand-holding"></i> ยืม
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>