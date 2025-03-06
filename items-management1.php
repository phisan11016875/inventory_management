<?php
// items.php - หน้ารายการพัสดุ/ครุภัณฑ์
require_once 'config.php';
requireLogin();

$page_title = "จัดการพัสดุ/ครุภัณฑ์";
$page_subtitle = "รายการพัสดุ/ครุภัณฑ์ทั้งหมด";
$breadcrumb = [
    ['title' => 'จัดการพัสดุ/ครุภัณฑ์', 'url' => 'items.php', 'active' => true]
];

// ตัวกรองสถานะ
$status_filter = isset($_GET['status']) ? clean($conn, $_GET['status']) : '';
$category_filter = isset($_GET['category']) ? (int)$_GET['category'] : 0;

// สร้างคำสั่ง SQL พื้นฐาน
$query = "SELECT i.*, c.name as category_name 
          FROM items i 
          LEFT JOIN categories c ON i.category_id = c.id 
          WHERE 1=1";

// เพิ่มเงื่อนไขการกรอง
if (!empty($status_filter)) {
    $query .= " AND i.status = '$status_filter'";
}

if ($category_filter > 0) {
    $query .= " AND i.category_id = $category_filter";
}

// เรียงลำดับข้อมูล
$query .= " ORDER BY i.id DESC";

// ดึงข้อมูลพัสดุ/ครุภัณฑ์
$result = $conn->query($query);

// ดึงข้อมูลหมวดหมู่สำหรับตัวกรอง
$category_query = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($category_query);

// เริ่มต้น Output Buffering
ob_start();
include('header.php');
?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">รายการพัสดุ/ครุภัณฑ์ทั้งหมด</h3>
        <div class="box-tools">
            <a href="item_add.php" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> เพิ่มพัสดุ/ครุภัณฑ์ใหม่</a>
            <a href="report_items.php" class="btn btn-info btn-sm"><i class="fa fa-print"></i> พิมพ์รายงาน</a>
            <a href="export_items.php" class="btn btn-warning btn-sm"><i class="fa fa-file-excel"></i> ส่งออก Excel</a>
        </div>
    </div>
    <div class="box-body">
        <!-- ตัวกรองข้อมูล -->
        <div class="row">
            <div class="col-md-12">
                <form method="get" action="items.php" class="form-inline mb-3">
                    <div class="form-group">
                        <label for="status">สถานะ:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="available" <?php echo $status_filter == 'available' ? 'selected' : ''; ?>>พร้อมใช้งาน</option>
                            <option value="borrowed" <?php echo $status_filter == 'borrowed' ? 'selected' : ''; ?>>กำลังถูกยืม</option>
                            <option value="maintenance" <?php echo $status_filter == 'maintenance' ? 'selected' : ''; ?>>ซ่อมบำรุง</option>
                            <option value="disposed" <?php echo $status_filter == 'disposed' ? 'selected' : ''; ?>>จำหน่าย</option>
                        </select>
                    </div>
                    
                    <div class="form-group ml-2">
                        <label for="category">หมวดหมู่:</label>
                        <select name="category" id="category" class="form-control">
                            <option value="0">-- ทั้งหมด --</option>
                            <?php while ($category = $categories->fetch_assoc()): ?>
                                <option value="<?php echo $category['id']; ?>" <?php echo $category_filter == $category['id'] ? 'selected' : ''; ?>>
                                    <?php echo $category['name']; ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <button type="submit" class="btn btn-primary ml-2"><i class="fa fa-search"></i> กรอง</button>
                    <a href="items.php" class="btn btn-default ml-2"><i class="fa fa-refresh"></i> รีเซ็ต</a>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped dataTable">
                <thead>
                    <tr>
                        <th width="60">ลำดับ</th>
                        <th>รหัสพัสดุ</th>
                        <th>รายการ</th>
                        <th>หมวดหมู่</th>
                        <th>วันที่ได้มา</th>
                        <th>ราคา (บาท)</th>
                        <th>สถานที่เก็บ</th>
                        <th>สถานะ</th>
                        <th width="150">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $i = 1; ?>
                        <?php while ($item = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $item['item_code']; ?></td>
                                <td><a href="item_detail.php?id=<?php echo $item['id']; ?>"><?php echo $item['name']; ?></a></td>
                                <td><?php echo $item['category_name'] ? $item['category_name'] : '-'; ?></td>
                                <td><?php echo $item['acquisition_date'] ? date('d/m/Y', strtotime($item['acquisition_date'])) : '-'; ?></td>
                                <td class="text-right"><?php echo number_format($item['acquisition_cost'], 2); ?></td>
                                <td><?php echo $item['location'] ? $item['location'] : '-'; ?></td>
                                <td>
                                    <?php if ($item['status'] == 'available'): ?>
                                        <span class="label label-success">พร้อมใช้งาน</span>
                                    <?php elseif ($item['status'] == 'borrowed'): ?>
                                        <span class="label label-warning">กำลังถูกยืม</span>
                                    <?php elseif ($item['status'] == 'maintenance'): ?>
                                        <span class="label label-primary">ซ่อมบำรุง</span>
                                    <?php elseif ($item['status'] == 'disposed'): ?>
                                        <span class="label label-danger">จำหน่าย</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="item_detail.php?id=<?php echo $item['id']; ?>" class="btn btn-info btn-xs"><i class="fa fa-eye"></i> ดู</a>
                                        <a href="item_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-primary btn-xs"><i class="fa fa-edit"></i> แก้ไข</a>
                                        <button type="button" class="btn btn-danger btn-xs btn-delete" data-id="<?php echo $item['id']; ?>" data-name="<?php echo $item['name']; ?>"><i class="fa fa-trash"></i> ลบ</button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center">ไม่พบข้อมูลพัสดุ/ครุภัณฑ์</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับการลบข้อมูล -->
<script>
$(document).ready(function() {
    $('.btn-delete').click(function() {
        var itemId = $(this).data('id');
        var itemName = $(this).data('name');
        
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: 'คุณต้องการลบพัสดุ/ครุภัณฑ์ "' + itemName + '" ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'item_delete.php?id=' + itemId;
            }
        });
    });
});
</script>

