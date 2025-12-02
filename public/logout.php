<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';

destroy_session();
set_flash('You have been successfully logged out.', 'success');
header('Location: login.php');
exit;
?>
