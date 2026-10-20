<?php
declare(strict_types=1);

return [
    // Add only owner-confirmed table labels; an empty list keeps dine-in disabled.
    'dine_in_table_labels' => [],
    'max_open_pending_orders' => 30,
    'max_orders_per_session_per_10_min' => 3,
    'max_orders_per_ip_per_10_min' => 10,
];
