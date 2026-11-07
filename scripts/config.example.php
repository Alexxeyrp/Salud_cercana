<?php
// Copy OUTSIDE the document root to ../salud-private/config.php.
// Never upload this template as your actual configuration inside htdocs.
return [
    'DB_HOST' => '',
    'DB_PORT' => '3306',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASSWORD' => '',
    'DB_SSL_CA' => '',
    // Use only if your host explicitly uses its own private database network
    // without TLS. Never use this for Aiven or public database connections.
    'DB_SSL_MODE' => 'verify',
];
