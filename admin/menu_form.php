<?php
declare(strict_types=1);
$query = array_filter(['tab' => 'menu', 'edit' => isset($_GET['id']) && is_string($_GET['id']) ? $_GET['id'] : null, 'success' => isset($_GET['success']) && is_string($_GET['success']) && $_GET['success'] === '1' ? 'saved' : null], static function ($value): bool { return $value !== null; });
header('Location: dashboard.php?' . http_build_query($query), true, 302);
exit;
