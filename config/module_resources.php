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
            'udirdlaga' => 'Аймгийн удирдлагууд',
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
                'udirdlaga' => 'Аймгийн удирдлагууд',
                'agentlag' => 'Агентлаг',
                'sum' => 'Сумд',
                'baiguullaga' => 'Байгууллага',
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
        'description' => 'Албан томилолтын удирдамж — батлах албан тушаалтнаар нь бүртгэж, маягтаар хэвлэнэ.',
        // «БАТЛАВ» хэсэгт хэн гарын үсэг зурахаар нь тусад нь бүртгэнэ.
        'scope_column' => 'approver',
        'scope_label' => 'Батлах албан тушаалтан',
        'default_scope' => 'governor',
        'scopes' => [
            'governor' => 'Засаг дарга',
            'chief' => 'Тамгын газрын дарга',
        ],
        // Цаасан бүртгэлийн маягттай ижил толгой.
        'row_number' => 'Д/д',
        'columns' => [
            /*
             * «edit» нь тухайн нүдийг засах горимд шууд бөглөх талбарыг
             * заана — мөр нэмээд хүснэгтэн дээрээ бөглөнө.
             */
            // Нэр нь хоёр мөр болж хуваагдахгүй — нэг мөрөнд багтана.
            ['key' => 'user_name', 'label' => 'Овог нэр', 'single_line' => true, 'width' => '11%', 'edit' => 'person_name', 'edit_people' => true],
            ['key' => 'user_position', 'label' => 'Албан тушаал', 'width' => '14%', 'edit' => 'position'],
            ['key' => 'destination', 'label' => 'Хаана', 'width' => '9%', 'edit' => 'destination'],
            ['key' => 'purpose', 'label' => 'Ямар ажлаар', 'width' => '19%', 'align' => 'left', 'edit' => 'purpose'],
            // Огноог зөвхөн цифрээр нь харьцуулж хайна.
            ['key' => 'start_date', 'label' => 'Хэзээнээс', 'width' => '8%', 'date' => true, 'edit' => 'start_date', 'edit_type' => 'date'],
            ['key' => 'day_count', 'label' => 'Хэд хоног', 'width' => '5%'],
            ['key' => 'order_number', 'label' => 'Тушаалын дугаар', 'width' => '7%', 'edit' => 'order_number'],
            ['key' => 'status', 'label' => 'Төлөв', 'from_options' => true, 'width' => '8%', 'edit' => 'status'],
            ['key' => 'approved_by', 'label' => 'Баталсан', 'single_line' => true, 'width' => '11%', 'inline_short' => true, 'edit' => 'approved_by'],
        ],
        'fields' => [
            // Сонголт нь утасны жагсаалтын «Удирдлага» ангиллаас бүрдэнэ.
            ['name' => 'approved_by', 'label' => 'Баталсан', 'type' => 'select', 'options_from' => 'assignment_leaders'],
            // Томилолт авч буй албан хаагч — утасны жагсаалтаас сонгоно.
            ['name' => 'person_name', 'label' => 'Овог нэр', 'type' => 'text'],
            ['name' => 'position', 'label' => 'Албан тушаал', 'type' => 'text'],
            ['name' => 'destination', 'label' => 'Очих газар', 'type' => 'text', 'required' => true],
            ['name' => 'purpose', 'label' => '1. Зорилго', 'type' => 'textarea'],
            ['name' => 'composition', 'label' => '2. Бүрэлдэхүүн', 'type' => 'textarea'],
            ['name' => 'start_date', 'label' => '3. Хугацаа — эхлэх', 'type' => 'date', 'required' => true],
            ['name' => 'end_date', 'label' => '3. Хугацаа — дуусах', 'type' => 'date', 'required' => true],
            ['name' => 'scope_of_work', 'label' => '4. Томилолтын хүрээнд /ажлын чиглэл/', 'type' => 'textarea'],
            ['name' => 'order_number', 'label' => 'Тушаалын дугаар', 'type' => 'text'],
            ['name' => 'report', 'label' => 'Томилолтын тайлан', 'type' => 'textarea'],
            // Үнэмлэхийн (ар талын) догол мөр — гараар бичнэ.
            ['name' => 'certificate_text', 'label' => 'Үнэмлэхийн бичвэр', 'type' => 'textarea'],
            ['name' => 'note', 'label' => 'Тэмдэглэл', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Төлөв', 'type' => 'select', 'options' => [
                'pending' => 'Хүлээгдэж буй', 'approved' => 'Зөвшөөрсөн', 'done' => 'Дууссан',
            ]],
        ],
        'row_actions' => [
            ['label' => 'Удирдамж хэвлэх', 'url' => '/modules/assignments/{id}/sheet', 'target' => '_blank'],
        ],
        // Шинэ бүртгэлийг A4 маягтын хэлбэрээр бөглөнө.
        'form_layout' => 'assignment_sheet',
        // Цаасан бүртгэлийг Excel/Word файлаас оруулна.
        'file_import' => true,
        // Сонгосон мөрүүдийг Excel/Word/PDF-ээр татна.
        'file_export' => true,
        // Хүснэгтэд шинэ мөрийг хоосноор нэмж, нүдэн дээр нь бөглөнө.
        'blank_row' => true,
        // Өөрчлөлтийн түүх хөтөлнө.
        'audit_log' => true,
        'defaults' => ['status' => 'pending'],
        'on_create' => 'attach_user_department',
    ],
    'regulations' => [
        'model' => App\Models\Regulation::class,
        'title' => 'Дотоод журам',
        'description' => 'Журмыг Word эсвэл PDF-ээр оруулж, табаар харна. Мөр дээр дарж бүтэн дэлгэцэнд нээнэ.',
        'scope_column' => 'category',
        'scope_label' => 'Ангилал',
        'hide_all_scope' => true,
        'default_scope' => 'internal',
        'dynamic_scopes' => true,
        'columns' => [
            ['key' => 'title', 'label' => 'Гарчиг'],
            ['key' => 'file_label', 'label' => 'Файл', 'type' => 'file'],
            ['key' => 'published_at', 'label' => 'Нийтэлсэн'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гарчиг', 'type' => 'text', 'required' => true],
            ['name' => 'file', 'label' => 'Файл (Word / PDF)', 'type' => 'file', 'required' => true,
                'accept' => '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'mimes' => 'pdf,doc,docx', 'max_kb' => 20480,
                'folder' => 'regulations', 'store_as' => 'file_path', 'name_as' => 'file_name'],
            ['name' => 'published_at', 'label' => 'Нийтэлсэн огноо', 'type' => 'datetime'],
        ],
        'defaults' => [],
        'on_create' => 'attach_creator',
    ],
    'decrees' => [
        'model' => App\Models\Decree::class,
        'title' => 'Захирамж, тушаал',
        'description' => 'Бланкны дугаар болон захирамж/тушаалын дугаарын тусдаа бүртгэл.',
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
