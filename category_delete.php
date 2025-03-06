<?php
// เริ่มต้น session
session_start();

// ตรวจสอบการล็อกอิน
include 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// ตรวจสอบสิทธิ์การใช้งาน (เฉพาะแอดมินเท่านั้นที่สามารถลบได้)
if ($_SESSION['role'] != 'admin') {
    header('Location: index.php?error=คุณไม่มีสิทธิ์ในการลบหมวดหมู่');
    exit();
}

// ตรวจสอบว่ามีการส่ง ID มาหรือไม่
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: categories.php?error=ไม่พบรหัสหมวดหมู่ที่ต้องการลบ');
    exit();
}

$category_id = $_GET['id'];

// ดึงข้อมูลหมวดหมู่ที่ต้องการลบ
$sql = "SELECT * FROM categories WHERE category_id = :category_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id);
$stmt->execute();
$category = $stmt->fetch(PDO::FETCH_ASSOC);

// ตรวจสอบว่าพบข้อมูลหรือไม่
if (!$category) {
    header('Location: categories.php?error=ไม่พบหมวดหมู่ที่ต้องการลบ');
    exit();
}

// ตรวจสอบจำนวนพัสดุในหมวดหมู่
$sql = "SELECT COUNT(*) FROM items WHERE category_id = :category_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id);
$stmt->execute();
$item_count = $stmt->fetchColumn();

// ตรวจสอบว่ามีการยืนยันการลบหรือไม่
if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] == 1) {
    try {
        // ถ้ามีพัสดุในหมวดหมู่นี้และไม่ได้เลือกตัวเลือกโอนย้าย
        if ($item_count > 0 && (!isset($_POST['transfer_items']) || empty($_POST['new_category_id']))) {
            throw new Exception("กรุณาเลือกหมวดหมู่ปลายทางสำหรับการโอนย้ายพัสดุ/ครุภัณฑ์ก่อนลบหมวดหมู่นี้");
        }

        // เริ่ม transaction
        $conn->beginTransaction();

        // ถ้ามีพัสดุในหมวดหมู่นี้และเลือกตัวเลือกโอนย้าย
        if ($item_count > 0 && isset($_POST['transfer_items']) && !empty($_POST['new_category_id'])) {
            $new_category_id = $_POST['new_category_id'];
            
            // ตรวจสอบว่าหมวดหมู่ปลายทางมีอยู่จริง
            $sql = "SELECT COUNT(*) FROM categories WHERE category_id = :new_category_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':new_category_id', $new_category_id);
            $stmt->execute();
            if ($stmt->fetchColumn() == 0) {
                throw new Exception("หมวดหมู่ปลายทางสำหรับการโอนย้ายไม่ถูกต้อง");
            }
            
            // โอนย้ายพัสดุไปยังหมวดหมู่ใหม่
            $sql = "UPDATE items SET 
                    category_id = :new_category_id,
                    updated_by = :updated_by,
                    updated_at = NOW()
                    WHERE category_id = :category_id";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':new_category_id', $new_category_id);
            $stmt->bindParam(':updated_by', $_SESSION['user_id']);
            $stmt->bindParam(':category_id', $category_id);
            $stmt->execute();
            
            // บันทึกล็อกการโอนย้าย
            $log_message = "โอนย้ายพัสดุ/ครุภัณฑ์ " . $item_count . " รายการ จากหมวดหมู่: " . $category['category_name'] . " ไปยังหมวดหมู่ ID: " . $new_category_id;
            $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'transfer_items', :details, NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->bindParam(':details', $log_message);
            $stmt->execute();
        }
        
        // ลบหมวดหมู่
        $sql = "DELETE FROM categories WHERE category_id = :category_id";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':category_id', $category_id);
        $stmt->execute();
        
         // บันทึกล็อกการลบหมวดหมู่
         $log_message = "ลบหมวดหมู่: " . $category['category_name'] . " (ID: " . $category_id . ")";
         $sql = "INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (:user_id, 'delete_category', :details, NOW())";
         $stmt = $conn->prepare($sql);
         $stmt->bindParam(':user_id', $_SESSION['user_id']);
         $stmt->bindParam(':details', $log_message);
         $stmt->execute();
        
        // ยืนยัน transaction
        $conn->commit();
        
        // แสดงข้อความสำเร็จและกลับไปยังหน้ารายการหมวดหมู่
        header('Location: categories.php?success=ลบหมวดหมู่เรียบร้อยแล้ว');
        exit();
        
    } catch (Exception $e) {
        // ยกเลิก transaction เมื่อเกิดข้อผิดพลาด
        $conn->rollBack();
        $error_message = $e->getMessage();
    }
}

// ดึงข้อมูลหมวดหมู่อื่นๆ ทั้งหมดเพื่อใช้ในการโอนย้าย
$sql = "SELECT * FROM categories WHERE category_id != :category_id ORDER BY category_name";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':category_id', $category_id);
$stmt->execute();
$other_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// แสดงผลหน้าเว็บ
include 'header.php';
?>