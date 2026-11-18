"""
Төрийн дээд одон, медалийн Excel/Word эх файлуудыг нэг JSON болгоно.
"""

from __future__ import annotations

import json
import re
import zipfile
from pathlib import Path

import openpyxl

DOWNLOADS = Path(r"C:\Users\IT-PC\Downloads")
OUT = Path(r"C:\xampp\htdocs\manage.dornogovi.gov.mn\storage\app\imports\state_high_awards.json")

# 2022 хоёр файл ойролцоо — илүү шинэ/_1-ийг авна.
STANDARD_FILES = [
    (DOWNLOADS / "ТӨРИЙН ШАГНАЛ-2022 он_1.xlsx", 2022),
    (DOWNLOADS / "ОДОН-БҮРТГЭЛ-2023.xlsx", 2023),
    # 2024 — шинэчилсэн файл (_1). Хуучин ОДОН-БҮРТГЭЛ-2024.xlsx-ийг бүү ашигла.
    (DOWNLOADS / "ОДОН-БҮРТГЭЛ-2024_1.xlsx", 2024),
    (DOWNLOADS / "төрийн шагнал бүртгэл 2025он.xlsx", 2025),
    (DOWNLOADS / "төрийн шагнал бүртгэл 2026он.xlsx", 2026),
]

STUDY_FILE = DOWNLOADS / "2017-2021 ОНЫ ШАГНАЛ СУДАЛГАА.xlsx"
HEADER_FILE = DOWNLOADS / "шагнал толгой_1.xlsx"
WORD_FILE = DOWNLOADS / "БҮРТГЭЛ-төрийн шагнал.docx"
SECTOR_FILE = DOWNLOADS / "салбар төрөл.docx"


def clean(value) -> str:
    if value is None:
        return ""
    text = str(value).replace("\xa0", " ").strip()
    text = re.sub(r"[ \t]+", " ", text)
    text = re.sub(r"\n+", " ", text)
    return text.strip(" /|-")


def to_int(value) -> int | None:
    text = clean(value)
    if not text:
        return None
    match = re.search(r"-?\d+", text.replace(",", "."))
    if not match:
        return None
    try:
        return int(match.group(0))
    except ValueError:
        return None


def parse_date(text: str) -> str | None:
    text = clean(text)
    if not text:
        return None

    patterns = [
        (r"(\d{4})[./-](\d{1,2})[./-](\d{1,2})", "%Y-%m-%d"),
        (r"(\d{4})\s*оны\s*(\d{1,2})\s*сарын\s*(\d{1,2})", "%Y-%m-%d"),
        (r"(\d{4})-(\d{1,2})-(\d{1,2})", "%Y-%m-%d"),
    ]
    for pattern, _ in patterns:
        m = re.search(pattern, text)
        if not m:
            continue
        y, mo, d = int(m.group(1)), int(m.group(2)), int(m.group(3))
        if mo > 12 and d <= 12:
            mo, d = d, mo
        if 1 <= mo <= 12 and 1 <= d <= 31 and 1900 <= y <= 2100:
            return f"{y:04d}-{mo:02d}-{d:02d}"
    return None


def parse_letter_no(text: str) -> str | None:
    text = clean(text)
    if not text:
        return None
    m = re.search(r"(\d{1,3}\s*/\s*\d{1,6})", text)
    if m:
        return re.sub(r"\s+", "", m.group(1))
    m = re.search(r"№?\s*(\d{1,6})\s*[-–]?\s*р", text, re.I)
    if m:
        return m.group(1)
    return None


def split_last_award(text: str) -> tuple[str | None, str | None]:
    text = clean(text)
    if not text:
        return None, None
    year = None
    m = re.search(r"(19|20)\d{2}", text)
    if m:
        year = f"{m.group(0)}-01-01"
    return text, year


def parse_decree_blob(text: str) -> tuple[str | None, str | None, str | None]:
    """Зарлигийн огноо/дугаар → (date, number, note)."""
    text = clean(text)
    if not text:
        return None, None, None
    date = parse_date(text)
    number = None
    m = re.search(r"№?\s*(\d{1,6})\s*[-–]?\s*р?\s*зарлиг", text, re.I)
    if m:
        number = m.group(1)
    else:
        m = re.search(r"(\d{1,3}/\d{1,6})", text)
        if m:
            number = m.group(1)
        else:
            m = re.search(r"№\s*(\d{1,6})", text)
            if m:
                number = m.group(1)
    return date, number, text if (date or number) else text


