#!/usr/bin/env bash
# DNS бүсийн эрүүл мэнд — сервер эсвэл админ компьютерээс ажиллуулна.
# Гарах код: 0 = OK, 1 = алдаа
set -euo pipefail

DOMAIN="${1:-manage.dornogovi.gov.mn}"
EXPECT_IP="${2:-202.37.109.67}"
NS_LIST=(ns.gov.mn ns1.gov.mn ns3.gov.mn ns4.gov.mn)

echo "== DNS check: ${DOMAIN} → ${EXPECT_IP} =="
fail=0

for ns in "${NS_LIST[@]}"; do
  out="$(dig +time=3 +tries=2 +short A "${DOMAIN}" "@${ns}" 2>/dev/null | head -n1 || true)"
  if [[ "${out}" == "${EXPECT_IP}" ]]; then
    echo "OK   @${ns}  A=${out}"
  else
    echo "FAIL @${ns}  A=${out:-TIMEOUT/EMPTY}"
    fail=1
  fi
done

soa="$(dig +time=3 +tries=2 +short SOA "${DOMAIN}" @ns.gov.mn 2>/dev/null | head -n1 || true)"
echo "SOA  ${soa:-TIMEOUT/EMPTY}"
if echo "${soa}" | grep -qi 'misconfigured'; then
  echo "FAIL SOA primary буруу (a.misconfigured.dns.server.invalid)"
  fail=1
fi

pub="$(dig +time=3 +tries=2 +short A "${DOMAIN}" @8.8.8.8 2>/dev/null | head -n1 || true)"
echo "PUB  @8.8.8.8 A=${pub:-TIMEOUT/EMPTY}"
if [[ "${pub}" != "${EXPECT_IP}" ]]; then
  fail=1
fi

code="$(curl -ksI --max-time 10 "https://${DOMAIN}/" | head -n1 || true)"
echo "HTTP ${code:-NO_RESPONSE}"

if [[ "${fail}" -eq 0 ]]; then
  echo "ҮР ДҮН: DNS OK"
  exit 0
fi

echo "ҮР ДҮН: DNS ДОГОЛДОЛ — deploy/dns-request-email.md-ийг НДТ руу илгээнэ үү"
exit 1
