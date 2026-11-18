"""
Нэг оны ОДОН-БҮРТГЭЛ Excel → JSON (awards:import-state-high-д зориулсан).

  python scripts/build_odon_year_import.py ^
    --file "C:\\Users\\IT-PC\\Downloads\\ОДОН-БҮРТГЭЛ-2024_1.xlsx" ^
    --year 2024
"""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

# Reuse parsers from the full builder.
sys.path.insert(0, str(Path(__file__).resolve().parent))
from build_state_high_awards_import import from_standard, upsert  # noqa: E402

ROOT = Path(__file__).resolve().parents[1]
DEFAULT_OUT = ROOT / "database" / "data" / "state_high_awards_year.json"


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--file", required=True, type=Path)
    parser.add_argument("--year", required=True, type=int)
    parser.add_argument("--out", type=Path, default=DEFAULT_OUT)
    args = parser.parse_args()

    if not args.file.is_file():
        raise SystemExit(f"Файл олдсонгүй: {args.file}")

    rows = from_standard(args.file, args.year)
    # Зөвхөн тухайн он + давхардлыг баялаг мөрөөр нэгтгэнэ.
    bucket: dict[str, dict] = {}
    for row in rows:
        if int(row.get("year") or 0) != args.year:
            continue
        upsert(bucket, row)
    entries = list(bucket.values())

    # supporting_org — системийн 2 утга руу хөрвүүлнэ.
    for entry in entries:
        blob = " ".join(
            str(entry.get(k) or "")
            for k in ("presidential_letter", "notes", "supporting_org")
        ).lower()
        if "ерөнхийлөгч" in blob or "муетг" in blob or "му-ын ерөнхийлөгч" in blob:
            entry["supporting_org"] = "Ерөнхийлөгчийн Тамгын газар"
        elif "итх" in blob or "төлөөлөгчдийн хурал" in blob:
            entry["supporting_org"] = "Аймгийн Иргэдийн Төлөөлөгчдийн Хурал"
        elif entry.get("supporting_org") in (None, "", "МУЕТГ"):
            entry["supporting_org"] = (
                "Ерөнхийлөгчийн Тамгын газар" if entry.get("forwarded_on") or entry.get("presidential_letter") else None
            )

    award_types = sorted(
        {
            (v or "").strip()
            for e in entries
            for v in (e.get("nominated_award"), e.get("received_award"))
            if (v or "").strip() and len((v or "").strip()) <= 80
        }
    )

    payload = {
        "category": "state_high",
        "year": args.year,
        "source": args.file.name,
        "total": len(entries),
        "award_types": award_types,
        "entries": entries,
    }

    args.out.parent.mkdir(parents=True, exist_ok=True)
    args.out.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(json.dumps({"out": str(args.out), "total": len(entries), "source": args.file.name}, ensure_ascii=False))


if __name__ == "__main__":
    main()
