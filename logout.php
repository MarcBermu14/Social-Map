<?php
require_once __DIR__ . '/config/db.php';
session_destroy();
header('Location: ' . url_for('index.php'));
exit;