def parse_forward_blob(text: str) -> tuple[str | None, str | None, str | None]:
    text = clean(text)
    if not text:
        return None, None, None
    return parse_date(text), parse_letter_no(text), text


def split_mongolian_full_name(full: str) -> tuple[str | None, str | None]:
    full = clean(full)
    if not full:
        return None, None
    # «Рэгзэнгийн Мөнцэцэг» → овог Рэгзэн, нэр Мөнцэцэг
    m = re.match(
        r"^(.+?)(?:гийн|ыин|ийн|ын|ий|ы)\s+(.+)$",
        full,
        flags=re.IGNORECASE,
    )
    if m:
        return clean(m.group(1)), clean(m.group(2))
    parts = full.split()
    if len(parts) >= 2:
        return parts[0], " ".join(parts[1:])
    return None, full


def blank_row(entry: dict) -> bool:
    return not any(
        entry.get(k)
        for k in (
            "surname",
            "given_name",
            "register_no",
            "nominated_award",
            "received_award",
            "position",
        )
    )


def dedupe_key(entry: dict) -> str:
    return "|".join(
        [
            str(entry.get("year") or ""),
            (entry.get("register_no") or "").upper().replace(" ", ""),
            (entry.get("surname") or "").lower(),
            (entry.get("given_name") or "").lower(),
            (entry.get("nominated_award") or "").lower(),
        ]
    )


def richness(entry: dict) -> int:
    return sum(1 for v in entry.values() if v not in (None, "", []))


def upsert(bucket: dict[str, dict], entry: dict) -> None:
    if blank_row(entry):
        return
    key = dedupe_key(entry)
    prev = bucket.get(key)
    if prev is None or richness(entry) >= richness(prev):
        bucket[key] = entry


def sheet_rows(path: Path):
    wb = openpyxl.load_workbook(path, read_only=True, data_only=True)
    try:
        for name in wb.sheetnames:
            ws = wb[name]
            rows = []
            for row in ws.iter_rows(values_only=True):
                vals = [clean(v) for v in row]
                while vals and vals[-1] == "":
                    vals.pop()
                if any(vals):
                    rows.append(vals)
            yield name, rows
    finally:
        wb.close()


def find_header(rows: list[list[str]], needles: list[str]) -> int | None:
    for idx, row in enumerate(rows[:12]):
        joined = " ".join(row).lower()
        if all(n.lower() in joined for n in needles):
            return idx
    return None


def map_by_headers(header: list[str], aliases: dict[str, list[str]]) -> dict[str, int]:
    mapping: dict[str, int] = {}
    used = set()
    norm = [re.sub(r"\s+", " ", h.lower()) for h in header]
    for field, keys in aliases.items():
        for i, h in enumerate(norm):
            if i in used or not h:
                continue
            if any(k in h for k in keys):
                mapping[field] = i
                used.add(i)
                break
    return mapping


STANDARD_ALIASES = {
    "year": ["тодорхойлсон он", "он"],
    "nominated_award": [
        "өргөн мэдүүлсэн шагнал",
        "өргөн мэдүүл-сэн шагнал",
        "өргөн мэдүүл",
        "шагналын нэр",
        "өргөн мэдүүлсэн шагналын төрөл",
    ],
    "surname": ["овог"],
    "given_name": ["нэр"],
    "register_no": ["регистрийн", "регистр", "pегистр", "рег"],
    "age": ["нас"],
    "gender": ["хүйс"],
    "position": ["ажил, албан", "албан тушаал", "эрхэлсэн ажил", "эрхэлдэг ажил"],
    "work_sector": ["харьяалах салбар", "ажилладаг салбар", "салбар"],
    "profession": ["мэргэжил"],
    "years_in_country": ["нийт ажилласан", "улсад ажилласан", "ажилласан жил"],
    "last_award": ["сүүлд авсан", "хамгийн сүүлд"],
    "forwarded_org_blob": ["дээд байгууллага", "санал авахаар уламжилсан"],
    "presidential_blob": ["муетг", "ерөнхийлөгчийн тамгын", "аймгийн итх", "му-ын ерөнхийлөгч"],
    "decree_blob": ["зарлиг", "шагнал олгох"],
}


