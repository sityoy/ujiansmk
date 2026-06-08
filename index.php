<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ujian CBT</title>
    <style>
        body { font-family: Arial, sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; background-color: #f4f7f6; margin: 0; }
        .container { text-align: center; background: #fff; padding: 40px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h2 { margin-bottom: 30px; color: #333; }
        a { display: inline-block; text-decoration: none; padding: 15px 30px; margin: 10px; border-radius: 5px; color: white; font-weight: bold; transition: 0.3s; }
        .btn-guru { background-color: #007bff; }
        .btn-guru:hover { background-color: #0056b3; }
        .btn-siswa { background-color: #28a745; }
        .btn-siswa:hover { background-color: #1e7e34; }
    </style>
</head>
<body>

    <div class="container">
        <h2>Selamat Datang di Portal CBT</h2>
        <h2>SMK ISLAM BAHAGIA</h2>
        <a href="admin/login.php" class="btn-guru">Login Admin Guru</a>
        <a href="siswa/login.php" class="btn-siswa">Login Ujian Siswa</a>
        <div style="text-align: center; padding: 20px 10px; margin-top: 40px; color: #6c757d; font-size: 14.5px; border-top: 1px solid #dee2e6; background: transparent;">
            &copy; <?php echo date('Y'); ?> <strong>Sityoy IT Solutions - SIS.COM (Free Mode)</strong>
        </div>
    </div>
    

</body>
</html>