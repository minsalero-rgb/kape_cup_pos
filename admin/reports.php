<?php
declare(strict_types=1);
$query = array_filter(['tab' => 'reports', 'date' => isset($_GET['date']) && is_string($_GET['date']) ? $_GET['date'] : null], static function ($value): bool { return $value !== null; });
header('Location: dashboard.php?' . http_build_query($query), true, 302);
exit;
