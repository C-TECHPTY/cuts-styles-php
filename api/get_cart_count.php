<?php
// api/get_cart_count.php
session_start();
header('Content-Type: application/json');

$count = 0;
if(isset($_SESSION['carrito'])) {
    $count = array_sum($_SESSION['carrito']);
}

echo json_encode(['count' => $count]);
?>