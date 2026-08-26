<?php

$filePublic = null;
$filePrivate = null;
$vapidFile = storage_path('app/webpush-vapid.json');

if (is_file($vapidFile)) {
    $json = json_decode((string) file_get_contents($vapidFile), true);
    if (is_array($json)) {
        $filePublic = $json['publicKey'] ?? null;
        $filePrivate = $json['privateKey'] ?? null;
    }
}

return [
    /*
    | VAPID түлхүүр — .env (VAPID_*) эсвэл storage/app/webpush-vapid.json
    | Үүсгэх: php artisan webpush:vapid
    */
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://manage.dornogovi.gov.mn')),
        'public_key' => env('VAPID_PUBLIC_KEY') ?: $filePublic,
        'private_key' => env('VAPID_PRIVATE_KEY') ?: $filePrivate,
    ],
];
