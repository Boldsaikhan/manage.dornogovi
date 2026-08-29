#!/usr/bin/env bash
# DNS бүсийн эрүүл мэнд — сервер эсвэл админ компьютерээс ажиллуулна.
# 8.8.8.8 болон 1.1.1.1 ХОЁУЛАА зөв A өгөх ёстой.
# Гарах код: 0 = бүрэн OK, 1 = apex алдаа, 2 = apex OK гэхдээ www NXDOMAIN
set -euo pipefail

DOMAIN="${1:-manage.dornogovi.gov.mn}"
EXPECT_IP="${2:-202.37.109.67}"
WWW_DOMAIN="www.${DOMAIN}"
NS_LIST=(ns.gov.mn ns1.gov.mn ns3.gov.mn ns4.gov.mn)
PUB_LIST=(8.8.8.8 1.1.1.1)

echo "== DNS check: ${DOMAIN} → ${EXPECT_IP} =="
fail=0
www_warn=0

query_a() {
  local name="$1"
  local at="$2"
  dig +time=3 +tries=2 +short A "${name}" "@${at}" 2>/dev/null | awk 'NF && $1 !~ /[Tt]imeout/ {print; exit}'
}

query_status() {
  local name="$1"
  local at="$2"
  dig +time=3 +tries=2 "${name}" "@${at}" 2>/dev/null \
    | awk '/^status:/{print $2; exit}'
}

check_www_at() {
  local at="$1"
  local label="$2"
  local status a cname

  status="$(query_status "${WWW_DOMAIN}" "${at}" || true)"
  a="$(query_a "${WWW_DOMAIN}" "${at}" || true)"
  cname="$(dig +time=3 +tries=2 +short CNAME "${WWW_DOMAIN}" "@${at}" 2>/dev/null | head -n1 || true)"

  case "${status}" in
    NXDOMAIN)
      echo "FAIL ${label}  www NXDOMAIN — утас Chrome «DNS_PROBE_FINISHED_NXDOMAIN»"
      www_warn=1
      ;;
    NOERROR)
      if [[ "${a}" == "${EXPECT_IP}" ]]; then
        echo "OK   ${label}  www A=${a}"
      elif [[ -n "${cname}" ]]; then
        echo "OK   ${label}  www CNAME=${cname}"
      elif [[ -z "${a}" ]]; then
        echo "WARN ${label}  www NOERROR гэхдээ A/CNAME хоосон"
        www_warn=1
      else
        echo "WARN ${label}  www A=${a} (apex ${EXPECT_IP}-тай таарахгүй)"
        www_warn=1
      fi
      ;;
    "")
      if [[ -z "${a}" ]]; then
        echo "WARN ${label}  www TIMEOUT/EMPTY"
        www_warn=1
      elif [[ "${a}" == "${EXPECT_IP}" ]]; then
        echo "OK   ${label}  www A=${a}"
      else
        echo "WARN ${label}  www A=${a}"
        www_warn=1
      fi
      ;;
    *)
      if [[ "${a}" == "${EXPECT_IP}" ]]; then
        echo "OK   ${label}  www A=${a} (status=${status})"
      elif [[ -z "${a}" ]]; then
        echo "WARN ${label}  www status=${status}, A хоосон"
        www_warn=1
      else
        echo "WARN ${label}  www A=${a} (status=${status})"
        www_warn=1
      fi
      ;;
  esac
}

for ns in "${NS_LIST[@]}"; do
  out="$(query_a "${DOMAIN}" "${ns}" || true)"
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
  out="$(query_a "${DOMAIN}" "${pub}" || true)"
  echo "PUB  @${pub} A=${out:-TIMEOUT/EMPTY}"
  if [[ "${out}" != "${EXPECT_IP}" ]]; then
    fail=1
  fi
done

echo
echo "== www: ${WWW_DOMAIN} (утасны Chrome ихэнхдээ www оролддог) =="
for ns in "${NS_LIST[@]}"; do
  check_www_at "${ns}" "@${ns}"
done
echo "--- public resolvers ---"
for pub in "${PUB_LIST[@]}"; do
  check_www_at "${pub}" "@${pub}"
done

code="$(curl -ksI --max-time 10 "https://${DOMAIN}/" | head -n1 || true)"
echo
echo "HTTP ${code:-NO_RESPONSE}"

if [[ "${fail}" -eq 0 ]]; then
  if [[ "${www_warn}" -eq 1 ]]; then
    echo
    echo "ҮР ДҮН: apex DNS OK — www бичлэг БАЙХГҮЙ (NXDOMAIN)"
    echo "  → nginx redirect www→apex ажиллахгүй (DNS-ээс өмнө хүрэхгүй)"
    echo "  → НДТ: www A 202.37.109.67 эсвэл CNAME manage.dornogovi.gov.mn"
    echo "  → deploy/dns-request-email.md-ийг mcloud.gov.mn руу илгээнэ үү"
    exit 2
  fi
  echo "ҮР ДҮН: DNS OK — apex болон www хоёулаа зөв"
  exit 0
fi

echo
echo "ҮР ДҮН: apex DNS ДОГОЛДОЛ — ns4 NXDOMAIN бол 8.8.8.8/1.1.1.1 кэш хордож утас DNS_PROBE_POSSIBLE авна"
echo "         deploy/dns-request-email.md-ийг НДТ руу илгээнэ үү"
exit 1
