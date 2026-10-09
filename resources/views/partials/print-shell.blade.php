{{--
    Хэвлэх хуудасны нийтлэг бүрхүүл.

    Хэмжээ, ирмэгийн зай, фонтыг «Бичиг хэргийн стандарт»-аас авна.
    Дэлгэцэн дээр саарал дэвсгэр дээр цагаан A4 хуудас болж харагдаж,
    хэвлэхэд @page-ийн зай үйлчилнэ.

    Хэрэглэх: контроллероос $format дамжуулж, @include('partials.print-shell')
    гэж толгойд нь оруулаад агуулгаа <div class="page"> дотор бичнэ.
--}}
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
        background: #f1f5f9;
    }

    /*
     * Хуудас яг A4. Агуулга нь нэг хуудаст багтах ёстой тул өндрийг
     * тогтмол авна — илүү гарвал хоёр цаас идэхгүй.
     */
    .page {
        width: {{ $format['width'] }}mm;
        height: {{ $format['height'] }}mm;
        overflow: hidden;
        margin: 0 auto;
        padding: {{ $format['top'] }}mm {{ $format['right'] }}mm {{ $format['bottom'] }}mm {{ $format['left'] }}mm;
        background: #fff;
        box-shadow: 0 2px 12px rgb(15 23 42 / 0.12);
    }

    .toolbar {
        max-width: {{ $format['width'] }}mm;
        margin: 6mm auto;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        font-family: Arial, sans-serif;
    }

    .toolbar button {
        padding: 8px 16px; border: 1px solid #cbd5e1; border-radius: 8px;
        background: #1e3a5f; color: #fff; font-size: 13px; cursor: pointer;
    }

    .toolbar button.ghost { background: #fff; color: #1e3a5f; }

    @media print {
        body { background: #fff; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .page { margin: 0; padding: 0; width: auto; height: auto; box-shadow: none; }
        .toolbar { display: none; }
    }
</style>
