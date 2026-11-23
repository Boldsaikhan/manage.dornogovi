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
    'whisper_model' => env('AI_WHISPER_MODEL', 'whisper-1'),
    'whisper_timeout' => (int) env('AI_WHISPER_TIMEOUT', 180),
    'whisper_max_mb' => (int) env('AI_WHISPER_MAX_MB', 25),
    // Амьд текст бичилтэд аудиог хэдэн секундээр хэсэглэх.
    'whisper_chunk_seconds' => (int) env('AI_WHISPER_CHUNK_SECONDS', 30),
    // Whisper API mn кодыг дэмжихгүй тул prompt-оор монгол руу чиглүүлнэ.
    'whisper_prompt' => env(
        'AI_WHISPER_PROMPT',
        'Энэ бол монгол хэлээр яригдсан албан хурлын бичлэг. '
        .'Зөвхөн сонсогдсон монгол үгийг кирилл үсгээр бич. '
        .'Англиар бүү орчуул, үг бүү зохио, үг бүү давта.',
    ),
    // WMA/ASF → MP3 хөрвүүлэхэд. Хоосон бол PATH /usr/bin/ffmpeg хайна.
    'ffmpeg_path' => env('AI_FFMPEG_PATH', ''),
    'max_history_messages' => 12,
    'max_tool_results' => 12,
];
