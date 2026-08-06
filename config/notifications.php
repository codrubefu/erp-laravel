<?php

return [
    'subject' => env('NOTIFICATION_MAIL_SUBJECT', 'Notificare'),
    'templates' => [
        'subscription.activated' => 'Cotizația :subscription a fost activată.',
        'subscription.expiring' => 'Cotizația :subscription expiră la :expires_at.',
        'subscription.expired' => 'Cotizația :subscription a expirat la :expires_at.',
        'schedule.changed' => 'Programul pentru :event a fost modificat.',
        'announcement.urgent' => 'URGENT: :message',
        'activity.resumed' => 'Activitatea :event a fost reluată.',
    ],
];
