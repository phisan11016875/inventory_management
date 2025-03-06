<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// กำหนดจำนวนรายการต่อหน้า
$items_per_page = 15;

// รับค่าการค้นหาและตัวกรอง
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// สร้าง query สำหรับการค้นหาและตัวกรอง
$params = [];
$where_conditions = [];

// เงื่อนไขการค้นหา
if (!empty($search)) {
    $where_conditions[] = "(b.borrowing_code LIKE :search OR i.item_name LIKE :search OR 
                           CONCAT(u.first_name, ' ', u.last_name) LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// เงื่อนไขตัวกรองสถานะ
if (!empty($status_filter)) {
    $where_conditions[] = "b.status = :status";
    $params[':status'] = $status_filter;
}

// เงื่อนไขตัวกรองวันที่
if (!empty($date_from)) {
    $where_conditions[] = "b.borrowing_date >= :date_from";
    $params[':date_from'] = $date_from;
}
if (!empty($date_to)) {
    $where_conditions[] = "b.borrowing_date <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

// เงื่อนไขเพิ่มเติมตามบทบาทผู้ใช้
if ($_SESSION['role'] == 'user') {
    $where_conditions[] = "b.borrower_id = :user_id";
    $params[':user_id'] = $_SESSION['user_id'];
}

// สร้าง WHERE clause
$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// คำนวณจำนวนรายการทั้งหมด
$sql = "SELECT COUNT(DISTINCT b.borrowing_id) as total
        FROM borrowings b
        LEFT JOIN borrowing_items bi ON b.borrowing_id = bi.borrowing_id
        LEFT JOIN items i ON bi.item_id = i.item_id
        LEFT JOIN users u ON b.borrower_id = u.user_id
        $where_clause";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$total_items = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// คำนวณจำนวนหน้าทั้งหมด
$total_pages = ceil($total_items / $items_per_page);

// ปรับค่าหน้าปัจจุบันให้ถูกต้อง
if ($current_page < 1) $current_page = 1;
if ($current_page > $total_pages && $total_pages > 0) $current_page = $total_pages;

// คำนวณ offset สำหรับ SQL
$offset = ($current_page - 1) * $items_per_page;

// ดึงข้อมูลการยืมจากฐานข้อมูล
$sql = "SELECT b.*, 
               CONCAT(u.first_name, ' ', u.last_name) as borrower_name,
               CONCAT(a.first_name, ' ', a.last_name) as approved_by_name,
               (SELECT COUNT(*) FROM borrowing_items WHERE borrowing_id = b.borrowing_id) as item_count
        FROM borrowings b
        LEFT JOIN users u ON b.borrower_id = u.user_id
        LEFT JOIN users a ON b.approved_by = a.user_id
        LEFT JOIN borrowing_items bi ON b.borrowing_id = bi.borrowing_id
        LEFT JOIN items i ON bi.item_id = i.item_id
        $where_clause
        GROUP BY b.borrowing_id
        ORDER BY 
            CASE 
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'approved' THEN 2
                WHEN b.status = 'borrowed' THEN 3
                WHEN b.status = 'overdue' THEN 4
                WHEN b.status = 'returned' THEN 5
                WHEN b.status = 'rejected' THEN 6
                WHEN b.status = 'canceled' THEN 7
                ELSE 8
            END,
            b.created_at DESC
        LIMIT :offset, :limit";
$stmt = $conn->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
$stmt->execute();
$borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <div class="row mb-4">
        <div class="col-md-6">
            <h2><i class="fas fa-exchange-alt"></i> จัดการการยืม-คืน</h2>
        </div>
        <div class="col-md-6 text-end">
            <?php if ($_SESSION['role'] != 'viewer'): ?>
            <a href="borrowing_add.php" class="btn btn-primary">
                <i class="fas fa-plus"></i> สร้างรายการยืมใหม่
            </a>
            <?php endif; ?>
            <?php if ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff'): ?>
            <a href="export_borrowings.php<?= !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '' ?>" class="btn btn-success ms-2">
                <i class="fas fa-file-excel"></i> ส่งออก Excel
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">ค้นหาและตัวกรอง</h5>
        </div>
        <div class="card-body">
            <form action="" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="search" class="form-label">ค้นหา</label>
                    <input type="text" class="form-control" id="search" name="search" placeholder="รหัสการยืม, ชื่อพัสดุ, ผู้ยืม" value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">สถานะ</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>รออนุมัติ</option>
                        <option value="approved" <?= $status_filter == 'approved' ? 'selected' : '' ?>>อนุมัติแล้ว</option>
                        <option value="borrowed" <?= $status_filter == 'borrowed' ? 'selected' : '' ?>>กำลังยืม</option>
                        <option value="returned" <?= $status_filter == 'returned' ? 'selected' : '' ?>>คืนแล้ว</option>
                        <option value="overdue" <?= $status_filter == 'overdue' ? 'selected' : '' ?>>เกินกำหนด</option>
                        <option value="rejected" <?= $status_filter == 'rejected' ? 'selected' : '' ?>>ไม่อนุมัติ</option>
                        <option value="canceled" <?= $status_filter == 'canceled' ? 'selected' : '' ?>>ยกเลิก</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="date_from" class="form-label">ตั้งแต่วันที่</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" value="<?= $date_from ?>">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label">ถึงวันที่</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" value="<?= $date_to ?>">
                </div>
                <div class="col-12 text-end">
                    <?php if (!empty($search) || !empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="borrowings.php" class="btn btn-secondary me-2">
                        <i class="fas fa-undo"></i> ล้างตัวกรอง
                    </a>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>รหัสการยืม</th>
                            <th>ผู้ยืม</th>
                            <th>จำนวนพัสดุ</th>
                            <th>วันที่ยืม</th>
                            <th>กำหนดคืน</th>
                            <th>วันที่คืน</th>
                            <th>สถานะ</th>
                            <th>การจัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($borrowings) > 0): ?>
                            <?php foreach ($borrowings as $borrowing): ?>
                                <tr>
                                    <td><?= htmlspecialchars($borrowing['borrowing_code']) ?></td>
                                    <td><?= htmlspecialchars($borrowing['borrower_name']) ?></td>
                                    <td><?= $borrowing['item_count'] ?> รายการ</td>
                                    <td><?= !empty($borrowing['borrowing_date']) ? date('d/m/Y', strtotime($borrowing['borrowing_date'])) : '-' ?></td>
                                    <td><?= !empty($borrowing['expected_return_date']) ? date('d/m/Y', strtotime($borrowing['expected_return_date'])) : '-' ?></td>
                                    <td><?= !empty($borrowing['actual_return_date']) ? date('d/m/Y', strtotime($borrowing['actual_return_date'])) : '-' ?></td>
                                    <td>
                                        <span class="badge <?= getBorrowingStatusBadgeClass($borrowing['status']) ?>">
                                            <?= getBorrowingStatusText($borrowing['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="borrowing_detail.php?id=<?= $borrowing['borrowing_id'] ?>" class="btn btn-info" title="ดูรายละเอียด">
                                                <i class="fas fa-info-circle"></i>
                                            </a>
                                            
                                            <?php if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') && $borrowing['status'] == 'pending'): ?>
                                            <a href="borrowing_approve.php?id=<?= $borrowing['borrowing_id'] ?>&action=approve" class="btn btn-success" title="อนุมัติ">
                                                <i class="fas fa-check"></i>
                                            </a>
                                            <a href="borrowing_approve.php?id=<?= $borrowing['borrowing_id'] ?>&action=reject" class="btn btn-danger" title="ไม่อนุมัติ">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') && $borrowing['status'] == 'approved'): ?>
                                            <a href="borrowing_return.php?id=<?= $borrowing['borrowing_id'] ?>" class="btn btn-primary" title="บันทึกการยืม">
                                                <i class="fas fa-share"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff') && ($borrowing['status'] == 'borrowed' || $borrowing['status'] == 'overdue')): ?>
                                            <a href="borrowing_return.php?id=<?= $borrowing['borrowing_id'] ?>" class="btn btn-success" title="บันทึกการคืน">
                                                <i class="fas fa-reply"></i>
                                            </a>
                                            <?php endif; ?>
                                            
                                            <?php if (($_SESSION['role'] == 'admin' || $_SESSION['user_id'] == $borrowing['borrower_id']) && $borrowing['status'] == 'pending'): ?>
                                            <a href="borrowing_cancel.php?id=<?= $borrowing['borrowing_id'] ?>" class="btn btn-secondary" title="ยกเลิก">
                                                <i class="fas fa-ban"></i>
                                            </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-3">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i>ไม่พบข้อมูลการยืม-คืน
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-center mt-4">
                <nav aria-label="Page navigation">
                    <ul class="pagination">
                        <?php if ($current_page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=1<?= !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('page=' . $current_page, '', $_SERVER['QUERY_STRING']) : '' ?>" aria-label="First">
                                <span aria-hidden="true">&laquo;&laquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('page=' . $current_page, '', $_SERVER['QUERY_STRING']) : '' ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                        <li class="page-item <?= $i == $current_page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $i ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('page=' . $current_page, '', $_SERVER['QUERY_STRING']) : '' ?>">
                                <?= $i ?>
                            </a>
                        </li>
                        <?php endfor; ?>
                        
                        <?php if ($current_page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('page=' . $current_page, '', $_SERVER['QUERY_STRING']) : '' ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                        <li class="page-item">
                            <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($_SERVER['QUERY_STRING']) ? '&' . str_replace('page=' . $current_page, '', $_SERVER['QUERY_STRING']) : '' ?>" aria-label="Last">
                                <span aria-hidden="true">&raquo;&raquo;</span>
                            </a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
            
            <div class="mt-3 text-center text-muted">
                แสดง <?= count($borrowings) ?> รายการ จากทั้งหมด <?= number_format($total_items) ?> รายการ
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>