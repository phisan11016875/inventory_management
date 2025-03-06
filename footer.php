<?php if (isset($_SESSION['user_id'])): ?>
                </main>
            <?php endif; ?>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.8/dist/sweetalert2.all.min.js"></script>
    
    <!-- ส่วนนี้สำหรับแสดงการแจ้งเตือน -->
    <?php if (isset($_SESSION['success'])): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'สำเร็จ!',
            text: '<?= $_SESSION['success'] ?>',
            timer: 3000,
            timerProgressBar: true
        });
    </script>
    <?php unset($_SESSION['success']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error'])): ?>
    <script>
        Swal.fire({
            icon: 'error',
            title: 'เกิดข้อผิดพลาด!',
            text: '<?= $_SESSION['error'] ?>',
            timer: 3000,
            timerProgressBar: true
        });
    </script>
    <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    
    <!-- Custom JavaScript -->
    <script>
    $(document).ready(function() {
        // เปิด/ปิด Sidebar บนอุปกรณ์มือถือ
        $('.navbar-toggler').click(function() {
            $('#sidebar').toggleClass('show');
        });
        
        // ตั้งค่า DataTables สำหรับตารางทั้งหมด (ถ้ามี)
        if ($.fn.DataTable && $('.dataTable').length > 0) {
            $('.dataTable').DataTable({
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
                "responsive": true
            });
        }
    });
    </script>
</body>
</html>