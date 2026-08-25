<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Чөлөөний хуудас</title>
    @php
        $pageWidth = $format?->width_mm ?? 210;
        $pageHeight = $format?->height_mm ?? 297;
        $marginTop = $format?->margin_top_mm ?? 20;
        $marginRight = $format?->margin_right_mm ?? 15;
        $marginBottom = $format?->margin_bottom_mm ?? 20;
        $marginLeft = $format?->margin_left_mm ?? 30;
        $fontName = $format?->font_name ?: 'Arial';
        $fontSize = $format?->font_size_pt ?? 12;
        $columns = $copies > 1 ? 2 : 1;

        // Хэвлэхэд харагдах цэгэн зураас — утга байвал түүнийг дунд нь бичнэ.
        $fill = function (?string $value, int $width) {
            $text = trim((string) $value);

            return $text !== ''
                ? '<span class="filled" style="min-width:'.$width.'mm">'.e($text).'</span>'
                : '<span class="blank" style="min-width:'.$width.'mm"></span>';
        };
    @endphp
    <style>
        @page {
            size: {{ $pageWidth }}mm {{ $pageHeight }}mm;
            margin: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: "{{ $fontName }}", Arial, sans-serif;
            font-size: {{ $fontSize - 2 }}pt;
            color: #000;
            background: #f1f5f9;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
            padding: 14px 20px;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            font-family: Arial, sans-serif;
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

        .sheet {
            width: {{ $pageWidth }}mm;
            min-height: {{ $pageHeight }}mm;
            margin: 18px auto;
            padding: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
            background: #fff;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .12);
            display: grid;
            grid-template-columns: repeat({{ $columns }}, 1fr);
            gap: 8mm 10mm;
            align-content: start;
        }

        .slip { break-inside: avoid; }

        .slip h1 {
            font-size: {{ $fontSize - 2 }}pt;
            font-weight: 400;
            text-align: center;
            margin: 0;
        }

        .slip .no { text-align: center; margin: 1mm 0 3mm; }

        .slip p {
            margin: 0;
            text-align: justify;
            line-height: 1.65;
        }

        .blank {
            display: inline-block;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
        }

        .filled {
            display: inline-block;
            border-bottom: 1px dotted #000;
            text-align: center;
            vertical-align: bottom;
            padding: 0 1mm;
        }

        u { text-underline-offset: 2px; }

        .sign {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 6mm;
            margin-top: 8mm;
            line-height: 1.3;
        }

        .sign .title { text-transform: uppercase; }
        .sign .line { white-space: nowrap; }

        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet {
                width: auto;
                min-height: auto;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button class="primary" onclick="window.print()">Хэвлэх</button>
        <span>Нэг хуудсанд:</span>
        @foreach ([1, 2, 4, 6] as $option)
            <a
                href="{{ request()->fullUrlWithQuery(['copies' => $option]) }}"
                class="{{ $copies === $option ? 'active' : '' }}"
            >{{ $option }}</a>
        @endforeach
        <span>Гарын үсэг:</span>
        <a
            href="{{ request()->fullUrlWithQuery(['signer' => 'acting']) }}"
            class="{{ $signer === 'acting' ? 'active' : '' }}"
        >Даргын албан үүргийг түр орлон гүйцэтгэгч</a>
        <a
            href="{{ request()->fullUrlWithQuery(['signer' => 'head']) }}"
            class="{{ $signer === 'head' ? 'active' : '' }}"
        >Хэлтсийн дарга</a>
        <span style="color:#64748b">Хуудасны хэмжээ: {{ $pageWidth }}×{{ $pageHeight }} мм (бичиг хэргийн стандартаас)</span>
    </div>

    <div class="sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="slip">
                <h1>ЧӨЛӨӨНИЙ ХУУДАС</h1>
                <div class="no">№ <span class="blank" style="min-width:18mm"></span></div>

                <p>
                    Аймгийн ЗДТГ-ын {!! $fill($unit, 34) !!} хэлтсийн
                    мэргэжилтэн {!! $fill($person, 48) !!} нь
                    {!! $fill($reason, 60) !!} үндэслэлээр {{ $year ?? '20' }}
                    оны {!! $fill($month, 10) !!} сарын {!! $fill($day, 10) !!}-ны өдрөөс
                    ажлын {!! $fill($days ? (string) $days : null, 12) !!} өдрийн
                    чөлөө /@if ($kind === 'chuluu')<u>цалинтай</u>@else цалинтай @endif,
                    цалингүй,
                    @if ($kind === 'amralt')<u>ээлжийн амралтаас</u>@else ээлжийн амралтаас @endif/
                    олгов. <em>Доогуур зурах</em>
                </p>

                <div class="sign">
                    @if ($signer === 'acting')
                        <span class="title">Даргын албан үүргийг<br>түр орлон гүйцэтгэгч</span>
                        <span class="line">{{ $actingName }}</span>
                    @else
                        <span class="title">Хэлтсийн<br>дарга</span>
                        <span class="line">/ &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; /</span>
                    @endif
                </div>
            </div>
        @endfor
    </div>
</body>
</html>
