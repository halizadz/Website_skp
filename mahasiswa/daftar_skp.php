<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('user_session'); // HARUS sebelum session_start()
    session_start();
}

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'user'){
    header("Location: index.php?x=login");
    exit();
}

require_once __DIR__ . '/../config/db.php'; 
require_once __DIR__ . '/../vendor/autoload.php';




// Ambil data user
$user = [];
$stmt = $con->prepare("SELECT id, nama, npm, prodi, foto_profil, profile_picture_type FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();



// Function untuk menampilkan foto profil
function displayProfilePicture($user) {
    if (!empty($user['foto_profil'])) {
        $imageType = $user['profile_picture_type'] ?? 'image/jpeg';
        return 'data:' . $imageType . ';base64,' . base64_encode($user['foto_profil']);
    }
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
    );
}


// Ambil data SKP
$activities = [];
$stmt = $con->prepare("
    SELECT 
        sa.activity_id,
        sa.description,
        sa.status,
        sa.created_at,
        sa.proof_file_path AS proof_file,
        sa.file_type,
        sa.original_filename,
        sa.rejection_notes,
        sc.subcategory_id,
        sc.subcategory_name,
        sc.points,
        ac.category_id,
        ac.category_name,
        u.nama AS approver_name
    FROM student_activities sa
    JOIN activity_subcategories sc ON sa.subcategory_id = sc.subcategory_id
    JOIN activity_categories ac ON sc.category_id = ac.category_id
    LEFT JOIN users u ON sa.approved_by = u.id
    WHERE sa.user_id = ?
    ORDER BY sa.created_at DESC
");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);

// Query untuk mendapatkan summary point
$summary = ['total_points' => 0, 'total_activities' => 0];
$summary_stmt = $con->prepare("
     SELECT 
        sa.activity_id,
        sa.description,
        sa.status,
        sa.created_at,
        sa.proof_file_path AS proof_file,
        sa.file_type,
        sa.original_filename,
        sa.rejection_notes,
        sc.subcategory_id,
        sc.subcategory_name,
        sc.points,
        ac.category_id,
        ac.category_name,
        u.nama AS approver_name
    FROM student_activities sa
    JOIN activity_subcategories sc ON sa.subcategory_id = sc.subcategory_id
    JOIN activity_categories ac ON sc.category_id = ac.category_id
    LEFT JOIN users u ON sa.approved_by = u.id
    WHERE sa.user_id = ?
    ORDER BY sa.created_at DESC
");
if ($summary_stmt) {
    $summary_stmt->bind_param("i", $_SESSION['id']);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    if ($summary_result->num_rows > 0) {
        $summary = $summary_result->fetch_assoc();
    }
}


// Hitung total poin dan aktivitas
$total_points = 0;
$approved_activities = 0;
$pending_activities = 0;
$rejected_activities = 0;

foreach ($activities as $activity) {
    if ($activity['status'] == 'approved') {
        $total_points += $activity['points'];
        $approved_activities++;
    } elseif ($activity['status'] == 'pending') {
        $pending_activities++;
    } elseif ($activity['status'] == 'rejected') {
        $rejected_activities++;
    }
}

// Jika summary kosong, gunakan perhitungan manual
if (!isset($summary['total_points']) || $summary['total_points'] == 0) {
    $summary['total_points'] = $total_points;
    $summary['total_activities'] = $approved_activities;
}
// Pastikan ini ada di file view_skp.php atau file terpisah untuk generate PDF
if (isset($_GET['action']) && $_GET['action'] == 'print') {
    // Ambil data summary dari database
    $summary_stmt = $con->prepare("
        SELECT 
        sa.activity_id,
        sa.description,
        sa.status,
        sa.created_at,
        sa.proof_file_path AS proof_file,
        sa.file_type,
        sa.original_filename,
        sa.rejection_notes,
        sc.subcategory_id,
        sc.subcategory_name,
        sc.points,
        ac.category_id,
        ac.category_name,
        u.nama AS approver_name
    FROM student_activities sa
    JOIN activity_subcategories sc ON sa.subcategory_id = sc.subcategory_id
    JOIN activity_categories ac ON sc.category_id = ac.category_id
    LEFT JOIN users u ON sa.approved_by = u.id
    WHERE sa.user_id = ?
    ORDER BY sa.created_at DESC
    ");
    $summary_stmt->bind_param("i", $_SESSION['id']);
    $summary_stmt->execute();
    $summary_result = $summary_stmt->get_result();
    $summary = $summary_result->fetch_assoc();


    $logoPath = __DIR__ . '/../assets/img/logo-UNSIKA.png';
    $ttdProdi = __DIR__ . '/../assets/img/Kordinator_Program_Study.png';
    $ttdVerifikator = __DIR__ . '/../assets/img/verifikator.png';


    // Buat konten PDF sesuai format gambar 1
 $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
        <title>e-SKPly - ' . htmlspecialchars($user['nama']) . '</title>
        <style>
                body { 
        font-family: "Times New Roman", Times, serif;
    }
    .kop-surat {
            display: flex;
    align-items: center;
    margin-bottom: 20px;
        }
     .kop-container {
            display: flex;
    align-items: center;
    gap: 20px;
        }

        .kop-logo {
        width: 100px;
    margin-right: 20px;
}


        .kop-text {
           text-align: center;
    flex-grow: 1;
        }

        .kop-text h1 {
            font-size: 14pt;
            margin: 0;
             line-height: 1.3;
        }
        .kop-text h1:first-child {
    font-weight: normal;
}

.kop-text h1:nth-child(2),
.kop-text h1:nth-child(3) {
    font-weight: bold;
}
        .kop-text h2 {
             font-size: 12pt;
    margin: 5px 0 0 0;
    font-weight: normal;
        }

    .header-line { 
         height: 3px;
    background-color: black;
    margin-top: 10px;
    }
                 .text-center {
            text-align: center;
        }
            .title { 
                text-align: center; 
                font-weight: bold; 
                margin: 15px 0; 
                font-size: 14px;
                text-decoration: underline;
            }
            .student-info { 
                margin-bottom: 20px; 
                font-size: 12px;
            }
            .table { 
                width: 100%; 
                border-collapse: collapse; 
                margin-bottom: 20px; 
                font-size: 11px;
            }
            .table th, .table td { 
                border: 1px solid #000; 
                padding: 5px; 
            }
            .table th { 
                background-color: #f2f2f2; 
                text-align: center; 
                font-weight: bold;
            }
             .signature {
        width: 100%;
        margin: 40px auto;
        border-collapse: separate;
        border-spacing: 20px;
    }
    
    .signature-col {
        width: 50%;
        vertical-align: top;
    }
    
    .signature-container {
        text-align: center;
        padding: 20px;
        border-radius: 8px;
    }
    
    .signature-title {
        font-size: 1.2em;
        font-weight: bold;
        margin-bottom: 15px;
        color: #333;
    }
    
    .signature-line {
        width: 150px;
        height: 1px;
        background: #333;
        margin: 0 auto 15px auto;
    }
    
    .signature-img {
        height: 80px;
        margin: 10px 0;
        display: block;
        margin-left: auto;
        margin-right: auto;
    }
    
    .signature-name {
        margin: 15px 0 5px 0;
        font-size: 1em;
        color: #222;
    }
    
    .signature-id {
        margin: 0;
        font-size: 0.9em;
        color: #555;
    }
            .footer { 
                font-size: 10px; 
                text-align: center; 
                margin-top: 20px; 
                font-style: italic;
            }
        </style>
    </head>
    <body>
        <table style="width: 100%; border-bottom: 3px solid black; margin-bottom: 10px;">
    <tr>
        <td style="width: 15%; text-align: center;">
            <img src="' . __DIR__ . '/../assets/img/logo-UNSIKA.png' . '" style="width: 90px;">
        </td>
        <td style="text-align: center;">
            <div style="font-size: 14px; font-weight: normal;">KEMENTERIAN PENDIDIKAN, KEBUDAYAAN, RISET, DAN TEKNOLOGI</div>
            <div style="font-size: 16px; font-weight: bold;">UNIVERSITAS SINGAPERBANGSA KARAWANG</div>
            <div style="font-size: 16px; font-weight: bold;">FAKULTAS KEGURUAN DAN ILMU PENDIDIKAN</div>
            <div style="font-size: 11px;">Jl. H.S. Ronggowaluyo Telukjambe Timur Telp. (0267) 641177 Fax. (0267) 641367 Ext. 102 - Karawang 41361</div>
            <div style="font-size: 11px;">Website: www.unsika.ac.id email: info@unsika.ac.id</div>
        </td>
    </tr>
</table>
        
        <div class="title">SATUAN KREDIT PRESTASI MAHASISWA</div>
        
        <div class="student-info">
            <p><strong>Nama:</strong> ' . htmlspecialchars($user['nama']) . '</p>
            <p><strong>NPM:</strong> ' . htmlspecialchars($user['npm']) . '</p>
            <p><strong>Program Studi:</strong> ' . htmlspecialchars($user['prodi']) . '</p>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th>No</th>
                    <th>Kegiatan</th>
                    <th>Poin</th>
                </tr>
            </thead>
            <tbody>';
    
    // Kelompokkan aktivitas dengan nama yang sama dan jumlahkan poinnya
    $grouped_activities = [];
    foreach ($activities as $activity) {
        if ($activity['status'] == 'approved') {
            $key = $activity['subcategory_name'];
            if (!isset($grouped_activities[$key])) {
                $grouped_activities[$key] = [
                    'points' => 0,
                    'count' => 0
                ];
            }
            $grouped_activities[$key]['points'] += $activity['points'];
            $grouped_activities[$key]['count']++;
        }
    }
    
    // Tambahkan ke tabel dengan nomor urut
    $counter = 1;
    foreach ($grouped_activities as $activity_name => $data) {
        $html .= '
                <tr>
                    <td>' . $counter . '</td>
                    <td>' . htmlspecialchars($activity_name) . ' (' . $data['count'] . 'x)</td>
                    <td>' . $data['points'] . '</td>
                </tr>';
        $counter++;
    }
    
    // Total poin
    $html .= '
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>Total</strong></td>
                    <td><strong>' . ($summary['total_points'] ?? $total_points) . '</strong></td>
                </tr>
            </tbody>
        </table>
        
<table class="signature">
    <tr>
        <td class="signature-col">
            <div class="signature-container">
                <p class="signature-title">Disetujui</p>
                <div class="signature-line"></div>
               <img src="' . $ttdProdi . '" alt="TTD Koordinator Prodi" class="signature-img">
                <p class="signature-name"><strong>Evi Karlina Ambarwati, S.S., M.Ed.</strong></p>
                <p class="signature-id">NIP. 198611212020122007</p>
            </div>
        </td>
        <td class="signature-col">
            <div class="signature-container">
                <p class="signature-title">Mengetahui</p>
                <div class="signature-line"></div>
                <img src="' . $ttdVerifikator . '" alt="TTD Verifikator" class="signature-img">
                <p class="signature-name"><strong>Yogi Setia Samsi, S.S., M.Hum.</strong></p>
                <p class="signature-id">NIDN. 0015079001</p>
            </div>
        </td>
    </tr>
</table>
        
        <div class="footer">
            <p>Dokumen ini dicetak secara otomatis dari Sistem SKP Universitas Singaperbangsa Karawang</p>
            <p>Tanggal cetak: ' . date('d F Y') . '</p>
        </div>
    </body>
    </html>';
    
    // Buat PDF dengan konfigurasi font
    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 20,
        'margin_bottom' => 20,
        'margin_header' => 10,
        'margin_footer' => 10,
        'default_font' => 'times',
        'tempDir' => __DIR__ . '/tmp'
    ]);
    
    $mpdf->SetTitle('e-SKPly - ' . $user['nama']);
    $mpdf->SetAuthor('Universitas Singaperbangsa Karawang');
    $mpdf->SetCreator('Sistem SKP FKIP');
    
    $mpdf->WriteHTML($html);
    
    $filename = 'e-SKPly_' . $user['npm'] . '_' . date('Ymd') . '.pdf';
    if (isset($_GET['preview']) && $_GET['preview'] == '1') {
        $mpdf->Output($filename, 'I');
        exit();
    }
    $mpdf->Output($filename, 'D');
    exit();
}

?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar SKP - Sistem SKP</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-dashboard.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
    
    <!-- Custom CSS -->
    <style>
        #previewModal .modal-dialog {
    max-width: 90%;
    height: 90vh;
}
#previewModal .modal-body {
    padding: 0;
    height: calc(90vh - 120px);
}
#pdfPreviewFrame {
    width: 100%;
    height: 100%;
    border: none;
}
        .status-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            
        }
        .badge-approved {
            background-color: #d1fae5;
            color: #065f46;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background-color: #f8fafc;
            border-radius: 12px;
            margin: 2rem 0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .empty-state-icon {
            color: #3b82f6;
            margin-bottom: 1.5rem;
        }

        .empty-state-title {
            font-size: 1.5rem;
            color: #1e293b;
            margin-bottom: 0.75rem;
        }

        .empty-state-description {
            color: #64748b;
            max-width: 500px;
            margin: 0 auto 1.5rem;
        }
        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 20px;
        }

        .btn-animate-icon {
        background-color: #3b82f6;
        color: white;
        border: none;
        padding: 12px 24px 12px 18px;
        font-size: 1.1rem;
        font-weight: 600;
        border-radius: 8px;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        box-shadow: 0 4px 6px rgba(59, 130, 246, 0.3);
    }

