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

// Get user data
$user = [];
$stmt = $con->prepare("SELECT id, nama, npm, prodi, email, foto_profil, profile_picture_type FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['id']);  // Changed from $_SESSION['user_id'] to $_SESSION['id']
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Function to display profile picture
function displayProfilePicture($user) {
    if (!empty($user['foto_profil'])) {
        $imageType = $user['profile_picture_type'] ?? 'image/jpeg';
        return 'data:' . $imageType . ';base64,' . base64_encode($user['foto_profil']);
    }
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150" viewBox="0 0 24 24" fill="none" stroke="#6c757d" stroke-width="1" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>'
    );
}

// Get SKP data
$activities = [];
$stmt = $con->prepare("
    SELECT sa.*, sc.subcategory_name, sc.points, ac.category_name 
    FROM student_activities sa
    JOIN activity_subcategories sc ON sa.subcategory_id = sc.subcategory_id
    JOIN activity_categories ac ON sc.category_id = ac.category_id
    WHERE sa.user_id = ?
    ORDER BY sa.created_at DESC
");
$stmt->bind_param("i", $_SESSION['id']);
$stmt->execute();
$result = $stmt->get_result();
$activities = $result->fetch_all(MYSQLI_ASSOC);

// Calculate total points
$total_points = 0;
$approved_activities = 0;
$pending_activities = 0;

foreach ($activities as $activity) {
    if ($activity['status'] == 'approved') {
        $total_points += $activity['points'];
        $approved_activities++;
    } elseif ($activity['status'] == 'pending') {
        $pending_activities++;
    }
}

// Get categories for quick add form
$categories = $con->query("SELECT * FROM activity_categories")->fetch_all(MYSQLI_ASSOC);
$subcategories = $con->query("SELECT * FROM activity_subcategories")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Mahasiswa</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-dashboard.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/vue@3/dist/vue.global.prod.js"></script>
</head>
<body>
<div id="app">
    <!-- Sidebar -->
    <div class="sidebar" :class="{ 'collapsed': sidebarCollapsed, 'mobile-view': isMobile }">
        <div class="sidebar-header">
            <div class="logo-container">
                <div class="logo-icon animate__animated animate__bounceIn">
                    <i class="fas fa-user-graduate"></i> <!-- Changed from admin icon to student icon -->
                </div>
                <div class="logo-text animate__animated animate__fadeIn">Student Panel</div> <!-- Changed from Admin to Student -->
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
            </li>
            <li class="nav-item">
                <a href="index.php?x=daftar_skp" class="nav-link">
                    <i class="fas fa-list"></i> <!-- Changed icon -->
                    <span>Daftar SKP</span> <!-- Changed text -->
                </a>
            </li>
            <li class="nav-item">
                <a href="index.php?x=addSkp" class="nav-link">
                    <i class="fas fa-plus-circle"></i> <!-- Changed icon -->
                    <span>Tambah SKP</span> <!-- Changed text -->
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
                                <div class="profile-role"><?php echo htmlspecialchars($user['prodi'] ?? 'Mahasiswa'); ?></div> <!-- Changed to display prodi -->
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
       
        <!-- Dashboard Content -->
        <div class="container-fluid p-4">
            <?php if (isset($_SESSION['alert'])): ?>
                <div class="alert alert-<?php echo $_SESSION['alert']['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo $_SESSION['alert']['message']; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php unset($_SESSION['alert']); ?>
            <?php endif; ?>
            
            <h3 class="mb-4">Dashboard SKP Mahasiswa</h3>
            <p class="text-muted">
    Selamat datang
    <?php if (isset($user['nama']) && trim($user['nama']) !== ''): ?>
        , <?php echo htmlspecialchars($user['nama']); ?>
    <?php endif; ?>
</p>
            
            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6>Total Poin SKP</h6>
                        <h3><?php echo $total_points; ?></h3>
                        <small class="text-muted">Poin yang sudah disetujui</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6>SKP Disetujui</h6>
                        <h3><?php echo $approved_activities; ?></h3>
                        <small class="text-muted">Aktivitas yang diterima</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card">
                        <h6>SKP Pending</h6>
                        <h3><?php echo $pending_activities; ?></h3>
                        <small class="text-muted">Menunggu persetujuan</small>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activities -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Aktivitas Terakhir</h6>
                    <a href="index.php?x=daftar_skp" class="btn btn-sm btn-outline-primary">Lihat Semua</a>
                </div>
                <div class="card-body">
                    <?php if (empty($activities)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                            <h5>Belum ada aktivitas SKP</h5>
                            <p class="text-muted">Mulai dengan menambahkan aktivitas SKP pertama Anda</p>
                            <a href="index.php?x=add_skp" class="btn btn-primary mt-3">
                                <i class="fas fa-plus me-2"></i>Tambah Aktivitas
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Kegiatan</th>
                                        <th>Kategori</th>
                                        <th>Poin</th>
                                        <th>Status</th>
                                        <th>Tanggal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($activities, 0, 5) as $activity): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($activity['subcategory_name']); ?></td>
                                            <td><?php echo htmlspecialchars($activity['category_name']); ?></td>
                                            <td><?php echo $activity['points']; ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $activity['status'] == 'approved' ? 'success' : 
                                                         ($activity['status'] == 'pending' ? 'warning' : 'danger'); 
                                                ?>">
                                                    <?php echo $activity['status']; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('d M Y', strtotime($activity['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/utils.js"></script>

</body>
</html>