<?php

/*
 | SMS gateway configuration. Credentials belong in .env, never the database.
 |
 |   SMS_DRIVER=log      (default) messages are only written to the log + message_logs table
 |   SMS_DRIVER=twilio   set TWILIO_SID, TWILIO_AUTH_TOKEN, TWILIO_FROM
 |   SMS_DRIVER=http     POST JSON to any gateway/proxy: SMS_HTTP_URL (+ optional SMS_HTTP_TOKEN)
 */
return [
    'driver' => env('SMS_DRIVER', 'log'),
    'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '91'),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'token' => env('SMS_HTTP_TOKEN'),
        'to_field' => env('SMS_HTTP_TO_FIELD', 'to'),
        'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
    ],
];
