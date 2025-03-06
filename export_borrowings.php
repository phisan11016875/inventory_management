<?php
// ไฟล์สำหรับส่งออกข้อมูลการยืม-คืนเป็น Excel
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
    header('Location: borrowings.php');
    exit;
}

// เรียกใช้ library PhpSpreadsheet
require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// กำหนดเงื่อนไขการค้นหา
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// สร้าง query พื้นฐาน
$sql = "SELECT b.*, i.code as item_code, i.name as item_name, 
        u.name as borrower_name, u.department,
        a.name as approved_by_name
        FROM borrowings b
        LEFT JOIN items i ON b.item_id = i.id
        LEFT JOIN users u ON b.user_id = u.id
        LEFT JOIN users a ON b.approved_by = a.id
        WHERE 1=1";
$params = [];

// เพิ่มเงื่อนไขการค้นหา
if (!empty($search)) {
    $sql .= " AND (i.code LIKE ? OR i.name LIKE ? OR u.name LIKE ? OR u.department LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($status)) {
    $sql .= " AND b.status = ?";
    $params[] = $status;
}

if (!empty($start_date)) {
    $sql .= " AND b.borrow_date >= ?";
    $params[] = $start_date;
}

if (!empty($end_date)) {
    $sql .= " AND b.borrow_date <= ?";
    $params[] = $end_date;
}

$sql .= " ORDER BY b.borrow_date DESC, b.id DESC";

try {
    // ดึงข้อมูลการยืม-คืน
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $borrowings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // สร้างไฟล์ Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // กำหนดชื่อ Sheet
    $sheet->setTitle("รายการยืม-คืนพัสดุ/ครุภัณฑ์");
    
    // กำหนดส่วนหัวของตาราง
    $headers = [
        'A1' => 'ลำดับ',
        'B1' => 'รหัสพัสดุ/ครุภัณฑ์',
        'C1' => 'ชื่อรายการ',
        'D1' => 'ผู้ยืม',
        'E1' => 'แผนก/ฝ่าย',
        'F1' => 'วันที่ยืม',
        'G1' => 'กำหนดคืน',
        'H1' => 'วันที่คืน',
        'I1' => 'วัตถุประสงค์',
        'J1' => 'สถานะ',
        'K1' => 'ผู้อนุมัติ',
        'L1' => 'หมายเหตุ'
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
                'rgb' => 'CCCCCC'
            ],
        ],
    ];
    
    $sheet->getStyle('A1:L1')->applyFromArray($headerStyle);
    
    // ปรับความกว้างของคอลัมน์
    $sheet->getColumnDimension('A')->setWidth(8);
    $sheet->getColumnDimension('B')->setWidth(18);
    $sheet->getColumnDimension('C')->setWidth(25);
    $sheet->getColumnDimension('D')->setWidth(20);
    $sheet->getColumnDimension('E')->setWidth(15);
    $sheet->getColumnDimension('F')->setWidth(15);
    $sheet->getColumnDimension('G')->setWidth(15);
    $sheet->getColumnDimension('H')->setWidth(15);
    $sheet->getColumnDimension('I')->setWidth(25);
    $sheet->getColumnDimension('J')->setWidth(15);
    $sheet->getColumnDimension('K')->setWidth(20);
    $sheet->getColumnDimension('L')->setWidth(25);
    
    // เพิ่มข้อมูลลงในตาราง
    $row = 2;
    $no = 1;
    
    foreach ($borrowings as $borrowing) {
        // แปลงค่าสถานะเป็นข้อความภาษาไทย
        switch ($borrowing['status']) {
            case 'pending':
                $status_text = 'รออนุมัติ';
                break;
            case 'approved':
                $status_text = 'อนุมัติแล้ว';
                break;
            case 'returned':
                $status_text = 'คืนแล้ว';
                break;
            case 'cancelled':
                $status_text = 'ยกเลิก';
                break;
            case 'overdue':
                $status_text = 'เกินกำหนด';
                break;
            default:
                $status_text = $borrowing['status'];
        }
        
        // แปลงรูปแบบวันที่
        $borrow_date = (!empty($borrowing['borrow_date'])) ? date('d/m/Y', strtotime($borrowing['borrow_date'])) : '-';
        $due_date = (!empty($borrowing['due_date'])) ? date('d/m/Y', strtotime($borrowing['due_date'])) : '-';
        $return_date = (!empty($borrowing['return_date'])) ? date('d/m/Y', strtotime($borrowing['return_date'])) : '-';
        
        $sheet->setCellValue('A' . $row, $no);
        $sheet->setCellValue('B' . $row, $borrowing['item_code']);
        $sheet->setCellValue('C' . $row, $borrowing['item_name']);
        $sheet->setCellValue('D' . $row, $borrowing['borrower_name']);
        $sheet->setCellValue('E' . $row, $borrowing['department']);
        $sheet->setCellValue('F' . $row, $borrow_date);
        $sheet->setCellValue('G' . $row, $due_date);
        $sheet->setCellValue('H' . $row, $return_date);
        $sheet->setCellValue('I' . $row, $borrowing['purpose']);
        $sheet->setCellValue('J' . $row, $status_text);
        $sheet->setCellValue('K' . $row, $borrowing['approved_by_name'] ?? '-');
        $sheet->setCellValue('L' . $row, $borrowing['notes']);
        
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
    
    $sheet->getStyle('A2:L' . ($row - 1))->applyFromArray($dataStyle);
    
    // จัดการยอดรวม
    $row++;
    $sheet->setCellValue('D' . $row, 'จำนวนรายการทั้งหมด:');
    $sheet->setCellValue('E' . $row, count($borrowings) . ' รายการ');
    $sheet->getStyle('D' . $row)->getFont()->setBold(true);
    
    // สถิติสถานะการยืม
    $row += 2;
    $sheet->setCellValue('A' . $row, 'สรุปสถานะการยืม-คืน:');
    $sheet->getStyle('A' . $row)->getFont()->setBold(true);
    
    // นับจำนวนแต่ละสถานะ
    $status_count = [
        'pending' => 0,
        'approved' => 0,
        'returned' => 0,
        'cancelled' => 0,
        'overdue' => 0
    ];
    
    foreach ($borrowings as $borrowing) {
        if (isset($status_count[$borrowing['status']])) {
            $status_count[$borrowing['status']]++;
        }
    }
    
    $row++;
    $sheet->setCellValue('A' . $row, 'รออนุมัติ:');
    $sheet->setCellValue('B' . $row, $status_count['pending'] . ' รายการ');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'อนุมัติแล้ว (ยังไม่คืน):');
    $sheet->setCellValue('B' . $row, $status_count['approved'] . ' รายการ');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'คืนแล้ว:');
    $sheet->setCellValue('B' . $row, $status_count['returned'] . ' รายการ');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'ยกเลิก:');
    $sheet->setCellValue('B' . $row, $status_count['cancelled'] . ' รายการ');
    
    $row++;
    $sheet->setCellValue('A' . $row, 'เกินกำหนด:');
    $sheet->setCellValue('B' . $row, $status_count['overdue'] . ' รายการ');
    
    // กำหนดชื่อไฟล์
    $filename = 'รายการยืม-คืนพัสดุครุภัณฑ์_' . date('Y-m-d_H-i-s') . '.xlsx';
    
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
    header('Location: borrowings.php');
    exit;
} catch (Exception $e) {
    $_SESSION['error'] = "เกิดข้อผิดพลาดในการสร้างไฟล์ Excel: " . $e->getMessage();
    header('Location: borrowings.php');
    exit;
}