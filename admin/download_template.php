<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$file = "templates/Template_Soal_CBT.xlsx";

if (!file_exists($file)) {
    die("Template tidak ditemukan.");
}

header('Content-Description: File Transfer');
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Template_Soal_CBT.xlsx"');
header('Content-Length: ' . filesize($file));

readfile($file);
exit;