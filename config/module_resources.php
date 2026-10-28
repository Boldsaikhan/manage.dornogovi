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
            'baiguullaga' => 'АЗДТГ',
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
                'baiguullaga' => 'АЗДТГ',
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
            ['key' => 'user_name', 'label' => 'Овог нэр', 'single_line' => true, 'width' => '11%', 'edit' => 'person_name', 'edit_people' => true, 'edit_sync_position' => true],
            // Албан тушаал нь нэрээс хамаарна — гараар засахгүй.
            ['key' => 'user_position', 'label' => 'Албан тушаал', 'width' => '14%'],
            ['key' => 'destination', 'label' => 'Хаана', 'width' => '9%', 'edit' => 'destination'],
            ['key' => 'purpose', 'label' => 'Ямар ажлаар', 'width' => '19%', 'align' => 'left', 'edit' => 'purpose'],
            // Огноог зөвхөн цифрээр нь харьцуулж хайна.
            ['key' => 'start_date', 'label' => 'Хэзээнээс', 'width' => '8%', 'date' => true, 'edit' => 'start_date', 'edit_type' => 'date'],
            // Хоногийг бичихэд дуусах огноо нь дагаж тохирно.
            ['key' => 'day_count', 'label' => 'Хэд хоног', 'width' => '5%', 'edit' => 'days', 'edit_type' => 'number'],
            ['key' => 'order_number', 'label' => 'Тушаалын дугаар', 'width' => '7%', 'edit' => 'order_number'],
            ['key' => 'status', 'label' => 'Төлөв', 'from_options' => true, 'width' => '8%', 'edit' => 'status'],
            // Зардлыг тооцоо хийхийг зөвшөөрсөн албан хаагч — үнэмлэхийн ар талд гарна.
            ['key' => 'closed_by', 'label' => 'Хаах', 'single_line' => true, 'width' => '9%', 'edit' => 'closed_by', 'edit_people' => true],
            ['key' => 'approved_by', 'label' => 'Баталсан', 'single_line' => true, 'width' => '11%', 'inline_short' => true, 'edit' => 'approved_by'],
        ],
        'fields' => [
            // Сонголт нь утасны жагсаалтын «Удирдлага» ангиллаас бүрдэнэ.
            ['name' => 'approved_by', 'label' => 'Баталсан', 'type' => 'select', 'options_from' => 'assignment_leaders'],
            // Хаах — зардлыг тооцоо хийхийг зөвшөөрсөн албан хаагч (хэн ч байж болно).
            ['name' => 'closed_by', 'label' => 'Хаах', 'type' => 'text'],
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
        /*
         * «Хоосон мөр» нэмэхэд user_id-г үүсгэсэн админд холбохгүй —
         * нэмж буй хүн ихэвчлэн өөр хэн нэгний (бусад албан хаагчийн)
         * томилолтыг бүртгэдэг тул user_id админд холбогдвол «Овог нэр»
         * хараахан сонгоогүй атал админы ӨӨРИЙНХ нь нэр, албан тушаал
         * хүснэгтэд санамсаргүй харагддаг байв.
         */
        'on_create' => 'attach_department',
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
            ['key' => 'title', 'label' => 'Гарчиг', 'align' => 'left'],
            ['key' => 'published_at', 'label' => 'Нийтэлсэн', 'width' => '12%'],
            // Файл нь зөвхөн icon — нэр нь тайлбарт гарна.
            ['key' => 'file_label', 'label' => 'Файл', 'type' => 'file', 'icon_only' => true, 'width' => '6%'],
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
        // Баталсан албан тушаалтнаар нь ангилна.
        'scope_column' => 'scope',
        'scope_label' => 'Баталсан',
        'default_scope' => 'zasag_darga',
        'scopes' => [
            'zasag_darga' => 'Засаг дарга',
            'tamgyn_darga' => 'Тамгын дарга',
            'busad' => 'Бусад',
        ],
        // Албан ёсны «гэрээний бүртгэл» маягтын багана, дараалал.
        'row_number' => '№',
        'columns' => [
            ['key' => 'title', 'label' => 'Гэрээний нэр, утга агуулга', 'width' => '22%', 'align' => 'left', 'edit' => 'title'],
            ['key' => 'issued_on', 'label' => 'Гэрээ байгуулсан он сар', 'width' => '10%', 'date' => true, 'edit' => 'issued_on', 'edit_type' => 'date'],
            ['key' => 'number', 'label' => 'Гэрээний дугаар', 'width' => '9%', 'edit' => 'number'],
            ['key' => 'copies', 'label' => 'Хувь', 'width' => '5%', 'edit' => 'copies', 'edit_type' => 'number'],
            ['key' => 'prepared_by', 'label' => 'Боловсруулсан албан тушаалтан', 'width' => '13%', 'edit' => 'prepared_by', 'edit_people' => true],
            ['key' => 'approved_by_name', 'label' => 'Баталсан', 'width' => '12%', 'edit' => 'approved_by_name', 'edit_people' => true],
            ['key' => 'signed_by', 'label' => 'Төлөөлж байгуулсан', 'width' => '12%', 'edit' => 'signed_by', 'edit_people' => true],
            ['key' => 'note', 'label' => 'Хянаж зөвлөгөө өгсөн тэмдэглэл', 'width' => '12%', 'align' => 'left', 'edit' => 'note'],
            // Гэрээний эх хувь — мөр бүр дээрээ хавсаргана.
            ['key' => 'file_label', 'label' => 'Гэрээ', 'width' => '5%', 'type' => 'file', 'upload' => 'file'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Гэрээний нэр, утга агуулга', 'type' => 'text', 'required' => true],
            ['name' => 'issued_on', 'label' => 'Гэрээ байгуулсан он сар', 'type' => 'date', 'required' => true],
            ['name' => 'number', 'label' => 'Гэрээний дугаар', 'type' => 'text', 'required' => true],
            ['name' => 'copies', 'label' => 'Хувь', 'type' => 'number'],
            ['name' => 'prepared_by', 'label' => 'Боловсруулсан албан тушаалтан', 'type' => 'text'],
            ['name' => 'approved_by_name', 'label' => 'Баталсан', 'type' => 'text'],
            ['name' => 'signed_by', 'label' => 'Төлөөлж байгуулсан', 'type' => 'text'],
            ['name' => 'counterparty', 'label' => 'Харилцагч / тал', 'type' => 'text'],
            ['name' => 'note', 'label' => 'Хянаж зөвлөгөө өгсөн тэмдэглэл', 'type' => 'textarea'],
            ['name' => 'file', 'label' => 'Гэрээний файл (Word / PDF)', 'type' => 'file',
                'accept' => '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'mimes' => 'pdf,doc,docx', 'max_kb' => 20480,
                'folder' => 'contracts', 'store_as' => 'file_path', 'name_as' => 'file_name'],
        ],
        'defaults' => [],
        // Хоосон мөр нэмээд нүд бүр дээр нь дарж бөглөнө.
        'blank_row' => true,
        'audit_log' => true,
        'on_create' => 'attach_issuer',
    ],
    'legal_acts' => [
        'model' => App\Models\LegalAct::class,
        'title' => 'Эрх зүйн акт, тогтоол',
        'description' => 'Салбартаа хамаарах хууль, тогтоол, журмыг legalinfo.mn болон бусад эх сурвалжаас нэгтгэн бүртгэнэ.',
        'scope_column' => 'scope',
        'scope_label' => 'Актын төрөл',
        'default_scope' => 'huuli',
        'scopes' => [
            'huuli' => 'Хууль',
            'uih' => 'УИХ-ын тогтоол',
            'zasgiin_gazar' => 'Засгийн газрын тогтоол',
            'said' => 'Сайдын тушаал',
            'oron_nutag' => 'Орон нутгийн',
            'busad' => 'Бусад',
        ],
        // Табуудыг бүлэглэж харуулна.
        'scope_groups' => [
            'huuli_togtoomj' => [
                'label' => 'Хууль тогтоомж',
                'scopes' => ['huuli', 'uih', 'zasgiin_gazar', 'said', 'oron_nutag', 'busad'],
            ],
            'uzuulelt' => [
                'label' => 'Салбарын үзүүлэлт',
                'scopes' => [],
            ],
        ],
        // Өөр хуудас руу очих таб.
        'scope_links' => [
            [
                'label' => 'Суурь мэдээлэл',
                'route' => 'indicators.index',
                'group' => 'uzuulelt',
            ],
        ],
        'row_number' => '№',
        'columns' => [
            ['key' => 'title', 'label' => 'Актын нэр', 'width' => '28%', 'align' => 'left', 'edit' => 'title'],
            ['key' => 'number', 'label' => 'Дугаар', 'width' => '9%', 'edit' => 'number'],
            ['key' => 'approved_on', 'label' => 'Батлагдсан', 'width' => '10%', 'date' => true, 'edit' => 'approved_on', 'edit_type' => 'date'],
            ['key' => 'effective_on', 'label' => 'Мөрдөж эхэлсэн', 'width' => '10%', 'date' => true, 'edit' => 'effective_on', 'edit_type' => 'date'],
            ['key' => 'unit_name', 'label' => 'Хамаарах нэгж', 'width' => '15%', 'edit' => 'unit_name'],
            ['key' => 'note', 'label' => 'Тэмдэглэл', 'width' => '16%', 'align' => 'left', 'edit' => 'note'],
            // Эх сурвалж, хавсралт хоёулаа зөвхөн icon.
            ['key' => 'source_url', 'label' => 'Эх сурвалж', 'type' => 'link', 'icon_only' => true, 'width' => '6%', 'edit' => 'source_url'],
            ['key' => 'file_label', 'label' => 'Файл', 'type' => 'file', 'icon_only' => true, 'upload' => 'file', 'width' => '6%'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Актын нэр', 'type' => 'text', 'required' => true],
            ['name' => 'number', 'label' => 'Дугаар', 'type' => 'text'],
            ['name' => 'approved_on', 'label' => 'Батлагдсан огноо', 'type' => 'date'],
            ['name' => 'effective_on', 'label' => 'Мөрдөж эхэлсэн огноо', 'type' => 'date'],
            ['name' => 'unit_name', 'label' => 'Хамаарах нэгж', 'type' => 'text'],
            ['name' => 'source_url', 'label' => 'Эх сурвалжийн холбоос (legalinfo.mn гэх мэт)', 'type' => 'text'],
            ['name' => 'note', 'label' => 'Тэмдэглэл', 'type' => 'textarea'],
            ['name' => 'file', 'label' => 'Файл (Word / PDF)', 'type' => 'file',
                'accept' => '.pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'mimes' => 'pdf,doc,docx', 'max_kb' => 20480,
                'folder' => 'legal-acts', 'store_as' => 'file_path', 'name_as' => 'file_name'],
        ],
        'defaults' => [],
        // Хоосон мөр нэмээд нүдэн дээр нь бөглөнө.
        'blank_row' => true,
        'file_export' => true,
        'audit_log' => true,
        'on_create' => 'attach_creator',
        // Шинэ мөр нэмэхэд эх сурвалж руу хурдан очих холбоос.
        'external_link' => [
            'label' => 'legalinfo.mn',
            'url' => 'https://legalinfo.mn/mn',
            'title' => 'Эрх зүйн мэдээллийн нэгдсэн систем — шинэ акт хайх',
        ],
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
        'row_number' => '№',
        'columns' => [
            ['key' => 'title', 'label' => 'Үүрэг чиглэл', 'edit' => 'title'],
            ['key' => 'period', 'label' => 'Хугацаа', 'edit' => 'period'],
            ['key' => 'supervisor_department', 'label' => 'Хяналт тавих хэлтэс', 'edit' => 'supervisor_department'],
            ['key' => 'body', 'label' => 'Хэрэгжилт', 'edit' => 'body'],
        ],
        'fields' => [
            ['name' => 'title', 'label' => 'Үүрэг чиглэл', 'type' => 'textarea', 'required' => true],
            ['name' => 'period', 'label' => 'Хугацаа', 'type' => 'text'],
            // Сонголт нь утасны жагсаалтын «Хэлтэс» ангиллаас бүрдэнэ.
            ['name' => 'supervisor_department', 'label' => 'Хяналт тавих хэлтэс', 'type' => 'select', 'options_from' => 'heltes_departments'],
            ['name' => 'body', 'label' => 'Хэрэгжилт', 'type' => 'textarea'],
        ],
        'defaults' => ['status' => 'draft'],
        'on_create' => 'attach_creator_department',
        // Word/Excel файлаас төлөвлөгөөний мөрүүдийг оруулна.
        'file_import' => true,
        // Хүснэгтэд шинэ мөрийг хоосноор нэмж, нүдэн дээр нь бөглөнө.
        'blank_row' => true,
    ],
    'foreign-trips' => [
        'model' => App\Models\ForeignTrip::class,
        'title' => 'Гадаадад зорчих хүсэлт',
        'description' => 'Гадаад улсад албаны томилолтоор зорчих хүсэлтийг бүртгэж, маягтаар хэвлэнэ.',
        'scope_column' => 'scope',
        'scope_label' => 'Хамрах хүрээ',
        'default_scope' => 'baiguullaga',
        'scopes' => [
            'agentlag' => 'Агентлаг',
            'sum' => 'Сумд',
            'baiguullaga' => 'АЗДТГ',
        ],
        'row_number' => 'Д/д',
        'columns' => [
            ['key' => 'person_name', 'label' => 'Овог нэр', 'single_line' => true, 'width' => '9%', 'edit' => 'person_name', 'edit_people' => true, 'edit_sync_position' => true, 'edit_sync_org' => true],
            // Албан тушаал, байгууллага нь нэрээс хамаарна — гараар засахгүй.
            ['key' => 'position', 'label' => 'Албан тушаал', 'width' => '10%'],
            ['key' => 'org_name', 'label' => 'Байгууллага / хэлтэс', 'width' => '10%'],
            ['key' => 'destination_country', 'label' => 'Очих улс', 'width' => '7%', 'edit' => 'destination_country'],
            ['key' => 'start_date', 'label' => 'Эхлэх', 'width' => '6%', 'date' => true, 'edit' => 'start_date', 'edit_type' => 'date'],
            ['key' => 'end_date', 'label' => 'Дуусах', 'width' => '6%', 'date' => true, 'edit' => 'end_date', 'edit_type' => 'date'],
            ['key' => 'reason', 'label' => '2. Шалтгаан', 'width' => '12%', 'align' => 'left', 'edit' => 'reason'],
            ['key' => 'companions', 'label' => '3. Хамт зорчих хүний тоо', 'width' => '8%', 'edit' => 'companions', 'edit_type' => 'number'],
            ['key' => 'route', 'label' => '4. Зорчих чиглэл, маршрут', 'width' => '9%', 'align' => 'left', 'edit' => 'route'],
            ['key' => 'funding_source', 'label' => '5. Зардлын эх үүсвэр (төгрөг)', 'width' => '8%', 'edit' => 'funding_source', 'edit_type' => 'number'],
            ['key' => 'foreign_contact', 'label' => '6. Уулзах байгууллага, албан тушаалтан', 'width' => '9%', 'align' => 'left', 'edit' => 'foreign_contact'],
            ['key' => 'accommodation', 'label' => '7. Байрлах газар', 'width' => '6%', 'align' => 'left', 'edit' => 'accommodation'],
        ],
        'fields' => [
            ['name' => 'person_name', 'label' => 'Овог нэр', 'type' => 'text'],
            ['name' => 'position', 'label' => 'Албан тушаал', 'type' => 'text'],
            ['name' => 'org_name', 'label' => 'Байгууллага / хэлтэс', 'type' => 'text'],
            ['name' => 'destination_country', 'label' => 'Очих улс', 'type' => 'select', 'options' => \App\Support\Countries::options()],
            ['name' => 'start_date', 'label' => 'Эхлэх', 'type' => 'date'],
            ['name' => 'end_date', 'label' => 'Дуусах', 'type' => 'date'],
            ['name' => 'reason', 'label' => '2. Гадаад улсад зорчих болсон шалтгаан', 'type' => 'textarea'],
            ['name' => 'companions', 'label' => '3. Хамт зорчих хүний тоо', 'type' => 'number'],
            ['name' => 'route', 'label' => '4. Гадаад улсад зорчих чиглэл, маршрут', 'type' => 'textarea'],
            ['name' => 'funding_source', 'label' => '5. Зардлын эх үүсвэр (төгрөг)', 'type' => 'number'],
            ['name' => 'foreign_contact', 'label' => '6. Хилийн чанадад уулзах байгууллага, албан тушаалтан, иргэн', 'type' => 'textarea'],
            ['name' => 'accommodation', 'label' => '7. Байрлах газар', 'type' => 'textarea'],
        ],
        'row_actions' => [
            ['label' => 'Хүсэлт хэвлэх', 'url' => '/modules/foreign-trips/{id}/print', 'target' => '_blank'],
        ],
        // Хүснэгтэд шинэ мөрийг хоосноор нэмж, нүдэн дээр нь бөглөнө.
        'blank_row' => true,
        'file_import' => true,
        'file_export' => true,
        'audit_log' => true,
        'on_create' => 'attach_user_department',
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
