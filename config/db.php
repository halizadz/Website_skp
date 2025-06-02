<?php
$host = "localhost";
$user = "root";  
$pass = ""; 
$dbname = "e_skply";  

$con = mysqli_connect($host, $user, $pass, $dbname);

// Cek koneksi
if (!$con) {
    die("Koneksi database gagal: " . mysqli_connect_error());
}

?>
