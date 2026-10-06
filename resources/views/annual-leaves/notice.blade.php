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

        /* Өгүүлбэр — урд талдаа таб зайтай, 2 талдаа тэнцүүлсэн (justify). */
        .notice .body {
            margin: 0 0 4mm;
            white-space: pre-wrap;
            text-align: justify;
            text-indent: 8mm;
        }

        /* Зөвшөөрсөн хэсэг + гарын үсэг — голлуулсан. */
        .notice .signatures-wrap {
            margin: 0 0 2mm;
        }

        .notice .signatures-header {
            text-align: center;
            margin-bottom: 3mm;
        }

        /*
         * Тушаал, нэрийн баганыг grid-ээр зохионо — багана тус бүр хамгийн
         * өргөн агуулгадаа тохируулан өргөнждөг тул хоёр мөрийн тушаал
         * (баруун талдаа), нэр (баруун талдаа) тус бүр нэг шугаманд
         * зэрэгцэнэ.
         */
        .notice .signatures-grid {
            display: grid;
            grid-template-columns: max-content max-content;
            column-gap: 10mm;
            row-gap: 3mm;
            justify-content: center;
        }

        .notice .signatures-grid .sig-title,
        .notice .signatures-grid .sig-name {
            text-align: right;
            white-space: pre-wrap;
        }

        .notice .signatures-grid .sig-spacer {
            grid-column: 1 / -1;
            height: 1mm;
        }

        /* Хуудсан дээрээ засах — хэвлэхэд ул мөр үлдэхгүй. */
        .notice .body[contenteditable='true'],
        .notice .sig-title[contenteditable='true'],
        .notice .sig-name[contenteditable='true'] {
            outline: 1px dashed #94a3b8;
            outline-offset: 1mm;
            cursor: text;
        }

        .notice .body[contenteditable='true'] { min-height: 12mm; outline-offset: 2mm; }

        .notice .body[contenteditable='true']:focus,
        .notice .sig-title[contenteditable='true']:focus,
        .notice .sig-name[contenteditable='true']:focus { outline-color: #1c55a5; }

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

        @media print {
            body { background: #fff; }
            .toolbar, .body-edit { display: none !important; }
            .page { margin: 0; box-shadow: none; }
            .notice .body[contenteditable='true'],
            .notice .sig-title[contenteditable='true'],
            .notice .sig-name[contenteditable='true'] { outline: none; }
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
                    <div
                        class="body"
                        contenteditable="true"
                        id="notice-body"
                        spellcheck="false"
                    >{{ $bodyText }}</div>
                    <div class="signatures-wrap">
                        <div class="signatures-header">ЗӨВШӨӨРСӨН:</div>
                        <div class="signatures-grid">
                            <div class="sig-title" contenteditable="true" id="notice-approver-title" spellcheck="false">{{ $signatureFields['approverTitle'] }}</div>
                            <div class="sig-name" contenteditable="true" id="notice-approver-name" spellcheck="false">{{ $signatureFields['approverName'] }}</div>
                            <div class="sig-spacer"></div>
                            <div class="sig-title" contenteditable="true" id="notice-own-title" spellcheck="false">{{ $signatureFields['ownTitle'] }}</div>
                            <div class="sig-name" contenteditable="true" id="notice-own-name" spellcheck="false">{{ $signatureFields['ownName'] }}</div>
                        </div>
                    </div>
                    <div class="body-edit">
                        <button type="button" id="notice-save">Хадгалах</button>
                        <button type="button" id="notice-reset" class="ghost">Дахин үүсгэх</button>
                        <span id="notice-status">Бичвэр дээр дарж засна.</span>
                    </div>
                @else
                    <div class="body" data-notice-body-copy>{{ $bodyText ?: '……………………………………………………………………………………………………' }}</div>
                    <div class="signatures-wrap">
                        <div class="signatures-header">ЗӨВШӨӨРСӨН:</div>
                        <div class="signatures-grid">
                            <div class="sig-title" data-notice-approver-title-copy>{{ $signatureFields['approverTitle'] }}</div>
                            <div class="sig-name" data-notice-approver-name-copy>{{ $signatureFields['approverName'] }}</div>
                            <div class="sig-spacer"></div>
                            <div class="sig-title" data-notice-own-title-copy>{{ $signatureFields['ownTitle'] }}</div>
                            <div class="sig-name" data-notice-own-name-copy>{{ $signatureFields['ownName'] }}</div>
                        </div>
                    </div>
                @endif
            </div>
        @endfor
    </div>

    <script>
        const body = document.getElementById('notice-body');
        const approverTitle = document.getElementById('notice-approver-title');
        const approverName = document.getElementById('notice-approver-name');
        const ownTitle = document.getElementById('notice-own-title');
        const ownName = document.getElementById('notice-own-name');
        const saveBtn = document.getElementById('notice-save');
        const resetBtn = document.getElementById('notice-reset');
        const status = document.getElementById('notice-status');
        // Хоёр дахь хувь байвал зэрэг шинэчилнэ — хуудас дахин ачаалахгүй.
        const otherBodyCopies = document.querySelectorAll('[data-notice-body-copy]');
        const otherApproverTitleCopies = document.querySelectorAll('[data-notice-approver-title-copy]');
        const otherApproverNameCopies = document.querySelectorAll('[data-notice-approver-name-copy]');
        const otherOwnTitleCopies = document.querySelectorAll('[data-notice-own-title-copy]');
        const otherOwnNameCopies = document.querySelectorAll('[data-notice-own-name-copy]');

        // Тушаал, нэрийг нэг таб тэмдэгтээр тусгаарлаж хадгална (харагдах
        // байрлалыг grid хариуцна, энэ зөвхөн утга тусгаарлах тэмдэг).
        const signaturesText = () => [
            'ЗӨВШӨӨРСӨН:',
            `${approverTitle.innerText.trim()}\t${approverName.innerText.trim()}`,
            '',
            `${ownTitle.innerText.trim()}\t${ownName.innerText.trim()}`,
        ].join('\n');

        // Хэсгүүдийг нэг хоосон мөрөөр холбоод ганц notice_text болгож хадгална.
        const combined = () => `${body.innerText.trim()}\n\n${signaturesText()}`;

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

        if (body && approverTitle && approverName && ownTitle && ownName && saveBtn) {
            let saved = combined();

            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                status.textContent = 'Хадгалж байна…';

                try {
                    const text = combined();
                    const response = await send(text);

                    if (response.ok) {
                        saved = text;
                        otherBodyCopies.forEach((el) => { el.textContent = body.innerText.trim(); });
                        otherApproverTitleCopies.forEach((el) => { el.textContent = approverTitle.innerText.trim(); });
                        otherApproverNameCopies.forEach((el) => { el.textContent = approverName.innerText.trim(); });
                        otherOwnTitleCopies.forEach((el) => { el.textContent = ownTitle.innerText.trim(); });
                        otherOwnNameCopies.forEach((el) => { el.textContent = ownName.innerText.trim(); });
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

            const onInput = () => {
                status.textContent = combined() === saved
                    ? 'Бичвэр дээр дарж засна.'
                    : 'Хадгалаагүй өөрчлөлт байна.';
            };

            body.addEventListener('input', onInput);
            approverTitle.addEventListener('input', onInput);
            approverName.addEventListener('input', onInput);
            ownTitle.addEventListener('input', onInput);
            ownName.addEventListener('input', onInput);

            // Хадгалаагүй байхад хуудсаас гарахаас сэргийлнэ.
            window.addEventListener('beforeunload', (event) => {
                if (combined() !== saved) {
                    event.preventDefault();
                    event.returnValue = '';
                }
            });
        }
    </script>
</body>
</html>
