<?php

// Ensure to import the necessary classes (namespaces can be used if they are defined)
require_once("Apeform.class.php");
require_once("../MyCSV.class.php");
require_once("MyCSVAdmin.class.php");

$admin = new MyCSVAdmin();

// Get the method name securely, sanitize it and check if it's valid
$method = filter_input(INPUT_GET, 'method', FILTER_SANITIZE_STRING);

// If method is not set or it's invalid, default to 'tables'
if (empty($method) || !method_exists($admin, $method)) {
    $method = "tables";
}

// Call the method safely
$admin->$method();

?>
