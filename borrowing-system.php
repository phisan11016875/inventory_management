<?php
// borrowings.php - หน้ารายการยืม-คืนพัสดุ/ครุภัณฑ์
require_once 'config.php';
requireLogin();

$page_title = "จัดการการยืม-คืนพัสดุ";
$page_subtitle = "รายการยืม-คืนพัสดุ/ครุภัณฑ์ทั้งหมด";
$breadcrumb = [
    ['title' => 'จัดการการยืม-คืนพัสดุ', 'url' => 'borrowings.php', 'active' => true]
];

// ตัวกรองสถานะ
$status_filter = isset($_GET['status']) ? clean($conn, $_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// สร้างคำสั่ง SQL พื้นฐาน
$query = "SELECT b.*, i.item_code, i.name as item_name, u.fullname as approved_by_name
          FROM borrowings b
          LEFT JOIN items i ON b.item_id = i.id
          LEFT JOIN users u ON b.approved_by = u.id
          WHERE 1=1";

// เพิ่มเงื่อนไขการกรอง
if (!empty($status_filter)) {
    $query .= " AND b.status = '$status_filter'";
}

if (!empty($start_date)) {
    $query .= " AND DATE(b.borrow_date) >= '$start_date'";
}

if (!empty($end_date)) {
    $query .= " AND DATE(b.borrow_date) <= '$end_date'";
}

// เรียงลำดับข้อมูล
$query .= " ORDER BY b.borrow_date DESC";

// ดึงข้อมูลการยืม-คืน
$result = $conn->query($query);

// เริ่มต้น Output Buffering
ob_start();
include('header.php');
?>

<div class="box">
    <div class="box-header with-border">
        <h3 class="box-title">รายการยืม-คืนพัสดุ/ครุภัณฑ์ทั้งหมด</h3>
        <div class="box-tools">
            <a href="borrowing_add.php" class="btn btn-success btn-sm"><i class="fa fa-plus"></i> เพิ่มรายการยืม</a>
            <a href="report_borrowings.php" class="btn btn-info btn-sm"><i class="fa fa-print"></i> พิมพ์รายงาน</a>
            <a href="export_borrowings.php" class="btn btn-warning btn-sm"><i class="fa fa-file-excel"></i> ส่งออก Excel</a>
        </div>
    </div>
    <div class="box-body">
        <!-- ตัวกรองข้อมูล -->
        <div class="row">
            <div class="col-md-12">
                <form method="get" action="borrowings.php" class="form-inline mb-3">
                    <div class="form-group">
                        <label for="status">สถานะ:</label>
                        <select name="status" id="status" class="form-control">
                            <option value="">-- ทั้งหมด --</option>
                            <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>รออนุมัติ</option>
                            <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>อนุมัติแล้ว</option>
                            <option value="returned" <?php echo $status_filter == 'returned' ? 'selected' : ''; ?>>คืนแล้ว</option>
                            <option value="overdue" <?php echo $status_filter == 'overdue' ? 'selected' : ''; ?>>เกินกำหนด</option>
                            <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>ยกเลิก</option>
                        </select>
                    </div>
                    
                    <div class="form-group ml-2">
                        <label for="start_date">วันที่เริ่มต้น:</label>
                        <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo $start_date; ?>">
                    </div>
                    
                    <div class="form-group ml-2">
                        <label for="end_date">วันที่สิ้นสุด:</label>
                        <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo $end_date; ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary ml-2"><i class="fa fa-search"></i> กรอง</button>
                    <a href="borrowings.php" class="btn btn-default ml-2"><i class="fa fa-refresh"></i> รีเซ็ต</a>
                </form>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-striped dataTable">
                <thead>
                    <tr>
                        <th width="60">ลำดับ</th>
                        <th>รหัสยืม</th>
                        <th>รหัสพัสดุ</th>
                        <th>รายการ</th>
                        <th>ผู้ยืม</th>
                        <th>วันที่ยืม</th>
                        <th>กำหนดคืน</th>
                        <th>วันที่คืน</th>
                        <th>สถานะ</th>
                        <th width="150">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php $i = 1; ?>
                        <?php while ($borrowing = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo $borrowing['id']; ?></td>
                                <td><?php echo $borrowing['item_code']; ?></td>
                                <td><a href="item_detail.php?id=<?php echo $borrowing['item_id']; ?>"><?php echo $borrowing['item_name']; ?></a></td>
                                <td><?php echo $borrowing['borrower_name'] . ($borrowing['borrower_department'] ? ' (' . $borrowing['borrower_department'] . ')' : ''); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($borrowing['borrow_date'])); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($borrowing['expected_return_date'])); ?></td>
                                <td><?php echo $borrowing['actual_return_date'] ? date('d/m/Y', strtotime($borrowing['actual_return_date'])) : '-'; ?></td>
                                <td>
                                    <?php if ($borrowing['status'] == 'pending'): ?>
                                        <span class="label label-warning">รออนุมัติ</span>
                                    <?php elseif ($borrowing['status'] == 'approved'): ?>
                                        <span class="label label-info">อนุมัติแล้ว</span>
                                    <?php elseif ($borrowing['status'] == 'returned'): ?>
                                        <span class="label label-success">คืนแล้ว</span>
                                    <?php elseif ($borrowing['status'] == 'overdue'): ?>
                                        <span class="label label-danger">เกินกำหนด</span>
                                    <?php elseif ($borrowing['status'] == 'cancelled'): ?>
                                        <span class="label label-default">ยกเลิก</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="borrowing_detail.php?id=<?php echo $borrowing['id']; ?>" class="btn btn-info btn-xs"><i class="fa fa-eye"></i> ดู</a>
                                        
                                        <?php if ($borrowing['status'] == 'pending'): ?>
                                            <!-- ปุ่มอนุมัติ (เฉพาะสถานะรออนุมัติ) -->
                                            <button type="button" class="btn btn-success btn-xs btn-approve" data-id="<?php echo $borrowing['id']; ?>"><i class="fa fa-check"></i> อนุมัติ</button>
                                        <?php endif; ?>
                                        
                                        <?php if ($borrowing['status'] == 'approved' || $borrowing['status'] == 'overdue'): ?>
                                            <!-- ปุ่มบันทึกการคืน (เฉพาะสถานะอนุมัติแล้วหรือเกินกำหนด) -->
                                            <a href="borrowing_return.php?id=<?php echo $borrowing['id']; ?>" class="btn btn-primary btn-xs"><i class="fa fa-undo"></i> คืน</a>
                                        <?php endif; ?>
                                        
                                        <?php if ($borrowing['status'] == 'pending'): ?>
                                            <!-- ปุ่มยกเลิก (เฉพาะสถานะรออนุมัติ) -->
                                            <button type="button" class="btn btn-danger btn-xs btn-cancel" data-id="<?php echo $borrowing['id']; ?>"><i class="fa fa-ban"></i> ยกเลิก</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center">ไม่พบข้อมูลการยืม-คืนพัสดุ</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- JavaScript สำหรับการจัดการการยืม-คืน -->