<?php
include('footer.php');
$html_content = ob_get_clean();
echo $html_content;
?>

<?php
// item_add.php - หน้าเพิ่มพัสดุ/ครุภัณฑ์ใหม่
require_once 'config.php';
requireLogin();

$page_title = "เพิ่มพัสดุ/ครุภัณฑ์";
$page_subtitle = "เพิ่มรายการพัสดุ/ครุภัณฑ์ใหม่";
$breadcrumb = [
    ['title' => 'จัดการพัสดุ/ครุภัณฑ์', 'url' => 'items.php', 'active' => false],
    ['title' => 'เพิ่มพัสดุ/ครุภัณฑ์', 'url' => 'item_add.php', 'active' => true]
];

// ดึงข้อมูลหมวดหมู่
$category_query = "SELECT * FROM categories ORDER BY name ASC";
$categories = $conn->query($category_query);

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจากฟอร์ม
    $item_code = clean($conn, $_POST['item_code']);
    $category_id = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
    $name = clean($conn, $_POST['name']);
    $description = clean($conn, $_POST['description']);
    $acquisition_date = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
    $acquisition_cost = !empty($_POST['acquisition_cost']) ? (float)$_POST['acquisition_cost'] : 0;
    $supplier = clean($conn, $_POST['supplier']);
    $status = clean($conn, $_POST['status']);
    $location = clean($conn, $_POST['location']);
    $condition = clean($conn, $_POST['condition']);
    $warranty_expiration = !empty($_POST['warranty_expiration']) ? $_POST['warranty_expiration'] : null;
    $notes = clean($conn, $_POST['notes']);
    
    // ตรวจสอบข้อผิดพลาด
    $errors = [];
    
    // ตรวจสอบว่ากรอกข้อมูลสำคัญครบหรือไม่
    if (empty($item_code)) $errors[] = "กรุณากรอกรหัสพัสดุ/ครุภัณฑ์";
    if (empty($name)) $errors[] = "กรุณากรอกชื่อรายการ";
    
    // ตรวจสอบว่ารหัสพัสดุซ้ำหรือไม่
    $check_code = "SELECT * FROM items WHERE item_code = '$item_code'";
    $result = $conn->query($check_code);
    if ($result->num_rows > 0) {
        $errors[] = "รหัสพัสดุ/ครุภัณฑ์นี้มีในระบบแล้ว กรุณาใช้รหัสอื่น";
    }
    
    // ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล
    if (empty($errors)) {
        // บันทึกข้อมูลพัสดุ/ครุภัณฑ์
        $query = "INSERT INTO items (item_code, category_id, name, description, acquisition_date, acquisition_cost, 
                  supplier, status, location, condition, warranty_expiration, notes) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sissdsssssss", $item_code, $category_id, $name, $description, $acquisition_date, 
                          $acquisition_cost, $supplier, $status, $location, $condition, $warranty_expiration, $notes);
        
        if ($stmt->execute()) {
            $item_id = $stmt->insert_id;
            
            // บันทึกประวัติกิจกรรม
            logActivity($conn, $_SESSION['user_id'], 'create_item', "เพิ่มพัสดุ/ครุภัณฑ์ใหม่: $name (รหัส: $item_code)");
            
            // แสดงข้อความสำเร็จและกลับไปหน้ารายการพัสดุ
            redirectWithAlert('items.php', 'success', 'เพิ่มพัสดุ/ครุภัณฑ์ใหม่เรียบร้อยแล้ว');
        } else {
            $errors[] = "เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $stmt->error;
        }
        
        $stmt->close();
    }
}

