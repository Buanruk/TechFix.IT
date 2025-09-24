<?php
require_once __DIR__.'/db_connect.php';

function dot($status){
    switch($status){
        case 'รอดำเนินการ': return '<span class="status-dot red"></span>'; 
        case 'กำลังซ่อม': return '<span class="status-dot blue"></span>'; 
        case 'ซ่อมเสร็จแล้ว': return '<span class="status-dot green"></span>'; 
        default: return '';
    }
}

// ตารางรายการที่ยังไม่เสร็จ
$sql_active = "SELECT id, username AS fullname, device_type AS device, floor, serial_number AS device_no, status 
               FROM device_reports WHERE status!='ซ่อมเสร็จแล้ว' ORDER BY id DESC";
$result_active = $conn->query($sql_active);

echo '<section class="card"><div class="card-header">รายการกำลังดำเนินการ</div><div class="table-wrap">';
echo '<table><thead><tr><th>ลำดับ</th><th>ชื่อ-สกุล</th><th>อุปกรณ์</th><th>ชั้นที่</th><th>หมายเลขเครื่อง</th><th>สถานะ</th></tr></thead><tbody>';

if($result_active && $result_active->num_rows>0){
    while($row = $result_active->fetch_assoc()){
        echo "<tr>
                <td data-label='ลำดับ'>{$row['id']}</td>
                <td data-label='ชื่อ-สกุล'>".htmlspecialchars($row['fullname'],ENT_QUOTES)."</td>
                <td data-label='อุปกรณ์'>".htmlspecialchars($row['device'],ENT_QUOTES)."</td>
                <td data-label='ชั้นที่'>".htmlspecialchars($row['floor'],ENT_QUOTES)."</td>
                <td data-label='หมายเลขเครื่อง'>".htmlspecialchars($row['device_no'],ENT_QUOTES)."</td>
                <td data-lab
