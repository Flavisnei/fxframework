<?php
return [
    'database' => dirname(__DIR__) . '/storage/admin.sqlite',
    'secure_cookie' => false, // Somente HTTP local; usar true em HTTPS.
    'permissions' => ['contacts.view', 'contacts.create', 'contacts.update', 'contacts.delete'],
];
