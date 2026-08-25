<!DOCTYPE html>
<html lang="mn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }}</title>
    @php
        $fontName = $format?->font_name ?: 'Arial';
        $isBlank = $tab === 'blank';
        $isNiit = $tab === 'niit';
        // Өргөн хүснэгт (бланк) — анхдагч хэвтээ A4
        $defaultPaper = 'A4';
        $defaultOrient = $isBlank ? 'landscape' : 'portrait';
        $marginTop = (int) ($format?->margin_top_mm ?? 15);
        $marginRight = (int) ($format?->margin_right_mm ?? 12);
        $marginBottom = (int) ($format?->margin_bottom_mm ?? 15);
        $marginLeft = (int) ($format?->margin_left_mm ?? 15);
    @endphp
    <style id="print-dynamic"></style>
    <style>
        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 0;
            background: #e2e8f0;
            color: #000;
            font-family: "{{ $fontName }}", Arial, sans-serif;
            font-size: 9pt;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 14px;
            align-items: end;
            padding: 12px 16px;
            background: #fff;
            border-bottom: 1px solid #cbd5e1;
            font-size: 13px;
            box-shadow: 0 1px 0 rgba(15, 23, 42, .04);
        }

        .toolbar .group {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .toolbar .group > span,
        .toolbar label > span {
            font-size: 11px;
            color: #64748b;
            font-weight: 600;
        }

        .toolbar label {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .toolbar select,
        .toolbar input[type="number"] {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 6px 10px;
            font: inherit;
            min-width: 4.5rem;
            background: #fff;
        }

        .toolbar .margins {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: end;
        }

        .toolbar .margins label {
            min-width: 4.2rem;
        }

        .toolbar .margins input {
            width: 4.2rem;
        }

        .toolbar button.primary {
            border: 1px solid #1c55a5;
            background: #1c55a5;
            color: #fff;
            border-radius: 8px;
            padding: 8px 16px;
            cursor: pointer;
            font: inherit;
            font-weight: 600;
        }

        .toolbar .meta {
            margin-left: auto;
            color: #475569;
            font-size: 12px;
            align-self: center;
        }

        .stage {
            overflow: auto;
            padding: 24px 16px 48px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: calc(100vh - 88px);
        }

        .sheet-wrap {
            transform-origin: top center;
        }

        .sheet {
            background: #fff;
            box-shadow: 0 10px 36px rgba(15, 23, 42, .16);
            width: var(--page-w);
            min-height: var(--page-h);
            padding: var(--mt) var(--mr) var(--mb) var(--ml);
            position: relative;
        }

        .sheet::before {
            content: '';
            position: absolute;
            inset: 0;
            border: 1px dashed #94a3b8;
            pointer-events: none;
            opacity: .35;
        }

        h1 {
            margin: 0 0 .35rem;
            text-align: center;
            font-size: 12pt;
        }

        .sheet-meta {
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
            body { background: #fff; }
            .toolbar { display: none !important; }
            .stage {
                padding: 0;
                min-height: 0;
                display: block;
                overflow: visible;
            }
            .sheet-wrap {
                transform: none !important;
            }
            .sheet {
                width: auto !important;
                min-height: auto !important;
                padding: 0 !important;
                box-shadow: none !important;
            }
            .sheet::before { display: none; }
        }
    </style>
</head>
<body
    data-default-paper="{{ $defaultPaper }}"
    data-default-orient="{{ $defaultOrient }}"
    data-mt="{{ $marginTop }}"
    data-mr="{{ $marginRight }}"
    data-mb="{{ $marginBottom }}"
    data-ml="{{ $marginLeft }}"
>
    <div class="toolbar">
        <button type="button" class="primary" onclick="window.print()">Хэвлэх</button>

        <label>
            <span>Цаасны хэмжээ</span>
            <select id="paperSize">
                <option value="A4">A4 (210×297 мм)</option>
                <option value="A5">A5 (148×210 мм)</option>
            </select>
        </label>

        <label>
            <span>Чиглэл</span>
            <select id="orient">
                <option value="portrait">Босоо</option>
                <option value="landscape">Хэвтээ</option>
            </select>
        </label>

        <div class="group">
            <span>Захын зай (мм)</span>
            <div class="margins">
                <label>
                    <span>Дээр</span>
                    <input id="marginTop" type="number" min="0" max="60" step="1">
                </label>
                <label>
                    <span>Баруун</span>
                    <input id="marginRight" type="number" min="0" max="60" step="1">
                </label>
                <label>
                    <span>Доор</span>
                    <input id="marginBottom" type="number" min="0" max="60" step="1">
                </label>
                <label>
                    <span>Зүүн</span>
                    <input id="marginLeft" type="number" min="0" max="60" step="1">
                </label>
            </div>
        </div>

        <span class="meta" id="sizeLabel">{{ $title }} · {{ $rows->count() }} мөр</span>
    </div>

    <div class="stage" id="stage">
        <div class="sheet-wrap" id="sheetWrap">
            <div class="sheet" id="sheet">
                <h1>{{ $title }}</h1>
                <p class="sheet-meta">{{ now()->format('Y') }} он · хэвлэсэн огноо: {{ now()->format('Y-m-d') }}</p>

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
        </div>
    </div>

    <script>
        (function () {
            const SIZES = {
                A4: { w: 210, h: 297 },
                A5: { w: 148, h: 210 },
            };

            const body = document.body;
            const paperEl = document.getElementById('paperSize');
            const orientEl = document.getElementById('orient');
            const mtEl = document.getElementById('marginTop');
            const mrEl = document.getElementById('marginRight');
            const mbEl = document.getElementById('marginBottom');
            const mlEl = document.getElementById('marginLeft');
            const sheet = document.getElementById('sheet');
            const wrap = document.getElementById('sheetWrap');
            const stage = document.getElementById('stage');
            const sizeLabel = document.getElementById('sizeLabel');
            const printStyle = document.getElementById('print-dynamic');
            const rowCount = {{ $rows->count() }};
            const title = @json($title);

            paperEl.value = body.dataset.defaultPaper || 'A4';
            orientEl.value = body.dataset.defaultOrient || 'portrait';
            mtEl.value = body.dataset.mt || 15;
            mrEl.value = body.dataset.mr || 12;
            mbEl.value = body.dataset.mb || 15;
            mlEl.value = body.dataset.ml || 15;

            function dims() {
                const base = SIZES[paperEl.value] || SIZES.A4;
                const landscape = orientEl.value === 'landscape';
                return {
                    w: landscape ? base.h : base.w,
                    h: landscape ? base.w : base.h,
                };
            }

            function num(el, fallback) {
                const n = Number.parseFloat(el.value);
                if (Number.isNaN(n) || n < 0) return fallback;
                return Math.min(60, n);
            }

            function apply() {
                const { w, h } = dims();
                const mt = num(mtEl, 15);
                const mr = num(mrEl, 12);
                const mb = num(mbEl, 15);
                const ml = num(mlEl, 15);

                sheet.style.setProperty('--page-w', w + 'mm');
                sheet.style.setProperty('--page-h', h + 'mm');
                sheet.style.setProperty('--mt', mt + 'mm');
                sheet.style.setProperty('--mr', mr + 'mm');
                sheet.style.setProperty('--mb', mb + 'mm');
                sheet.style.setProperty('--ml', ml + 'mm');

                printStyle.textContent =
                    '@page { size: ' + w + 'mm ' + h + 'mm; margin: ' +
                    mt + 'mm ' + mr + 'mm ' + mb + 'mm ' + ml + 'mm; }';

                sizeLabel.textContent =
                    title + ' · ' + rowCount + ' мөр · ' +
                    paperEl.value + ' ' +
                    (orientEl.value === 'landscape' ? 'хэвтээ' : 'босоо') +
                    ' (' + w + '×' + h + ' мм)';

                fitPreview(w, h);
            }

            function fitPreview(wMm, hMm) {
                // 1mm ≈ 3.78px at 96dpi
                const pxPerMm = 96 / 25.4;
                const sheetW = wMm * pxPerMm;
                const avail = Math.max(280, stage.clientWidth - 48);
                const scale = Math.min(1, avail / sheetW);
                wrap.style.transform = 'scale(' + scale.toFixed(4) + ')';
                wrap.style.marginBottom = Math.max(0, (hMm * pxPerMm * (scale - 1))) + 'px';
            }

            [paperEl, orientEl, mtEl, mrEl, mbEl, mlEl].forEach((el) => {
                el.addEventListener('change', apply);
                el.addEventListener('input', apply);
            });

            window.addEventListener('resize', apply);
            window.addEventListener('beforeprint', () => {
                wrap.style.transform = 'none';
                wrap.style.marginBottom = '0';
            });
            window.addEventListener('afterprint', apply);

            apply();
        })();
    </script>
</body>
</html>
