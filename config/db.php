<?php
$host = "localhost";
$user = "root";  
$pass = ""; 
$dbname = "db_fkip";  

$con = mysqli_connect($host, $user, $pass, $dbname);

// Cek koneksi
if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

?>
