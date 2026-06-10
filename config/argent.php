<?php

return [

    // 4-digit PIN gate for the single user. Set APP_PIN in .env (never commit).
    'pin' => env('APP_PIN', '1234'),

    // Protects /setup (one-time migration) and /cron/run (reminder fallback).
    'setup_key' => env('SETUP_KEY', ''),

    'vapid' => [
        'subject' => env('VAPID_SUBJECT', 'mailto:zhugelisty@gmail.com'),
        'public' => env('VAPID_PUBLIC_KEY', ''),
        'private' => env('VAPID_PRIVATE_KEY', ''),
    ],

];
