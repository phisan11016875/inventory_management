<?php
// ไฟล์สำหรับออกจากระบบ
session_start();

// ล้างข้อมูล session ทั้งหมด
$_SESSION = array();

// ลบคุกกี้ session ถ้ามี
if (ini_get("session.use_cookies")) {
    $params = session.cookie_params;
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// ทำลาย session
session_destroy();

// เปลี่ยนเส้นทางไปยังหน้าล็อกอิน
header("Location: login.php");
exit;
?>