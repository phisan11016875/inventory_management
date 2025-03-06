<?php
/**
 * ไฟล์การตั้งค่าระบบบริหารจัดการพัสดุ/ครุภัณฑ์
 * ประกอบด้วยการตั้งค่าการเชื่อมต่อฐานข้อมูลและค่าคงที่ต่างๆ ของระบบ
 */

// การตั้งค่า timezone
date_default_timezone_set('Asia/Bangkok');

// การตั้งค่าการแสดงผลข้อผิดพลาด (ควรปิดในโหมด production)
// error_reporting(0);
// ini_set('display_errors', 0);

// แสดงข้อผิดพลาดในโหมด development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ข้อมูลการเชื่อมต่อฐานข้อมูล
define('DB_HOST', 'localhost');
define('DB_NAME', 'inventory_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8');

// ค่าคงที่ของระบบ
define('SITE_NAME', 'ระบบบริหารจัดการพัสดุ/ครุภัณฑ์');
define('SITE_URL', 'http://localhost/inventory_management');
define('UPLOAD_PATH', __DIR__ . '/uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// การตั้งค่า session
ini_set('session.gc_maxlifetime', 28800); // 8 ชั่วโมง
ini_set('session.cookie_lifetime', 0);
session_cache_limiter('private');

// เชื่อมต่อฐานข้อมูลด้วย PDO
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("การเชื่อมต่อฐานข้อมูลล้มเหลว: " . $e->getMessage());
}

/**
 * ฟังก์ชันสำหรับกรองข้อมูลเพื่อป้องกันการโจมตี XSS
 * 
 * @param string $data ข้อมูลที่ต้องการกรอง
 * @return string ข้อมูลที่ผ่านการกรองแล้ว
 */
function xss_clean($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/**
 * ฟังก์ชันสำหรับสร้างการแจ้งเตือน
 * 
 * @param string $message ข้อความแจ้งเตือน
 * @param string $type ประเภทของการแจ้งเตือน (success, danger, warning, info)
 * @return string HTML ของการแจ้งเตือน
 */
function alert($message, $type = 'info') {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">
                ' . $message . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>';
}

/**
 * ฟังก์ชันสำหรับเปลี่ยนเส้นทางไปยัง URL ที่กำหนด
 * 
 * @param string $url URL ที่ต้องการเปลี่ยนเส้นทาง
 * @return void
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * ฟังก์ชันสำหรับตรวจสอบว่าผู้ใช้ล็อกอินหรือไม่
 * 
 * @return bool true ถ้าล็อกอินแล้ว, false ถ้ายังไม่ล็อกอิน
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * ฟังก์ชันสำหรับตรวจสอบว่าผู้ใช้เป็น admin หรือไม่
 * 
 * @return bool true ถ้าเป็น admin, false ถ้าไม่ใช่
 */
function is_admin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * ฟังก์ชันสำหรับตรวจสอบว่าผู้ใช้เป็นเจ้าหน้าที่หรือไม่
 * 
 * @return bool true ถ้าเป็นเจ้าหน้าที่, false ถ้าไม่ใช่
 */
function is_staff() {
    return isset($_SESSION['user_role']) && ($_SESSION['user_role'] == 'staff' || $_SESSION['user_role'] == 'admin');
}

/**
 * ฟังก์ชันสำหรับแสดงวันที่ในรูปแบบไทย
 * 
 * @param string $date วันที่ในรูปแบบ Y-m-d
 * @param bool $with_time แสดงเวลาด้วยหรือไม่
 * @return string วันที่ในรูปแบบไทย
 */
function thai_date($date, $with_time = false) {
    if (empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') {
        return '-';
    }
    
    $thai_months = [
        1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน', 5 => 'พฤษภาคม', 6 => 'มิถุนายน',
        7 => 'กรกฎาคม', 8 => 'สิงหาคม', 9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
    ];
    
    $date_parts = date_parse($date);
    
    $thai_year = $date_parts['year'] + 543;
    $month = $thai_months[$date_parts['month']];
    $day = $date_parts['day'];
    
    $formatted_date = $day . ' ' . $month . ' ' . $thai_year;
    
    if ($with_time) {
        $formatted_date .= ' ' . $date_parts['hour'] . ':' . 
                           str_pad($date_parts['minute'], 2, '0', STR_PAD_LEFT) . ' น.';
    }
    
    return $formatted_date;
}

/**
 * ฟังก์ชันสำหรับแปลงสถานะของพัสดุ/ครุภัณฑ์เป็นข้อความภาษาไทย
 * 
 * @param string $status สถานะในระบบ
 * @return string สถานะภาษาไทย
 */
function item_status_text($status) {
    switch ($status) {
        case 'available':
            return 'พร้อมใช้งาน';
        case 'borrowed':
            return 'ถูกยืม';
        case 'repair':
            return 'ซ่อมบำรุง';
        case 'disposed':
            return 'จำหน่ายแล้ว';
        default:
            return $status;
    }
}

/**
 * ฟังก์ชันสำหรับแปลงสถานะของการยืม-คืนเป็นข้อความภาษาไทย
 * 
 * @param string $status สถานะในระบบ
 * @return string สถานะภาษาไทย
 */
function borrowing_status_text($status) {
    switch ($status) {
        case 'pending':
            return 'รออนุมัติ';
        case 'approved':
            return 'อนุมัติแล้ว';
        case 'returned':
            return 'คืนแล้ว';
        case 'cancelled':
            return 'ยกเลิก';
        case 'overdue':
            return 'เกินกำหนด';
        default:
            return $status;
    }
}

/**
 * ฟังก์ชันสำหรับแปลงสถานะของการยืม-คืนเป็นสีของ Bootstrap
 * 
 * @param string $status สถานะในระบบ
 * @return string รหัสสี Bootstrap
 */
function borrowing_status_color($status) {
    switch ($status) {
        case 'pending':
            return 'warning';
        case 'approved':
            return 'primary';
        case 'returned':
            return 'success';
        case 'cancelled':
            return 'secondary';
        case 'overdue':
            return 'danger';
        default:
            return 'info';
    }
}
?>