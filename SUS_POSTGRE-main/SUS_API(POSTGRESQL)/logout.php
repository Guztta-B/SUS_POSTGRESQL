<?php
// logout.php - Fazer logout

session_start();
session_destroy();
header('Location: login.php');
exit();
?>