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

        .page {
            width: 210mm;
            min-height: 297mm;
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

        .approve .name { display: inline-block; margin-top: 2mm; }

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

        .line { display: block; height: 6mm; border-bottom: 1px dotted #000; }

        .budget-title { margin: 6mm 0 2mm; }

        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 2mm; font-size: 11pt; }
        th { text-align: center; font-weight: bold; }
        td.no, td.center { text-align: center; }
        td.kind { text-align: center; }
        tbody tr { height: 9mm; }

        .report-title { margin: 6mm 0 2mm; font-weight: bold; text-transform: uppercase; }

        .sign { margin-top: 8mm; }
        .sign div { margin-bottom: 4mm; }

        .toolbar {
            max-width: 210mm; margin: 6mm auto; display: flex; gap: 8px; justify-content: flex-end;
            font-family: Arial, sans-serif;
        }
        .toolbar button {
            padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px;
            background: #1e3a5f; color: #fff; font-size: 13px; cursor: pointer;
        }
        .toolbar button.ghost { background: #fff; color: #1e3a5f; }

        @media print {
            body { background: #fff; }
            .page { margin: 0; padding: 0; width: auto; min-height: 0; }
            .toolbar { display: none; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <button type="button" onclick="window.print()">Хэвлэх</button>
    <button type="button" class="ghost" onclick="window.close()">Хаах</button>
</div>

<div class="page">
    <div class="approve">
        БАТЛАВ<br>
        @foreach ($lines as $line)
            {{ $line }}@if (! $loop->last)<br>@endif
        @endforeach
        <span class="name">{{ $signerName ?: '.....................' }}</span>
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
            @foreach (['Байр', 'Зам хоног', 'Түлш, шатахуун'] as $i => $kind)
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
        @for ($i = 0; $i < 14; $i++)
            <span class="line"></span>
        @endfor
    @endif

    <div class="sign">
        <div>Тайлан гаргасан &nbsp;................................................/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /</div>
        <div>Танилцсан................................................/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /</div>
    </div>
</div>

</body>
</html>
