<?php
declare(strict_types=1);
$query = array_filter(['tab' => 'sessions', 'success' => isset($_GET['resolved']) && is_string($_GET['resolved']) && $_GET['resolved'] === '1' ? 'closed' : null], static function ($value): bool { return $value !== null; });
header('Location: dashboard.php?' . http_build_query($query), true, 302);
exit;
