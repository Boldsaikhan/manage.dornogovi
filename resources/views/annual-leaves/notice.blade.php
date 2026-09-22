<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ээлжийн амралт олгох тухай мэдэгдэл — {{ $annualLeave->person_name }}</title>
    <style>
        @page {
            size: {{ $format['width'] }}mm {{ $format['height'] }}mm;
            margin: {{ $format['top'] }}mm {{ $format['right'] }}mm {{ $format['bottom'] }}mm {{ $format['left'] }}mm;
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

        .notice .body {
            margin: 0 0 8mm;
            text-align: justify;
            text-indent: 8mm;
        }

        .notice .sign {
            margin-bottom: 5mm;
        }

        .notice .sign .row {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 6mm;
        }

        .notice .sign .title {
            text-transform: uppercase;
            font-weight: normal;
        }

        .notice .sign .name {
            white-space: nowrap;
        }

        .edit-panel {
            max-width: {{ $format['width'] }}mm;
            margin: 0 auto 24px;
            padding: 12px 16px;
            background: #fff;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 13px;
        }

        .edit-panel textarea {
            width: 100%;
            min-height: 70px;
            margin-top: 8px;
            padding: 8px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font: inherit;
        }

        .edit-panel .row {
            display: flex;
            gap: 8px;
            margin-top: 8px;
            align-items: center;
        }

        .edit-panel button {
            padding: 6px 14px;
            border: 1px solid #1c55a5;
            border-radius: 8px;
            background: #1c55a5;
            color: #fff;
            font-size: 13px;
            cursor: pointer;
        }

        .edit-panel button[disabled] { opacity: .6; cursor: default; }

        @media print {
            body { background: #fff; }
            .toolbar, .edit-panel { display: none !important; }
            .page { margin: 0; box-shadow: none; }
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

    <div class="page">
        @for ($i = 0; $i < $copies; $i++)
            <div class="notice">
                <h1>Ээлжийн амралт олгох тухай мэдэгдэл</h1>

                <div class="meta">
                    <span><span class="dots">&nbsp;</span> оны <span class="dots">&nbsp;</span>-р сарын <span class="dots">&nbsp;</span>-ны өдөр</span>
                    <span>Дугаар <span class="dots">&nbsp;</span></span>
                </div>

                <p class="body">{{ $text ?: '……………………………………………………………………………………………………' }}</p>

                <div class="sign">
                    <div class="row">
                        <span class="title">Зөвшөөрсөн:</span>
                    </div>
                    @foreach ($approverLines as $line)
                        <div class="row">
                            <span class="title">{{ $line }}</span>
                            @if ($loop->last)
                                <span class="name">{{ $approverName ?: '/ ……………… /' }}</span>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="sign">
                    <div class="row">
                        <span class="title">{{ $annualLeave->position ?: 'Албан хаагч' }}</span>
                        <span class="name">{{ $annualLeave->person_name }}</span>
                    </div>
                </div>
            </div>
        @endfor
    </div>

    @if ($canEdit)
        <div class="edit-panel">
            <label>Мэдэгдлийн бичвэр:</label>
            <textarea id="notice-text">{{ $text }}</textarea>
            <div class="row">
                <button type="button" id="notice-save">Хадгалах</button>
                <button type="button" id="notice-reset" style="background:#fff;color:#1c55a5;">Дахин үүсгэх</button>
                <span id="notice-status" style="color:#64748b;">Засаад хадгална уу.</span>
            </div>
        </div>
    @endif

    <script>
        const textArea = document.getElementById('notice-text');
        const saveBtn = document.getElementById('notice-save');
        const resetBtn = document.getElementById('notice-reset');
        const status = document.getElementById('notice-status');

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

        if (saveBtn) {
            saveBtn.addEventListener('click', async () => {
                saveBtn.disabled = true;
                status.textContent = 'Хадгалж байна…';

                try {
                    const response = await send(textArea.value.trim());
                    status.textContent = response.ok ? 'Хадгаллаа — шинэчлэхэд хуудсан дээр гарна.' : 'Хадгалж чадсангүй.';
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

                    if (response.ok) {
                        location.reload();
                    } else {
                        status.textContent = 'Дахин үүсгэж чадсангүй.';
                        resetBtn.disabled = false;
                    }
                } catch (e) {
                    status.textContent = 'Сүлжээгүй байна.';
                    resetBtn.disabled = false;
                }
            });
        }
    </script>
</body>
</html>
