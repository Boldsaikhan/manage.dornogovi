<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @php
        // Бичиг хэргийн стандартаас — хэвтээ A4 (хүснэгт өргөн тул)
        $pageWidth = $format?->height_mm ?? 297;
        $pageHeight = $format?->width_mm ?? 210;
        $marginTop = $format?->margin_top_mm ?? 20;
        $marginRight = $format?->margin_right_mm ?? 15;
        $marginBottom = $format?->margin_bottom_mm ?? 20;
        $marginLeft = $format?->margin_left_mm ?? 30;
        $fontName = $format?->font_name ?: 'Arial';
        $isBlank = $tab === 'blank';
        $isNiit = $tab === 'niit';
    @endphp
    <style>
        @page {
            size: {{ $pageWidth }}mm {{ $pageHeight }}mm;
            margin: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 1.5rem;
            background: #f1f5f9;
            color: #000;
            font-family: "{{ $fontName }}", Arial, sans-serif;
            font-size: 9pt;
        }

        .toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 13px;
        }

        .toolbar button {
            border: 1px solid #1c55a5;
            background: #1c55a5;
            color: #fff;
            border-radius: 8px;
            padding: 6px 14px;
            cursor: pointer;
            font: inherit;
        }

        .sheet {
            background: #fff;
            padding: 1.25rem;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .12);
        }

        h1 {
            margin: 0 0 .35rem;
            text-align: center;
            font-size: 12pt;
        }

        .meta {
            margin: 0 0 .75rem;
            text-align: center;
            font-size: 8pt;
            color: #475569;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        th, td {
            border: 1px solid #000;
            padding: 2px 4px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th { text-align: center; font-weight: 600; background: #f8fafc; }
        td.center { text-align: center; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
        .idx td { font-size: 7pt; color: #64748b; text-align: center; }

        @media print {
            body { background: #fff; padding: 0; font-size: 8pt; }
            .toolbar { display: none; }
            .sheet { padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Хэвлэх</button>
        <span>{{ $title }} · {{ $rows->count() }} мөр</span>
    </div>

    <div class="sheet">
        <h1>{{ $title }}</h1>
        <p class="meta">{{ now()->format('Y') }} он · хэвлэсэн огноо: {{ now()->format('Y-m-d') }}</p>

        @if ($isBlank)
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width:4%">Д/д</th>
                        <th rowspan="2" style="width:16%">Хэвлэмэл хуудас авсан ажилтны нэр</th>
                        <th rowspan="2" style="width:9%">Огноо</th>
                        <th colspan="8">Олгосон хэвлэмэл хуудас</th>
                        <th colspan="2">Хэвлэмэл хуудасны дугаар</th>
                        <th colspan="2">Үрэгдүүлсэн хуудасны дугаар</th>
                    </tr>
                    <tr>
                        <th>Захирамж</th>
                        <th>Монгол бичиг</th>
                        <th>Тушаал</th>
                        <th>Монгол бичиг</th>
                        <th>Албан даалгавар</th>
                        <th>Монгол бичиг</th>
                        <th>Зөвлөлийн хурал</th>
                        <th>Монгол бичиг</th>
                        <th>Захирамж</th>
                        <th>Тушаал</th>
                        <th>Захирамж</th>
                        <th>Тушаал</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="center">{{ $row['no'] }}</td>
                            <td>{{ $row['person_name'] }}</td>
                            <td class="center">{{ $row['issued_on_display'] }}</td>
                            <td class="center">{{ $row['qty_zahiramj'] }}</td>
                            <td class="center">{{ $row['qty_zahiramj_mn'] }}</td>
                            <td class="center">{{ $row['qty_tushaal'] }}</td>
                            <td class="center">{{ $row['qty_tushaal_mn'] }}</td>
                            <td class="center">{{ $row['qty_assignment'] }}</td>
                            <td class="center">{{ $row['qty_assignment_mn'] }}</td>
                            <td class="center">{{ $row['qty_council'] }}</td>
                            <td class="center">{{ $row['qty_council_mn'] }}</td>
                            <td class="center">{{ $row['num_zahiramj'] }}</td>
                            <td class="center">{{ $row['num_tushaal'] }}</td>
                            <td class="center">{{ $row['void_zahiramj'] }}</td>
                            <td class="center">{{ $row['void_tushaal'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="15" class="center">Бүртгэл алга.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @else
            <table>
                <thead>
                    <tr>
                        <th rowspan="2" style="width:4%">№</th>
                        <th rowspan="2" style="width:8%">Дугаар</th>
                        <th rowspan="2" style="width:10%">Огноо</th>
                        <th rowspan="2">{{ $titleLabel }}</th>
                        <th rowspan="2" style="width:7%">Хуудасны тоо</th>
                        <th colspan="2">Хавсралтын мэдээлэл</th>
                        <th rowspan="2" style="width:14%">Боловсруулсан албан тушаалтан</th>
                        @if ($isNiit)
                            <th rowspan="2" style="width:9%">Төрөл</th>
                        @endif
                    </tr>
                    <tr>
                        <th style="width:14%">Баримт бичгийн нэр</th>
                        <th style="width:7%">Хуудасны тоо</th>
                    </tr>
                    <tr class="idx">
                        <td>1</td><td>2</td><td>3</td><td>4</td><td>5</td><td>6</td><td>7</td><td>8</td>
                        @if ($isNiit)<td>9</td>@endif
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="center">{{ $row['no'] }}</td>
                            <td class="center">{{ $row['number'] }}</td>
                            <td class="center">{{ $row['issued_on_display'] }}</td>
                            <td>{{ $row['title'] }}</td>
                            <td class="center">{{ $row['page_count'] }}</td>
                            <td>{{ $row['attachment_name'] }}</td>
                            <td class="center">{{ $row['attachment_pages'] }}</td>
                            <td>{{ $row['person_name'] }}</td>
                            @if ($isNiit)
                                <td class="center">{{ $row['kind_label'] }}</td>
                            @endif
                        </tr>
                    @empty
                        <tr><td colspan="{{ $isNiit ? 9 : 8 }}" class="center">Бүртгэл алга.</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endif
    </div>
</body>
</html>
