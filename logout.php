<?php
require_once __DIR__ . '/config/db.php';
$_SESSION = [];
session_destroy();
<<<<<<< HEAD
redirectTo('index.php');

=======
header('Location: ' . BASE . '/index.php');
exit;
>>>>>>> main
