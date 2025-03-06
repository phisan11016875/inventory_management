<?php
// เริ่มต้น session
session_start();

// เรียกใช้ไฟล์ config.php
require_once 'config.php';

// ตรวจสอบการล็อกอิน หากยังไม่ล็อกอินให้ redirect ไปหน้า login.php
if (!isset($_SESSION['user_id'])) {
    redirect(SITE_URL . '/login.php');
}

// เรียกใช้ header
include 'header.php';
?>

<!-- เนื้อหาหน้า Dashboard -->
<div class="content-wrapper">
    <div class="content-header">
        <div class="container-fluid">
            <div class="row mb-2">
                <div class="col-sm-6">
                    <h1 class="m-0">แดชบอร์ด</h1>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-right">
                        <li class="breadcrumb-item"><a href="#">หน้าหลัก</a></li>
                        <li class="breadcrumb-item active">แดชบอร์ด</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="container-fluid">
            <!-- Small boxes (Stat box) -->
            <div class="row">
                <!-- จำนวนพัสดุ/ครุภัณฑ์ทั้งหมด -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <?php
                            $stmt = $conn->query("SELECT COUNT(*) as total FROM items");
                            $total_items = $stmt->fetch()['total'];
                            ?>
                            <h3><?php echo $total_items; ?></h3>
                            <p>พัสดุ/ครุภัณฑ์ทั้งหมด</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-bag"></i>
                        </div>
                        <a href="items.php" class="small-box-footer">ข้อมูลเพิ่มเติม <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                
                <!-- จำนวนพัสดุ/ครุภัณฑ์ที่ถูกยืม -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <?php
                            $stmt = $conn->query("SELECT COUNT(*) as total FROM items WHERE status = 'borrowed'");
                            $borrowed_items = $stmt->fetch()['total'];
                            ?>
                            <h3><?php echo $borrowed_items; ?></h3>
                            <p>พัสดุ/ครุภัณฑ์ที่ถูกยืม</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-stats-bars"></i>
                        </div>
                        <a href="items.php?status=borrowed" class="small-box-footer">ข้อมูลเพิ่มเติม <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                
                <!-- จำนวนผู้ใช้งาน -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-warning">
                        <div class="inner">
                            <?php
                            $stmt = $conn->query("SELECT COUNT(*) as total FROM users");
                            $total_users = $stmt->fetch()['total'];
                            ?>
                            <h3><?php echo $total_users; ?></h3>
                            <p>ผู้ใช้งานทั้งหมด</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-person-add"></i>
                        </div>
                        <a href="users.php" class="small-box-footer">ข้อมูลเพิ่มเติม <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
                
                <!-- จำนวนการยืม-คืนที่รออนุมัติ -->
                <div class="col-lg-3 col-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <?php
                            $stmt = $conn->query("SELECT COUNT(*) as total FROM borrowings WHERE status = 'pending'");
                            $pending_borrowings = $stmt->fetch()['total'];
                            ?>
                            <h3><?php echo $pending_borrowings; ?></h3>
                            <p>การยืมที่รออนุมัติ</p>
                        </div>
                        <div class="icon">
                            <i class="ion ion-pie-graph"></i>
                        </div>
                        <a href="borrowings.php?status=pending" class="small-box-footer">ข้อมูลเพิ่มเติม <i class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>
            </div>
            
            <!-- รายการยืมล่าสุด -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">รายการยืมล่าสุด</h3>
                        </div>
                        <div class="card-body table-responsive p-0">
                            <table class="table table-hover text-nowrap">
                                <thead>
                                    <tr>
                                        <th>รหัสการยืม</th>
                                        <th>พัสดุ/ครุภัณฑ์</th>
                                        <th>ผู้ยืม</th>
                                        <th>วันที่ยืม</th>
                                        <th>กำหนดคืน</th>
                                        <th>สถานะ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $conn->query("
                                        SELECT b.*, i.name as item_name, u.fullname as borrower_name 
                                        FROM borrowings b
                                        JOIN items i ON b.item_id = i.id
                                        JOIN users u ON b.user_id = u.id
                                        ORDER BY b.created_at DESC
                                        LIMIT 5
                                    ");
                                    $recent_borrowings = $stmt->fetchAll();
                                    
                                    foreach($recent_borrowings as $borrowing): 
                                        $status_class = borrowing_status_color($borrowing['status']);
                                    ?>
                                    <tr>
                                        <td><?php echo $borrowing['id']; ?></td>
                                        <td><?php echo xss_clean($borrowing['item_name']); ?></td>
                                        <td><?php echo xss_clean($borrowing['borrower_name']); ?></td>
                                        <td><?php echo thai_date($borrowing['borrow_date']); ?></td>
                                        <td><?php echo thai_date($borrowing['due_date']); ?></td>
                                        <td>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo borrowing_status_text($borrowing['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    
                                    <?php if(count($recent_borrowings) == 0): ?>
                                    <tr>
                                        <td colspan="6" class="text-center">ไม่พบรายการยืม</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php
// เรียกใช้ footer
include 'footer.php';
?>