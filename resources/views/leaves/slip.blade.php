<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Чөлөөний хуудас №{{ $slipNumber ?: $leave->id }}</title>
    @php
        $pageWidth = 210;
        $pageHeight = 297;
        // Албан бичгийн захын зай — агуулгын өндөр / 3 ≈ нэг хуудасны өндөр
        $marginTop = 12;
        $marginRight = 12;
        $marginBottom = 12;
        $marginLeft = 14;
        $gapX = 8;
        $gapY = 6;
        $contentH = $pageHeight - $marginTop - $marginBottom;
        $contentW = $pageWidth - $marginLeft - $marginRight;
        $cols = $copies >= 2 ? 2 : 1;
        $rows = (int) ceil($copies / max(1, $cols));
        $slipH = ($contentH - ($rows - 1) * $gapY) / max(1, $rows);

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
        @page {
            size: {{ $pageWidth }}mm {{ $pageHeight }}mm;
            margin: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
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
            justify-content: space-between;
            padding: 1mm 0;
        }

        .slip h1 {
            font-size: 11pt;
            font-weight: 700;
            text-align: center;
            margin: 0;
            letter-spacing: 0.02em;
        }

        .slip .no { text-align: center; margin: 1mm 0 2mm; font-size: 10pt; }

        .slip .body {
            margin: 0;
            text-align: justify;
            line-height: 1.55;
            flex: 1;
        }

        .blank, .filled {
            display: inline-block;
            border-bottom: 1px dotted #000;
            vertical-align: bottom;
            padding: 0 0.8mm;
        }

        .filled { text-align: center; }

        u { text-underline-offset: 2px; }

        .sign {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 4mm;
            margin-top: 3mm;
            line-height: 1.25;
            font-size: 9.5pt;
        }

        .sign .title {
            text-transform: uppercase;
            max-width: 58%;
        }

        .sign .line { white-space: nowrap; }

        @media print {
            body { background: #fff; }
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
        <span>Нэг A4 хуудсанд:</span>
        @foreach ([1, 2, 4, 6] as $option)
            <a href="{{ request()->fullUrlWithQuery(['copies' => $option]) }}" class="{{ $copies === $option ? 'active' : '' }}">{{ $option }}</a>
        @endforeach
        <span>Гарын үсэг:</span>
        <a href="{{ request()->fullUrlWithQuery(['signer' => 'acting']) }}" class="{{ $signer === 'acting' ? 'active' : '' }}">Даргын үүрэг гүйцэтгэгч</a>
        <a href="{{ request()->fullUrlWithQuery(['signer' => 'head']) }}" class="{{ $signer === 'head' ? 'active' : '' }}">Хэлтсийн дарга</a>
    </div>

    <div class="sheet">
        @for ($i = 0; $i < $copies; $i++)
            <div class="slip">
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
                        олгов. <em>Доогуур зурах</em>
                    </p>
                </div>
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