def from_standard(path: Path, default_year: int) -> list[dict]:
    out: list[dict] = []
    for _name, rows in sheet_rows(path):
        if not rows:
            continue
        hi = find_header(rows, ["овог", "нэр"])
        if hi is None:
            continue
        mapping = map_by_headers(rows[hi], STANDARD_ALIASES)
        for row in rows[hi + 1 :]:
            def get(field: str) -> str:
                i = mapping.get(field)
                return clean(row[i]) if i is not None and i < len(row) else ""

            year = to_int(get("year")) or default_year
            last_name, last_date = split_last_award(get("last_award"))
            fwd_date, fwd_no, fwd_note = parse_forward_blob(get("forwarded_org_blob"))
            pre_date, pre_no, pre_note = parse_forward_blob(get("presidential_blob"))
            dec_date, dec_no, dec_note = parse_decree_blob(get("decree_blob"))

            # МУЕТГ багана илүү «уламжилсан» мэдээлэлтэй байдаг.
            if pre_date and not fwd_date:
                fwd_date, fwd_no = pre_date, pre_no
            supporting = None
            if get("presidential_blob") or get("forwarded_org_blob"):
                supporting = "МУЕТГ" if ("муетг" in get("presidential_blob").lower() or "ерөнхийлөгч" in get("presidential_blob").lower()) else None

            notes_parts = [p for p in [fwd_note, pre_note, dec_note] if p]
            nominated = get("nominated_award") or None
            received = nominated if (dec_date or dec_no) else None

            entry = {
                "year": year,
                "nominated_award": nominated,
                "surname": get("surname") or None,
                "given_name": get("given_name") or None,
                "register_no": get("register_no").upper().replace(" ", "") or None,
                "age": to_int(get("age")),
                "gender": get("gender") or None,
                "position": get("position") or None,
                "work_sector": get("work_sector") or None,
                "profession": get("profession") or None,
                "years_in_country": to_int(get("years_in_country")),
                "last_award": last_name,
                "last_award_date": last_date,
                "supporting_org": supporting,
                "forwarded_on": fwd_date,
                "forwarded_letter_no": fwd_no,
                "received_award": received,
                "award_date": dec_date,
                "resolution_number": dec_no,
                "presidential_letter": pre_note,
                "notes": " | ".join(dict.fromkeys(notes_parts)) or None,
                "source": path.name,
            }
            out.append(entry)
    return out


STUDY_ALIASES = {
    "surname": ["овог"],
    "given_name": ["нэр"],
    "register_no": ["регистр", "pегистр", "регистрийн"],
    "position": ["эрхэлсэн ажил", "албан тушаал"],
    "address": ["оршин суугаа", "хаяг"],
    "nominated_award": ["шагналын нэр"],
    "received_blob": ["шагнагдсан"],
    "forwarded_blob": ["уламжилсан", "албан бичгийн"],
    "date": ["огноо"],
    "resolution": ["тогтоолын"],
}


def from_study(path: Path) -> list[dict]:
    out: list[dict] = []
    for name, rows in sheet_rows(path):
        if not re.fullmatch(r"20(17|18|19|20|21)", name.strip()):
            continue
        year = int(name.strip())
        hi = find_header(rows, ["овог"])
        if hi is None:
            continue
        mapping = map_by_headers(rows[hi], STUDY_ALIASES)
        for row in rows[hi + 1 :]:
            def get(field: str) -> str:
                i = mapping.get(field)
                return clean(row[i]) if i is not None and i < len(row) else ""

            nominated = get("nominated_award") or None
            dec_date, dec_no, dec_note = parse_decree_blob(get("received_blob"))
            fwd_date, fwd_no, fwd_note = parse_forward_blob(get("forwarded_blob"))
            entry = {
                "year": year,
                "nominated_award": nominated,
                "surname": get("surname") or None,
                "given_name": get("given_name") or None,
                "register_no": get("register_no").upper().replace(" ", "") or None,
                "position": get("position") or None,
                "address": get("address") or None,
                "supporting_org": "МУЕТГ" if get("forwarded_blob") else None,
                "forwarded_on": fwd_date,
                "forwarded_letter_no": fwd_no,
                "received_award": nominated if (dec_date or dec_no or "зарлиг" in get("received_blob").lower()) else None,
                "award_date": dec_date,
                "resolution_number": dec_no or (get("resolution") or None),
                "notes": " | ".join(p for p in [get("received_blob"), fwd_note, dec_note] if p) or None,
                "source": f"{path.name}#{name}",
            }
            out.append(entry)
    return out


