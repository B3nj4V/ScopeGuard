<?php
// ScopeGuard/config/config.php
return [
    'db_path' => __DIR__ . '/../data/app.db',         // se crea solo
    'secret'  => 'CHANGE_ME_TO_A_LONG_RANDOM_HEX',    // cambia por algo largo aleatorio
    'token_ttl_minutes' => 60*24*7,                   // 7 días
    'base_url' => '',                                 // ej: 'http://127.0.0.1:8000' (vacío = autodetect)

    'mail' => [
        'from' => 'no-reply@tudominio.com',
        'reply_to' => 'soporte@tudominio.com',
    ],
    'notify' => [
        'staff_emails' => ['pm@tudominio.com', 'legal@tudominio.com'], // staff por defecto
        // 'client_emails' => ['cliente@empresa.com'], // opcional respaldo

]];
