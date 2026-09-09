<?php

return [

    /*
    |--------------------------------------------------------------------------
    | verify.mn — утасны дугаар баталгаажуулах
    |--------------------------------------------------------------------------
    |
    | Нууц үгээ мартсан хэрэглэгч утасны дугаараа оруулахад verify.mn дээр
    | session үүсгэж, нэг удаагийн код илгээнэ. Хэрэглэгч кодоо хуудсан дээр
    | бичиж, эсвэл verify.mn-ээс ирсэн callback-аар баталгаажина.
    |
    | VERIFY_API_KEY-г verify.mn-ийн API KEY хэсгээс авна.
    |
    */

    'enabled' => env('VERIFY_ENABLED', false),

    'api_key' => env('VERIFY_API_KEY'),

    'base_url' => rtrim((string) env('VERIFY_BASE_URL', 'https://api.verify.mn'), '/'),

    'timeout' => (int) env('VERIFY_TIMEOUT', 15),

    /** Баталгаажсаны дараа хэрэглэгч рүү буцаах SMS. */
    'response_sms' => env('VERIFY_RESPONSE_SMS', 'Дорноговь аймгийн дотоод систем: баталгаажлаа.'),

    /** Кодыг хэрэглэгчид харуулах SMS (сервер талаас илгээх тохиолдолд). */
    'code_message' => env(
        'VERIFY_CODE_MESSAGE',
        'Дорноговь аймгийн дотоод систем. Нууц үг сэргээх код: {code}. Хугацаа {minutes} минут.',
    ),

    /** Кодын урт ба хүчинтэй хугацаа (минут). */
    'code_length' => (int) env('VERIFY_CODE_LENGTH', 6),
    'code_ttl' => (int) env('VERIFY_CODE_TTL', 10),

    /** Код буруу оруулж болох дээд тоо. */
    'max_attempts' => (int) env('VERIFY_MAX_ATTEMPTS', 5),

    /** verify.mn-ээс ирэх callback-ийг таних нууц түлхүүр. */
    'callback_secret' => env('VERIFY_CALLBACK_SECRET'),

];
