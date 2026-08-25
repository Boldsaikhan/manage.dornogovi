<?php

/**
 * Модуль бүрийн жагсаалт/форм тохиргоо.
 */
return [
    'leaves' => [
        'model' => App\Models\Leave::class,
        'title' => 'Чөлөөний бүртгэл',
        'description' => 'Албан хаагчдын чөлөө, амралтын бүртгэл.',
        'scope_column' => 'scope',
        'scope_label' => 'Хамрах хүрээ',
        'scopes' => [
            'agentlag' => 'Агентлаг',
            'sum' => 'Сумд',
            'baiguullaga' => 'Байгууллага',
        ],
        'columns' => [
            ['key' => 'person_label', 'label' => 'Албан хаагч'],
            ['key' => 'scope', 'label' => 'Хамрах хүрээ'],
            ['key' => 'org_name', 'label' => 'Агентлаг / сум / байгууллага'],
            ['key' => 'type', 'label' => 'Төрөл'],
            ['key' => 'start_date', 'label' => 'Эхлэх'],
            ['key' => 'end_date', 'label' => 'Дуусах'],
            ['key' => 'status', 'label' => 'Төлөв'],
        ],
        'fields' => [
            ['name' => 'scope', 'label' => 'Хамрах хүрээ', 'type' => 'select', 'required' => true, 'options' => [
                'agentlag' => 'Агентлаг', 'sum' => 'Сумд', 'baiguullaga' => 'Байгууллага',
            ]],
            ['name' => 'org_name', 'label' => 'Агентлаг / сум / байгууллага', 'type' => 'directory_org', 'scope_field' => 'scope'],
            ['name' => 'person_name', 'label' => 'Дарга / албан хаагч', 'type' => 'directory_person', 'depends_on' => 'org_name'],
            ['name' => 'slip_number', 'label' => 'Хуудасны №', 'type' => 'text'],
            ['name' => 'signer', 'label' => 'Гарын үсэг', 'type' => 'select', 'options' => [
                'acting' => 'Даргын албан үүргийг түр орлон гүйцэтгэгч',
                'head' => 'Хэлтсийн дарга',
            ]],
            ['name' => 'type', 'label' => 'Төрөл', 'type' => 'select', 'options' => [
                'tsalintai' => 'Цалинтай', 'tsalingui' => 'Цалингүй', 'eeljiin' => 'Ээлжийн амралтаас',
            ]],
            ['name' => 'start_date', 'label' => 'Эхлэх', 'type' => 'date', 'required' => true],
            ['name' => 'days', 'label' => 'Ажлын өдөр', 'type' => 'number', 'required' => true],
            ['name' => 'reason', 'label' => 'Үндэслэл', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Төлөв', 'type' => 'select', 'options' => [
                'pending' => 'Хүлээгдэж буй', 'approved' => 'Зөвшөөрсөн', 'rejected' => 'Татгалзсан',
            ]],
        ],
        'row_actions' => [
            ['label' => 'Чөлөөний хуудас', 'url' => '/modules/leaves/{id}/slip', 'target' => '_blank'],
        ],
        'defaults' => ['status' => 'approved', 'type' => 'tsalintai', 'scope' => 'baiguullaga', 'signer' => 'acting'],
        'on_create' => 'attach_user_department',
    ],
    'assignments' => [
        'model' => App\Models\TravelAssignment::class,
        'title' => 'Томилолтын бүртгэл',
        'description' => 'Албан томилолтын бүртгэл, хяналт.',
        'columns' => [
            ['key' => 'user_name', 'label' => 'Албан хаагч'],
            ['key' => 'destination', 'label' => 'Очих газар'],
            ['key' => 'start_date', 'label' => 'Эхлэх'],
            ['key' => 'end_date', 'label' => 'Дуусах'],
            ['key' => 'order_number', 'label' => 'Тушаалын дугаар'],
            ['key' => 'status', 'label' => 'Төлөв'],
        ],
        'fields' => [
            ['name' => 'destination', 'label' => 'Очих газар', 'type' => 'text', 'required' => true],
            ['name' => 'purpose', 'label' => 'Зорилго', 'type' => 'text'],
            ['name' => 'start_date', 'label' => 'Эхлэх', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => 'Дуусах', 'type' => 'date', 'required' => true],
            ['name' => 'order_number', 'label' => 'Тушаалын дугаар', 'type' => 'text'],
            ['name' => 'note', 'label' => 'Тэмдэглэл', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Төлөв', 'type' => 'select', 'options' => [
                'pending' => 'Хүлээгдэж буй', 'approved' => 'Зөвшөөрсөн', 'done' => 'Дууссан',
            ]],
        ],
        'defaults' => ['status' => 'pending'],
        'on_create' => 'attach_user_department',
    ],
    'regulations' => [
        'model' => App\Models\Regulation::class,
        'title' => 'Дотоод журам',
        'description' => 'Дотоод журам болон бусад журамтай танилцах.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'category', 'label' => 'Ангилал'],
            ['key' => 'published_at', 'label' => 'Нийтэлсэн'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'category', 'label' => 'Ангилал', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Агуулга', 'type' => 'textarea'],
            ['name' => 'published_at', 'label' => 'Нийтэлсэн огноо', 'type' => 'datetime'],
        ],
        'defaults' => [],
        'on_create' => 'attach_creator',
    ],
    'decrees' => [
        'model' => App\Models\Decree::class,
        'title' => 'Захирамж, тушаал',
        'description' => 'Захирамж, тушаалын тусдаа бүртгэл болон хэвлэмэл хуудасны нийт бүртгэл.',
        // Хуудас DecreeController-оор ажиллана.
        'columns' => [],
        'fields' => [],
        'defaults' => [],
    ],
    'contracts' => [
        'model' => App\Models\Contract::class,
        'title' => 'Гэрээний дугаар',
        'description' => 'Гэрээний дугаар авах, бүртгэх.',
        'columns' => [
            ['key' => 'number', 'label' => 'Дугаар'],
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'counterparty', 'label' => 'Талууд'],
            ['key' => 'issued_on', 'label' => 'Олгосон'],
        ],
        'fields' => [
            ['name' => 'number', 'label' => 'Дугаар', 'type' => 'text', 'required' => true],
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'counterparty', 'label' => 'Харилцагч / тал', 'type' => 'text'],
            ['name' => 'issued_on', 'label' => 'Огноо', 'type' => 'date', 'required' => true],
            ['name' => 'note', 'label' => 'Тэмдэглэл', 'type' => 'textarea'],
        ],
        'defaults' => [],
        'on_create' => 'attach_issuer',
    ],
    'archives' => [
        'model' => App\Models\Archive::class,
        'title' => 'Архивын мэдээлэл',
        'description' => 'Архивын баримт, мэдээллийн бүртгэл.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'category', 'label' => 'Ангилал'],
            ['key' => 'year', 'label' => 'Он'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'category', 'label' => 'Ангилал', 'type' => 'text'],
            ['name' => 'year', 'label' => 'Он', 'type' => 'number'],
            ['name' => 'description', 'label' => 'Тайлбар', 'type' => 'textarea'],
        ],
        'defaults' => [],
        'on_create' => 'attach_creator',
    ],
    'doc_standards' => [
        'model' => App\Models\DocumentStandard::class,
        'title' => 'Бичиг хэргийн стандарт',
        'description' => 'Албан бичгийн стандарт, загвар, заавар.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'sort_order', 'label' => 'Дараалал'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'body', 'label' => 'Агуулга', 'type' => 'textarea'],
            ['name' => 'sort_order', 'label' => 'Дараалал', 'type' => 'number'],
        ],
        'defaults' => ['sort_order' => 0],
    ],
    'plans' => [
        'model' => App\Models\Plan::class,
        'title' => 'Төлөвлөгөө',
        'description' => 'Хэлтэс, байгууллагын төлөвлөгөө оруулах.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'year', 'label' => 'Он'],
            ['key' => 'period', 'label' => 'Хугацаа'],
            ['key' => 'status', 'label' => 'Төлөв'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'year', 'label' => 'Он', 'type' => 'number'],
            ['name' => 'period', 'label' => 'Хугацаа', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Агуулга', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Төлөв', 'type' => 'select', 'options' => [
                'draft' => 'Ноорог', 'active' => 'Хэрэгжиж буй', 'done' => 'Дууссан',
            ]],
        ],
        'defaults' => ['status' => 'draft'],
        'on_create' => 'attach_creator_department',
    ],
    'meetings' => [
        'model' => App\Models\Meeting::class,
        'title' => 'Хурлын тэмдэглэл',
        'description' => 'Хурлын тэмдэглэлийг бүртгэж, автоматжуулах суурь.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'held_at', 'label' => 'Огноо'],
            ['key' => 'status', 'label' => 'Төлөв'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'held_at', 'label' => 'Хэзээ', 'type' => 'datetime'],
            ['name' => 'minutes', 'label' => 'Тэмдэглэл', 'type' => 'textarea'],
            ['name' => 'transcript', 'label' => 'Бичлэг / транскрипт', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Төлөв', 'type' => 'select', 'options' => [
                'draft' => 'Ноорог', 'final' => 'Батлагдсан',
            ]],
        ],
        'defaults' => ['status' => 'draft'],
        'on_create' => 'attach_creator',
    ],
    'reports' => [
        'model' => App\Models\Report::class,
        'title' => 'Тайлан мэдээлэл',
        'description' => 'Тайлан, мэдээллийн цэс.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'period', 'label' => 'Хугацаа'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'period', 'label' => 'Хугацаа', 'type' => 'text'],
            ['name' => 'body', 'label' => 'Агуулга', 'type' => 'textarea'],
        ],
        'defaults' => [],
        'on_create' => 'attach_creator_department',
    ],
    'onboarding' => [
        'model' => App\Models\Training::class,
        'title' => 'Гарын авлага, сургалт',
        'description' => 'Шинэ албан хаагчдад өгөх гарын авлага, богино сургалт.',
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'for_new_hires', 'label' => 'Шинэ АХ'],
            ['key' => 'sort_order', 'label' => 'Дараалал'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'body', 'label' => 'Агуулга', 'type' => 'textarea'],
            ['name' => 'for_new_hires', 'label' => 'Шинэ албан хаагчид', 'type' => 'checkbox'],
            ['name' => 'sort_order', 'label' => 'Дараалал', 'type' => 'number'],
        ],
        'defaults' => ['for_new_hires' => true, 'sort_order' => 0],
    ],
];