// เริ่มต้น Output Buffering
ob_start();
include('header.php');
?>

<div class="box box-primary">
    <div class="box-header with-border">
        <h3 class="box-title">เพิ่มพัสดุ/ครุภัณฑ์ใหม่</h3>
    </div>
    
    <form action="item_add.php" method="post" class="form-horizontal">
        <div class="box-body">
            <?php if (isset($errors) && !empty($errors)): ?>
                <div class="alert alert-danger">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label for="item_code" class="col-sm-2 control-label">รหัสพัสดุ/ครุภัณฑ์ *</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="item_code" name="item_code" placeholder="รหัสพัสดุ/ครุภัณฑ์" value="<?php echo isset($_POST['item_code']) ? $_POST['item_code'] : ''; ?>" required>
                </div>
                
                <label for="category_id" class="col-sm-2 control-label">หมวดหมู่</label>
                <div class="col-sm-4">
                    <select class="form-control" id="category_id" name="category_id">
                        <option value="">-- เลือกหมวดหมู่ --</option>
                        <?php while ($category = $categories->fetch_assoc()): ?>
                            <option value="<?php echo $category['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo $category['name']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="name" class="col-sm-2 control-label">ชื่อรายการ *</label>
                <div class="col-sm-10">
                    <input type="text" class="form-control" id="name" name="name" placeholder="ชื่อรายการพัสดุ/ครุภัณฑ์" value="<?php echo isset($_POST['name']) ? $_POST['name'] : ''; ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="description" class="col-sm-2 control-label">รายละเอียด</label>
                <div class="col-sm-10">
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="รายละเอียดเพิ่มเติม"><?php echo isset($_POST['description']) ? $_POST['description'] : ''; ?></textarea>
                </div>
            </div>
            
            <div class="form-group">
                <label for="acquisition_date" class="col-sm-2 control-label">วันที่ได้มา</label>
                <div class="col-sm-4">
                    <input type="date" class="form-control" id="acquisition_date" name="acquisition_date" value="<?php echo isset($_POST['acquisition_date']) ? $_POST['acquisition_date'] : ''; ?>">
                </div>
                
                <label for="acquisition_cost" class="col-sm-2 control-label">ราคา (บาท)</label>
                <div class="col-sm-4">
                    <input type="number" step="0.01" min="0" class="form-control" id="acquisition_cost" name="acquisition_cost" placeholder="0.00" value="<?php echo isset($_POST['acquisition_cost']) ? $_POST['acquisition_cost'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="supplier" class="col-sm-2 control-label">ผู้จำหน่าย/แหล่งที่มา</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="supplier" name="supplier" placeholder="ชื่อผู้จำหน่ายหรือแหล่งที่มา" value="<?php echo isset($_POST['supplier']) ? $_POST['supplier'] : ''; ?>">
                </div>
                
                <label for="location" class="col-sm-2 control-label">สถานที่เก็บ</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="location" name="location" placeholder="สถานที่เก็บหรือติดตั้ง" value="<?php echo isset($_POST['location']) ? $_POST['location'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="status" class="col-sm-2 control-label">สถานะ *</label>
                <div class="col-sm-4">
                    <select class="form-control" id="status" name="status" required>
                        <option value="available" <?php echo (isset($_POST['status']) && $_POST['status'] == 'available') ? 'selected' : ''; ?>>พร้อมใช้งาน</option>
                        <option value="borrowed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'borrowed') ? 'selected' : ''; ?>>กำลังถูกยืม</option>
                        <option value="maintenance" <?php echo (isset($_POST['status']) && $_POST['status'] == 'maintenance') ? 'selected' : ''; ?>>ซ่อมบำรุง</option>
                        <option value="disposed" <?php echo (isset($_POST['status']) && $_POST['status'] == 'disposed') ? 'selected' : ''; ?>>จำหน่าย</option>
                    </select>
                </div>
                
                <label for="condition" class="col-sm-2 control-label">สภาพ *</label>
                <div class="col-sm-4">
                    <select class="form-control" id="condition" name="condition" required>
                        <option value="new" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'new') ? 'selected' : ''; ?>>ใหม่</option>
                        <option value="good" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'good') ? 'selected' : ''; ?>>ดี</option>
                        <option value="fair" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'fair') ? 'selected' : ''; ?>>พอใช้</option>
                        <option value="poor" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'poor') ? 'selected' : ''; ?>>ชำรุด</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="warranty_expiration" class="col-sm-2 control-label">วันหมดประกัน</label>
                <div class="col-sm-4">
                    <input type="date" class="form-control" id="warranty_expiration" name="warranty_expiration" value="<?php echo isset($_POST['warranty_expiration']) ? $_POST['warranty_expiration'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes" class="col-sm-2 control-label">หมายเหตุ</label>
                <div class="col-sm-10">
                    <textarea class="form-control" id="notes" name="notes" rows="3" placeholder="หมายเหตุเพิ่มเติม"><?php echo isset($_POST['notes']) ? $_POST['notes'] : ''; ?></textarea>
                </div>
            </div>
        </div>
        
        <div class="box-footer">
            <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> บันทึก</button>
            <a href="items.php" class="btn btn-default"><i class="fa fa-arrow-left"></i> ยกเลิก</a>
        </div>
    </form>
