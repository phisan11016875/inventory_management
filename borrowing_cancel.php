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
    header('Location: borrowings.php?error=ไม่พบรหัสการยืมที่ต้องการยกเลิก');
    exit();
}

$borrowing_id = $_GET['id'];

// ดึงข้อมูลการยืม
$sql = "SELECT b.*, CONCAT(u.first_name, ' ', u.last_name) as borrower_name
        FROM borrowings b
        JOIN users u ON b.borrower_id = u.user_id
        WHERE b.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$borrowing) {
    header('Location: borrowings.php?error=ไม่พบข้อมูลการยืมที่ต้องการยกเลิก');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินหรือเจ้าของรายการเท่านั้นที่สามารถยกเลิกได้)
if ($_SESSION['role'] != 'admin' && $_SESSION['user_id'] != $borrowing['borrower_id']) {
    header('Location: borrowings.php?error=คุณไม่มีสิทธิ์ในการยกเลิกการยืมนี้');
    exit();
}

// ตรวจสอบว่าสถานะเป็น pending หรือไม่
if ($borrowing['status'] != 'pending') {
    header('Location: borrowing_detail.php?id=' . $borrowing_id . '&error=รายการยืมนี้ไม่สามารถยกเลิกได้เนื่องจากไม่อยู่ในสถานะรออนุมัติ');
    exit();
}

// ดึงรายการพัสดุที่ยืม
$sql = "SELECT bi.*, i.item_code, i.item_name, i.unit 
        FROM borrowing_items bi
        JOIN items i ON bi.item_id = i.item_id
        WHERE bi.borrowing_id = :borrowing_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':borrowing_id', $borrowing_id);
$stmt->execute();
$borrowing_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ตรวจสอบการส่งฟอร์ม
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // ตรวจสอบเหตุผลในการยกเลิก
        $cancel_reason = isset($_POST['cancel_reason']) ? trim($_POST['cancel_reason']) : '';
        
        if (empty($cancel_reason)) {
            throw new Exception("กรุณาระบุเหตุผลในการยกเลิก");
        }
        
        // เริ่ม transaction
        $conn->beginTransaction();
        
        // อัปเดตสถานะเป็น canceled
        $sql = "UPDATE borrowings SET 
                status = 'canceled',
                note = CONCAT(IFNULL(note, ''), '\n\nเหตุผลที่ยกเลิก: ', :cancel_reason),
                updated_by = :updated_by,
                updated_at = NOW()
                WHERE borrowing_id = :borrowing_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':cancel_reason', $cancel_reason);
        $stmt->bindParam(':updated_by', $_SESSION['user_id']);
        $stmt->bindParam(':borrowing_id', $borrowing_id);
        $stmt->execute();
        
        // บันทึกล็อกการยกเลิก
        $log_message = "ยกเลิกการยืม: " . $borrowing['borrowing_code'] . " เหตุผล: " . $cancel_reason;
        $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'cancel_borrowing', :details, NOW())";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':user_id', $_SESSION['user_id']);
        $stmt->bindParam(':details', $log_message);
        $stmt->execute();
        
        // ยืนยัน transaction
        $conn->commit();
        
        // ข้อความแจ้งเตือน
        $success_message = "ยกเลิกการยืมเรียบร้อยแล้ว";
        
        // กลับไปยังหน้ารายละเอียดการยืม
        header("Location: borrowing_detail.php?id={$borrowing_id}&success=" . urlencode($success_message));
        exit();
        
    } catch (Exception $e) {
        // ยกเลิก transaction เมื่อเกิดข้อผิดพลาด
        $conn->rollBack();
        $error_message = $e->getMessage();
    }
}

// แสดงผลหน้าเว็บ
include 'header.php';
?>