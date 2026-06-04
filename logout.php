<?php
require_once __DIR__ . '/config/db.php';
require_once 'config.php';
session_destroy();
header('Location: ' . BASE . '/index.php');
exit;
