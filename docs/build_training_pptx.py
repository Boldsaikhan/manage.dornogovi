# -*- coding: utf-8 -*-
"""manage дотоод систем — сургалт, танилцуулгын PPT."""

from pathlib import Path

from pptx import Presentation
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN
from pptx.oxml.ns import qn
from pptx.util import Inches, Pt
from lxml import etree

NAVY = RGBColor(0x0F, 0x2F, 0x63)
NAVY2 = RGBColor(0x1C, 0x55, 0xA5)
ORANGE = RGBColor(0xEA, 0x58, 0x0C)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
SLATE = RGBColor(0x47, 0x55, 0x69)
DARK = RGBColor(0x0F, 0x17, 0x2A)
LIGHT = RGBColor(0xF1, 0xF5, 0xF9)
MUTED = RGBColor(0x64, 0x74, 0x8B)

W = Inches(13.333)
H = Inches(7.5)


def set_run(run, size=18, bold=False, color=DARK, font="Calibri"):
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    run.font.name = font
    rPr = run._r.get_or_add_rPr()
    ea = rPr.find(qn("a:ea"))
    if ea is None:
        ea = etree.SubElement(rPr, qn("a:ea"))
    ea.set("typeface", font)


def add_text_box(slide, l, t, w, h, text, size=18, bold=False, color=DARK, align=PP_ALIGN.LEFT):
    box = slide.shapes.add_textbox(l, t, w, h)
    tf = box.text_frame
    tf.word_wrap = True
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    set_run(run, size=size, bold=bold, color=color)
    return box


def add_rect(slide, l, t, w, h, fill):
    shape = slide.shapes.add_shape(MSO_SHAPE.RECTANGLE, l, t, w, h)
    shape.fill.solid()
    shape.fill.fore_color.rgb = fill
    shape.line.fill.background()
    return shape


def add_round(slide, l, t, w, h, fill):
    shape = slide.shapes.add_shape(MSO_SHAPE.ROUNDED_RECTANGLE, l, t, w, h)
    shape.fill.solid()
    shape.fill.fore_color.rgb = fill
    shape.line.fill.background()
    shape.adjustments[0] = 0.08
    return shape


def bullets(slide, l, t, w, h, items, size=18, color=DARK, spacing=10):
    box = slide.shapes.add_textbox(l, t, w, h)
    tf = box.text_frame
    tf.word_wrap = True
    for i, item in enumerate(items):
        p = tf.paragraphs[0] if i == 0 else tf.add_paragraph()
        p.level = 0
        p.space_after = Pt(spacing)
        run = p.add_run()
        run.text = "•  " + item
        set_run(run, size=size, color=color)
    return box


def footer(slide, page, total=20):
    add_rect(slide, 0, Inches(7.22), W, Inches(0.28), NAVY)
    add_text_box(
        slide, Inches(0.4), Inches(7.22), Inches(10), Inches(0.28),
        "manage дотоод систем  ·  Дорноговь аймгийн ЗДТГ  ·  manage.dornogovi.gov.mn",
        size=11, color=WHITE,
    )
    add_text_box(
        slide, Inches(11.6), Inches(7.22), Inches(1.4), Inches(0.28),
        f"{page} / {total}",
        size=11, color=WHITE, align=PP_ALIGN.RIGHT,
    )


def header_bar(slide, title, subtitle=None):
    add_rect(slide, 0, 0, W, Inches(1.15), NAVY)
    add_rect(slide, 0, Inches(1.15), Inches(0.12), Inches(6.07), ORANGE)
    add_text_box(slide, Inches(0.5), Inches(0.22), Inches(12), Inches(0.5), title, size=28, bold=True, color=WHITE)
    if subtitle:
        add_text_box(slide, Inches(0.5), Inches(0.7), Inches(12), Inches(0.35), subtitle, size=14, color=RGBColor(0xBF, 0xDB, 0xFE))


