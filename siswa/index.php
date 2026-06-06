<?php
// ==============================================================
// 🛡️ SISTEM KEAMANAN ANTI-HACK (HTTP SECURITY HEADERS)
// ==============================================================

// 1. Mencegah Clickjacking (Website tidak bisa ditampilkan di dalam <iframe> web lain)
header('X-Frame-Options: DENY');

// 2. Mencegah MIME-Sniffing (Memaksa browser mengikuti tipe file yang sebenarnya)
header('X-Content-Type-Options: nosniff');

// 3. Mengaktifkan Filter XSS (Mencegah script berbahaya dieksekusi di browser)
header('X-XSS-Protection: 1; mode=block');

// 4. Mematikan Caching (Agar halaman ini tidak pernah disimpan di memori/history browser)
// Ini mencegah orang iseng menekan tombol 'Back' untuk melihat halaman sebelumnya
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// 5. Menyembunyikan versi PHP dari header (Agar hacker tidak tahu versi PHP server)
header_remove("X-Powered-By");

// ==============================================================
// 🚀 PENGALIHAN OTOMATIS (REDIRECT)
// ==============================================================

// Arahkan pengunjung secara langsung ke halaman login
header("Location: login.php");

// Hentikan eksekusi script lebih lanjut (Wajib dipasang setelah header location)
exit;
?>