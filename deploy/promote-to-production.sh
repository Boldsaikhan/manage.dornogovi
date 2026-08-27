#!/usr/bin/env bash
#
# demo салбарыг production (main) руу нэгтгэж илгээнэ.
# Зөвхөн хэрэглэгч «production руу гарга» гэж хэлэхэд ажиллуулна.
#
#   git fetch origin && bash deploy/promote-to-production.sh
#
set -euo pipefail

cd "$(git rev-parse --show-toplevel)"

git fetch origin

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Хадгалаагүй өөрчлөлт байна. Эхлээд commit хийнэ үү."
    exit 1
fi

echo "==> demo-г origin руу илгээж байна"
git checkout demo
git pull --ff-only origin demo
git push origin demo

echo "==> main (production) руу нэгтгэж байна"
git checkout main
git pull --ff-only origin main
git merge --no-ff demo -m "Production: demo салбарыг нэгтгэв"

git push origin main

git checkout demo
echo "==> Дууслаа. Production ~2 минутын дараа шинэчлэгдэнэ."
echo "    Буцаад demo салбар дээр ажиллаж байна."
