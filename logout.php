<?php
session_start();
require_once 'inc/functions.php';

logoutUser();
header("Location: login.php");
exit;