<?php
class Statistics {
    private $con;
    
    public function __construct($con) {
        $this->con = $con;
    }
    
    public function hitungTotalMahasiswa() {
        $query = "SELECT COUNT(*) as total FROM users WHERE role = 'user'";
        $hasil = $this->con->query($query);
        return $hasil ? $hasil->fetch_assoc()['total'] : 0;
    }
    
   public function hitungPendaftarBulanIni() {
       $bulan = date('m');
        $tahun = date('Y');
        $query = "SELECT COUNT(*) as jumlah FROM users 
               WHERE role = 'user' 
                AND MONTH(created_at) = ? 
                 AND YEAR(created_at) = ?";
        
       $stmt = $this->con->prepare($query);
        $stmt->bind_param("ii", $bulan, $tahun);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result ? $result->fetch_assoc()['jumlah'] : 0;
    }
    
    public function dataMahasiswaPerJurusan() {
        $query = "SELECT prodi as jurusan, COUNT(*) as jumlah 
                 FROM users 
                 WHERE role = 'user'
                 GROUP BY prodi 
                 ORDER BY jumlah DESC";
        
        $hasil = $this->con->query($query);
        $data = [];
        while ($row = $hasil->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
    
    public function getRecentRegistrations($limit = 5) {
        $query = "SELECT nama, npm, prodi, created_at 
                 FROM users 
                 WHERE role = 'user'
                 ORDER BY created_at DESC 
                 LIMIT ?";
        
        $stmt = $this->con->prepare($query);
        $stmt->bind_param("i", $limit);
        $stmt->execute();
        $hasil = $stmt->get_result();
        
        $data = [];
        while ($row = $hasil->fetch_assoc()) {
            $data[] = $row;
        }
        return $data;
    }
}
?>