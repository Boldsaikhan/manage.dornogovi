<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Гадаад улсад зорчих хүсэлт — {{ $trip->person_name }}</title>
    @php
        // Утга байвал бичнэ, үгүй бол цэгээр дүүргэсэн зай үлдээнэ (гараар бөглөнө).
        $fill = function (?string $value) {
            $text = trim((string) $value);

            return $text !== ''
                ? '<span class="filled">'.e($text).'</span>'
                : '<span class="dots"></span>';
        };

        $fillBlock = function (?string $value) {
            $text = trim((string) $value);

            return $text !== ''
                ? '<div class="filled-block">'.nl2br(e($text)).'</div>'
                : '<span class="line"></span><span class="line"></span>';
        };
    @endphp
    <style>
        /* Хэмжээ, зай, фонтыг «Бичиг хэргийн стандарт»-аас авна. */
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
        }

        .addressee {
            text-align: right;
            margin-bottom: 10mm;
        }

        h1 {
            margin: 0 0 8mm;
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            text-transform: uppercase;
        }

        ol.items { margin: 0; padding-left: 6mm; }
        ol.items > li { margin-bottom: 5mm; }
        ol.items > li > .label { font-weight: bold; }

        .hint {
            display: block;
            text-align: center;
            font-size: 9pt;
            color: #444;
            margin-top: -1mm;
        }

        .dots {
            display: inline-block;
            min-width: 30mm;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
        }

        .filled { font-weight: normal; border-bottom: 1px dotted #000; }

        .filled-block { white-space: pre-wrap; border-bottom: 1px dotted #000; min-height: 5mm; }

        .line { display: block; height: 6mm; border-bottom: 1px dotted #000; }

        .signature {
            margin-top: 14mm;
            text-align: center;
        }

        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
    <div class="addressee">
        <span class="dots"></span> БАЙГУУЛЛАГЫН ДАРГА <span class="dots" style="min-width: 15mm;"></span> ТАНАА
    </div>

    <h1>Гадаад улсад зорчих хүсэлт</h1>

    <ol class="items">
        <li>
            {!! $fill($trip->org_name) !!} -ын {!! $fill($trip->position) !!} албан тушаалтай
            <br>
            {!! $fill($trip->person_name) !!} би
            {{ optional($trip->start_date)?->format('Y') }} оны {{ optional($trip->start_date)?->format('n') }} дугаар сарын {{ optional($trip->start_date)?->format('j') }}-ны өдрөөс
            {{ optional($trip->end_date)?->format('Y') }} оны {{ optional($trip->end_date)?->format('n') }} дугаар сарын {{ optional($trip->end_date)?->format('j') }}-ны өдрийн хооронд
            {!! $fill($trip->destination_country) !!} улсад зорчих болсон тул энэхүү хүсэлтийг гаргав.
        </li>
        <li>
            <span class="label">Гадаад улсад зорчих болсон шалтгаан:</span>
            {!! $fillBlock($trip->reason) !!}
        </li>
        <li>
            <span class="label">Хамт зорчих хүн:</span>
            {!! $fillBlock($trip->companions) !!}
        </li>
        <li>
            <span class="label">Гадаад улсад зорчих чиглэл, маршрут:</span>
            {!! $fillBlock($trip->route) !!}
        </li>
        <li>
            <span class="label">Гадаад улсад зорчих зардлыг ямар эх үүсвэрээс гаргасан:</span>
            {!! $fillBlock($trip->funding_source) !!}
        </li>
        <li>
            <span class="label">Хилийн чанадад уулзах байгууллага, албан тушаалтан, иргэн:</span>
            {!! $fillBlock($trip->foreign_contact) !!}
        </li>
        <li>
            <span class="label">Байрлах газар:</span>
            {!! $fillBlock($trip->accommodation) !!}
        </li>
    </ol>

    <div class="signature">
        ХҮСЭЛТ ГАРГАСАН: <span class="dots" style="min-width: 40mm;"></span> / <span class="dots" style="min-width: 40mm;"></span> /
    </div>
</body>
</html>
