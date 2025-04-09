<?php
$hostname = "mysql";
$username = "user";
$password = "password";
$dbname = "recipes_db";

try {
    $conn = new PDO("mysql:host=$hostname;dbname=$dbname", $username, $password);

    // Set the PDO error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // echo "Connected successfully \n";
}catch(PDOException $e){
    echo "Connection failed:". $e->getMessage();
}
?>