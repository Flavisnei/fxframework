<?php
return [
    'database' => dirname(__DIR__) . '/storage/admin.sqlite',
    'secure_cookie' => false, // Somente HTTP local; usar true em HTTPS.
    'mail' => is_file(__DIR__ . '/mail.local.json') ? require __DIR__ . '/mail.php' : null,
    'permissions' => ['contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete'],
];
