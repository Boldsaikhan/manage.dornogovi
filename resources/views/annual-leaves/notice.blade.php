<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ээлжийн амралт олгох тухай мэдэгдэл — {{ $annualLeave->person_name }}</title>
    <style>
        /*
         * Margin-ийг зөвхөн .page-ийн padding-аар зурна — @page дээр давхар
         * margin өгвөл (0 биш) хуудасны бодит хэмжээ А4-аас давж, хэвлэгч
         * бүхэл хуудсыг багасгаж хэвлэдэг байсан (margin+padding давхардна).
         */
        @page {
            size: {{ $format['width'] }}mm {{ $format['height'] }}mm;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "{{ $format['font'] }}", Arial, Helvetica, sans-serif;
            font-size: {{ $format['size'] }}pt;
            line-height: {{ $format['spacing'] }};
            color: #000;
            background: #e2e8f0;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #cbd5e1;
            font-size: 13px;
        }

        .toolbar a, .toolbar button {
            border: 1px solid #cbd5e1;
            background: #fff;
            border-radius: 8px;
            padding: 6px 12px;
            cursor: pointer;
            color: #1c55a5;
            text-decoration: none;
            font: inherit;
        }

        .toolbar .primary { background: #1c55a5; border-color: #1c55a5; color: #fff; }
        .toolbar .active { border-color: #1c55a5; font-weight: 700; }

        .page {
            width: {{ $format['width'] }}mm;
            min-height: {{ $format['height'] }}mm;
            margin: 16px auto;
            padding: {{ $format['top'] }}mm {{ $format['right'] }}mm {{ $format['bottom'] }}mm {{ $format['left'] }}mm;
            background: #fff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .12);
        }

        .notice + .notice {
            margin-top: 14mm;
            padding-top: 14mm;
            border-top: 1px dashed #cbd5e1;
        }

        /*
         * 2 хувь хэвлэхэд хуудсыг яг голоор нь хайчилж тасдах боломжтой
         * байхын тулд хоёр хувийг хуудасны агуулгын өндрийг яг тэнцүү
         * хуваасан өндөртэй болгоно (контент хэт урт бол tasarна).
         */
        .page--split {
            height: {{ $format['height'] }}mm;
            display: flex;
            flex-direction: column;
        }

        .page--split .notice {
            flex: 1 1 50%;
            min-height: 0;
            overflow: hidden;
        }

        .page--split .notice + .notice {
            margin-top: 0;
            padding-top: 6mm;
        }

        .page--split .notice:first-child {
            padding-bottom: 6mm;
        }

        .notice h1 {
            font-size: 12pt;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            margin: 0 0 8mm;
        }

        .notice .meta {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6mm;
        }

        .notice .meta .dots {
            display: inline-block;
            min-width: 24mm;
            border-bottom: 1px dotted #000;
        }

        .notice .meta .filled {
            display: inline-block;
            min-width: 24mm;
            border-bottom: 1px dotted #000;
            font-weight: 700;
            text-align: center;
        }

        .notice .body {
            margin: 0 0 2mm;
            text-align: justify;
            text-indent: 8mm;
        }

        /* Хуудсан дээрээ засах — хэвлэхэд ул мөр үлдэхгүй. */
        .notice .body[contenteditable='true'] {
            outline: 1px dashed #94a3b8;
            outline-offset: 2mm;
            min-height: 12mm;
            cursor: text;
        }

        .notice .body[contenteditable='true']:focus { outline-color: #1c55a5; }

        .notice .approver {
            margin-top: 4mm;
            margin-bottom: 5mm;
            text-align: center;
        }

        .notice .approver-label {
            margin-bottom: 1mm;
        }

        /* Тушаал — том үсгээр, нэрээс ялгарахаар энгийн жинтэй. */
        .notice .approver-title {
            text-transform: uppercase;
            font-weight: normal;
        }

        /* Нэр — тушаалаас тодоор ялгарч харагдана. */
        .notice .approver-name {
            margin-top: 1mm;
            font-weight: 700;
        }

        .notice .approver-title[contenteditable='true'],
        .notice .approver-name[contenteditable='true'] {
            display: inline-block;
            outline: 1px dashed #94a3b8;
            outline-offset: 1.5mm;
            min-width: 50mm;
            cursor: text;
        }

        .notice .approver-title[contenteditable='true']:focus,
        .notice .approver-name[contenteditable='true']:focus { outline-color: #1c55a5; }

        .notice .body-edit {
            margin: 0 0 8mm;
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #64748b;
        }

        .notice .body-edit button {
            padding: 4px 12px;
            border: 1px solid #1c55a5;
            border-radius: 6px;
            background: #1c55a5;
            color: #fff;
            font-size: 11px;
            cursor: pointer;
        }

        .notice .body-edit button[disabled] { opacity: .6; cursor: default; }

        .notice .body-edit button.ghost { background: #fff; color: #1c55a5; }

        .notice .approver .body-edit { justify-content: center; }

        .notice .sign {
            margin-top: 4mm;
            margin-bottom: 5mm;
        }

        .notice .sign .row {
            display: flex;
            align-items: flex-end;
        }

        /*
         * Тушаалын багана тогтмол өргөнтэй — текст 1 мөр, 2 мөр ямар ч
         * байсан нэр үргэлж яг ижил зайд эхэлнэ (гарын үсэг зурах зай
         * тушаалын урт/богиноос хамаарахгүйгээр тооцогдоно).
         */
        .notice .sign .title {
            flex: 0 0 70mm;
            max-width: 70mm;
            text-align: center;
            text-transform: uppercase;
            font-weight: normal;
        }

        .notice .sign .name {
            flex: 0 0 auto;
            margin-left: 20mm;
            white-space: nowrap;
        }

        @media print {
            body { background: #fff; }
            .toolbar, .body-edit { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            .notice .body[contenteditable='true'] { outline: none; }
            .notice .approver-title[contenteditable='true'],
            .notice .approver-name[contenteditable='true'] { outline: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Хэвлэх</button>
        <span>Нэг A4 хуудсанд:</span>
        @foreach ([1, 2] as $option)
            <a href="{{ request()->fullUrlWithQuery(['copies' => $option]) }}" class="{{ $copies === $option ? 'active' : '' }}">{{ $option }}</a>
        @endforeach
    </div>

    <div class="page {{ $copies === 2 ? 'page--split' : '' }}">
        @for ($i = 0; $i < $copies; $i++)
            <div class="notice">
                <h1>Ээлжийн амралт олгох тухай мэдэгдэл</h1>

                <div class="meta">
                    <span>
                        <span class="filled">{{ $registeredOn?->format('Y') }}</span> оны
                        <span class="filled">{{ $registeredOn?->format('n') }}</span>-р сарын
                        <span class="filled">{{ $registeredOn?->format('j') }}</span>-ны өдөр
                    </span>
                    <span>Дугаар <span class="filled">{{ $number }}</span></span>
                </div>

                @if ($i === 0 && $canEdit)
                    <p
                        class="body"
                        contenteditable="true"
                        id="notice-body"
                        spellcheck="false"
                    >{{ $text }}</p>
                    <div class="body-edit">
                        <button type="button" id="notice-save">Хадгалах</button>
                        <button type="button" id="notice-reset" class="ghost">Дахин үүсгэх</button>
                        <span id="notice-status">Бичвэр дээр дарж засна.</span>
                    </div>
                @else
                    <p class="body" data-notice-copy>{{ $text ?: '……………………………………………………………………………………………………' }}</p>
                @endif

                <div class="approver">
                    <div class="approver-label">Зөвшөөрсөн:</div>
                    @if ($i === 0 && $canEdit)
                        <div
                            class="approver-title"
                            contenteditable="true"
                            id="approver-title"
                            spellcheck="false"
                        >{{ implode(' ', $approverLines) }}</div>
                        <div
                            class="approver-name"
                            contenteditable="true"
                            id="approver-name"
                            spellcheck="false"
                        >{{ $approverName }}</div>
                        <div class="body-edit">
                            <button type="button" id="approver-save">Хадгалах</button>
                            <button type="button" id="approver-reset" class="ghost">Дахин үүсгэх</button>
                            <span id="approver-status">Бичвэр дээр дарж засна.</span>
                        </div>
                    @else
                        <div class="approver-title" data-approver-title-copy>{{ implode(' ', $approverLines) }}</div>
                        <div class="approver-name" data-approver-name-copy>{{ $approverName }}</div>
                    @endif
                </div>

                <div class="sign">
                    <div class="row">
                        <span class="title">{{ $ownPositionLine }}</span>
                        <span class="name">{{ $annualLeave->person_name }}</span>
                    </div>
                </div>
            </div>
        @endfor
    </div>

    <script>
        const body = document.getElementById('notice-body');
        const saveBtn = document.getElementById('notice-save');
        const resetBtn = document.getElementById('notice-reset');
        const status = document.getElementById('notice-status');
        // Хоёр дахь хувь байвал зэрэг шинэчилнэ — хуудас дахин ачаалахгүй.
        const otherCopies = document.querySelectorAll('[data-notice-copy]');

        const send = (text) => fetch(@json(route('annual-leaves.notice.text', $annualLeave)), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ notice_text: text }),
        });

        if (body && saveBtn) {
            let saved = body.innerText.trim();

            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                status.textContent = 'Хадгалж байна…';

                try {
                    const text = body.innerText.trim();
                    const response = await send(text);

                    if (response.ok) {
                        saved = text;
                        otherCopies.forEach((el) => { el.textContent = text; });
                        status.textContent = 'Хадгаллаа.';
                    } else {
                        status.textContent = 'Хадгалж чадсангүй.';
                    }
                } catch (e) {
                    status.textContent = 'Сүлжээгүй байна.';
                } finally {
                    saveBtn.disabled = false;
                }
            });

            resetBtn.addEventListener('click', async () => {
                if (! confirm('Бичвэрийг бүртгэлийн мэдээллээс дахин үүсгэх үү?')) return;

                resetBtn.disabled = true;
                status.textContent = 'Дахин үүсгэж байна…';

                try {
                    const response = await send('');

                    if (! response.ok) {
                        status.textContent = 'Дахин үүсгэж чадсангүй.';
                        resetBtn.disabled = false;

                        return;
                    }

                    saved = '';
                    location.reload();
                } catch (e) {
                    status.textContent = 'Сүлжээгүй байна.';
                    resetBtn.disabled = false;
                }
            });

            body.addEventListener('input', () => {
                status.textContent = body.innerText.trim() === saved
                    ? 'Бичвэр дээр дарж засна.'
                    : 'Хадгалаагүй өөрчлөлт байна.';
            });

            // Хадгалаагүй байхад хуудсаас гарахаас сэргийлнэ.
            window.addEventListener('beforeunload', (event) => {
                if (body.innerText.trim() !== saved) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }

        const approverTitle = document.getElementById('approver-title');
        const approverName = document.getElementById('approver-name');
        const approverSaveBtn = document.getElementById('approver-save');
        const approverResetBtn = document.getElementById('approver-reset');
        const approverStatus = document.getElementById('approver-status');
        const otherApproverTitles = document.querySelectorAll('[data-approver-title-copy]');
        const otherApproverNames = document.querySelectorAll('[data-approver-name-copy]');

        const sendApprover = (title, name) => fetch(@json(route('annual-leaves.notice.approver', $annualLeave)), {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify({ approver_title: title, approver_name: name }),
        });

        if (approverTitle && approverName && approverSaveBtn) {
            let savedApproverTitle = approverTitle.innerText.trim();
            let savedApproverName = approverName.innerText.trim();

            approverSaveBtn.addEventListener('click', async () => {
                approverSaveBtn.disabled = true;
                approverStatus.textContent = 'Хадгалж байна…';

                try {
                    const title = approverTitle.innerText.trim();
                    const name = approverName.innerText.trim();
                    const response = await sendApprover(title, name);

                    if (response.ok) {
                        savedApproverTitle = title;
                        savedApproverName = name;
                        otherApproverTitles.forEach((el) => { el.textContent = title; });
                        otherApproverNames.forEach((el) => { el.textContent = name; });
                        approverStatus.textContent = 'Хадгаллаа.';
                    } else {
                        approverStatus.textContent = 'Хадгалж чадсангүй.';
                    }
                } catch (e) {
                    approverStatus.textContent = 'Сүлжээгүй байна.';
                } finally {
                    approverSaveBtn.disabled = false;
                }
            });

            approverResetBtn.addEventListener('click', async () => {
                if (! confirm('Бичвэрийг бүртгэлийн мэдээллээс дахин үүсгэх үү?')) return;

                approverResetBtn.disabled = true;
                approverStatus.textContent = 'Дахин үүсгэж байна…';

                try {
                    const response = await sendApprover('', '');

                    if (! response.ok) {
                        approverStatus.textContent = 'Дахин үүсгэж чадсангүй.';
                        approverResetBtn.disabled = false;

                        return;
                    }

                    savedApproverTitle = '';
                    savedApproverName = '';
                    location.reload();
                } catch (e) {
                    approverStatus.textContent = 'Сүлжээгүй байна.';
                    approverResetBtn.disabled = false;
                }
            });

            const onApproverInput = () => {
                const unchanged = approverTitle.innerText.trim() === savedApproverTitle
                    && approverName.innerText.trim() === savedApproverName;

                approverStatus.textContent = unchanged
                    ? 'Бичвэр дээр дарж засна.'
                    : 'Хадгалаагүй өөрчлөлт байна.';
            };

            approverTitle.addEventListener('input', onApproverInput);
            approverName.addEventListener('input', onApproverInput);

            window.addEventListener('beforeunload', (event) => {
                if (approverTitle.innerText.trim() !== savedApproverTitle
                    || approverName.innerText.trim() !== savedApproverName) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
    </script>
</body>
</html>
