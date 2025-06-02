<?php 
require_once __DIR__ . '/../config/db.php'; 

$errors = [];

if(isset($_POST['submit'])){
    $nama = $_POST['nama'];
    $npm = $_POST['npm'];
    $prodi = $_POST['prodi'];
    $email = $_POST['email'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validasi NPM harus 13 karakter angka
    if(!preg_match('/^[0-9]{13}$/', $npm)) {
        $errors['npm'] = "NPM harus 13 karakter angka";
    }
    
    // Validasi email harus mengandung '@' dan '.'
    if(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = "Email tidak valid";
    }
    
    // Validasi password minimal 8 karakter, 1 huruf kapital, 1 angka
    if(!preg_match('/^(?=.*[A-Z])(?=.*[0-9]).{8,}$/', $password)) {
        $errors['password'] = "Password minimal 8 karakter, 1 kapital, dan 1 angka";
    }
    
    // Konfirmasi password
    if($password !== $confirm_password) {
        $errors['confirm_password'] = "Password tidak cocok";
    }
    
    // Cek apakah email atau NPM sudah digunakan
    $verify_query = mysqli_query($con, "SELECT email, npm FROM users WHERE email='$email' OR npm='$npm'");
    if(mysqli_num_rows($verify_query) > 0) {
        $errors['email'] = "Email atau NPM sudah terpakai";
    }
    
    // Jika tidak ada error, proses pendaftaran
    if(empty($errors)) {
        // Enkripsi password dengan SHA-256
        $epassword = hash('sha256', $password);
        
        // Query untuk insert ke database
        $query = "INSERT INTO users(nama, npm, prodi, email, password, role) 
                  VALUES('$nama', '$npm', '$prodi', '$email', '$epassword', 'user')";
        
        if(mysqli_query($con, $query)) {
            echo '<!DOCTYPE html>
            <html>
            <head>
                <title>Redirecting...</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
            </head>
            <body>
                <script>
                    Swal.fire({
                        title: "Sukses!",
                        text: "Registrasi berhasil!",
                        icon: "success",
                        confirmButtonText: "OK"
                    }).then(() => {
                        window.location.href = "index.php?x=login";
                    });
                </script>
            </body>
            </html>';
            exit();
        }
    }
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
    <title>Register</title>
</head>
<body>
    <div class="container">
        <div class="box form-box">
            <header>Daftar</header>
            <form action="" method="post">
                <div class="field input">
                    <label for="nama">Nama</label>
                    <input type="text" name="nama" id="nama" placeholder="Masukkan nama lengkap" required value="<?php echo isset($nama) ? htmlspecialchars($nama) : ''; ?>">
                    <?php if(isset($errors['nama'])): ?>
                        <span class="error"><?php echo $errors['nama']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="field input">
                    <label for="npm">NPM</label>
                    <input type="text" name="npm" id="npm" placeholder="Masukkan NPM (13 digit)" required value="<?php echo isset($npm) ? htmlspecialchars($npm) : ''; ?>">
                    <?php if(isset($errors['npm'])): ?>
                        <span class="error"><?php echo $errors['npm']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="field input">
                    <label for="prodi">Program Studi</label>
                    <select name="prodi" id="prodi" required>
                        <option value="">Pilih Program Studi</option>
                        <option value="Pendidikan Matematika" <?php echo (isset($prodi) && $prodi == "Pendidikan Matematika") ? 'selected' : ''; ?>>Pendidikan Matematika</option>
                        <option value="Pendidikan Bahasa Inggris" <?php echo (isset($prodi) && $prodi == "Pendidikan Bahasa Inggris") ? 'selected' : ''; ?>>Pendidikan Bahasa Inggris</option>
                        <option value="Pendidikan Bahasa dan Sastra Indonesia" <?php echo (isset($prodi) && $prodi == "Pendidikan Bahasa dan Sastra Indonesia") ? 'selected' : ''; ?>>Pendidikan Bahasa dan Sastra Indonesia</option>
                        <option value="Pendidikan Masyarakat" <?php echo (isset($prodi) && $prodi == "Pendidikan Masyarakat") ? 'selected' : ''; ?>>Pendidikan Masyarakat</option>
                        <option value="Pendidikan Jasmani Kesehatan dan Rekreasi" <?php echo (isset($prodi) && $prodi == "Pendidikan Jasmani Kesehatan dan Rekreasi") ? 'selected' : ''; ?>>Pendidikan Jasmani Kesehatan dan Rekreasi</option>
                    </select>
                </div>

                <div class="field input">
                    <label for="email">Email</label>
                    <input type="text" name="email" id="email" placeholder="Masukkan email" required value="<?php echo isset($email) ? htmlspecialchars($email) : ''; ?>">
                    <?php if(isset($errors['email'])): ?>
                        <span class="error"><?php echo $errors['email']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="field input">
                    <label for="password">Password</label>
                    <input type="password" name="password" id="password" placeholder="Masukkan password" required>
                    <?php if(isset($errors['password'])): ?>
                        <span class="error"><?php echo $errors['password']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="field input">
                    <label for="confirm_password">Konfirmasi Password</label>
                    <input type="password" name="confirm_password" id="confirm_password" placeholder="Masukkan ulang password" required>
                    <?php if(isset($errors['confirm_password'])): ?>
                        <span class="error"><?php echo $errors['confirm_password']; ?></span>
                    <?php endif; ?>
                </div>

                <div class="field">
                    <input type="submit" class="btn" name="submit" value="Daftar">
                </div>
                <div class="links">
                    Sudah punya akun? <a href="index.php?x=login">Login Sekarang</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>