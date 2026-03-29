<?php
$conn = mysqli_connect("[x]", "[x]", "[x]", "[x]");
$code = $_GET['code'];

$sql = "SELECT price, market, updated_at FROM stock_prices WHERE code = '$code'";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

echo json_encode($row);
mysqli_close($conn);
?>
