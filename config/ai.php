<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AI Assistant
    |--------------------------------------------------------------------------
    |
    | API түлхүүр frontend-д хэзээ ч илгээгдэхгүй. Зөвхөн backend → provider.
    | Тохиргоог Системийн тохиргоо → Manage AI хэсэгт хадгална (app_settings).
    |
    */
    'default_provider' => env('AI_PROVIDER', 'local'),
    'openai_base_url' => env('AI_OPENAI_BASE_URL', 'https://api.openai.com/v1'),
    'openai_timeout' => (int) env('AI_OPENAI_TIMEOUT', 45),
    'max_history_messages' => 12,
    'max_tool_results' => 12,
];
