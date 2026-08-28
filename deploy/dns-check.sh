#!/usr/bin/env bash
# DNS бүсийн эрүүл мэнд — сервер эсвэл админ компьютерээс ажиллуулна.
# 8.8.8.8 болон 1.1.1.1 ХОЁУЛАА зөв A өгөх ёстой.
# Гарах код: 0 = OK, 1 = алдаа
set -euo pipefail

DOMAIN="${1:-manage.dornogovi.gov.mn}"
EXPECT_IP="${2:-202.37.109.67}"
NS_LIST=(ns.gov.mn ns1.gov.mn ns3.gov.mn ns4.gov.mn)
PUB_LIST=(8.8.8.8 1.1.1.1)

echo "== DNS check: ${DOMAIN} → ${EXPECT_IP} =="
fail=0

query_a() {
  local at="$1"
  dig +time=3 +tries=2 +short A "${DOMAIN}" "@${at}" 2>/dev/null | awk 'NF && $1 !~ /[Tt]imeout/ {print; exit}'
}

for ns in "${NS_LIST[@]}"; do
  out="$(query_a "${ns}" || true)"
  if [[ "${out}" == "${EXPECT_IP}" ]]; then
    echo "OK   @${ns}  A=${out}"
  elif [[ -z "${out}" ]]; then
    echo "FAIL @${ns}  TIMEOUT/EMPTY"
    fail=1
  else
    echo "FAIL @${ns}  A=${out} (NXDOMAIN эсвэл буруу)"
    fail=1
  fi
done

soa="$(dig +time=3 +tries=2 +short SOA "${DOMAIN}" @ns.gov.mn 2>/dev/null | head -n1 || true)"
echo "SOA  ${soa:-TIMEOUT/EMPTY}"
if echo "${soa}" | grep -qi 'misconfigured'; then
  echo "FAIL SOA primary буруу (a.misconfigured.dns.server.invalid)"
  fail=1
fi

for pub in "${PUB_LIST[@]}"; do
  out="$(query_a "${pub}" || true)"
  echo "PUB  @${pub} A=${out:-TIMEOUT/EMPTY}"
  if [[ "${out}" != "${EXPECT_IP}" ]]; then
    fail=1
  fi
done

code="$(curl -ksI --max-time 10 "https://${DOMAIN}/" | head -n1 || true)"
echo "HTTP ${code:-NO_RESPONSE}"

if [[ "${fail}" -eq 0 ]]; then
  echo "ҮР ДҮН: DNS OK — 8.8.8.8 болон 1.1.1.1 хоёулаа зөв"
  exit 0
fi

echo "ҮР ДҮН: DNS ДОГОЛДОЛ — ns4 NXDOMAIN бол 8.8.8.8/1.1.1.1 кэш хордож утас DNS_PROBE_POSSIBLE авна"
echo "         deploy/dns-request-email.md-ийг НДТ руу илгээнэ үү"
exit 1
