<?php
// หน้าจัดการพัสดุ/ครุภัณฑ์
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// กำหนดชื่อหน้า
$page_title = "รายการพัสดุ/ครุภัณฑ์";

// ดึงข้อมูลหมวดหมู่ทั้งหมด
$categories = [];
try {
    $stmt = $conn->query("SELECT * FROM categories ORDER BY name ASC");
    $categories = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// กำหนดเงื่อนไขการค้นหา
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// สร้าง query พื้นฐาน
$sql = "SELECT i.*, c.name as category_name
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE 1=1";
$params = [];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search_term)) {
    $sql .= " AND (i.code LIKE ? OR i.name LIKE ? OR i.details LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($category_id)) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category_id;
}

if (!empty($status)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.id DESC";

// ดึงข้อมูลพัสดุ/ครุภัณฑ์
$items = [];
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container-fluid">
    <!-- Page Heading -->
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">รายการพัสดุ/ครุภัณฑ์</h1>
        <?php if (is_staff()): ?>
        <div>
            <a href="export_items.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                <i class="fas fa-download fa-sm text-white-50"></i> ส่งออกข้อมูล
            </a>
            <a href="item_add.php" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm ms-2">
                <i class="fas fa-plus fa-sm text-white-50"></i> เพิ่มพัสดุ/ครุภัณฑ์
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- ฟอร์มค้นหา -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">ค้นหาพัสดุ/ครุภัณฑ์</h6>
        </div>
        <div class="card-body">
            <form action="" method="get" class="row g-3">
                <div class="col-md-4">
                    <label for="search" class="form-label">คำค้นหา</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           value="<?php echo htmlspecialchars($search_term); ?>" 
                           placeholder="รหัส, ชื่อ, รายละเอียด">
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label">หมวดหมู่</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">ทั้งหมด</option>
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" <?php echo $category_id == $category['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">สถานะ</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">ทั้งหมด</option>
                        <option value="available" <?php echo $status == 'available' ? 'selected' : ''; ?>>พร้อมใช้งาน</option>
                        <option value="borrowed" <?php echo $status == 'borrowed' ? 'selected' : ''; ?>>ถูกยืม</option>
                        <option value="repair" <?php echo $status == 'repair' ? 'selected' : ''; ?>>ซ่อมบำรุง</option>
                        <option value="disposed" <?php echo $status == 'disposed' ? 'selected' : ''; ?>>จำหน่ายแล้ว</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> ค้นหา
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ตารางข้อมูล -->
    <div class="card shadow mb-4">
        <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
            <h6 class="m-0 font-weight-bold text-primary">รายการพัสดุ/ครุภัณฑ์ทั้งหมด <?php echo count($items); ?> รายการ</h6>
            <?php if (!empty($search_term) || !empty($category_id) || !empty($status)): ?>
            <a href="items.php" class="btn btn-sm btn-secondary">
                <i class="fas fa-redo"></i> ล้างการค้นหา
            </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if (empty($items)): ?>
                <div class="text-center py-4">
                    <i class="fas fa-box-open fa-3x mb-3 text-gray-300"></i>
                    <p class="text-gray-500">ไม่พบข้อมูลพัสดุ/ครุภัณฑ์</p>
                    <?php if (is_staff()): ?>
                    <a href="item_add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> เพิ่มพัสดุ/ครุภัณฑ์
                    </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-bordered datatable" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>รหัส</th>
                                <th>ชื่อ</th>
                                <th>หมวดหมู่</th>
                                <th>ราคา</th>
                                <th>สถานที่เก็บ</th>
                                <th>สถานะ</th>
                                <th width="150">ดำเนินการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['code']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                    <td><?php echo number_format($item['price']); ?> บาท</td>
                                    <td><?php echo htmlspecialchars($item['location']); ?></td>
                                    <td>
                                        <?php
                                        switch ($item['status']) {
                                            case 'available':
                                                echo '<span class="badge bg-success">พร้อมใช้งาน</span>';
                                                break;
                                            case 'borrowed':
                                                echo '<span class="badge bg-primary">ถูกยืม</span>';
                                                break;
                                            case 'repair':
                                                echo '<span class="badge bg-warning">ซ่อมบำรุง</span>';
                                                break;
                                            case 'disposed':
                                                echo '<span class="badge bg-secondary">จำหน่ายแล้ว</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-info">' . $item['status'] . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="item_detail.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-info" data-bs-toggle="tooltip" title="รายละเอียด">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                        <?php if ($item['status'] == 'available' && !is_admin()): ?>
                                        <a href="borrowing_add.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" data-bs-toggle="tooltip" title="ยืม">
                                            <i class="fas fa-hand-holding"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (is_staff()): ?>
                                        <a href="item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning" data-bs-toggle="tooltip" title="แก้ไข">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php if (is_admin()): ?>
                                        <a href="item_delete.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" data-bs-toggle="tooltip" title="ลบ" 
                                           onclick="return confirm('คุณต้องการลบพัสดุ/ครุภัณฑ์นี้ใช่หรือไม่?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- คำอธิบายสถานะ -->
    <div class="row">
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">คำอธิบายสถานะ</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-success me-2">พร้อมใช้งาน</span>
                        <span>- พัสดุ/ครุภัณฑ์ที่พร้อมสำหรับการยืม</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-primary me-2">ถูกยืม</span>
                        <span>- พัสดุ/ครุภัณฑ์ที่กำลังถูกยืมอยู่</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <span class="badge bg-warning me-2">ซ่อมบำรุง</span>
                        <span>- พัสดุ/ครุภัณฑ์ที่อยู่ระหว่างการซ่อมบำรุง</span>
                    </div>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-secondary me-2">จำหน่ายแล้ว</span>
                        <span>- พัสดุ/ครุภัณฑ์ที่จำหน่ายหรือตัดออกจากระบบแล้ว</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">สรุปสถานะพัสดุ/ครุภัณฑ์</h6>
                </div>
                <div class="card-body">
                    <?php
                    // นับจำนวนตามสถานะ
                    $status_counts = [
                        'available' => 0,
                        'borrowed' => 0,
                        'repair' => 0,
                        'disposed' => 0
                    ];
                    
                    foreach ($items as $item) {
                        if (isset($status_counts[$item['status']])) {
                            $status_counts[$item['status']]++;
                        }
                    }
                    
                    $total_items = count($items);
                    ?>
                    
                    <div class="mb-2">
                        <span>พร้อมใช้งาน:</span>
                        <div class="progress mb-1">
                            <div class="progress-bar bg-success" role="progressbar" 
                                 style="width: <?php echo $total_items > 0 ? ($status_counts['available'] / $total_items) * 100 : 0; ?>%" 
                                 aria-valuenow="<?php echo $status_counts['available']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_items; ?>">
                                <?php echo $status_counts['available']; ?> รายการ
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <span>ถูกยืม:</span>
                        <div class="progress mb-1">
                            <div class="progress-bar bg-primary" role="progressbar" 
                                 style="width: <?php echo $total_items > 0 ? ($status_counts['borrowed'] / $total_items) * 100 : 0; ?>%" 
                                 aria-valuenow="<?php echo $status_counts['borrowed']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_items; ?>">
                                <?php echo $status_counts['borrowed']; ?> รายการ
                            </div>
                        </div>
                    </div>
                    <div class="mb-2">
                        <span>ซ่อมบำรุง:</span>
                        <div class="progress mb-1">
                            <div class="progress-bar bg-warning" role="progressbar" 
                                 style="width: <?php echo $total_items > 0 ? ($status_counts['repair'] / $total_items) * 100 : 0; ?>%" 
                                 aria-valuenow="<?php echo $status_counts['repair']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_items; ?>">
                                <?php echo $status_counts['repair']; ?> รายการ
                            </div>
                        </div>
                    </div>
                    <div>
                        <span>จำหน่ายแล้ว:</span>
                        <div class="progress">
                            <div class="progress-bar bg-secondary" role="progressbar" 
                                 style="width: <?php echo $total_items > 0 ? ($status_counts['disposed'] / $total_items) * 100 : 0; ?>%" 
                                 aria-valuenow="<?php echo $status_counts['disposed']; ?>" aria-valuemin="0" aria-valuemax="<?php echo $total_items; ?>">
                                <?php echo $status_counts['disposed']; ?> รายการ
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>