def card(slide, l, t, w, h, title, body, accent=NAVY2):
    add_round(slide, l, t, w, h, WHITE)
    add_rect(slide, l, t, Inches(0.1), h, accent)
    add_text_box(slide, l + Inches(0.28), t + Inches(0.15), w - Inches(0.4), Inches(0.4), title, size=16, bold=True, color=NAVY)
    add_text_box(slide, l + Inches(0.28), t + Inches(0.55), w - Inches(0.4), h - Inches(0.7), body, size=14, color=SLATE)


def build():
    prs = Presentation()
    prs.slide_width = W
    prs.slide_height = H
    blank = prs.slide_layouts[6]
    total = 20

    # 1 Title
    s = prs.slides.add_slide(blank)
    add_rect(s, 0, 0, W, H, NAVY)
    add_rect(s, 0, Inches(6.55), W, Inches(0.95), ORANGE)
    add_text_box(s, Inches(0.7), Inches(1.6), Inches(12), Inches(0.4), "ДОРНОГОВЬ АЙМГИЙН ЗДТГ", size=16, color=RGBColor(0x93, 0xC5, 0xFD), bold=True)
    add_text_box(s, Inches(0.7), Inches(2.1), Inches(12), Inches(1.2), "manage дотоод систем", size=44, bold=True, color=WHITE)
    add_text_box(s, Inches(0.7), Inches(3.4), Inches(12), Inches(0.8), "Албан хаагчдад зориулсан сургалт, танилцуулга", size=22, color=RGBColor(0xE2, 0xE8, 0xF0))
    add_text_box(s, Inches(0.7), Inches(6.7), Inches(12), Inches(0.5), "https://manage.dornogovi.gov.mn", size=18, bold=True, color=WHITE)

    # 2 Agenda
    s = prs.slides.add_slide(blank)
    header_bar(s, "Сургалтын хөтөлбөр", "Ойролцоогоор 30–40 минут")
    items = [
        ("01", "Систем юу хийдэг вэ"),
        ("02", "Хэрхэн нэвтрэх"),
        ("03", "Самбар, холбосон системүүд"),
        ("04", "Үүрэг даалгавар, ажлын удирдлага"),
        ("05", "Бичиг хэрэг, хүний нөөц"),
        ("06", "Эрх, мобайл, анхаарах зүйлс"),
    ]
    for i, (num, label) in enumerate(items):
        col, row = i % 2, i // 2
        x = Inches(0.55 + col * 6.3)
        y = Inches(1.55 + row * 1.7)
        add_round(s, x, y, Inches(6.0), Inches(1.45), WHITE)
        add_text_box(s, x + Inches(0.3), y + Inches(0.3), Inches(1.2), Inches(0.8), num, size=28, bold=True, color=ORANGE)
        add_text_box(s, x + Inches(1.5), y + Inches(0.45), Inches(4.2), Inches(0.6), label, size=20, bold=True, color=NAVY)
    footer(s, 2, total)

    # 3 Why
    s = prs.slides.add_slide(blank)
    header_bar(s, "Яагаад энэ систем вэ", "Нэг цонхноос ажлаа удирдана")
    cards = [
        ("Нэг хаяг", "Төрийн ERP, шуудан, дашборд болон дотоод бүртгэл нэг дор. Хаяг хайх шаардлагагүй."),
        ("Аюулгүй нэвтрэлт", "Нэвтрэх мэдээлэл шифрлэгдэнэ. Зөвхөн эзэмшигч өөрөө задлана."),
        ("Хамтын ажил", "Үүрэг, төлөвлөгөө, чөлөө, шагнал — хэлтэс хооронд нэг мэдээлэл."),
        ("Эрхийн хяналт", "Модуль тус бүр харах / удирдах эрхтэй. Хэлтсийн дарга, мэргэжилтэн ялгаатай."),
    ]
    for i, (title, body) in enumerate(cards):
        col, row = i % 2, i // 2
        card(s, Inches(0.5 + col * 6.4), Inches(1.55 + row * 2.5), Inches(6.1), Inches(2.25), title, body, ORANGE if i % 2 else NAVY2)
    footer(s, 3, total)

    # 4 Login
    s = prs.slides.add_slide(blank)
    header_bar(s, "Хэрхэн нэвтрэх вэ", "Браузер дээр хаягаа нээнэ")
    add_round(s, Inches(0.5), Inches(1.5), Inches(12.3), Inches(1.2), LIGHT)
    add_text_box(s, Inches(0.75), Inches(1.75), Inches(12), Inches(0.7), "Хаяг:  https://manage.dornogovi.gov.mn", size=24, bold=True, color=NAVY)
    bullets(s, Inches(0.6), Inches(2.95), Inches(12), Inches(3.8), [
        "Нэвтрэх нэр — гар утасны дугаар (8 орон).",
        "Нууц үг — утасны сүүлийн 4 орон + нэрийн латин бичлэг.",
        "И-мэйл хаяг — нэр@dornogovi.gov.mn хэлбэртэй (жнь: badral@dornogovi.gov.mn).",
        "Нууц үгээ мартсан бол админ / Хандах эрх хэсгээс шинэчилнэ.",
        "Эхний нэвтрэлтийн дараа өөрийн нууц үгээ солихыг зөвлөнө.",
    ], size=18)
    footer(s, 4, total)

    # 5 Login examples
    s = prs.slides.add_slide(blank)
    header_bar(s, "Нэвтрэх жишээ", "Дүрэм: 4 орон + латин нэр")
    rows = [
        ("Нэр", "Утас (нэвтрэх)", "Нууц үг", "И-мэйл"),
        ("А.Номин", "99178904", "8904Nomin", "nomin@dornogovi.gov.mn"),
        ("А.Бадрал", "94588599", "8599Badral", "badral@dornogovi.gov.mn"),
        ("Ц.Сансармаа", "91116259", "6259Sansarmaa", "sansarmaa@dornogovi.gov.mn"),
    ]
    col_w = [Inches(2.6), Inches(3.0), Inches(3.2), Inches(3.5)]
    x0, y0 = Inches(0.5), Inches(1.55)
    for r, row in enumerate(rows):
        x = x0
        for c, val in enumerate(row):
            bg = NAVY if r == 0 else (LIGHT if r % 2 == 0 else WHITE)
            fg = WHITE if r == 0 else DARK
            add_rect(s, x, y0 + Inches(r * 0.7), col_w[c], Inches(0.7), bg)
            add_text_box(s, x + Inches(0.15), y0 + Inches(r * 0.7) + Inches(0.15), col_w[c] - Inches(0.2), Inches(0.45), val, size=16, bold=(r == 0 or c == 2), color=fg)
            x += col_w[c]
    add_text_box(
        s, Inches(0.5), Inches(4.55), Inches(12.3), Inches(2.2),
        "Кирилл нэр латин болно: Номин → Nomin, Бадрал → Badral, Сансармаа → Sansarmaa.\n"
        "Хэрэв ижил нэртэй хүн байвал и-мэйл nomin2@… гэж дугаарлагдана.\n"
        "Супер админы нууц үг энэ дүрмээр солигдохгүй.",
        size=16, color=SLATE,
    )
    footer(s, 5, total)

    # 6 UI
    s = prs.slides.add_slide(blank)
    header_bar(s, "Дэлгэцийн бүтэц", "Зүүн цэсээр бүх хэсэгт орно")
    groups = [
        ("САМБАР", "Албан хаагчийн самбар — өөрийн ажил, чөлөө, үүргийн тойм"),
        ("ХОЛБООСОН СИСТЕМҮҮД", "Төрийн ERP, дашборд, шуудан, унаа тээвэр гэх мэт"),
        ("АЖЛЫН УДИРДЛАГА", "Үүрэг даалгавар, ажлын хэсэг, төлөвлөгөө, хурал, тайлан"),
        ("БИЧИГ ХЭРЭГ", "Журам, захирамж/тушаал, гэрээ, архив, стандарт"),
        ("ХҮНИЙ НӨӨЦ", "Утасны жагсаалт, чөлөө, томилолт, шагнал"),
        ("УДИРДЛАГА", "Хандах эрх — зөвхөн админ / эрхтэй хэрэглэгч"),
    ]
    for i, (title, body) in enumerate(groups):
        y = Inches(1.45 + i * 0.88)
        add_round(s, Inches(0.5), y, Inches(12.3), Inches(0.78), WHITE)
        add_rect(s, Inches(0.5), y, Inches(0.12), Inches(0.78), ORANGE if i == 0 else NAVY2)
        add_text_box(s, Inches(0.85), y + Inches(0.08), Inches(11.7), Inches(0.32), title, size=15, bold=True, color=NAVY)
        add_text_box(s, Inches(0.85), y + Inches(0.38), Inches(11.7), Inches(0.32), body, size=14, color=SLATE)
    footer(s, 6, total)

    # 7 Dashboard
    s = prs.slides.add_slide(blank)
    header_bar(s, "Албан хаагчийн самбар", "Нэвтэрсний дараах эхний хуудас")
    bullets(s, Inches(0.55), Inches(1.5), Inches(12.2), Inches(5.2), [
        "Хүлээгдэж буй чөлөө, идэвхтэй томилолт, төлөвлөгөө, ажлын хэсгийн тоо харагдана.",
        "Үүрэг даалгаврын дундаж биелэлтийг хувиар харна.",
        "Карт дээр дарахад тухайн бүртгэл рүү шууд орно.",
        "Эндээс өдөр тутмын ажлын тоймоо авна — бүх цэсийг эхнээс нь хайхгүй.",
    ], size=20, spacing=16)
    footer(s, 7, total)

    # 8 Connected systems
    s = prs.slides.add_slide(blank)
    header_bar(s, "Холбосон системүүд", "Төрийн систем рүү нэг товчоор")
    bullets(s, Inches(0.55), Inches(1.5), Inches(12.2), Inches(5.2), [
        "Жишээ: Төрийн ERP, Төрийн дашборд, Төрийн шуудан, Унаа тээврийн систем.",
        "Нэвтрэх нэр, нууц үгээ нэг удаа хадгална — дараа нь автоматаар бөглөгдөнө.",
        "Хадгалсан нууц үгийг задлахын тулд энэ системийн нууц үг эсвэл хурууны хээ / нүүрээр баталгаажуулна.",
        "Хөтөч дээрх өргөтгөл (extension) холбосон системд автоматаар нэвтрүүлэхэд тусална.",
        "Нэвтрэх мэдээлэл байхгүй систем дээр улаан тэмдэг гарвал «нэвтрэх мэдээлэл нэмнэ».",
    ], size=18, spacing=14)
    footer(s, 8, total)

    # 9 Tasks
    s = prs.slides.add_slide(blank)
    header_bar(s, "Үүрэг даалгавар", "Хэрэгжилтийг хүснэгтээр шууд засна")
    card(s, Inches(0.5), Inches(1.5), Inches(6.1), Inches(2.4), "Үүрэг чиглэл", "№, агуулга, хариуцах эзэн, хяналт тавих албан тушаалтан, хэрэгжилт, биелэлтийн хувь.", NAVY2)
    card(s, Inches(6.75), Inches(1.5), Inches(6.05), Inches(2.4), "Бэлтгэл ажил хангах төлөвлөгөө", "Ажлын чиглэл, арга хэмжээ, хугацаа, хариуцах / хамтран хэрэгжүүлэх, хэрэгжилт.", ORANGE)
    bullets(s, Inches(0.55), Inches(4.15), Inches(12.2), Inches(2.7), [
        "«+ Хэсэг нэмэх» — энэ хоёртой ижил шинэ таб үүсгэнэ (нэр + загвар сонгоно).",
        "«Үүрэг чиглэл нэмэх» / «Мөр нэмэх» — тухайн хэсэгт шинэ мөр нэмнэ.",
        "Word оруулах, Word / Excel / PDF-ээр татах боломжтой.",
        "Нүдэн дээр дарж нэр сонгоно — утасны жагсаалтаас хайна.",
    ], size=16, spacing=8)
    footer(s, 9, total)

    # 10 Task how-to
    s = prs.slides.add_slide(blank)
    header_bar(s, "Үүрэг дээр ажиллах дараалал", "Өдөр тутмын хэрэглээ")
    steps = [
        ("1", "Табаа сонгоно", "Үүрэг чиглэл эсвэл төлөвлөгөө, эсвэл шинэ хэсэг."),
        ("2", "Мөр нэмнэ / засна", "Нүд дээр дарж бичнэ. Хадгалах товч дарахгүй — шууд хадгалагдана."),
        ("3", "Хариуцагч онооно", "Утасны жагсаалтаас нэр сонгоно. Олон хүн « / »-аар залгана."),
        ("4", "Хувь шинэчилнэ", "0–100%. Дашборд дээр нийт, дууссан, эхлээгүй тоо харагдана."),
    ]
    for i, (n, t, b) in enumerate(steps):
        y = Inches(1.45 + i * 1.3)
        add_round(s, Inches(0.5), y, Inches(12.3), Inches(1.15), WHITE)
        add_round(s, Inches(0.7), y + Inches(0.28), Inches(0.6), Inches(0.6), ORANGE)
        add_text_box(s, Inches(0.7), y + Inches(0.35), Inches(0.6), Inches(0.5), n, size=20, bold=True, color=WHITE, align=PP_ALIGN.CENTER)
        add_text_box(s, Inches(1.55), y + Inches(0.18), Inches(10.8), Inches(0.4), t, size=18, bold=True, color=NAVY)
        add_text_box(s, Inches(1.55), y + Inches(0.58), Inches(10.8), Inches(0.42), b, size=15, color=SLATE)
    footer(s, 10, total)

    # 11 Work mgmt
    s = prs.slides.add_slide(blank)
    header_bar(s, "Бусад ажлын удирдлага", "Үүрэг даалгаврын хажуугийн цэсүүд")
    items = [
        ("Ажлын хэсэг", "Түр баг, үүрэг, явцыг бүртгэнэ."),
        ("Төлөвлөгөө", "Хэлтэс, байгууллагын төлөвлөгөө."),
        ("Хурлын тэмдэглэл", "Хурлын шийдвэр, оролцогч."),
        ("Тайлан мэдээлэл", "Тайлангийн бүртгэл, хавсралт."),
    ]
    for i, (t, b) in enumerate(items):
        col, row = i % 2, i // 2
        card(s, Inches(0.5 + col * 6.4), Inches(1.55 + row * 2.5), Inches(6.1), Inches(2.25), t, b)
    footer(s, 11, total)

    # 12 Documents
    s = prs.slides.add_slide(blank)
    header_bar(s, "Бичиг хэрэг", "Албан бичгийн бүртгэл нэг дор")
    items = [
        ("Дотоод журам", "Байгууллагын журам, заавар."),
        ("Захирамж, тушаал", "Дугаарлалт, хэвлэх, бүртгэл."),
        ("Гэрээний дугаар", "Гэрээнд дугаар өгөх."),
        ("Архив", "Хадгалах, хайх."),
        ("Бичиг хэргийн стандарт", "Загвар, шаардлага."),
        ("Сан", "Баримт бичгийн сан."),
    ]
    for i, (t, b) in enumerate(items):
        col, row = i % 3, i // 3
        card(s, Inches(0.4 + col * 4.25), Inches(1.5 + row * 2.55), Inches(4.05), Inches(2.3), t, b, ORANGE if i == 1 else NAVY2)
    footer(s, 12, total)

    # 13 HR
    s = prs.slides.add_slide(blank)
    header_bar(s, "Хүний нөөц", "Өдөр тутмын бүртгэл")
    items = [
        ("Утасны жагсаалт", "Байгууллага, албан хаагчдын өрөө / гар утас. Хайлт, ангилал, Word татах, шинэ нэмэх."),
        ("Чөлөөний бүртгэл", "Хүснэгт болон хуудасны харагдац. А4-т 6 ширхэгээр хэвлэнэ."),
        ("Томилолтын бүртгэл", "Албан томилолтын явц, хугацаа."),
        ("Шагнал", "Төрийн дээд одон, АЗД шагнал, бусад. Нүдэн дээр дарж бөглөнө, Excel татна."),
    ]
    for i, (t, b) in enumerate(items):
        col, row = i % 2, i // 2
        card(s, Inches(0.5 + col * 6.4), Inches(1.5 + row * 2.55), Inches(6.1), Inches(2.35), t, b)
    footer(s, 13, total)

    # 14 Phone
    s = prs.slides.add_slide(blank)
    header_bar(s, "Утасны жагсаалт — түгээмэл хэрэглээ", "Нэр сонголт бусад модульд эндээс ирнэ")
    bullets(s, Inches(0.55), Inches(1.5), Inches(12.2), Inches(5.2), [
        "Нэр, албан тушаал, утсаар хайна.",
        "Ангилал: Аймгийн удирдлага, Хэлтэс, Агентлаг, Сумд, Байгууллага.",
        "Засварлах горимд нэр, утас, албан тушаалыг шууд хүснэгтэнд засна.",
        "«Шинэ нэмэх» — шинэ албан хаагч / нэгж нэмнэ.",
        "Үүрэг даалгавар, чөлөө, хандах эрх дээрх нэрийн жагсаалт энэ бүртгэлээс гарна.",
        "Тиймээс утас, нэр зөв байх нь бүхэл системийн үндэс.",
    ], size=18, spacing=12)
    footer(s, 14, total)

    # 15 Leave + awards
    s = prs.slides.add_slide(blank)
    header_bar(s, "Чөлөө ба шагнал", "Бүртгэл → хэвлэх / Excel")
    card(
        s, Inches(0.5), Inches(1.5), Inches(6.1), Inches(5.2),
        "Чөлөөний бүртгэл",
        "Шинэ чөлөө нэмнэ: хамрах хүрээ, байгууллага, албан хаагч, төрөл, хоног, үндэслэл.\n\n"
        "Хүснэгт — бүх багана дэлгэцэнд багтана.\n"
        "Хуудасны харагдац — хэвлэхэд бэлэн хуудас.\n"
        "«Хэвлэх» — тухайн мөрийн хуудас.",
        NAVY2,
    )
    card(
        s, Inches(6.75), Inches(1.5), Inches(6.05), Inches(5.2),
        "Шагнал",
        "Таб: төрийн дээд одон, АЗД өргөмжлөл/жуух, тэргүүний, бусад.\n\n"
        "Онөөр шүүнэ. Нүдэн дээр дарж засна.\n"
        "Excel татах — тайлан гаргахад.\n"
        "«Шинэ нэмэх» — хоосон мөр нэмэгдэнэ.",
        ORANGE,
    )
    footer(s, 15, total)

    # 16 Access
    s = prs.slides.add_slide(blank)
    header_bar(s, "Хандах эрх", "Админ, хэлтсийн даргад")
    bullets(s, Inches(0.55), Inches(1.5), Inches(12.2), Inches(5.2), [
        "Зүүн талд албан хаагчдын жагсаалт — нэр, и-мэйл, утсаар хайна. Жагсаалт талбар дотроо гүйлгэнэ.",
        "«Хэлтэст бүгдэд эрх өгөх» — утасны жагсаалтын Хэлтэс ангиллаас нэвтрэх эрх үүсгэнэ.",
        "Роль: супер админ, хэлтсийн дарга, мэргэжилтэн. Загвар хэрэглээд модуль тус бүрээр засаж болно.",
        "Модулийн эрх: Харах (бүгд) · Удирдах (бүгд) · Харах/удирдах (хамааралтай) · Хаалттай.",
        "Шинэ албан хаагч нэмэхдээ утасны жагсаалтаас нэр сонгоно.",
    ], size=17, spacing=12)
    footer(s, 16, total)

    # 17 Mobile
    s = prs.slides.add_slide(blank)
    header_bar(s, "Утас, аюулгүй байдал", "Гар утсан дээр ч ашиглана")
    items = [
        ("Нүүр дэлгэцэнд нэмэх", "Safari / Chrome-оос «Add to Home Screen». Апп шиг нээгдэнэ."),
        ("Түгжээ", "Идэвхгүй бол нууц үг эсвэл биометрээр дахин нээнэ."),
        ("QR уншигч", "Гар утсан дээр толгой хэсэгт. Нэвтрэх болон холбосон системийн QR."),
        ("Мэдэгдэл", "Хонхны дүрс — үүрэг, эрх шинэчлэгдсэн мэдэгдэл."),
    ]
    for i, (t, b) in enumerate(items):
        col, row = i % 2, i // 2
        card(s, Inches(0.5 + col * 6.4), Inches(1.5 + row * 2.55), Inches(6.1), Inches(2.35), t, b)
    footer(s, 17, total)

    # 18 Roles reminder
    s = prs.slides.add_slide(blank)
    header_bar(s, "Хэн юу хийх вэ", "Эрхээс хамаарна")
    rows = [
        ("Түвшин", "Юу хийх вэ"),
        ("Мэргэжилтэн", "Өөрт хамаатай үүрэг, бүртгэлийг харна / засна. Цэс эрхээр нээгдэнэ."),
        ("Хэлтсийн дарга", "Хэлтсийнхээ үүрэг, чөлөө, төлөвлөгөөг удирдана."),
        ("Супер админ", "Бүх модуль, хандах эрх, холбосон систем, хэрэглэгч."),
    ]
    widths = [Inches(3.2), Inches(9.1)]
    y0 = Inches(1.55)
    for r, row in enumerate(rows):
        x = Inches(0.5)
        for c, val in enumerate(row):
            bg = NAVY if r == 0 else (LIGHT if r % 2 == 0 else WHITE)
            fg = WHITE if r == 0 else DARK
            add_rect(s, x, y0 + Inches(r * 1.05), widths[c], Inches(1.05), bg)
            add_text_box(s, x + Inches(0.2), y0 + Inches(r * 1.05) + Inches(0.3), widths[c] - Inches(0.35), Inches(0.5), val, size=16, bold=(r == 0 or c == 0), color=fg)
            x += widths[c]
    add_text_box(s, Inches(0.5), Inches(5.9), Inches(12.3), Inches(0.9), "Цэс харагдахгүй бол эрх дутуу — Хандах эрх хэсгээс тохируулна.", size=16, color=SLATE)
    footer(s, 18, total)

    # 19 Tips
    s = prs.slides.add_slide(blank)
    header_bar(s, "Анхаарах зүйлс", "Сургалтын дараа дадлага")
    bullets(s, Inches(0.55), Inches(1.5), Inches(12.2), Inches(5.2), [
        "Нууц үгээ бусадтай бүү хуваалцана. Анхны нууц үгийг солино.",
        "Утасны жагсаалт дахь нэр, дугаараа шалгана — бусад бүртгэл эндээс авна.",
        "Үүргийн биелэлтийг тогтмол хувиар шинэчилнэ. Хоосон үлдээвэл дашборд буруу харагдана.",
        "Чөлөө, томилолтоо өөрөө бүртгүүлэх / хэлтсийн мэргэжилтэнд мэдэгдэнэ.",
        "Асуудал гарвал: эхлээд шинэчлэх (Ctrl+F5), дараа нь мэдээлэл технологийн мэргэжилтэнд хандана.",
        "Хаяг зөвхөн https://manage.dornogovi.gov.mn — хуурамч хуудаснаас болгоомжилно.",
    ], size=18, spacing=12)
    footer(s, 19, total)

    # 20 Thanks
    s = prs.slides.add_slide(blank)
    add_rect(s, 0, 0, W, H, NAVY)
    add_rect(s, 0, Inches(6.55), W, Inches(0.95), ORANGE)
    add_text_box(s, Inches(0.7), Inches(2.1), Inches(12), Inches(1.0), "Асуулт байна уу?", size=40, bold=True, color=WHITE)
    add_text_box(
        s, Inches(0.7), Inches(3.3), Inches(12), Inches(1.4),
        "Одоо хамтдаа нэвтэрч, самбар болон үүрэг даалгавар дээр\n2–3 минут дадлага хийцгээе.",
        size=20, color=RGBColor(0xE2, 0xE8, 0xF0),
    )
    add_text_box(s, Inches(0.7), Inches(6.7), Inches(12), Inches(0.5), "manage.dornogovi.gov.mn", size=18, bold=True, color=WHITE)

    out = Path(__file__).resolve().parent / "manage-dotood-sistem-surgalt.pptx"
    prs.save(out)
    print(out)


if __name__ == "__main__":
    build()
