<?php

return [
    'adminEmail' => 'admin@example.com',
    'senderEmail' => 'noreply@example.com',
    'senderName' => 'Example.com mailer',
    'jwt' => [
        'secret' => getenv('JWT_SECRET') ?: 'dev-secret-change-me',
        'issuer' => getenv('JWT_ISSUER') ?: 'rti-solution-yii2',
        'audience' => getenv('JWT_AUDIENCE') ?: 'rti-solution-yii2-clients',
        'ttl' => (int)(getenv('JWT_TTL') ?: 3600),
    ],
];