HEADER_ALIASES = {
    "nominated_award": ["өргөн мэдүүлсэн шагналын төрөл", "шагналын төрөл"],
    "surname": ["овог"],
    "given_name": ["нэр"],
    "register_no": ["регистрийн"],
    "position": ["ажил, албан", "албан тушаал"],
    "work_sector": ["харьяалах салбар", "салбар"],
    "profession": ["мэргэжил"],
    "years_in_country": ["улсад ажилласан", "ажилласан жил"],
    "last_award": ["шагналын нэр"],
    "last_award_date": ["огноо"],
    "supporting_org": ["байгууллага"],
    "forwarded_on": ["огноо"],
    "forwarded_letter_no": ["албан бичгийн"],
    "received_award": ["шагналын төрөл"],
    "award_date": ["зарлигийн огноо"],
    "resolution_number": ["зарлиг"],
}


def from_header_template(path: Path) -> list[dict]:
    """шагнал толгой_1.xlsx — UI-тай ойролцоо 2 мөртэй толгой."""
    out: list[dict] = []
    for _name, rows in sheet_rows(path):
        if len(rows) < 3:
            continue
        # 2-р мөр дэд толгой
        sub = rows[1]
        # Гараар индекс — файлын бүтэц тогтмол
        # №, nominated, surname, given, register, age, gender, position, sector, profession,
        # years, last_award, last_date, org, fwd_date, letter, received, award_date, resolution
        for row in rows[2:]:
            cells = row + [""] * 20
            if not any(cells[1:5]):
                continue
            year = 2020
            fwd_date = parse_date(cells[14]) or parse_date(cells[15])
            letter = clean(cells[15]) or None
            if letter and "/" in letter:
                letter = re.sub(r"\s+", "", letter.strip(" /"))
            entry = {
                "year": year,
                "nominated_award": clean(cells[1]) or None,
                "surname": clean(cells[2]) or None,
                "given_name": clean(cells[3]) or None,
                "register_no": clean(cells[4]).upper().replace(" ", "") or None,
                "age": to_int(cells[5]),
                "gender": clean(cells[6]) or None,
                "position": clean(cells[7]) or None,
                "work_sector": clean(cells[8]) or None,
                "profession": clean(cells[9]) or None,
                "years_in_country": to_int(cells[10]),
                "last_award": clean(cells[11]) or None,
                "last_award_date": parse_date(cells[12]),
                "supporting_org": clean(cells[13]) or None,
                "forwarded_on": fwd_date,
                "forwarded_letter_no": letter if letter and re.search(r"\d", letter) else parse_letter_no(str(cells[15])),
                "received_award": clean(cells[16]) or None,
                "award_date": parse_date(cells[17]),
                "resolution_number": clean(cells[18]) or None,
                "source": path.name,
            }
            out.append(entry)
    return out


def docx_tables(path: Path) -> list[list[list[str]]]:
    ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
    with zipfile.ZipFile(path) as zf:
        xml = zf.read("word/document.xml")
    # ElementTree
    import xml.etree.ElementTree as ET

    root = ET.fromstring(xml)
    tables = []
    for tbl in root.findall(".//w:tbl", ns):
        table = []
        for tr in tbl.findall("w:tr", ns):
            cells = []
            for tc in tr.findall("w:tc", ns):
                texts = [t.text or "" for t in tc.findall(".//w:t", ns)]
                cells.append(clean("".join(texts)))
            if any(cells):
                table.append(cells)
        if table:
            tables.append(table)
    return tables


