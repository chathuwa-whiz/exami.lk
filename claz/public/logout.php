<?php
require_once __DIR__ . '/../src/config.php';
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
session_destroy();
header('Location: ' . app_href(''));
