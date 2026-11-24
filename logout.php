<?php
require_once 'includes/config.php';
require_once 'includes/functions.php';

$_SESSION = array();
session_destroy();
header("Location: " . url('index.php'));
exit;
