<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Чөлөөний хуудас №{{ $slipNumber ?: $leave->id }}</title>
    @php
        // Хэмжээ нь контроллероос ирнэ (A4/A5, босоо/хэвтээ).
        $pageWidth = $pageWidth ?? 210;
        $pageHeight = $pageHeight ?? 297;
        // Албан бичгийн захын зай — агуулгын өндөр / 3 ≈ нэг хуудасны өндөр
        $marginTop = 12;
        $marginRight = 12;
        $marginBottom = 12;
        $marginLeft = 14;
        $gapX = 8;
        $gapY = 6;
        $contentH = $pageHeight - $marginTop - $marginBottom;
        $contentW = $pageWidth - $marginLeft - $marginRight;
        // 2 хувь — дээр доор нь (хагас A4). 4, 6 — хоёр баганаар.
        $cols = $copies >= 4 ? 2 : 1;
        $rows = (int) ceil($copies / max(1, $cols));
        $slipH = ($contentH - ($rows - 1) * $gapY) / max(1, $rows);
        $slipW = ($contentW - ($cols - 1) * $gapX) / max(1, $cols);

        /*
         * Бичвэрийг өөрчлөхгүйгээр хуудастаа багтаана.
         *
         * Нэг хувийн талбай багасах тусам фонт жаахан жижигрэнэ — A5
         * дээр ч, нэг A4-д 6 хувь байрлуулахад ч бичвэр таслагдахгүй.
         */
        $area = $slipH * $slipW;
        $fontPt = max(7.5, min(10.5, round(sqrt($area) / 7.4, 1)));
        $titlePt = round($fontPt + 0.5, 1);
        $signPt = round($fontPt - 1, 1);
        // Нэг «enter» = нэг мөрийн өндөр (line-height 1.5).
        $linePt = round($fontPt * 1.5, 1);

        $fill = function (?string $value, int $width) {
            $text = trim((string) $value);

            return $text !== ''
                ? '<span class="filled" style="min-width:'.$width.'mm">'.e($text).'</span>'
                : '<span class="blank" style="min-width:'.$width.'mm"></span>';
        };

        $kindMark = function (string $key) use ($kind) {
            return $kind === $key ? '<u>'.e([
                'tsalintai' => 'цалинтай',
                'tsalingui' => 'цалингүй',
                'eeljiin' => 'ээлжийн амралтаас',
            ][$key] ?? $key).'</u>' : e([
                'tsalintai' => 'цалинтай',
                'tsalingui' => 'цалингүй',
                'eeljiin' => 'ээлжийн амралтаас',
            ][$key] ?? $key);
        };
    @endphp
    <style>
        /*
         * Margin-ийг зөвхөн .sheet-ийн padding-аар зурна — @page дээр давхар
         * margin өгвөл (0 биш) хуудасны бодит хэмжээ А4-аас давж, хэвлэгч
         * бүхэл хуудсыг багасгаж хэвлэдэг байсан (margin+padding давхардна).
         */
        @page {
            size: {{ $pageWidth }}mm {{ $pageHeight }}mm;
            margin: 0;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: Arial, "Times New Roman", sans-serif;
            font-size: 10.5pt;
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
        .toolbar .sep { color: #64748b; margin-left: 6px; }
        .toolbar .active { border-color: #1c55a5; font-weight: 700; }

        .sheet {
            width: {{ $pageWidth }}mm;
            height: {{ $pageHeight }}mm;
            margin: 16px auto;
            padding: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
            background: #fff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .12);
            display: grid;
            grid-template-columns: repeat({{ $cols }}, 1fr);
            grid-template-rows: repeat({{ $rows }}, {{ $slipH }}mm);
            gap: {{ $gapY }}mm {{ $gapX }}mm;
        }

        .slip {
            height: {{ $slipH }}mm;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            /* Гарын үсэг нь доод ирмэгт наалдахгүй — өгүүлбэрээ дагана. */
            justify-content: flex-start;
            padding: 1mm 0;
        }

        .slip h1 {
            font-size: {{ $titlePt }}pt;
            font-weight: 700;
            text-align: center;
            /* Гарчгийн доор 2 хоосон мөр. */
            margin: 0 0 {{ round($linePt * 2, 1) }}pt;
            letter-spacing: 0.02em;
        }

        .slip .no { text-align: center; margin: 0 0 1mm; font-size: {{ $fontPt }}pt; }

        .slip .body {
            margin: 0;
            text-align: justify;
            line-height: 1.5;
            font-size: {{ $fontPt }}pt;
            /* Эхний мөрийн догол. */
            text-indent: 10mm;
        }

        /* Бөглөх зайд зурах мөр — цэггүй, нимгэн зураас. */
        .blank {
            display: inline-block;
            border-bottom: 1px solid #000;
            vertical-align: bottom;
            padding: 0 0.8mm;
        }

        /*
         * Бөглөгдсөн утга — доогуураа зураасгүй, энгийн урсгал бичвэр.
         * inline-block + min-width байхад мөр дунд өргөн хоосон зай үүсч,
         * догол мэт харагддаг байсан тул min-width-ийг хүчингүй болгоно.
         */
        .filled {
            display: inline;
            min-width: 0 !important;
            padding: 0;
        }

        u { text-underline-offset: 2px; }

        /*
         * Албан тушаал — гарын үсэг — нэр гэж зэрэгцэнэ. Хоёр ирмэг рүү
         * тараахгүй: хооронд нь зөвхөн гарын үсэг зурах зай үлдэнэ.
         */
        .sign {
            display: flex;
            justify-content: flex-start;
            align-items: flex-end;
            gap: 4mm;
            /* Өгүүлбэрийн доороос 3 хоосон мөр. */
            margin-top: {{ round($linePt * 3, 1) }}pt;
            line-height: 1.25;
            font-size: {{ $signPt }}pt;
        }

        .sign .title { text-transform: uppercase; }

        .sign .name { white-space: nowrap; }

        /* Гарын үсэг зурах зай — ташуу зураасын хооронд. */
        .sign .line { white-space: nowrap; }
        .sign .gap { display: inline-block; min-width: 26mm; }

        /* Засварын горим — хэвлэхэд ул мөр үлдэхгүй. */
        [contenteditable]:focus { outline: none; }

        .slip:hover { background: #f8fafc; }

        .toolbar .hint { color: #64748b; }

        @media print {
            body { background: #fff; }
            .slip:hover { background: transparent; }
            .toolbar { display: none !important; }
            .sheet {
                margin: 0;
                box-shadow: none;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Хэвлэх</button>

        <span class="sep">Цаас:</span>
        @foreach (['a4' => 'A4', 'a5' => 'A5'] as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['paper' => $value]) }}"
               class="{{ $paper === $value ? 'active' : '' }}">{{ $label }}</a>
        @endforeach

        <span class="sep">Чиглэл:</span>
        @foreach (['portrait' => 'Босоо', 'landscape' => 'Хөндлөн'] as $value => $label)
            <a href="{{ request()->fullUrlWithQuery(['orient' => $value]) }}"
               class="{{ ($landscape ? 'landscape' : 'portrait') === $value ? 'active' : '' }}">{{ $label }}</a>
        @endforeach

        <span class="hint">Бичвэр дээр дарж засна — засвар нь зөвхөн энэ хэвлэлтэд үйлчилнэ.</span>

        <span class="sep">Нэг хуудсанд:</span>
        @foreach ([1, 2, 4, 6] as $option)
            <a href="{{ request()->fullUrlWithQuery(['copies' => $option]) }}"
               class="{{ $copies === $option ? 'active' : '' }}">{{ $option }}</a>
        @endforeach
    </div>

    <div class="sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="slip" contenteditable="true" spellcheck="false">
                <div>
                    <h1>ЧӨЛӨӨНИЙ ХУУДАС</h1>
                    <div class="no">№ {!! $fill($slipNumber, 16) !!}</div>
                    <p class="body">
                        Аймгийн ЗДТГ-ын {!! $fill($unit, 28) !!} хэлтсийн
                        мэргэжилтэн {!! $fill($person, 42) !!} нь
                        {!! $fill($reason, 48) !!} үндэслэлээр
                        {{ $year ?: '____' }} оны {!! $fill($month ? (string) $month : null, 8) !!} сарын
                        {!! $fill($day ? (string) $day : null, 8) !!}-ны өдрөөс
                        ажлын {!! $fill($days ? (string) $days : null, 10) !!} өдрийн
                        чөлөө /{!! $kindMark('tsalintai') !!}, {!! $kindMark('tsalingui') !!}, {!! $kindMark('eeljiin') !!}/
                        олгов.
                    </p>
                </div>
                <div class="sign">
                    {{-- Баталсан албан хаагч: албан тушаал — гарын үсгийн зай — нэр. --}}
                    <span class="title">{!! $signerTitle !== '' ? e($signerTitle) : $fill(null, 45) !!}</span>
                    <span class="line">/<span class="gap"></span>/</span>
                    <span class="name">{!! $fill($signerName, 32) !!}</span>
                </div>
            </div>
        @endfor
    </div>

    <script>
        /*
         * Нэг хуудсанд хэд ч хувь байсан нэгийг нь засахад бусад нь
         * дагана — хоёр хувь өөр өөр бичвэртэй гарахаас сэргийлнэ.
         */
        (function () {
            var slips = Array.prototype.slice.call(document.querySelectorAll('.slip'));

            if (slips.length < 2) {
                return;
            }

            var syncing = false;

            slips.forEach(function (slip) {
                slip.addEventListener('input', function () {
                    if (syncing) {
                        return;
                    }

                    syncing = true;
                    slips.forEach(function (other) {
                        if (other !== slip) {
                            other.innerHTML = slip.innerHTML;
                        }
                    });
                    syncing = false;
                });
            });
        })();
    </script>
</body>
</html>
