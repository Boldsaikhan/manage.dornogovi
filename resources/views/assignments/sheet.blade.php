<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Томилолтын удирдамж — {{ $assignment->destination }}</title>
    @php
        // Утга байвал бичнэ, үгүй бол цэгээр дүүргэсэн зай үлдээнэ (гараар бөглөнө).
        $fill = function (?string $value) {
            $text = trim((string) $value);

            return $text !== ''
                ? '<span class="filled">'.e($text).'</span>'
                : '<span class="dots"></span>';
        };
    @endphp
    <style>
        @page { size: 210mm 297mm; margin: 15mm 15mm 15mm 20mm; }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "Times New Roman", Times, serif;
            font-size: 12pt;
            line-height: 1.45;
            color: #000;
            background: #f1f5f9;
        }

        /*
         * Хуудас яг A4 (210×297мм). Агуулга нь нэг хуудаст багтах ёстой
         * тул өндрийг тогтмол авна — илүү гарвал хоёр цаас идэхгүй.
         */
        .page {
            width: 210mm;
            height: 297mm;
            overflow: hidden;
            margin: 0 auto;
            padding: 15mm 15mm 15mm 20mm;
            background: #fff;
        }

        .approve {
            margin-left: 55%;
            text-transform: uppercase;
            font-weight: bold;
            line-height: 1.35;
        }

        /*
         * Сүүлийн мөр: зүүн талд албан тушаал, баруун талд нэр. Голын зай
         * нь гарын үсэг зурах зориулалттай.
         */
        .signrow {
            display: flex;
            justify-content: space-between;
            gap: 20mm;
        }

        .signrow .name { white-space: nowrap; }

        h1 {
            margin: 10mm 0 0;
            text-align: center;
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        .year { margin-top: 4mm; text-align: right; font-weight: bold; }

        ol.items { margin: 5mm 0 0; padding-left: 7mm; }
        ol.items > li { margin-bottom: 3mm; }
        ol.items > li > .label { font-weight: bold; font-style: italic; text-decoration: underline; }

        .dots {
            display: inline-block;
            min-width: 40mm;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
        }

        .filled { font-weight: normal; }

        .line { display: block; height: 5.5mm; border-bottom: 1px dotted #000; }

        .budget-title { margin: 5mm 0 2mm; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 2mm; font-size: 11pt; }
        th { text-align: center; font-weight: bold; }
        td.no, td.center { text-align: center; }
        td.kind { text-align: center; }
        tbody tr { height: 9mm; }

        .report-title { margin: 5mm 0 2mm; font-weight: bold; text-transform: uppercase; }

        .sign { margin-top: 6mm; }
        .sign div { margin-bottom: 3mm; }

        .toolbar {
            max-width: 210mm; margin: 6mm auto; display: flex; gap: 8px; justify-content: flex-end;
            font-family: Arial, sans-serif;
        }
        .toolbar button {
            padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #1e3a5f; color: #fff; font-size: 13px; cursor: pointer;
        }
        .toolbar button.ghost { background: #fff; color: #1e3a5f; }

        /* ── Албан томилолтын үнэмлэх (ар тал) ───────────────────── */

        .cert { display: flex; gap: 10mm; }
        .cert__col { width: 50%; }

        .cert__number { text-align: center; font-weight: bold; margin-bottom: 5mm; }

        .cert__body { text-align: justify; text-indent: 8mm; }

        .cert__title {
            margin-top: 28mm;
            text-align: center;
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.3;
        }

        .cert__year { margin-top: 20mm; text-align: center; font-weight: bold; }

        .cert__signer { margin-top: 8mm; font-weight: bold; text-transform: uppercase; line-height: 1.35; }
        .cert__signer .signrow { gap: 8mm; }

        .cert__date { margin-top: 10mm; text-align: center; }

        .cert__mark-title { margin-top: 20mm; }

        .cert table.cert__marks { margin-top: 2mm; }
        .cert table.cert__marks th,
        .cert table.cert__marks td { font-size: 10pt; padding: 1mm; }
        .cert table.cert__marks tbody tr { height: 7mm; }

        .cert__special { margin-top: 28mm; text-align: center; font-weight: bold; text-transform: uppercase; }
        .cert__special-sub { margin-top: 6mm; text-align: center; }

        .cert__note { margin-top: 6mm; }
        .cert__note .line { height: 7mm; }

        .page-break { page-break-after: always; break-after: page; }

        /*
         * Дэлгэцэн дээр хоёр хуудсыг зэрэгцүүлнэ.
         *
         * Хоёр A4 нь ихэнх дэлгэцээс өргөн тул JS-ээр багасгаж багтаана.
         * Хэвлэхэд энэ бүхэн хүчингүй болж, хуудас бүр цаасандаа гарна.
         */
        .sheets {
            display: flex;
            gap: 8mm;
            justify-content: center;
            align-items: flex-start;
            transform-origin: top center;
        }

        .sheet { flex: 0 0 auto; }

        .sheets .page { box-shadow: 0 2px 12px rgb(15 23 42 / 0.12); }

        /* Хуудас бүрийн дээд талын хэвлэх хэсэг. */
        .sheet__bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 3mm;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #475569;
        }

        .sheet__bar button {
            padding: 6px 14px; border: 1px solid #1e3a5f; border-radius: 8px;
            background: #1e3a5f; color: #fff; font-size: 12px; cursor: pointer;
        }

        @media print {
            body { background: #fff; }
            .page { margin: 0; padding: 0; width: auto; height: auto; box-shadow: none; }
            .toolbar { display: none; }

            /* Хэвлэхэд хуудас бүр өөрийн цаасан дээр гарна. */
            .sheets { display: block; gap: 0; transform: none !important; margin-bottom: 0 !important; }
            .sheet { display: block; }
            .sheet__bar { display: none; }

            /* Зөвхөн нэг талыг хэвлэх үед нөгөөг нь нуух. */
            body.print-back .sheet--front,
            body.print-front .sheet--back { display: none; }

            /* Ганц хуудас хэвлэхэд илүү хуудас гарахаас сэргийлнэ. */
            body.print-back .page--back,
            body.print-front .page--front { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="printSide('')">Хоёуланг хэвлэх</button>
    <button type="button" class="ghost" onclick="window.close()">Хаах</button>
</div>

<div class="sheets">

{{-- Ар тал — албан томилолтын үнэмлэх --}}
<div class="sheet sheet--back">
<div class="sheet__bar">
    <span>Ар тал — албан томилолтын үнэмлэх</span>
    <button type="button" onclick="printSide('back')">Энэ талыг хэвлэх</button>
</div>
<div class="page page--back page-break">
    <div class="cert">
        <div class="cert__col">
            <div class="cert__title">Албан томилолтын<br>үнэмлэх</div>
            <div class="cert__year">{{ $year }} он</div>

            <div class="cert__mark-title">Томилолтоор ажилласан тухай тэмдэглэл</div>

            <table class="cert__marks">
                <thead>
                    <tr>
                        <th rowspan="2" style="width:34%">Хаана</th>
                        <th colspan="2">Сар, өдөр</th>
                        <th rowspan="2" style="width:26%">Гарын үсэг</th>
                    </tr>
                    <tr>
                        <th style="width:20%">Ирсэн</th>
                        <th style="width:20%">Буцсан</th>
                    </tr>
                </thead>
                <tbody>
                    @for ($i = 0; $i < 6; $i++)
                        <tr><td></td><td></td><td></td><td></td></tr>
                    @endfor
                </tbody>
            </table>

            <div class="cert__note">
                Албан томилолтын ажлын дүнг эх зардлыг ………хувиар тооцоо хийхийг зөвшөөрсөн.
            </div>

            <div class="cert__signer">
                @foreach (array_slice($lines, 0, -1) as $line)
                    {{ $line }}<br>
                @endforeach
                <div class="signrow">
                    <span>{{ $lines[count($lines) - 1] }}</span>
                    <span class="name">{{ $signerName ?: '.....................' }}</span>
                </div>
            </div>

            <div class="cert__date">………. оны …… сар …… өдөр</div>
        </div>

        <div class="cert__col">
            <div class="cert__number">Дугаар {{ $number }}</div>

            <div class="cert__body">
                @if (trim((string) $assignment->certificate_text) !== '')
                    {{ $assignment->certificate_text }}
                @else
                    @for ($i = 0; $i < 6; $i++)
                        <span class="line"></span>
                    @endfor
                @endif
            </div>

            <div class="cert__signer">
                @foreach (array_slice($lines, 0, -1) as $line)
                    {{ $line }}<br>
                @endforeach
                <div class="signrow">
                    <span>{{ $lines[count($lines) - 1] }}</span>
                    <span class="name">{{ $signerName ?: '.....................' }}</span>
                </div>
            </div>

            <div class="cert__date">………. оны …… сар …… өдөр</div>

            <div class="cert__special">Тусгай тэмдэглэл</div>
            <div class="cert__special-sub">Хугацаа сунгах тухай</div>

            <div class="cert__note">
                <span class="line"></span>газар, байгууллагын
                <span class="line"></span>тодорхойлолт, хүсэлт
                <span class="line"></span>ажил гүйцэтгэх шаардлагын дагуу томилолтын хугацааг
                ……. хоногоор сунгав.
            </div>

            <div style="margin-top:10mm">Зөвшөөрсөн дарга &nbsp;..................................</div>
        </div>
    </div>
</div>

</div>{{-- .sheet--back --}}

{{-- Урд тал — томилолтын удирдамж --}}
<div class="sheet sheet--front">
<div class="sheet__bar">
    <span>Урд тал — томилолтын удирдамж</span>
    <button type="button" onclick="printSide('front')">Энэ талыг хэвлэх</button>
</div>
<div class="page page--front">
    <div class="approve">
        БАТЛАВ<br>
        @foreach (array_slice($lines, 0, -1) as $line)
            {{ $line }}<br>
        @endforeach
        <div class="signrow">
            <span>{{ $lines[count($lines) - 1] }}</span>
            <span class="name">{{ $signerName ?: '.....................' }}</span>
        </div>
    </div>

    <h1>Томилолтын удирдамж</h1>

    <div class="year">{{ $year }} он</div>

    <ol class="items">
        <li>
            <span class="label">Зорилго:</span>
            {!! $fill($assignment->purpose) !!}
        </li>
        <li>
            <span class="label">Бүрэлдэхүүн:</span>
            {!! $fill($assignment->composition ?: $assignment->user?->name) !!}
        </li>
        <li>
            <span class="label">Хугацаа:</span>
            {!! $fill($period) !!}
        </li>
        <li>
            <span class="label">Томилолтын хүрээнд: /ажлын чиглэл/</span>
            @if (trim((string) $assignment->scope_of_work) !== '')
                <div class="filled">{{ $assignment->scope_of_work }}</div>
            @else
                <span class="line"></span>
                <span class="line"></span>
                <span class="line"></span>
            @endif
        </li>
    </ol>

    <div class="budget-title">Албан томилолтоор ажиллах төсөв</div>

    <table>
        <thead>
            <tr>
                <th style="width:8%">№</th>
                <th style="width:24%">Зардлын төрөл</th>
                <th style="width:17%">Нэгжийн үнэ</th>
                <th style="width:17%">Тоо хэмжээ</th>
                <th style="width:17%">Ажиллах хоног</th>
                <th style="width:17%">Нийт дүн</th>
            </tr>
        </thead>
        <tbody>
            @foreach (\App\Support\AssignmentSheet::BUDGET_KINDS as $i => $kind)
                <tr>
                    <td class="no">{{ $i + 1 }}</td>
                    <td class="kind">{{ $kind }}</td>
                    <td></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="report-title">Томилолтын тайлан:</div>

    @if (trim((string) $assignment->report) !== '')
        <div class="filled">{{ $assignment->report }}</div>
    @else
        @for ($i = 0; $i < 10; $i++)
            <span class="line"></span>
        @endfor
    @endif

    <div class="sign">
        <div>Тайлан гаргасан &nbsp;................................................/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /</div>
        <div>Танилцсан................................................/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /</div>
    </div>
</div>
</div>{{-- .sheet--front --}}

</div>{{-- .sheets --}}

<script>
    /** Аль талыг хэвлэхийг заана: 'back', 'front', эсвэл хоосон бол хоёуланг. */
    function printSide(side) {
        document.body.classList.remove('print-back', 'print-front');

        if (side) {
            document.body.classList.add('print-' + side);
        }

        window.print();
    }

    // Хэвлэж дууссаны дараа дэлгэцийн байдлыг сэргээнэ.
    window.addEventListener('afterprint', function () {
        document.body.classList.remove('print-back', 'print-front');
    });

    /**
     * Хоёр A4 нь ихэнх дэлгэцээс өргөн тул багтаах хэмжээгээр багасгана.
     */
    var sheets = document.querySelector('.sheets');

    function fitSheets() {
        if (! sheets) return;

        sheets.style.transform = 'none';

        var width = sheets.scrollWidth;
        var available = document.documentElement.clientWidth - 32;
        var scale = Math.min(1, available / width);

        sheets.style.transform = 'scale(' + scale + ')';
        // Багасгасны дараа үлдэх хоосон зайг арилгана.
        sheets.style.marginBottom = ((scale - 1) * sheets.scrollHeight) + 'px';
    }

    fitSheets();
    window.addEventListener('resize', fitSheets);
    window.addEventListener('load', fitSheets);
</script>

</body>
</html>
