<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินเท่านั้น)
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการเข้าถึงหน้านี้');
    exit();
}

// แสดงข้อความแจ้งเตือน
$success_message = isset($_GET['success']) ? $_GET['success'] : '';
$error_message = isset($_GET['error']) ? $_GET['error'] : '';

// แสดงผลหน้าเว็บ
include 'header.php';
?>

<div class="container-fluid mt-4">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4><i class="fas fa-users me-2"></i>จัดการผู้ใช้</h4>
            <a href="user_add.php" class="btn btn-success">
                <i class="fas fa-user-plus me-1"></i> เพิ่มผู้ใช้ใหม่
            </a>
        </div>
        <div class="card-body">
            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-1"></i> <?= $success_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-1"></i> <?= $error_message ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table id="usersTable" class="table table-striped table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%">รหัส</th>
                            <th width="15%">ชื่อผู้ใช้</th>
                            <th width="20%">ชื่อ-นามสกุล</th>
                            <th width="20%">อีเมล/เบอร์โทร</th>
                            <th width="10%">แผนก/ตำแหน่ง</th>
                            <th width="10%">บทบาท</th>
                            <th width="10%">สถานะ</th>
                            <th width="10%">จัดการ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $sql = "SELECT * FROM users ORDER BY user_id DESC";
                            $stmt = $conn->prepare($sql);
                            $stmt->execute();
                            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($users) > 0) {
                                foreach ($users as $user) {
                                    // กำหนดสีของป้ายบทบาท
                                    $role_class = '';
                                    $role_text = '';
                                    switch ($user['role']) {
                                        case 'admin':
                                            $role_class = 'bg-danger';
                                            $role_text = 'ผู้ดูแลระบบ';
                                            break;
                                        case 'staff':
                                            $role_class = 'bg-primary';
                                            $role_text = 'เจ้าหน้าที่';
                                            break;
                                        case 'user':
                                            $role_class = 'bg-info';
                                            $role_text = 'ผู้ใช้งานทั่วไป';
                                            break;
                                        case 'viewer':
                                            $role_class = 'bg-secondary';
                                            $role_text = 'ผู้ดูข้อมูล';
                                            break;
                                    }
                                    
                                    // สถานะผู้ใช้
                                    $status_class = $user['active'] ? 'bg-success' : 'bg-warning text-dark';
                                    $status_text = $user['active'] ? 'เปิดใช้งาน' : 'ปิดใช้งาน';
                                    
                                    echo '<tr>';
                                    echo '<td>' . $user['user_id'] . '</td>';
                                    echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                                    echo '<td>' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '</td>';
                                    echo '<td>';
                                    echo htmlspecialchars($user['email']) . '<br>';
                                    if (!empty($user['phone'])) {
                                        echo '<small class="text-muted"><i class="fas fa-phone me-1"></i>' . htmlspecialchars($user['phone']) . '</small>';
                                    }
                                    echo '</td>';
                                    echo '<td>';
                                    if (!empty($user['department'])) {
                                        echo htmlspecialchars($user['department']) . '<br>';
                                    }
                                    if (!empty($user['position'])) {
                                        echo '<small class="text-muted">' . htmlspecialchars($user['position']) . '</small>';
                                    }
                                    echo '</td>';
                                    echo '<td><span class="badge ' . $role_class . '">' . $role_text . '</span></td>';
                                    echo '<td><span class="badge ' . $status_class . '">' . $status_text . '</span></td>';
                                    echo '<td>';
                                    echo '<a href="user_edit.php?id=' . $user['user_id'] . '" class="btn btn-primary btn-sm mb-1"><i class="fas fa-edit"></i> แก้ไข</a> ';
                                    
                                    // ไม่ให้ลบตัวเอง
                                    if ($user['user_id'] != $_SESSION['user_id']) {
                                        echo '<button class="btn btn-danger btn-sm delete-user" data-id="' . $user['user_id'] . '" 
                                              data-name="' . htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . '">
                                              <i class="fas fa-trash"></i> ลบ</button>';
                                    }
                                    
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } else {
                                echo '<tr><td colspan="8" class="text-center">ไม่พบข้อมูลผู้ใช้</td></tr>';
                            }
                        } catch (PDOException $e) {
                            echo '<tr><td colspan="8" class="text-center text-danger">เกิดข้อผิดพลาด: ' . $e->getMessage() . '</td></tr>';
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal ยืนยันการลบ -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>ยืนยันการลบผู้ใช้</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>คุณแน่ใจหรือไม่ที่ต้องการลบผู้ใช้ "<span id="userName"></span>"?</p>
                <p class="text-danger"><i class="fas fa-exclamation-circle me-1"></i>การลบข้อมูลนี้ไม่สามารถเรียกคืนได้</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i> ยกเลิก
                </button>
                <a href="#" id="deleteUserButton" class="btn btn-danger">
                    <i class="fas fa-trash me-1"></i> ยืนยันการลบ
                </a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ตั้งค่า DataTable
    $('#usersTable').DataTable({
        "language": {
            "lengthMenu": "แสดง _MENU_ รายการต่อหน้า",
            "zeroRecords": "ไม่พบข้อมูล",
            "info": "แสดงหน้า _PAGE_ จาก _PAGES_",
            "infoEmpty": "ไม่มีข้อมูล",
            "infoFiltered": "(กรองจากทั้งหมด _MAX_ รายการ)",
            "search": "ค้นหา:",
            "paginate": {
                "first": "หน้าแรก",
                "last": "หน้าสุดท้าย",
                "next": "ถัดไป",
                "previous": "ก่อนหน้า"
            }
        },
        "order": [[0, "desc"]], // เรียงตามรหัสผู้ใช้จากมากไปน้อย
        "pageLength": 10
    });
    
    // จัดการปุ่มลบ
    const deleteButtons = document.querySelectorAll('.delete-user');
    const deleteUserModal = new bootstrap.Modal(document.getElementById('deleteUserModal'));
    const userNameElement = document.getElementById('userName');
    const deleteUserButton = document.getElementById('deleteUserButton');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.getAttribute('data-id');
            const userName = this.getAttribute('data-name');
            
            userNameElement.textContent = userName;
            deleteUserButton.href = 'user_delete.php?id=' + userId;
            
            deleteUserModal.show();
        });
    });
    
    // ซ่อนการแจ้งเตือนอัตโนมัติหลังจาก 5 วินาที
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});
</script>

<?php include 'footer.php'; ?>