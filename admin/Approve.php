<?php
if (session_status() === PHP_SESSION_NONE) {
    session_name('admin_session'); // HARUS sebelum session_start()
    session_start();
}

if(!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location:index.php?x=login");
    exit();
}


require_once __DIR__ . '/../config/db.php';  


// Ambil data profil admin
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

// Handle approve/reject action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['activity_id']) || !isset($_POST['action'])) {
        $_SESSION['notification'] = [
            'type' => 'error',
            'message' => 'Invalid request: missing activity_id or action'
        ];
        header("Location: index.php?x=approve");
        exit();
    }
    
    // Get parameters
    $activity_id = $_POST['activity_id'];
    $action = $_POST['action'];
    $admin_id = $_SESSION['id'];
    
    // Process based on action
    if ($action === 'approve') {
        $con->begin_transaction();
        
        try {
            // 1. Update status activity
            $stmt = $con->prepare("UPDATE student_activities SET status = 'approved' WHERE activity_id = ?");
            $stmt->bind_param("i", $activity_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update status: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Tidak ada data yang diupdate, mungkin activity_id tidak ditemukan");
            }
            
            // 2. Update point summary
            $updateQuery = "INSERT INTO student_point_summary_real (user_id, nama, npm, prodi, total_points, total_activities, last_activity_date)
            SELECT 
                sa.user_id, 
                u.nama, 
                u.npm, 
                u.prodi, 
                COALESCE((
                    SELECT SUM(sc.points) 
                    FROM student_activities sa2 
                    JOIN activity_subcategories sc ON sa2.subcategory_id = sc.subcategory_id 
                    WHERE sa2.user_id = sa.user_id AND sa2.status = 'approved'
                ), 0) AS total_points,
                COALESCE((
                    SELECT COUNT(*) 
                    FROM student_activities 
                    WHERE user_id = sa.user_id AND status = 'approved'
                ), 0) AS total_activities,
                NOW() AS last_activity_date
            FROM student_activities sa
            JOIN users u ON sa.user_id = u.id
            WHERE sa.activity_id = ?
            ON DUPLICATE KEY UPDATE 
                nama = VALUES(nama),
                npm = VALUES(npm),
                prodi = VALUES(prodi),
                total_points = VALUES(total_points),
                total_activities = VALUES(total_activities),
                last_activity_date = VALUES(last_activity_date)";
            
            $update_stmt = $con->prepare($updateQuery);
            $update_stmt->bind_param("i", $activity_id);
            
            if (!$update_stmt->execute()) {
                throw new Exception("Gagal update point summary: " . $update_stmt->error);
            }
            
            $con->commit();
            
            $_SESSION['notification'] = [
                'type' => 'success', 
                'message' => 'Aktivitas berhasil disetujui!',
                'approved_activity' => $activity_id
            ];
            
        } catch (Exception $e) {
            $con->rollback();
            $_SESSION['notification'] = [
                'type' => 'error', 
                'message' => $e->getMessage()
            ];
        }
        
    } elseif ($action === 'reject' || $action === 'confirm_reject') {
        $notes = $_POST['rejection_notes'] ?? '';
        
        if (empty($notes)) {
            $_SESSION['notification'] = [
                'type' => 'error',
                'message' => 'Harap isi alasan penolakan'
            ];
            header("Location: index.php?x=approve#activity-" . $activity_id);
            exit();
        }
        
        $con->begin_transaction();
        
        try {
            // 1. Update status activity
            $stmt = $con->prepare("UPDATE student_activities SET status = 'rejected', rejection_notes = ? WHERE activity_id = ?");
            $stmt->bind_param("si", $notes, $activity_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Gagal update status penolakan: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("Tidak ada data yang diupdate, mungkin activity_id tidak ditemukan");
            }
            
            // 2. Add notification for student
            $user_id_stmt = $con->prepare("SELECT user_id FROM student_activities WHERE activity_id = ?");
            $user_id_stmt->bind_param("i", $activity_id);
            
            if (!$user_id_stmt->execute()) {
                throw new Exception("Gagal mendapatkan user_id: " . $user_id_stmt->error);
            }
            
            $user_id_result = $user_id_stmt->get_result();
            $user_data = $user_id_result->fetch_assoc();
            
            
            
            $con->commit();
            
            $_SESSION['notification'] = [
                'type' => 'success', 
                'message' => 'Aktivitas berhasil ditolak!',
                'rejected_activity' => $activity_id
            ];
            
        } catch (Exception $e) {
            $con->rollback();
            $_SESSION['notification'] = [
                'type' => 'error', 
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Redirect back to the page
    header("Location: index.php?x=approve#activity-" . $activity_id);
    exit();

}

// Ambil data aktivitas yang perlu disetujui
$pending_activities = [];
$query = "
    SELECT 
        sa.activity_id,
        sa.user_id,
        u.nama AS student_name,
        u.npm,
        u.prodi,
        ac.category_name,
        sc.subcategory_name,
        sc.points,
        sa.description,
        sa.created_at,
        sa.file_type,
        sa.proof_file_path,
        sa.original_filename
    FROM student_activities sa
    JOIN users u ON sa.user_id = u.id
    JOIN activity_subcategories sc ON sa.subcategory_id = sc.subcategory_id
    JOIN activity_categories ac ON sc.category_id = ac.category_id
    WHERE sa.status = 'pending'
    ORDER BY sa.created_at DESC
";

$pending_activities = [];
$result = $con->query($query);
if ($result && $result->num_rows > 0) {
    $pending_activities = $result->fetch_all(MYSQLI_ASSOC);
}

// Notifikasi
$notification = $_SESSION['notification'] ?? null;
unset($_SESSION['notification']);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Approve Aktivitas</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    
    <!-- Custom CSS -->
   <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-dashboard.css">

    <!-- Vue.js -->
    <script src="https://cdn.jsdelivr.net/npm/vue@2.6.14/dist/vue.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        .activity-card {
            border-left: 4px solid #0d6efd;
            margin-bottom: 15px;
            transition: all 0.3s;
        }
        .activity-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        .file-preview {
            max-width: 100%;
            max-height: 200px;
            margin-top: 10px;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .reject-notes {
            display: none; /* Tetap hidden awal */
        }

        .reject-notes.show {
            display: block; /* Ditampilkan dengan JS */
        }
    </style>
</head>
<body>
    
    <div id="app">
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
                        <a href="index.php?x=dashboard" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="index.php?x=listMahasiswa" class="nav-link">
                    <i class="fas fa-user-graduate"></i>
                    <span>List Mahasiswa</span>
                </a>
                <a href="index.php?x=approve" class="nav-link active">
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
            </nav>

                <!-- Page Content -->
                <div class="container-fluid">
                    <div class="dashboard-header animate__animated animate__fadeIn">
                        <h1 class="dashboard-title">Approve Aktivitas Mahasiswa</h1>
                        <p class="dashboard-subtitle">Tinjau dan setujui atau tolak aktivitas yang diajukan mahasiswa</p>
                    </div>

                    <!-- Notifikasi -->
                    <?php if ($notification): ?>
                        <div class="alert alert-<?= $notification['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible fade show animate__animated animate__fadeIn">
                            <?= $notification['message'] ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body d-flex justify-content-between align-items-center">
                                    <div>
                                        <h5 class="card-title mb-0">Aktivitas Pending</h5>
                                        <p class="card-text fs-3 fw-bold mb-0"><?= is_array($pending_activities) ? count($pending_activities) : 0 ?></p>
                                    </div>
                                    <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Daftar Aktivitas -->
                    <div class="card animate__animated animate__fadeIn">
                        <div class="card-body">
                            
                        <?php if (is_array($pending_activities) && !empty($pending_activities)): ?>
                        <?php foreach ($pending_activities as $activity): ?>
                            <div class="card activity-card mb-3" id="activity-<?= $activity['activity_id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1"><?= htmlspecialchars($activity['student_name']) ?></h5>
                                            <p class="text-muted small mb-2">
                                                NPM: <?= htmlspecialchars($activity['npm']) ?> | 
                                                Prodi: <?= htmlspecialchars($activity['prodi']) ?>
                                            </p>
                                        </div>
                                        <span class="badge bg-secondary">
                                            <?= date('d M Y H:i', strtotime($activity['created_at'])) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <span class="badge bg-primary"><?= htmlspecialchars($activity['category_name']) ?></span>
                                        <span class="badge bg-info text-dark"><?= htmlspecialchars($activity['subcategory_name']) ?></span>
                                        <span class="badge bg-success"><?= $activity['points'] ?> Poin</span>
                                        <span class="badge badge-pending">Pending</span>
                                    </div>
                                    
                                    <p class="card-text"><?= nl2br(htmlspecialchars($activity['description'])) ?></p>
                                    
                                    <?php if (!empty($activity['original_filename'])): ?>
                                        <div class="mb-3">
                                            <p class="mb-1"><strong>File Lampiran:</strong></p>
                                            <?php if (strpos($activity['file_type'], 'image/') === 0): ?>
                                                <img src="data:<?= $activity['file_type'] ?>;base64,<?= base64_encode($activity['proof_file_path']) ?>" 
                                                    alt="Preview" class="file-preview img-thumbnail">
                                            <?php else: ?>
                                                <a href="data:<?= $activity['file_type'] ?>;base64,<?= base64_encode($activity['proof_file_path']) ?>" 
                                                download="<?= htmlspecialchars($activity['original_filename']) ?>" 
                                                class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-download"></i> Unduh <?= htmlspecialchars($activity['original_filename']) ?>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                            
                                            <!-- Form untuk submit approve/reject -->
                                            <div id="activity-<?= $activity['activity_id'] ?>">
                                            <form method="post" action="index.php?x=approve" class="activity-form">
                                                <input type="hidden" name="activity_id" value="<?= $activity['activity_id'] ?>">
                                                <input type="hidden" name="action" id="action-type-<?= $activity['activity_id'] ?>">
                                                
                                                <div class="mt-3" id="action-container-<?= $activity['activity_id'] ?>">
                                                    <div class="d-flex justify-content-end gap-2 approval-buttons-<?= $activity['activity_id'] ?>">
                                                        <button type="button" class="btn btn-danger reject-btn" 
                                                                id="reject-btn-<?= $activity['activity_id'] ?>"
                                                                data-activity-id="<?= $activity['activity_id'] ?>">
                                                            <i class="fas fa-times"></i> Tolak
                                                        </button>
                                                        <button type="submit" class="btn btn-success approve-btn" 
                                                                onclick="document.getElementById('action-type-<?= $activity['activity_id'] ?>').value = 'approve'">
                                                            <i class="fas fa-check"></i> Setujui
                                                        </button>
                                                    </div>
                                                    
                                                    <div class="reject-notes mt-3" id="reject-notes-<?= $activity['activity_id'] ?>" style="display: none;">
                                                        <label for="reject-notes-input-<?= $activity['activity_id'] ?>" class="form-label">
                                                            Alasan Penolakan:
                                                        </label>
                                                        <textarea class="form-control" 
                                                                name="rejection_notes"
                                                                id="reject-notes-input-<?= $activity['activity_id'] ?>" 
                                                                rows="3" 
                                                                placeholder="Masukkan alasan penolakan aktivitas ini"
                                                                required></textarea>
                                                        <div class="d-flex justify-content-end gap-2 mt-2">
                                                            <button type="button" class="btn btn-secondary cancel-reject-btn" 
                                                                    data-activity-id="<?= $activity['activity_id'] ?>">
                                                                Batal
                                                            </button>
                                                            <button type="submit" class="btn btn-danger"
                                                                    onclick="document.getElementById('action-type-<?= $activity['activity_id'] ?>').value = 'reject'">
                                                                <i class="fas fa-paper-plane"></i> Kirim Penolakan
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </form>
                                            </div>
                                        </div> 
                                    </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-check-circle fa-4x text-success mb-3"></i>
                                    <h4>Tidak ada aktivitas yang perlu disetujui</h4>
                                    <p class="text-muted">Semua aktivitas mahasiswa telah diproses</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap Bundle with Popper -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script src="<?= BASE_URL ?>/assets/js/utils.js"></script>
    <script src="<?= BASE_URL ?>/assets/js/script.js"></script>
        
        <!-- Vue.js Application -->
 <!-- Add this script at the bottom of your file, before the closing </body> tag -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle approve button clicks
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const form = this.closest('form');
            const actionInput = form.querySelector('input[name="action"]');
            
            Swal.fire({
                title: 'Konfirmasi Persetujuan',
                text: 'Anda yakin ingin menyetujui aktivitas ini?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Ya, Setujui',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d'
            }).then((result) => {
                if (result.isConfirmed) {
                    actionInput.value = 'approve';
                    form.submit();
                }
            });
        });
    });
    
    // Handle reject button clicks
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const activityId = this.getAttribute('data-activity-id');
            const form = this.closest('form');
            
            Swal.fire({
                title: 'Form Penolakan',
                html: `
                    <form id="rejectForm">
                        <div class="mb-3">
                            <label for="rejectNotes" class="form-label">Alasan Penolakan:</label>
                            <textarea class="form-control" id="rejectNotes" rows="3" 
                                      placeholder="Masukkan alasan penolakan aktivitas ini" required></textarea>
                        </div>
                    </form>
                `,
                showCancelButton: true,
                confirmButtonText: 'Kirim Penolakan',
                cancelButtonText: 'Batal',
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                focusConfirm: false,
                preConfirm: () => {
                    const notes = document.getElementById('rejectNotes').value.trim();
                    if (!notes) {
                        Swal.showValidationMessage('Harap isi alasan penolakan');
                        return false;
                    }
                    return notes;
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a hidden input for notes
                    const notesInput = document.createElement('input');
                    notesInput.type = 'hidden';
                    notesInput.name = 'rejection_notes';
                    notesInput.value = result.value;
                    form.appendChild(notesInput);
                    
                    // Set action to reject and submit
                    const actionInput = form.querySelector('input[name="action"]');
                    actionInput.value = 'reject';
                    form.submit();
                }
            });
        });
    });
    
    // Notification handling
    <?php if ($notification): ?>
        Swal.fire({
            icon: '<?= $notification['type'] === 'success' ? 'success' : 'error' ?>',
            title: '<?= $notification['type'] === 'success' ? 'Berhasil!' : 'Gagal!' ?>',
            text: '<?= addslashes($notification['message']) ?>',
            showConfirmButton: true,
            timer: 3000
        });
        
        <?php if (isset($notification['rejected_activity']) || isset($notification['approved_activity'])): ?>
            setTimeout(function() {
                const activityId = '<?= $notification['rejected_activity'] ?? $notification['approved_activity'] ?? '' ?>';
                if (activityId) {
                    const element = document.getElementById('activity-' + activityId);
                    if (element) {
                        element.scrollIntoView({ behavior: 'smooth' });
                        element.classList.add('animate__animated', 'animate__shakeX');
                        setTimeout(() => {
                            element.classList.remove('animate__animated', 'animate__shakeX');
                        }, 1000);
                    }
                }
            }, 500);
        <?php endif; ?>
    <?php endif; ?>
    
});

</script>
    </body>
</html>