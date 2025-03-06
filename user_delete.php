<?php
// ไฟล์สำหรับลบผู้ใช้
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] != 'admin') {
    // ถ้าไม่ใช่ admin ให้เปลี่ยนเส้นทางไปหน้าหลัก
    header('Location: index.php');
    exit;
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ไม่พบข้อมูลผู้ใช้ที่ต้องการลบ";
    header('Location: users.php');
    exit;
}

$user_id = $_GET['id'];

try {
    // ตรวจสอบว่าผู้ใช้นี้มีรายการยืมที่ยังไม่คืนหรือไม่
    $stmt = $conn->prepare("SELECT COUNT(*) FROM borrowings WHERE user_id = ? AND status IN ('pending', 'approved')");
    $stmt->execute([$user_id]);
    $hasPendingBorrowings = $stmt->fetchColumn() > 0;
    
    if ($hasPendingBorrowings) {
        $_SESSION['error'] = "ไม่สามารถลบผู้ใช้นี้ได้ เนื่องจากมีรายการยืมที่ยังไม่คืน";
        header('Location: users.php');
        exit;
    }
    
    // ตรวจสอบว่าไม่ใช่การลบตัวเอง
    if ($_SESSION['user_id'] == $user_id) {
        $_SESSION['error'] = "ไม่สามารถลบบัญชีของตัวเองได้";
        header('Location: users.php');
        exit;
    }
    
    // ลบผู้ใช้
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if ($stmt->rowCount() > 0) {
        $_SESSION['success'] = "ลบผู้ใช้เรียบร้อยแล้ว";
    } else {
        $_SESSION['error'] = "ไม่พบผู้ใช้ที่ต้องการลบ";
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
}

// เปลี่ยนเส้นทางกลับไปหน้าจัดการผู้ใช้
header('Location: users.php');
exit;
?>