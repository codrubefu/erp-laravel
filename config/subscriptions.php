<?php

return [
    'expiration_notice_days' => (int) env('SUBSCRIPTION_EXPIRATION_NOTICE_DAYS', 1),

    'expiration_notice_message' => env(
        'SUBSCRIPTION_EXPIRATION_NOTICE_MESSAGE',
        'Abonamentul :subscription expira la data de :expires_at.'
    ),
];
