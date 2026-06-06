<?php

session_start();

if (!isset($_SESSION['admin_id'])) {
    exit;
}

$host='localhost';
$user='root';
$pass='';
$db='db_ujiansmkbhg1test';

$file =
'backup_' .
date('Ymd_His') .
'.sql';

header(
'Content-Disposition: attachment; filename='.$file
);

passthru(
"mysqldump --user=$user $db"
);