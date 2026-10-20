<?php
declare(strict_types=1);

$timezone = getenv('APP_TIMEZONE');
return [
    'timezone' => is_string($timezone) && $timezone !== '' ? $timezone : 'Asia/Manila',
];