</div>

<?php
include('footer.php');
$html_content = ob_get_clean();
echo $html_content;
?>

<?php
// categories.php - หน้าจัดการหมวดหมู่
require_once 'config.php';
requireLogin();

$page_title = "จัดการหมวดหมู่";
$page_subtitle = "รายการหมวดหมู่พัสดุ/ครุภัณฑ์";
$breadcrumb = [
    ['title' => 'จัดการพัสดุ/ครุภัณฑ์', 'url' => 'items.php', 'active' => false],
    ['title' => 'จัดการหมวดหมู่', 'url' => 'categories.php', 'active' => true]
];

// ตรวจสอบการส่งฟอร์มเพิ่มหมวดหมู่
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_category'])) {
    $name = clean($conn, $_POST['name']);
    $description = clean($conn, $_POST['description']);
    
    // ตรวจสอบข้อผิดพลาด
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อหมวดหมู่";
    }
    
    // ตรวจสอบว่าชื่อหมวดหมู่ซ้ำหรือไม่
    $check_name = "SELECT * FROM categories WHERE name = '$name'";
    $result = $conn->query($check_name);
    if ($result->num_rows > 0) {
        $errors[] = "ชื่อหมวดหมู่นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น";
    }
    
    // ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล
    if (empty($errors)) {
        $query = "INSERT INTO categories (name, description) VALUES (?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $name, $description);
        
        if ($stmt->execute()) {
            // บันทึกประวัติกิจกรรม
            logActivity($conn, $_SESSION['user_id'], 'create_category', "เพิ่มหมวดหมู่ใหม่: $name");
            
            // แสดงข้อความสำเร็จ
            showAlert('success', 'เพิ่มหมวดหมู่ใหม่เรียบร้อยแล้ว');
        } else {
            showAlert('error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error);
        }
        
        $stmt->close();
    } else {
        foreach ($errors as $error) {
            showAlert('error', $error);
        }
    }
}

