<?php
// get_active_routers.php
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

include 'config.php';

$stmt = $pdo->prepare("SELECT id, nama, ip_address, port FROM router_list WHERE is_active = 1 ORDER BY id");
$stmt->execute();
$routers = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($routers);
?>