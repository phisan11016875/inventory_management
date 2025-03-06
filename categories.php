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
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้');
    exit();
}

// การจัดการเพิ่มหมวดหมู่ใหม่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    try {
        // ตรวจสอบข้อมูลที่จำเป็น
        if (empty($_POST['category_name'])) {
            throw new Exception("กรุณาระบุชื่อหมวดหมู่");
        }

        $category_name = $_POST['category_name'];
        $description = isset($_POST['description']) ? $_POST['description'] : '';

        // ตรวจสอบว่ามีหมวดหมู่นี้อยู่แล้วหรือไม่
        $sql = "SELECT COUNT(*) FROM categories WHERE category_name = :category_name";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_name', $category_name);
        $stmt->execute();
        if ($stmt->fetchColumn() > 0) {
            throw new Exception("หมวดหมู่นี้มีอยู่แล้วในระบบ");
        }

        // เพิ่มหมวดหมู่ใหม่
        $sql = "INSERT INTO categories (category_name, description, created_by, created_at) VALUES (:category_name, :description, :created_by, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_name', $category_name);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        $stmt->execute();

        // บันทึกล็อกการทำงาน
        $log_message = "เพิ่มหมวดหมู่: " . $category_name;
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'add_category', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();

        // แสดงข้อความสำเร็จ
        $success_message = "เพิ่มหมวดหมู่ \"" . htmlspecialchars($category_name) . "\" เรียบร้อยแล้ว";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// ดึงข้อมูลหมวดหมู่ทั้งหมด
$search = isset($_GET['search']) ? $_GET['search'] : '';
$sql = "SELECT c.*, 
               CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
               CONCAT(uu.first_name, ' ', uu.last_name) as updated_by_name,
               (SELECT COUNT(*) FROM items WHERE category_id = c.category_id) as item_count
        FROM categories c
        LEFT JOIN users u ON c.created_by = u.user_id
        LEFT JOIN users uu ON c.updated_by = uu.user_id
        WHERE (c.category_name LIKE :search OR c.description LIKE :search)
        ORDER BY c.category_name";
$stmt = $conn->prepare($sql);
$stmt->bindValue(':search', '%' . $search . '%');
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// คำนวณจำนวนหมวดหมู่ทั้งหมด
$total_categories = count($categories);

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container mt-4">
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

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
            <h2><i class="fas fa-tags"></i> จัดการหมวดหมู่</h2>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus"></i> เพิ่มหมวดหมู่ใหม่
            </button>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row mb-3">
                <div class="col-md-6">
                    <form action="" method="GET" class="d-flex">
                        <input type="text" name="search" class="form-control me-2" placeholder="ค้นหาหมวดหมู่..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i>
                        </button>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">ทั้งหมด <?= $total_categories ?> หมวดหมู่</span>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="25%">ชื่อหมวดหมู่</th>
                            <th width="30%">รายละเอียด</th>
                            <th width="15%">จำนวนพัสดุ</th>
                            <th width="15%">วันที่สร้าง</th>
                            <th width="10%">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($categories) > 0): ?>
                            <?php $counter = 1; ?>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= $counter++ ?></td>
                                    <td><?= htmlspecialchars($category['category_name']) ?></td>
                                    <td><?= !empty($category['description']) ? htmlspecialchars($category['description']) : '<span class="text-muted">- ไม่มีข้อมูล -</span>' ?></td>
                                    <td>
                                        <?php if ($category['item_count'] > 0): ?>
                                            <a href="items.php?category_id=<?= $category['category_id'] ?>" class="badge bg-info text-dark text-decoration-none">
                                                <?= number_format($category['item_count']) ?> รายการ
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 รายการ</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small><?= date('d/m/Y', strtotime($category['created_at'])) ?></small>
                                        <div class="small text-muted">โดย: <?= htmlspecialchars($category['created_by_name']) ?></div>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="category_edit.php?id=<?= $category['category_id'] ?>" class="btn btn-warning" title="แก้ไข">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                                <a href="category_delete.php?id=<?= $category['category_id'] ?>" class="btn btn-danger" title="ลบ">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-3">
                                    <div class="alert alert-info mb-0">
                                        <i class="fas fa-info-circle me-2"></i> ไม่พบข้อมูลหมวดหมู่ที่ตรงกับการค้นหา
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่มหมวดหมู่ใหม่ -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="addCategoryModalLabel">เพิ่มหมวดหมู่ใหม่</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="category_name" class="form-label">ชื่อหมวดหมู่ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="category_name" name="category_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">รายละเอียด</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_category" class="btn btn-primary">บันทึก</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>