def from_word(path: Path) -> list[dict]:
    out: list[dict] = []
    year = 2022
    for table in docx_tables(path):
        hi = None
        for i, row in enumerate(table[:8]):
            joined = " ".join(row).lower()
            if "овог" in joined and ("нэр" in joined or "регистр" in joined):
                hi = i
                break
        if hi is None:
            # Зарим хүснэгт гарчиггүй — эхний мөр өгөгдөл
            sample = " ".join(table[0]).lower() if table else ""
            if re.search(r"\d", sample) and len(table[0]) >= 5:
                hi = -1
            else:
                continue
        body = table[hi + 1 :] if hi >= 0 else table
        for row in body:
            cells = row + [""] * 12
            # Д/д | Овог нэр | Регистр | Албан тушаал | Жил | Шагнал | ... зарлиг
            full_name = cells[1]
            surname, given = split_mongolian_full_name(full_name)
            nominated = clean(cells[5]).strip('“”"') or None
            fwd_date, fwd_no, fwd_note = parse_forward_blob(cells[6] if len(cells) > 6 else "")
            pre_note = clean(cells[7]) if len(cells) > 7 else ""
            dec_date, dec_no, dec_note = parse_decree_blob(cells[8] if len(cells) > 8 else "")
            entry = {
                "year": year,
                "nominated_award": nominated,
                "surname": surname,
                "given_name": given,
                "register_no": clean(cells[2]).upper().replace(" ", "") or None,
                "position": clean(cells[3]) or None,
                "years_in_country": to_int(cells[4]),
                "supporting_org": "МУЕТГ" if pre_note or fwd_note else None,
                "forwarded_on": fwd_date,
                "forwarded_letter_no": fwd_no,
                "received_award": nominated if (dec_date or dec_no) else None,
                "award_date": dec_date,
                "resolution_number": dec_no,
                "presidential_letter": pre_note or None,
                "notes": " | ".join(p for p in [fwd_note, pre_note, dec_note] if p) or None,
                "source": path.name,
            }
            out.append(entry)
    return out


def sector_lines(path: Path) -> list[str]:
    if not path.exists():
        return []
    with zipfile.ZipFile(path) as zf:
        xml = zf.read("word/document.xml").decode("utf-8", errors="ignore")
    text = re.sub(r"</w:p>", "\n", xml)
    text = re.sub(r"<[^>]+>", "", text)
    return [clean(ln) for ln in text.splitlines() if clean(ln)]


def main() -> None:
    bucket: dict[str, dict] = {}
    stats: dict[str, int] = {}

    for path, year in STANDARD_FILES:
        rows = from_standard(path, year)
        stats[path.name] = len(rows)
        for row in rows:
            upsert(bucket, row)

    study_rows = from_study(STUDY_FILE)
    stats[STUDY_FILE.name] = len(study_rows)
    for row in study_rows:
        upsert(bucket, row)

    header_rows = from_header_template(HEADER_FILE)
    stats[HEADER_FILE.name] = len(header_rows)
    for row in header_rows:
        upsert(bucket, row)

    if WORD_FILE.exists():
        word_rows = from_word(WORD_FILE)
        stats[WORD_FILE.name] = len(word_rows)
        for row in word_rows:
            upsert(bucket, row)

    entries = sorted(
        bucket.values(),
        key=lambda e: (
            int(e.get("year") or 0),
            e.get("surname") or "",
            e.get("given_name") or "",
        ),
    )

    # Зөвхөн өргөн мэдүүлсэн / авсан шагналын нэрийг төрөл болгоно.
    award_types = sorted(
        {
            clean(v)
            for e in entries
            for v in (e.get("nominated_award"), e.get("received_award"))
            if clean(v) and len(clean(v)) <= 80
        }
    )

    payload = {
        "category": "state_high",
        "stats": stats,
        "total": len(entries),
        "award_types": award_types,
        "sectors": sector_lines(SECTOR_FILE),
        "entries": entries,
    }

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"out": str(OUT), "total": len(entries), "stats": stats}, ensure_ascii=False, indent=2))


if __name__ == "__main__":
    main()
