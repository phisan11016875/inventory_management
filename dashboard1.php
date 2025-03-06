

<?php
// แสดงส่วนท้ายของเว็บไซต์
include('footer.php');
// เก็บ Output HTML ทั้งหมด
$html_content = ob_get_clean();
// แสดงผล HTML
echo $html_content;
?><?php
// index.php - หน้าแดชบอร์ดหลักของระบบ
require_once 'config.php';
requireLogin();

$page_title = "แดชบอร์ด";
$page_subtitle = "สรุปข้อมูลพัสดุ/ครุภัณฑ์";

// นับจำนวนพัสดุแยกตามสถานะ
$query_items = "SELECT 
                COUNT(*) as total_items,
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available_items,
                COUNT(CASE WHEN status = 'borrowed' THEN 1 END) as borrowed_items,
                COUNT(CASE WHEN status = 'maintenance' THEN 1 END) as maintenance_items,
                COUNT(CASE WHEN status = 'disposed' THEN 1 END) as disposed_items,
                SUM(acquisition_cost) as total_cost
                FROM items";
$result_items = $conn->query($query_items);
$items_stats = $result_items->fetch_assoc();

// นับจำนวนรายการยืมที่ยังไม่ได้คืน
$query_borrowings = "SELECT COUNT(*) as pending_borrowings 
                    FROM borrowings 
                    WHERE status = 'approved' AND actual_return_date IS NULL";
$result_borrowings = $conn->query($query_borrowings);
$borrowings_stats = $result_borrowings->fetch_assoc();

// นับจำนวนรายการซ่อมบำรุงที่ยังไม่เสร็จ
$query_maintenance = "SELECT COUNT(*) as pending_maintenance 
                     FROM maintenance 
                     WHERE status IN ('scheduled', 'in_progress')";
$result_maintenance = $conn->query($query_maintenance);
$maintenance_stats = $result_maintenance->fetch_assoc();

// ดึงรายการยืมล่าสุด 5 รายการ
$query_recent_borrowings = "SELECT b.*, i.name as item_name, u.fullname as approved_by_name
                           FROM borrowings b
                           LEFT JOIN items i ON b.item_id = i.id
                           LEFT JOIN users u ON b.approved_by = u.id
                           ORDER BY b.borrow_date DESC
                           LIMIT 5";
$result_recent_borrowings = $conn->query($query_recent_borrowings);

// ดึงรายการพัสดุที่มีการยืมบ่อยที่สุด 5 รายการ
$query_top_borrowed = "SELECT i.id, i.name, COUNT(b.id) as borrow_count
                      FROM items i
                      JOIN borrowings b ON i.id = b.item_id
                      GROUP BY i.id
                      ORDER BY borrow_count DESC
                      LIMIT 5";
$result_top_borrowed = $conn->query($query_top_borrowed);

// ดึงพัสดุที่เพิ่มเข้ามาล่าสุด 5 รายการ
$query_latest_items = "SELECT i.*, c.name as category_name
                      FROM items i
                      LEFT JOIN categories c ON i.category_id = c.id
                      ORDER BY i.created_at DESC
                      LIMIT 5";
$result_latest_items = $conn->query($query_latest_items);

// เริ่มต้น Output Buffering เพื่อรวบรวม HTML
ob_start();
include('header.php');
?>

<!-- Info boxes -->
<div class="row">
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">พัสดุ/ครุภัณฑ์ทั้งหมด</span>
                <span class="info-box-number"><?php echo number_format($items_stats['total_items']); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-purple"><i class="fa fa-tools"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">อยู่ระหว่างซ่อมบำรุง</span>
                <span class="info-box-number"><?php echo number_format($items_stats['maintenance_items']); ?></span>
            </div>
        </div>
    </div>
</div>
<!-- /.row -->

