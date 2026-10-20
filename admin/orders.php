<?php
declare(strict_types=1);
$query = array_filter(['tab' => 'orders', 'status' => isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : null], static function ($value): bool { return $value !== null; });
header('Location: dashboard.php?' . http_build_query($query), true, 302);
exit;
