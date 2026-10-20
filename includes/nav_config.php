<?php
declare(strict_types=1);

return [
    ['key' => 'overview', 'label' => 'Dashboard', 'icon' => 'dashboard', 'url' => '/admin/dashboard.php?tab=overview', 'roles' => ['admin']],
    ['key' => 'menu', 'label' => 'Menu items', 'icon' => 'coffee', 'url' => '/admin/dashboard.php?tab=menu', 'roles' => ['admin']],
    ['key' => 'categories', 'label' => 'Categories', 'icon' => 'category', 'url' => '/admin/dashboard.php?tab=categories', 'roles' => ['admin']],
    ['key' => 'modifiers', 'label' => 'Modifiers', 'icon' => 'tune', 'url' => '/admin/dashboard.php?tab=modifiers', 'roles' => ['admin']],
    ['key' => 'orders', 'label' => 'Orders', 'icon' => 'receipt_long', 'url' => '/admin/dashboard.php?tab=orders', 'roles' => ['admin']],
    ['key' => 'sessions', 'label' => 'Duty sessions', 'icon' => 'schedule', 'url' => '/admin/dashboard.php?tab=sessions', 'roles' => ['admin']],
    ['key' => 'staff', 'label' => 'Staff accounts', 'icon' => 'group', 'url' => '/admin/dashboard.php?tab=staff', 'roles' => ['admin']],
    ['key' => 'reports', 'label' => 'Reports', 'icon' => 'monitoring', 'url' => '/admin/dashboard.php?tab=reports', 'roles' => ['admin']],
    ['key' => 'stock', 'label' => 'Inventory', 'icon' => 'inventory_2', 'url' => '/staff/stock.php', 'roles' => ['admin']],
    ['key' => 'dashboard', 'label' => 'Dashboard', 'icon' => 'dashboard', 'url' => '/staff/dashboard.php', 'roles' => ['staff']],
    ['key' => 'pos', 'label' => 'POS Register', 'icon' => 'point_of_sale', 'url' => '/staff/offline_pos.php?page=pos', 'roles' => ['staff']],
    ['key' => 'queue', 'label' => 'Kitchen Queue', 'icon' => 'skillet', 'url' => '/staff/offline_pos.php?page=queue', 'roles' => ['staff'], 'badge' => 'orders'],
    ['key' => 'reports', 'label' => 'Sales & Reports', 'icon' => 'monitoring', 'url' => '/staff/offline_pos.php?page=reports', 'roles' => ['staff']],
    ['key' => 'stock', 'label' => 'Stock & Items', 'icon' => 'inventory_2', 'url' => '/staff/offline_pos.php?page=stock', 'roles' => ['staff'], 'badge' => 'stock'],
];
