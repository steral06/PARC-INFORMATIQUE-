<?php
require 'vendor/autoload.php';
$conn = new PDO('sqlite:var/data_prod.db');
$stmt = $conn->query('PRAGMA table_info(audit_log)');
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    print_r($row);
}