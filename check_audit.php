<?php
require 'vendor/autoload.php';
$conn = new PDO('sqlite:var/data_prod.db');
$stmt = $conn->query('SELECT * FROM audit_log ORDER BY id DESC LIMIT 10');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}