// ดึงข้อมูลหมวดหมู่ทั้งหมด
$query = "SELECT c.*, 
          (SELECT COUNT(*) FROM items WHERE category_id = c.id) as item_count 
          FROM categories c 
          ORDER BY c.name ASC";
$result = $conn->query($query);

// เริ่มต้น Output Buffering
ob_start();
include('header.php');
?>

<div class="row">
    <div class="col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">เพิ่มหมวดหมู่ใหม่</h3>
            </div>
            
            <form action="categories.php" method="post">
                <div class="box-footer">
                    <button type="submit" name="add_category" class="btn btn-primary btn-block"><i class="fa fa-plus"></i> เพิ่มหมวดหมู่</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title">รายการหมวดหมู่ทั้งหมด</h3>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped dataTable">
                        <thead>
                            <tr>
                                <th width="60">ลำดับ</th>
                                <th>ชื่อหมวดหมู่</th>
                                <th>รายละเอียด</th>
                                <th>จำนวนรายการ</th>
                                <th>วันที่สร้าง</th>
                                <th width="130">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php $i = 1; ?>
                                <?php while ($category = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><?php echo $category['name']; ?></td>
                                        <td><?php echo $category['description'] ? $category['description'] : '-'; ?></td>
                                        <td class="text-center"><?php echo $category['item_count']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <button type="button" class="btn btn-primary btn-xs btn-edit" 
                                                    data-id="<?php echo $category['id']; ?>" 
                                                    data-name="<?php echo $category['name']; ?>" 
                                                    data-description="<?php echo $category['description']; ?>">
                                                <i class="fa fa-edit"></i> แก้ไข
                                            </button>
                                            <?php if ($category['item_count'] == 0): ?>
                                                <button type="button" class="btn btn-danger btn-xs btn-delete" 
                                                        data-id="<?php echo $category['id']; ?>" 
                                                        data-name="<?php echo $category['name']; ?>">
                                                    <i class="fa fa-trash"></i> ลบ
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">ไม่พบข้อมูลหมวดหมู่</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal แก้ไขหมวดหมู่ -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" role="dialog" aria-labelledby="editCategoryModalLabel">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="editCategoryModalLabel">แก้ไขหมวดหมู่</h4>
            </div>
            <form action="category_edit.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="form-group">
                        <label for="edit_name">ชื่อหมวดหมู่ *</label>
                        <input type="text" class="form-control" id="edit_name" name="name" placeholder="ชื่อหมวดหมู่" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_description">รายละเอียด</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3" placeholder="รายละเอียดของหมวดหมู่"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><i class="fa fa-times"></i> ยกเลิก</button>
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> บันทึกการเปลี่ยนแปลง</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับการจัดการหมวดหมู่ -->
<script>
$(document).ready(function() {
    // แสดง Modal แก้ไขหมวดหมู่
    $('.btn-edit').click(function() {
        var categoryId = $(this).data('id');
        var categoryName = $(this).data('name');
        var categoryDescription = $(this).data('description');
        
        $('#edit_category_id').val(categoryId);
        $('#edit_name').val(categoryName);
        $('#edit_description').val(categoryDescription);
        
        $('#editCategoryModal').modal('show');
    });
    
    // ลบหมวดหมู่
    $('.btn-delete').click(function() {
        var categoryId = $(this).data('id');
        var categoryName = $(this).data('name');
        
        Swal.fire({
            title: 'ยืนยันการลบ',
            text: 'คุณต้องการลบหมวดหมู่ "' + categoryName + '" ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ลบเลย',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'category_delete.php?id=' + categoryId;
            }
        });
    });
});
</script>

<?php
include('footer.php');
$html_content = ob_get_clean();
echo $html_content;
?>

