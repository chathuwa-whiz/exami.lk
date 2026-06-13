<?php
session_start();
echo "Session ID: " . session_id() . "\n";
echo "Session data: ";
print_r($_SESSION);
echo "\nGET data: ";
print_r($_GET);
echo "\nPOST data: ";
print_r($_POST);
?>
