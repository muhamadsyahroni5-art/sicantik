================================================================
PANDUAN INSTALASI SICANTIK KEMENAG LEBAK (PHP & MYSQL)
Untuk Laragon dan XAMPP
================================================================

1. EKSTRAK FOLDER APLIKASI
   - Laragon : Letakkan seluruh file di folder C:\laragon\www\sicantik\
   - XAMPP   : Letakkan seluruh file di folder C:\xampp\htdocs\sicantik\

2. JALANKAN SERVICE WEB & DATABASE
   - Buka Laragon -> klik "Start All" (Apache/Nginx & MySQL).
   - Atau buka XAMPP Control Panel -> klik Start pada "Apache" dan "MySQL".

3. KONEKSI DATABASE MYSQL OTOMATIS (LARAGON & XAMPP)
   - Aplikasi ini sudah dilengkapi fitur auto-bootstrap di file `koneksi.php` dan `api.php`.
   - Mendukung otomatis port MySQL 3306 maupun 3307 serta host `localhost` / `127.0.0.1`.
   - Saat pertama kali membuka http://localhost/sicantik/ di browser:
     * Database `sicantik_kemenag_lebak` akan dibuat otomatis.
     * Seluruh tabel (`unit_kerja`, `pegawai`, `users`, `cuti_requests`, `hari_libur`, `app_settings`) dan data awal akan terisi otomatis.
     * Jika menjalankan hasil build React (`dist/`) di Laragon/XAMPP bersama `api.php`, penyimpanan otomatis beralih dari browser (localStorage) ke MySQL.
   - Jika ingin mengimpor secara manual lewat phpMyAdmin:
     * Buka http://localhost/phpmyadmin
     * Klik tab Import -> pilih file `database.sql` -> klik Go.
   - Apabila username/password/port MySQL Anda berbeda, sesuaikan `$dbUser`/`$dbPass`/`$dbPort` di `koneksi.php` & `api.php` (atau atur env `SICANTIK_DB_HOST`, `SICANTIK_DB_PORT`, `SICANTIK_DB_USER`, `SICANTIK_DB_PASS`, `SICANTIK_DB_NAME`).

4. AKUN LOGIN DEFAULT (Password semua akun: password123)
   - Super Admin     : superadmin (atau NIP 197001011995031001)
   - Admin           : admin
   - Operator TU     : operator
   - Operator Penmad : operator_penmad
================================================================
