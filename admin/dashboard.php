<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin_session'); // HARUS sebelum session_start()
    session_start();
}

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: index.php?x=login");
    exit();
}


require_once __DIR__ . '/../config/db.php';    
require_once __DIR__ . '/../config/statistics.php';
    


// Ambil data statistik awal
$stats = new Statistics($con);
$totalMahasiswa = $stats->hitungTotalMahasiswa();
$dataJurusan = $stats->dataMahasiswaPerJurusan();
$recentRegistrations = $stats->getRecentRegistrations(5);
$pendaftarBulanIni = $stats->hitungPendaftarBulanIni();

$profileData = [];
if (isset($_SESSION['id'])) {
    $stmt = $con->prepare("SELECT nama, npm, prodi, email, foto_profil, profile_picture_type FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $profileData = $result->fetch_assoc();
}
function displayProfilePicture($profileData) {
    if (!empty($profileData['foto_profil'])) {
        $mimeType = $profileData['profile_picture_type'] ?? 'image/jpeg';
        return 'data:' . $mimeType . ';base64,' . base64_encode($profileData['foto_profil']);
    }
    
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
    );
}


// Siapkan data untuk chart jurusan
$labels_jurusan = array_column($dataJurusan, 'jurusan');
$values_jurusan = array_column($dataJurusan, 'jumlah');



?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-dashboard.css">

    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

     <!-- Vue.js CDN -->
     <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
</head>
<body>
    
    <div id="app">
        <!-- Sidebar -->
        <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
            <div class="sidebar-header">
                <div class="logo-container">
                    <div class="logo-icon animate__animated animate__bounceIn">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="logo-text animate__animated animate__fadeIn">Admin Panel</div>
                </div>
                <button class="btn btn-link" @click="toggleSidebar">
                <i class="fas fa-chevron-left"></i>
            </button>
            </div>
            
            <ul class="nav flex-column sidebar-menu">
                <li class="nav-item">
                    <a href="index.php?x=dashboard" class="nav-link active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="index.php?x=listMahasiswa" class="nav-link">
                    <i class="fas fa-user-graduate"></i>
                    <span>List Mahasiswa</span>
                </a>
                <a href="index.php?x=approve" class="nav-link">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <span>Approve</span>
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
        </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" :class="{ 'expanded': sidebarCollapsed }">
            <!-- Top Navbar -->
                <nav class="top-navbar navbar navbar-expand-lg">
                    <div class="container-fluid">
                        <div class="d-flex align-items-center ms-auto">
                        <div class="profile-container">
                            <!-- Dropdown untuk menu profil -->
                            <div class="profile-container dropdown">
                            <div class="profile-trigger" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="profile-avatar">
                                <?php 
                                // Ambil data profil sekali saja di awal file
                                if (!empty($profileData['foto_profil'])): ?>
                                    <img src="<?php echo displayProfilePicture($profileData); ?>" 
                                        alt="Profile Picture" 
                                        class="profile-image">
                                <?php else: ?>
                                    <div class="avatar-fallback">
                                        <i class="fas fa-user"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($_SESSION['nama'] ?? 'Admin'); ?></div>
                                <div class="profile-role">Administrator</div>
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
           
        

                <!-- Page Content -->
                <div class="container-fluid">
                    <div class="dashboard-header animate__animated animate__fadeIn">
                        <h1 class="dashboard-title">Dashboard Admin</h1>
                        <p class="dashboard-subtitle">Selamat datang di panel admin. Berikut adalah ringkasan statistik sistem.</p>
                    </div>
                    
                    <!-- Statistik Ringkas -->
                    <div class="stats-container">
                        <div class="stat-card primary animate__animated animate__fadeInLeft">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="stat-number counter">{{dashboardData.totalMahasiswa}}</div>
                            <div class="stat-label">Total Mahasiswa</div>
                        </div>
                        
                        <div class="stat-card success animate__animated animate__fadeInRight">
                            <div class="stat-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-number counter">{{dashboardData.pendaftarBulanIni}}</div>
                            <div class="stat-label">Pendaftar Bulan Ini</div>
                        </div>
                    </div>
                
                
                    
                    <div class="chart-card animate__animated animate__fadeInUp">
                        <div class="chart-header">
                            <h4 class="chart-title">Distribusi Mahasiswa per Jurusan</h4>
                        </div>
                        <div class="chart-container">
  <canvas ref="departmentChartRef" style="display: block; width: 100%; height: 300px;"></canvas>
</div>
</div>
                    </div>
                
                    <!-- Pendaftaran Terbaru -->
                    <div class="card mt-4 animate__animated animate__fadeIn">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title m-0">Pendaftaran Terbaru</h5>
                                <a href="index.php?x=listMahasiswa" class="btn btn-sm btn-primary">Lihat Semua</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>NPM</th>
                                            <th>Program Studi</th>
                                            <th>Tanggal Daftar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentRegistrations as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($student['npm']); ?></td>
                                            <td><?php echo htmlspecialchars($student['prodi']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    </div>

            <!-- Bootstrap Bundle with Popper -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            

            <!-- Load file JS sebagai module -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
                         <script src="<?= BASE_URL ?>/assets/js/utils.js"></script>
<script src="<?= BASE_URL ?>/assets/js/chart.js"></script>
            <script>
            // Inisialisasi data dan mulai pengecekan
            window.serverData = {
                totalMahasiswa: <?php echo json_encode((int)$totalMahasiswa); ?>,
                pendaftarBulanIni: <?php echo json_encode((int)$pendaftarBulanIni); ?>,
                labels_jurusan: <?php echo isset($labels_jurusan) ? json_encode($labels_jurusan) : '[]'; ?>,
                values_jurusan: <?php echo isset($values_jurusan) ? json_encode($values_jurusan) : '[]'; ?>,
                recentRegistrations: <?php echo isset($recentRegistrations) ? json_encode($recentRegistrations) : '[]'; ?>
            };

            console.log('Server Data:', window.serverData);
            </script>
<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
    </body>
</html>