<script>
$(document).ready(function() {
    // อนุมัติการยืม
    $('.btn-approve').click(function() {
        var borrowingId = $(this).data('id');
        
        Swal.fire({
            title: 'ยืนยันการอนุมัติ',
            text: 'คุณต้องการอนุมัติการยืมรายการนี้ใช่หรือไม่?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3c8dbc',
            cancelButtonColor: '#d33',
            confirmButtonText: 'ใช่, อนุมัติ',
            cancelButtonText: 'ยกเลิก'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'borrowing_approve.php?id=' + borrowingId;
            }
        });
    });
    
    // ยกเลิกการยืม
    $('.btn-cancel').click(function() {
        var borrowingId = $(this).data('id');
        
        Swal.fire({
            title: 'ยืนยันการยกเลิก',
            text: 'คุณต้องการยกเลิกการยืมรายการนี้ใช่หรือไม่?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'ใช่, ยกเลิก',
            cancelButtonText: 'ไม่ใช่'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'borrowing_cancel.php?id=' + borrowingId;
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
// borrowing_add.php - หน้าเพิ่มรายการยืมพัสดุ/ครุภัณฑ์
require_once 'config.php';
requireLogin();

$page_title = "เพิ่มรายการยืม";
$page_subtitle = "เพิ่มรายการยืมพัสดุ/ครุภัณฑ์ใหม่";
$breadcrumb = [
    ['title' => 'จัดการการยืม-คืนพัสดุ', 'url' => 'borrowings.php', 'active' => false],
    ['title' => 'เพิ่มรายการยืม', 'url' => 'borrowing_add.php', 'active' => true]
];

// ดึงข้อมูลพัสดุที่พร้อมใช้งาน
$items_query = "SELECT id, item_code, name, category_id, location FROM items WHERE status = 'available' ORDER BY name ASC";
$items = $conn->query($items_query);

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // รับค่าจากฟอร์ม
    $item_id = (int)$_POST['item_id'];
    $borrower_name = clean($conn, $_POST['borrower_name']);
    $borrower_department = clean($conn, $_POST['borrower_department']);
    $borrower_contact = clean($conn, $_POST['borrower_contact']);
    $borrow_date = $_POST['borrow_date'];
    $expected_return_date = $_POST['expected_return_date'];
    $notes = clean($conn, $_POST['notes']);
    
    // ตรวจสอบข้อผิดพลาด
    $errors = [];
    
    // ตรวจสอบว่ากรอกข้อมูลสำคัญครบหรือไม่
    if (empty($item_id)) $errors[] = "กรุณาเลือกพัสดุ/ครุภัณฑ์";
    if (empty($borrower_name)) $errors[] = "กรุณากรอกชื่อผู้ยืม";
    if (empty($borrow_date)) $errors[] = "กรุณาระบุวันที่ยืม";
    if (empty($expected_return_date)) $errors[] = "กรุณาระบุวันที่คาดว่าจะคืน";
    
    // ตรวจสอบวันที่
    if (strtotime($expected_return_date) < strtotime($borrow_date)) {
        $errors[] = "วันที่คาดว่าจะคืนต้องไม่น้อยกว่าวันที่ยืม";
    }
    
    // ตรวจสอบสถานะพัสดุว่าพร้อมใช้งานหรือไม่
    $item_check = "SELECT status FROM items WHERE id = $item_id";
    $item_result = $conn->query($item_check);
    if ($item_result->num_rows > 0) {
        $item = $item_result->fetch_assoc();
        if ($item['status'] != 'available') {
            $errors[] = "พัสดุ/ครุภัณฑ์นี้ไม่พร้อมให้ยืม (สถานะปัจจุบัน: {$item['status']})";
        }
    } else {
        $errors[] = "ไม่พบข้อมูลพัสดุ/ครุภัณฑ์";
    }
    
    // ถ้าไม่มีข้อผิดพลาด ให้บันทึกข้อมูล
    if (empty($errors)) {
        // บันทึกข้อมูลการยืม
        $query = "INSERT INTO borrowings (item_id, borrower_name, borrower_department, borrower_contact, 
                 borrow_date, expected_return_date, notes) 
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("issssss", $item_id, $borrower_name, $borrower_department, $borrower_contact, 
                         $borrow_date, $expected_return_date, $notes);
        
        if ($stmt->execute()) {
            $borrowing_id = $stmt->insert_id;
            
            // บันทึกประวัติกิจกรรม
            logActivity($conn, $_SESSION['user_id'], 'create_borrowing', "เพิ่มรายการยืมใหม่: $borrower_name (รหัสยืม: $borrowing_id)");
            
            // หากเป็นแอดมิน จะอนุมัติทันที
            if (isAdmin()) {
                // อัปเดตสถานะการยืมเป็นอนุมัติแล้ว
                $update_borrowing = "UPDATE borrowings SET status = 'approved', approved_by = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_borrowing);
                $user_id = $_SESSION['user_id'];
                $update_stmt->bind_param("ii", $user_id, $borrowing_id);
                $update_stmt->execute();
                
                // อัปเดตสถานะพัสดุเป็นถูกยืม
                $update_item = "UPDATE items SET status = 'borrowed' WHERE id = ?";
                $update_item_stmt = $conn->prepare($update_item);
                $update_item_stmt->bind_param("i", $item_id);
                $update_item_stmt->execute();
                
                // บันทึกประวัติกิจกรรม
                logActivity($conn, $_SESSION['user_id'], 'approve_borrowing', "อนุมัติการยืมอัตโนมัติ (รหัสยืม: $borrowing_id)");
                
                $update_stmt->close();
                $update_item_stmt->close();
            }
            
            // แสดงข้อความสำเร็จและกลับไปหน้ารายการยืม
            redirectWithAlert('borrowings.php', 'success', 'เพิ่มรายการยืมเรียบร้อยแล้ว' . (isAdmin() ? ' และอนุมัติแล้ว' : ''));
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
        <h3 class="box-title">เพิ่มรายการยืมพัสดุ/ครุภัณฑ์</h3>
    </div>
    
    <form action="borrowing_add.php" method="post" class="form-horizontal">
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
                <label for="item_id" class="col-sm-2 control-label">เลือกพัสดุ/ครุภัณฑ์ *</label>
                <div class="col-sm-10">
                    <select class="form-control" id="item_id" name="item_id" required>
                        <option value="">-- เลือกพัสดุ/ครุภัณฑ์ --</option>
                        <?php while ($item = $items->fetch_assoc()): ?>
                            <option value="<?php echo $item['id']; ?>" <?php echo (isset($_POST['item_id']) && $_POST['item_id'] == $item['id']) ? 'selected' : ''; ?>>
                                <?php echo $item['item_code'] . ' - ' . $item['name'] . ' (' . $item['location'] . ')'; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="borrower_name" class="col-sm-2 control-label">ชื่อผู้ยืม *</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="borrower_name" name="borrower_name" placeholder="ชื่อ-นามสกุลผู้ยืม" value="<?php echo isset($_POST['borrower_name']) ? $_POST['borrower_name'] : ''; ?>" required>
                </div>
                
                <label for="borrower_department" class="col-sm-2 control-label">หน่วยงาน/แผนก</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="borrower_department" name="borrower_department" placeholder="หน่วยงานหรือแผนกของผู้ยืม" value="<?php echo isset($_POST['borrower_department']) ? $_POST['borrower_department'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="borrower_contact" class="col-sm-2 control-label">ติดต่อผู้ยืม</label>
                <div class="col-sm-4">
                    <input type="text" class="form-control" id="borrower_contact" name="borrower_contact" placeholder="เบอร์โทรหรืออีเมลผู้ยืม" value="<?php echo isset($_POST['borrower_contact']) ? $_POST['borrower_contact'] : ''; ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="borrow_date" class="col-sm-2 control-label">วันที่ยืม *</label>
                <div class="col-sm-4">
                    <input type="date" class="form-control" id="borrow_date" name="borrow_date" value="<?php echo isset($_POST['borrow_date']) ? $_POST['borrow_date'] : date('Y-m-d'); ?>" required>
                </div>
                
                <label for="expected_return_date" class="col-sm-2 control-label">วันที่คาดว่าจะคืน *</label>
                <div class="col-sm-4">