<?php
// ไฟล์สำหรับส่งออกข้อมูลพัสดุ/ครุภัณฑ์เป็น Excel
session_start();
require_once 'config.php';

// ตรวจสอบการล็อกอิน
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] != 'admin' && $_SESSION['user_role'] != 'staff')) {
    header('Location: login.php');
    exit;
}

// ตรวจสอบว่ามีการติดตั้ง PhpSpreadsheet
if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
    $_SESSION['error'] = "ระบบไม่พบ library PhpSpreadsheet กรุณาติดตั้งก่อนใช้งาน";
    header('Location: items.php');
    exit;
}

// เรียกใช้ library PhpSpreadsheet
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// กำหนดเงื่อนไขการค้นหา
$search_term = isset($_GET['search']) ? $_GET['search'] : '';
$category_id = isset($_GET['category']) ? $_GET['category'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';

// สร้าง query พื้นฐาน
$sql = "SELECT i.*, c.name as category_name
        FROM items i
        LEFT JOIN categories c ON i.category_id = c.id
        WHERE 1=1";
$params = [];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search_term)) {
    $sql .= " AND (i.code LIKE ? OR i.name LIKE ? OR i.details LIKE ?)";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
    $params[] = "%$search_term%";
}

if (!empty($category_id)) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category_id;
}

if (!empty($status)) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY i.id DESC";

try {
    // ดึงข้อมูลพัสดุ/ครุภัณฑ์
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // สร้างไฟล์ Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // กำหนดชื่อ Sheet
    $sheet->setTitle("รายการพัสดุ/ครุภัณฑ์");
    
    // กำหนดส่วนหัวของตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'รหัสพัสดุ/ครุภัณฑ์',
        'C1' => 'ชื่อรายการ',
        'D1' => 'หมวดหมู่',
        'E1' => 'ราคา (บาท)',
        'F1' => 'วันที่ได้รับ',
        'G1' => 'สถานที่เก็บ',
        'H1' => 'สถานะ',
        'I1' => 'หมายเหตุ'
    ];
    
    foreach ($headers as $cell => $value) {
        $sheet->setCellValue($cell, $value);
    }
    
    // จัดรูปแบบส่วนหัว
    $headerStyle = [
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => [
                'rgb' => 'CCCCCC',
            ],
        ],
    ];
    
    $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);
    
    // ปรับความกว้างของคอลัมน์
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(20);
    $sheet->getColumnDimension('C')->setWidth(30);
    $sheet->getColumnDimension('D')->setWidth(15);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(20);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(25);
    
    // เพิ่มข้อมูลลงในตาราง
    $row = 2;
    $no = 1;
    
    foreach ($items as $item) {
        // แปลงค่าสถานะเป็นข้อความภาษาไทย
        switch ($item['status']) {
            case 'available':
                $status_text = 'พร้อมใช้งาน';
                break;
            case 'borrowed':
                $status_text = 'ถูกยืม';
                break;
            case 'repair':
                $status_text = 'ซ่อมบำรุง';
                break;
            case 'disposed':
                $status_text = 'จำหน่ายแล้ว';
                break;
            default:
                $status_text = $item['status'];
        }
        
        // แปลงรูปแบบวันที่
        $acquired_date = (!empty($item['acquired_date'])) ? date('d/m/Y', strtotime($item['acquired_date'])) : '-';
        
        $sheet->setCellValue('A' . $row, $no);
        $sheet->setCellValue('B' . $row, $item['code']);
        $sheet->setCellValue('C' . $row, $item['name']);
        $sheet->setCellValue('D' . $row, $item['category_name']);
        $sheet->setCellValue('E' . $row, number_format($item['price'], 2));
        $sheet->setCellValue('F' . $row, $acquired_date);
        $sheet->setCellValue('G' . $row, $item['location']);
        $sheet->setCellValue('H' . $row, $status_text);
        $sheet->setCellValue('I' . $row, $item['notes']);
        
        $row++;
        $no++;
    }
    
    // จัดรูปแบบข้อมูลในตาราง
    $dataStyle = [
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
            ],
        ],
    ];
    
    $sheet->getStyle('A2:I' . ($row - 1))->applyFromArray($dataStyle);
    
    // จัดการยอดรวม
    $row++;
    $sheet->setCellValue('D' . $row, 'จำนวนรายการทั้งหมด:');
    $sheet->setCellValue('E' . $row, count($items) . ' รายการ');
    $sheet->getStyle('D' . $row)->getFont()->setBold(true);
    
    // กำหนดชื่อไฟล์
    $filename = 'รายการพัสดุครุภัณฑ์_' . date('Y-m-d_H-i-s') . '.xlsx';
    
    // กำหนด header สำหรับการดาวน์โหลด
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    // สร้างไฟล์ Excel และส่งออก
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาด: " . $e->getMessage();
    header('Location: items.php');
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการสร้างไฟล์ Excel: " . $e->getMessage();
    header('Location: items.php');
    exit;
}
?>