<?php
// category_edit.php - สำหรับแก้ไขหมวดหมู่
require_once 'config.php';
requireLogin();

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['category_id'])) {
    $category_id = (int)$_POST['category_id'];
    $name = clean($conn, $_POST['name']);
    $description = clean($conn, $_POST['description']);
    
    // ตรวจสอบข้อผิดพลาด
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "กรุณากรอกชื่อหมวดหมู่";
    }
    
    // ตรวจสอบว่าชื่อหมวดหมู่ซ้ำหรือไม่ (ยกเว้นหมวดหมู่นี้)
    $check_name = "SELECT * FROM categories WHERE name = '$name' AND id != $category_id";
    $result = $conn->query($check_name);
    if ($result->num_rows > 0) {
        $errors[] = "ชื่อหมวดหมู่นี้มีในระบบแล้ว กรุณาใช้ชื่ออื่น";
    }
    
    // ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล
    if (empty($errors)) {
        $query = "UPDATE categories SET name = ?, description = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssi", $name, $description, $category_id);
        
        if ($stmt->execute()) {
            // บันทึกประวัติกิจกรรม
            logActivity($conn, $_SESSION['user_id'], 'update_category', "แก้ไขหมวดหมู่: $name");
            
            // แสดงข้อความสำเร็จและกลับไปหน้าหมวดหมู่
            redirectWithAlert('categories.php', 'success', 'แก้ไขหมวดหมู่เรียบร้อยแล้ว');
        } else {
            redirectWithAlert('categories.php', 'error', 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $stmt->error);
        }
        
        $stmt->close();
    } else {
        // แสดงข้อความผิดพลาดและกลับไปหน้าหมวดหมู่
        redirectWithAlert('categories.php', 'error', implode('<br>', $errors));
    }
} else {
    // ไม่ได้ส่งข้อมูลมา ให้กลับไปหน้าหมวดหมู่
    header("Location: categories.php");
    exit();
}
?>

<?php
// category_delete.php - สำหรับลบหมวดหมู่
require_once 'config.php';
requireLogin();

// ตรวจสอบว่ามี ID หรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    redirectWithAlert('categories.php', 'error', 'ไม่พบข้อมูลหมวดหมู่ที่ต้องการลบ');
}

$category_id = (int)$_GET['id'];

// ดึงข้อมูลหมวดหมู่ที่จะลบ
$query = "SELECT name FROM categories WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    redirectWithAlert('categories.php', 'error', 'ไม่พบข้อมูลหมวดหมู่ที่ต้องการลบ');
}

$category = $result->fetch_assoc();
$stmt->close();

// ตรวจสอบว่ามีพัสดุที่ใช้หมวดหมู่นี้หรือไม่
$check_items = "SELECT COUNT(*) as item_count FROM items WHERE category_id = ?";
$stmt = $conn->prepare($check_items);
$stmt->bind_param("i", $category_id);
$stmt->execute();
$result = $stmt->get_result();
$item_count = $result->fetch_assoc()['item_count'];
$stmt->close();

if ($item_count > 0) {
    redirectWithAlert('categories.php', 'error', "ไม่สามารถลบหมวดหมู่ '{$category['name']}' ได้ เนื่องจากมีพัสดุ/ครุภัณฑ์ที่ใช้หมวดหมู่นี้อยู่ $item_count รายการ");
}

// ลบหมวดหมู่
$query = "DELETE FROM categories WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $category_id);

if ($stmt->execute()) {
    // บันทึกประวัติกิจกรรม
    logActivity($conn, $_SESSION['user_id'], 'delete_category', "ลบหมวดหมู่: {$category['name']}");
    
    // แสดงข้อความสำเร็จ
    redirectWithAlert('categories.php', 'success', "ลบหมวดหมู่ '{$category['name']}' เรียบร้อยแล้ว");
} else {
    redirectWithAlert('categories.php', 'error', 'เกิดข้อผิดพลาดในการลบหมวดหมู่: ' . $stmt->error);
}

$stmt->close();
?>body">
                    <div class="form-group">
                        <label for="name">ชื่อหมวดหมู่ *</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="ชื่อหมวดหมู่" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">รายละเอียด</label>
                        <textarea class="form-control" id="description" name="description" rows="3" placeholder="รายละเอียดของหมวดหมู่"></textarea>
                    </div>
                </div>
                
                <div class="box-