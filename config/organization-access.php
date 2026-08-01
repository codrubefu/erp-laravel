<?php

return [
    'disabled_right_groups' => [
        1 => ['events', 'event_participants'],
    ],

    'right_groups' => [
        'users' => ['users.*'],
        'groups' => ['groups.*'],
        'rights' => ['rights.*'],
        'locations' => ['locations.*'],
        'subscriptions' => ['subscriptions.*', 'sms.view'],
        'articles' => ['articles.*'],
        'events' => ['events.*', 'event_participants.*'],
        'payments' => ['payments.*'],
        'custom-fields' => ['custom-fields.*'],
    ],
];
