<?php

/**
 * Файлын сан — зөвшөөрөгдсөн төрөл, хэмжээ.
 */
return [
    /** Байршуулах дээд хэмжээ (KB). */
    'max_kb' => (int) env('FILE_LIBRARY_MAX_KB', 20480),

    /** Storage дээрх үндсэн хавтас (local disk). */
    'folder' => 'file-library',

    /**
     * Зөвшөөрөгдсөн өргөтгөл.
     *
     * @var list<string>
     */
    'extensions' => [
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'txt', 'csv',
        'png', 'jpg', 'jpeg', 'gif', 'webp',
        'zip', 'rar', '7z',
    ],
];
