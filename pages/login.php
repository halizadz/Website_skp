<?php 
if (session_status() === PHP_SESSION_NONE) {
    $sessionName = $user['role'] === 'admin' ? 'admin_session' : 'user_session';
    session_name($sessionName);
    session_start();
}


// Contoh penggunaan dalam proses login:
function verifyLogin($con, $email, $password) {
    $stmt = $con->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $hashedInput = hashPassword($password); // Menggunakan fungsi yang sama
        
        if ($hashedInput === $user['password']) {
            // Login berhasil

            return $user;
        }
    }

    return false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- Tambahkan SweetAlert -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <title>Login</title>
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <?php 
            require_once __DIR__ . '/../config/db.php';
            
            if(isset($_POST['submit'])){
                $email = mysqli_real_escape_string($con, $_POST['email']);
                $password = $_POST['password'];

                // Query untuk mengambil data user berdasarkan email
                $query = "SELECT * FROM users WHERE email='$email'";
                $result = mysqli_query($con, $query) or die("Query Error");
                
                if(mysqli_num_rows($result) > 0){
                    $user = mysqli_fetch_assoc($result); 
                    $password_database = $user['password'];

                    // Di login.php setelah query
                    error_log("Login attempt for: " . $email);
                    error_log("DB password: " . $password_database);
                    error_log("Input hashed: " . hash('sha256', $password));
                    
                    if(hash('sha256', $password) === $password_database){
                        $_SESSION['email'] = $user['email'];
                        $_SESSION['role'] = $user['role'];
                        $_SESSION['nama'] = $user['nama'];
                        $_SESSION['id'] = $user['id'];
                        
                        $roleWelcome = $user['role'] === 'admin' ? 'Admin' : 'Mahasiswa';
                    echo "<script>
                        Swal.fire({
                            title: 'Login Berhasil!',
                            text: 'Selamat datang $roleWelcome!',
                            icon: 'success'
                        }).then(() => {
                            window.location.href = 'index.php?x=dashboard';
                        });
                    </script>";
                    exit();

                } else {
                    echo "<script>
                        Swal.fire({
                            title: 'Gagal Login!',
                            text: 'Email atau password salah!',
                            icon: 'error'
                        });
                    </script>";
                }
            } else {
                echo "<script>
                    Swal.fire({
                        title: 'Gagal Login!',
                        text: 'Email tidak ditemukan!',
                        icon: 'error'
                    });
                </script>";
            }
        }
            ?>
            
            <header>Login</header>
            <form action="" method="post">
                <div class="field input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" placeholder="Masukkan email" required>
                </div>

                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Login">
                </div>
                <div class="links">
                    Belum punya akun? <a href="index.php?x=register">Daftar Sekarang</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>