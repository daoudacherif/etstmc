<?php
$con = mysqli_connect("mysql.hostinger.com", "u553063725_Daoudacherif", "Daoudacherif4321", "u553063725_italalu");

if (!$con) {
    die("Connection failed: " . mysqli_connect_error());
} else {
    echo "Connected successfully"; // Remove this after testing
}
?>
