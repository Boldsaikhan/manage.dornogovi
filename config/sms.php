<?php

return [

    /*
    |--------------------------------------------------------------------------
    | SMS илгээх
    |--------------------------------------------------------------------------
    |
    | Нэвтрэх нууц үгийг утасны дугаар луу илгээнэ. Үйлдвэрлэлд SMS_HTTP_* тохируулна.
    | Хөгжүүлэлтэд SMS_DRIVER=log ашиглаж, storage/logs/laravel.log дээр харагдана.
    |
    */

    'enabled' => env('SMS_ENABLED', false),

    /** log — зөвхөн бичлэг · http — гадны SMS API */
    'driver' => env('SMS_DRIVER', 'log'),

    /** API-д явуулах утасны формат: 976 + 8 орон (жнь 97699112233) */
    'phone_prefix' => env('SMS_PHONE_PREFIX', '976'),

    /** Хэлтсийн албан хаагчид эрх үүсгэхэд шинэ бүртгэлд SMS */
    'send_on_provision' => env('SMS_SEND_ON_PROVISION', true),

    /** Дахин provision хийхэд (шинэчлэх) SMS — ихэнхдээ false */
    'send_on_provision_update' => env('SMS_SEND_ON_PROVISION_UPDATE', false),

    /** Админ гараар хэрэглэгч нэмэхэд */
    'send_on_admin_create' => env('SMS_SEND_ON_ADMIN_CREATE', true),

    /** Админ нууц үг солиход */
    'send_on_password_reset' => env('SMS_SEND_ON_PASSWORD_RESET', true),

    /**
     * {app}, {url}, {phone}, {password}, {name} — placeholder.
     * Хоосон бол доорх login_message ашиглана.
     */
    'login_message' => env('SMS_LOGIN_MESSAGE'),

    'login_message_default' => <<<'TXT'
{app} нэвтрэх мэдээлэл:
Хаяг: {url}
Утас (нэвтрэх нэр): {phone}
Нууц үг: {password}
TXT,

    'http' => [
        'url' => env('SMS_HTTP_URL'),
        'method' => env('SMS_HTTP_METHOD', 'POST'),
        'token' => env('SMS_HTTP_TOKEN'),
        'phone_field' => env('SMS_HTTP_PHONE_FIELD', 'phone'),
        'message_field' => env('SMS_HTTP_MESSAGE_FIELD', 'message'),
        'timeout' => (int) env('SMS_HTTP_TIMEOUT', 15),
    ],

    'from' => env('SMS_FROM', 'ZDTG'),

];