.icon-wrapper {
    display: inline-flex;
    margin-right: 10px;
    transition: all 0.3s ease;
}

.btn-animate-icon:hover {
    background-color:rgb(19, 71, 155);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(59, 130, 246, 0.3);
}

.btn-animate-icon:hover .icon-wrapper {
    transform: rotate(90deg);
}
        .print-btn {
            background-color: #28a745;
            color: white;
        }
        .print-btn:hover {
            background-color: #218838;
            color: white;
        }
    </style>
</head>
<body>
<div id="app">
    <!-- Sidebar -->
    <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon animate__animated animate__bounceIn">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <div class="logo-text animate__animated animate__fadeIn">Student Panel</div>
            </div>
            <button class="btn btn-link" @click="toggleSidebar">
                <i class="fas fa-chevron-left"></i>
            </button>
        </div>
        
        <ul class="nav flex-column sidebar-menu">
            <li class="nav-item">
                <a href="index.php?x=dashboard" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?x=daftar_skp" class="nav-link active">
                    <i class="fas fa-list"></i>
                    <span>Daftar SKP</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?x=addSkp" class="nav-link">
                    <i class="fas fa-plus-circle"></i>
                    <span>Tambah SKP</span>
                </a>
            </li>
        </ul>
        
        <div class="sidebar-footer p-3">
            <a href="index.php?x=logout" class="nav-link logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content" :class="{ 'expanded': sidebarCollapsed }">
        <!-- Top Navbar -->
        <nav class="top-navbar navbar navbar-expand-lg">
            <div class="container-fluid">
                <div class="d-flex align-items-center ms-auto">
                    <div class="profile-container dropdown">
                        <div class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-avatar">
                                <?php if (!empty($user['foto_profil'])): ?>
                                    <img src="<?php echo displayProfilePicture($user); ?>" 
                                        alt="Profile Picture" 
                                        class="profile-image">
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($user['nama'] ?? 'Mahasiswa'); ?></div>
                                <div class="profile-role"><?php echo htmlspecialchars($user['prodi'] ?? 'Mahasiswa'); ?></div>
                            </div>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end profile-dropdown-menu">
                            <li>
                                <a class="dropdown-item" href="index.php?x=profile">
                                    <i class="fas fa-user-edit me-2"></i> Edit Profil
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Modal Preview PDF -->
        <div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="previewModalLabel">Preview Dokumen SKP</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ratio ratio-1x1">
                            <iframe id="pdfPreviewFrame" src="" style="width:100%; height:80vh; border:none;"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Page Content -->
        <div class="container-fluid p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3></i>Daftar Aktivitas SKP</h3>
                <?php if (!empty($activities)): ?>
                <button class="btn print-btn" data-bs-toggle="modal" data-bs-target="#previewModal" onclick="loadPdfPreview()">
                    <i class="fas fa-file-pdf me-2"></i>Cetak Laporan
                </button>
            <?php endif; ?>
            </div>
            
            <?php if (empty($activities)): ?>
                <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-clipboard-list fa-4x"></i>
                </div>
                <h3 class="empty-state-title">Belum ada aktivitas SKP</h3>
                <p class="empty-state-description">
                    Mulai bangun portofolio SKP Anda dengan menambahkan aktivitas pertama.
                </p>
                <a href="index.php?x=addSkp" class="btn btn-animate-icon">
                    <i class="fas fa-plus-circle me-2"></i>Tambah Aktivitas Pertama
                </a>
                <div class="empty-state-tips mt-4">
                    <p class="text-muted"><small>Tips: Aktivitas SKP bisa berupa partisipasi seminar, kepanitiaan, atau prestasi akademik/non-akademik</small></p>
                </div>
            </div>
            <?php else: ?>
                <div class="card shadow">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</th>
                                        <th>Kegiatan</th>
                                        <th>Kategori</th>
                                        <th>Poin</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                        <th>Aksi</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activities as $index => $activity): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($activity['subcategory_name']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['category_name']); ?></td>
                                            <td><?php echo $activity['points']; ?></td>
                                            <td>
                                                <?php if ($activity['status'] == 'approved'): ?>
                                                    <span class="status-badge badge-approved">
                                                        <i class="fas fa-check-circle me-1"></i>Disetujui
                                                    </span>
                                                <?php elseif ($activity['status'] == 'pending'): ?>
                                                    <span class="status-badge badge-pending">
                                                        <i class="fas fa-clock me-1"></i>Pending
                                                    </span>
                                                <?php else: ?>
                                                    <span class="status-badge badge-rejected">
                                                        <i class="fas fa-times-circle me-1"></i>Ditolak
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($activity['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" 
                                                    data-bs-target="#detailModal<?php echo $activity['activity_id']; ?>">
                                                    <i class="fas fa-eye"></i> Detail
                                                </button>
                                            </td>
                                        </tr>
                                        
                                        <!-- Modal Detail -->
                                        <div class="modal fade" id="detailModal<?php echo $activity['activity_id']; ?>" tabindex="-1" 
    aria-labelledby="detailModalLabel<?php echo $activity['activity_id']; ?>" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="detailModalLabel<?php echo $activity['activity_id']; ?>">
                    Detail Aktivitas SKP
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Kategori:</strong><br>
                        <?php echo htmlspecialchars($activity['category_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Aktivitas:</strong><br>
                        <?php echo htmlspecialchars($activity['subcategory_name']); ?></p>
                    </div>
                </div>
                <div class="row mb-3">
                    <div class="col-md-6">
                        <p><strong>Poin:</strong><br>
                        <?php echo $activity['points']; ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong><br>
                        <?php if ($activity['status'] == 'approved'): ?>
                            <span class="status-badge badge-approved">
                                <i class="fas fa-check-circle me-1"></i>Disetujui
                            </span>
                        <?php elseif ($activity['status'] == 'pending'): ?>
                            <span class="status-badge badge-pending">
                                <i class="fas fa-clock me-1"></i>Pending
                            </span>
                        <?php else: ?>
                            <span class="status-badge badge-rejected">
                                <i class="fas fa-times-circle me-1"></i>Ditolak
                            </span>
                        <?php endif; ?>
                        </p>
                    </div>
                </div>
                <div class="mb-3">
                    <p><strong>Deskripsi Kegiatan:</strong><br>
                    <?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                </div>
                <?php if (!empty($activity['proof_file'])): ?>
                    <div class="mb-3">
                        <p><strong>Bukti Dokumen:</strong></p>
                        <?php if (strpos($activity['file_type'], 'image') !== false): ?>
                            <img src="data:<?php echo $activity['file_type']; ?>;base64,<?php echo base64_encode($activity['proof_file']); ?>" 
                                alt="Bukti Dokumen" class="img-fluid">
                        <?php else: ?>
                            <a href="data:<?php echo $activity['file_type']; ?>;base64,<?php echo base64_encode($activity['proof_file']); ?>" 
                                download="bukti_skp_<?php echo $activity['activity_id']; ?>.<?php echo str_replace('application/', '', str_replace('image/', '', $activity['file_type'])); ?>" 
                                class="btn btn-primary">
                                <i class="fas fa-download me-2"></i>Download Dokumen
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($activity['status'] == 'rejected'): ?>
                    <div class="alert alert-danger">
                        <p><strong>Alasan Penolakan:</strong><br>
                        <?php 
                        // Menampilkan rejection_notes sebagai alasan penolakan
                        echo nl2br(htmlspecialchars($activity['rejection_notes']));
                        ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>
                                        </div>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Vue.js -->
<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>


<script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.js"></script>
<script src="<?= BASE_URL ?>/assets/js/utils.js"></script>
<script>function loadPdfPreview() {
    // Buat iframe untuk menampilkan preview PDF
    const iframe = document.getElementById('pdfPreviewFrame');
    iframe.src = 'index.php?x=daftar_skp&action=print&preview=1';
    
    // Atur ukuran iframe
    iframe.onload = function() {
        this.style.height = '80vh';
    };
}
</script>
</body>
</html>