<?php

/**
 * This example script for the TM::MyCSV class shows how easy it is to put the
 * result from a MySQL query into a CSV file. The extended class
 * MyCSV_MySQL.class.php converts data in the other direction and is not used in
 * this example.
 *
 * @author Thiemo M�ttig (http://maettig.com/)
 */

require_once("MyCSV.class.php");

// Create a new MyCSV object
$csv = new MyCSV();

// Change the delimiter to ";" or "\t" if needed.
$csv->delimiter = ",";

// MySQL connection parameters
$host = "localhost";
$username = "root";
$password = "";
$database = "test";

// Create a new mysqli connection
$mysqli = new mysqli($host, $username, $password, $database);

// Check the connection
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

// The SQL query can contain all combinations of WHERE, ORDER BY, etc.
$sql = "SELECT * FROM `test`";

// Execute the query and get the result
$result = $mysqli->query($sql);

// Check if the query was successful
if ($result) {
    // Push all data into the MyCSV object
    while ($record = $result->fetch_assoc()) {
        $csv->insert($record);
    }

    // If the delimiter is "\t", the content type should be
    // "text/tab-separated-values" or "text/plain".
    header("Content-Type: text/comma-separated-values");

    // Dump the CSV data to screen
    $csv->dump();
} else {
    // If the query fails, show an error
    echo "Error: " . $mysqli->error;
}

// Close the MySQL connection
$mysqli->close();

?>
