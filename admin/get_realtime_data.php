<?php
header('Content-Type: application/json');
require_once("../config/db.php");
require_once("../config/statistics.php");

try {
    checkUserSession('admin');
    $stats = new Statistics($con);

    // Data utama sekaligus
    $dataJurusan = $stats->dataMahasiswaPerJurusan();
    $recentRegs = $stats->getRecentRegistrations(5);

    // Format response
    $response = [
        'success' => true,
        'totalMahasiswa' => (int)$stats->hitungTotalMahasiswa(),
        'pendaftarBulanIni' => (int)$stats->hitungPendaftarBulanIni(),
        'dataJurusan' => $dataJurusan,
        'labels_jurusan' => array_column($dataJurusan, 'jurusan'),
        'values_jurusan' => array_map('intval', array_column($dataJurusan, 'jumlah')),
        'recentRegistrations' => array_map(function($reg) {
            return [
                'nama' => htmlspecialchars($reg['nama']),
                'npm' => htmlspecialchars($reg['npm']),
                'prodi' => htmlspecialchars($reg['prodi']),
                'tanggal_daftar' => date('d M Y', strtotime($reg['created_at']))
            ];
        }, $recentRegs),
        'lastUpdate' => date('Y-m-d H:i:s')
    ];

    // Cek aktivitas baru
    $lastActivity = $con->query("SELECT MAX(created_at) as last_activity FROM student_activities")->fetch_assoc()['last_activity'];
    $response['has_new_activity'] = ($_SESSION['LAST_DATA_TIMESTAMP'] ?? null) !== $lastActivity;
    $_SESSION['LAST_DATA_TIMESTAMP'] = $lastActivity;

    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage(),
        'debug_info' => ['session_status' => session_status()]
    ]);
}