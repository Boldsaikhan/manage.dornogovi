<?php

return [

    /*
    |--------------------------------------------------------------------------
    | verify.mn — утасны дугаар баталгаажуулах (MO SMS)
    |--------------------------------------------------------------------------
    |
    | Урсгал нь ЭСРЭГ чиглэлтэй: бид хэрэглэгч рүү SMS илгээхгүй. Харин
    | хэрэглэгч өөрөө 144773 дугаар руу нэг удаагийн кодоо SMS-ээр илгээнэ.
    |
    |   1. POST /sessions        — утас + код өгч session үүсгэнэ
    |   2. хэрэглэгч 144773 руу кодоо SMS-ээр илгээнэ (sms: холбоосоор нэг товшилт)
    |   3. GET  callback         — verify.mn «шалгаарай» гэж сэрээнэ (бие агуулгагүй)
    |   4. GET /sessions/{id}    — АЛБАН ЁСНЫ төлөв. Зөвхөн VERIFIED бол баталгаажсан
    |
    | Callback-д гарын үсэг байхгүй тул түүнд дангаар нь ИТГЭХГҮЙ — үргэлж
    | төлвийг нь дахин асууна.
    |
    */

    'enabled' => env('VERIFY_ENABLED', false),

    'api_key' => env('VERIFY_API_KEY'),

    'base_url' => rtrim((string) env('VERIFY_BASE_URL', 'https://api.verify.mn'), '/'),

    'timeout' => (int) env('VERIFY_TIMEOUT', 15),

    /** Хэрэглэгчийн SMS илгээх богино дугаар. */
    'shortcode' => env('VERIFY_SHORTCODE', '144773'),

    /** Нэг SMS-ийн үнэ (₮) — хэрэглэгч өөрөө төлдөг тул хуудсан дээр анхааруулна. */
    'sms_cost' => (int) env('VERIFY_SMS_COST', 150),

    /**
     * Баталгаажсаны дараа хэрэглэгч рүү буцах хариу SMS.
     *
     * Зөвхөн латин үсэг, тоо, 160 тэмдэгт хүртэл. Оператороос хамаарна:
     * Unitel зөвхөн үндсэн хариуг илгээдэг, Lime огт хариу илгээдэггүй.
     * Тиймээс үр дүнг ЗӨВХӨН хуудсан дээрээ харуулна.
     */
    'response_sms' => env('VERIFY_RESPONSE_SMS', 'Dornogovi ZDTG: batalgaajlaa.'),

    /** Кодын урт (verify.mn дээр яг ийм текстээр таардаг). */
    'code_length' => (int) env('VERIFY_CODE_LENGTH', 6),

    /** Session-ы TTL — verify.mn талдаа 5 минут. */
    'code_ttl' => (int) env('VERIFY_CODE_TTL', 5),

    /** Нөөц сувгаар (SMS API) код илгээсэн үед хэдэн удаа буруу оруулж болох. */
    'max_attempts' => (int) env('VERIFY_MAX_ATTEMPTS', 5),

    /** verify.mn-ээс ирэх callback-ийг таних нууц түлхүүр. */
    'callback_secret' => env('VERIFY_CALLBACK_SECRET'),

];