<!-- สถิติเพิ่มเติม -->
<div class="row">
    <div class="col-md-8">
        <div class="box box-info">
            <div class="box-header with-border">
                <h3 class="box-title">รายการยืมล่าสุด</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table no-margin">
                        <thead>
                            <tr>
                                <th>รหัสยืม</th>
                                <th>รายการ</th>
                                <th>ผู้ยืม</th>
                                <th>วันที่ยืม</th>
                                <th>กำหนดคืน</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_recent_borrowings->num_rows > 0): ?>
                                <?php while ($borrowing = $result_recent_borrowings->fetch_assoc()): ?>
                                    <tr>
                                        <td><a href="borrowing_detail.php?id=<?php echo $borrowing['id']; ?>">#<?php echo $borrowing['id']; ?></a></td>
                                        <td><?php echo $borrowing['item_name']; ?></td>
                                        <td><?php echo $borrowing['borrower_name']; ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($borrowing['borrow_date'])); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($borrowing['expected_return_date'])); ?></td>
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
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">ไม่มีรายการยืม</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="box-footer clearfix">
                <a href="borrowings.php" class="btn btn-sm btn-info btn-flat pull-right">ดูรายการทั้งหมด</a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">สรุปมูลค่าพัสดุ/ครุภัณฑ์</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="info-box bg-aqua">
                    <span class="info-box-icon"><i class="fa fa-money-bill-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">มูลค่ารวมทั้งหมด</span>
                        <span class="info-box-number"><?php echo number_format($items_stats['total_cost'], 2); ?> บาท</span>
                    </div>
                </div>
                
                <div class="dashboard-box primary">
                    <h4>รายการที่รอการคืน</h4>
                    <p class="text-center" style="font-size: 24px;">
                        <strong><?php echo number_format($borrowings_stats['pending_borrowings']); ?></strong>
                        <small>รายการ</small>
                    </p>
                </div>
                
                <div class="dashboard-box warning">
                    <h4>รายการที่รอการซ่อมบำรุง</h4>
                    <p class="text-center" style="font-size: 24px;">
                        <strong><?php echo number_format($maintenance_stats['pending_maintenance']); ?></strong>
                        <small>รายการ</small>
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- /.row -->

<div class="row">
    <div class="col-md-6">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">พัสดุที่มีการยืมบ่อยที่สุด</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ลำดับ</th>
                                <th>รายการ</th>
                                <th>จำนวนครั้งที่ยืม</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_top_borrowed->num_rows > 0): ?>
                                <?php $i = 1; ?>
                                <?php while ($item = $result_top_borrowed->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $i++; ?></td>
                                        <td><a href="item_detail.php?id=<?php echo $item['id']; ?>"><?php echo $item['name']; ?></a></td>
                                        <td><?php echo $item['borrow_count']; ?> ครั้ง</td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="3" class="text-center">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="box box-warning">
            <div class="box-header with-border">
                <h3 class="box-title">พัสดุที่เพิ่มล่าสุด</h3>
                <div class="box-tools pull-right">
                    <button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
                </div>
            </div>
            <div class="box-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>รหัสพัสดุ</th>
                                <th>รายการ</th>
                                <th>ประเภท</th>
                                <th>สถานะ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result_latest_items->num_rows > 0): ?>
                                <?php while ($item = $result_latest_items->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo $item['item_code']; ?></td>
                                        <td><a href="item_detail.php?id=<?php echo $item['id']; ?>"><?php echo $item['name']; ?></a></td>
                                        <td><?php echo $item['category_name']; ?></td>
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
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">ไม่มีข้อมูล</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="box-footer clearfix">
                <a href="items.php" class="btn btn-sm btn-warning btn-flat pull-right">ดูรายการทั้งหมด</a>
            </div>
        </div>
    </div>
</div>
<!-- /.row -->
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">พร้อมใช้งาน</span>
                <span class="info-box-number"><?php echo number_format($items_stats['available_items']); ?></span>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6 col-xs-12">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-hand-holding"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">กำลังถูกยืม</span>
                <span class="info-box-number"><?php echo number_format($items_stats['borrowed_items']); ?></span>
            </div>