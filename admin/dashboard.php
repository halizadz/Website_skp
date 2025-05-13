<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (isset($_GET['check_update'])) {
    header('Content-Type: application/json');
    
    $last_check = $_SESSION['last_check'] ?? date('Y-m-d H:i:s');
    $user_id = $_SESSION['id'];
    
    // Cek update khusus untuk user ini saja (tidak semua data)
    $query = "SELECT COUNT(*) FROM student_activities WHERE user_id = ? AND updated_at > ?";
    $stmt = $con->prepare($query);
    $stmt->bind_param("is", $user_id, $last_check);
    $stmt->execute();
    
    $updated = $stmt->get_result()->fetch_row()[0] > 0;
    $_SESSION['last_check'] = date('Y-m-d H:i:s');
    
    echo json_encode(['updated' => $updated]);
    exit();
}
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin'){
    header("Location: ../pages/login.php");
    exit();
}

// Fungsi untuk mengecek halaman aktif
function isActivePage($pageName) {
    $currentPage = basename($_SERVER['PHP_SELF']);
    return ($currentPage == $pageName) ? true : false;
}

require_once("../config/db.php");       
require_once("../config/statistics.php");
require_once("../assets/php/my_profile.php");


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
    <link rel="stylesheet" href="../assets/css/admin-dashboard.css">
    
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
                    <a href="dashboard.php" class="nav-link <?php echo isActivePage('admin/dashboard.php') ? 'active' : ''; ?>">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="listMahasiswa.php" class="nav-link <?php echo isActivePage('admin/listMahasiswa.php') ? 'active' : ''; ?>">
                        <i class="fas fa-user-graduate"></i>
                        <span>List Mahasiswa</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="Approve.php" class="nav-link <?php echo isActivePage('admin/Approve.php') ? 'active' : ''; ?>">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span>Approve</span>
                    </a>
                </li>
            </ul>
            
            <div class="sidebar-footer p-3">
                
                <a href="../pages/logout.php" class="nav-link logout-btn">
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
                            <a class="dropdown-item" href="../profile.php">
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
                            <div class="stat-number counter">{{formatNumber(dashboardData.totalMahasiswa)}}</div>
                            <div class="stat-label">Total Mahasiswa</div>
                        </div>
                        
                        <div class="stat-card success animate__animated animate__fadeInRight">
                            <div class="stat-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="stat-number counter">{{formatNumber(dashboardData.pendaftarBulanIni)}}</div>
                            <div class="stat-label">Pendaftar Bulan Ini</div>
                        </div>
                    </div>
                
                
                    
                    <div class="chart-card animate__animated animate__fadeInUp">
                        <div class="chart-header">
                            <h4 class="chart-title">Distribusi Mahasiswa per Jurusan</h4>
                            <div class="chart-actions">
                                <button class="btn btn-sm btn-outline-secondary chart-export" @click="handleExportChart('chartJurusan')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                        <div class="chart-container">
                             <canvas ref="departmentChartRef" id="chartJurusan" style="display: block; width: 100%; height: 300px;"></canvas>
                        </div>
                    </div>
                
                    <!-- Pendaftaran Terbaru -->
                    <div class="card mt-4 animate__animated animate__fadeIn">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title m-0">Pendaftaran Terbaru</h5>
                                <a href="listMahasiswa.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Nama</th>
                                            <th>NPM</th>
                                            <th>Program Studi</th>
                                            <th>Tanggal Daftar</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($recentRegistrations as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['nama']); ?></td>
                                            <td><?php echo htmlspecialchars($student['npm']); ?></td>
                                            <td><?php echo htmlspecialchars($student['prodi']); ?></td>
                                            <td><?php echo date('d M Y', strtotime($student['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary view-btn" data-id="<?php echo htmlspecialchars($student['npm']); ?>">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        
            <!-- Modal View Student -->
            <div class="modal fade" id="studentModal" tabindex="-1" aria-labelledby="studentModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="studentModalLabel">Detail Mahasiswa</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body" id="studentDetails">
                            <!-- Detail mahasiswa akan dimuat di sini -->
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                        </div>
                    </div>
                </div>
            </div>
    </div>

            <!-- Bootstrap Bundle with Popper -->
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
            

            <!-- Load file JS sebagai module -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>

            

            <script>
// Fungsi untuk mengecek pembaruan data
function checkUpdates() {
    fetch('?check_update=1')
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(data => {
            if (data.updated) {
                // Tampilkan notifikasi sebelum reload
                Swal.fire({
                    title: 'Data Diperbarui',
                    text: 'Ada pembaruan data terbaru. Akan memuat ulang...',
                    icon: 'info',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        })
        .catch(error => {
            console.error('Error checking updates:', error);
            // Coba lagi dalam 60 detik jika error
            setTimeout(checkUpdates, 60000);
        });
}

// Inisialisasi data dan mulai pengecekan
window.serverData = {
    totalMahasiswa: <?php echo json_encode((int)$totalMahasiswa); ?>,
    pendaftarBulanIni: <?php echo json_encode((int)$pendaftarBulanIni); ?>,
    labels_jurusan: <?php echo isset($labels_jurusan) ? json_encode($labels_jurusan) : '[]'; ?>,
    values_jurusan: <?php echo isset($values_jurusan) ? json_encode($values_jurusan) : '[]'; ?>,
    recentRegistrations: <?php echo isset($recentRegistrations) ? json_encode($recentRegistrations) : '[]'; ?>
};

console.log("Data from PHP:", window.serverData);

// Mulai pengecekan pertama kali
checkUpdates();

// Jadwalkan pengecekan berkala setiap 30 detik
const updateInterval = setInterval(checkUpdates, 30000);

// Optional: Hentikan pengecekan saat tab tidak aktif
document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
        clearInterval(updateInterval);
    } else {
        checkUpdates(); // Langsung cek saat tab aktif kembali
        updateInterval = setInterval(checkUpdates, 30000);
    }
});
</script>

        <script src="../assets/js/utils.js"></script>
        <script src="../assets/js/chart.js"></script>
        <script src="../assets/js/script.js"></script>
    </body>
</html>