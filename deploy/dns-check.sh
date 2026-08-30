#!/usr/bin/env bash
# DNS бүсийн эрүүл мэнд — сервер эсвэл админ компьютерээс ажиллуулна.
# 8.8.8.8 болон 1.1.1.1 ХОЁУЛАА зөв A өгөх ёстой.
# Дотоодын ISP resolver-ууд (Univision / Skytel) ч зөв өгөх ёстой — эдгээр
# NXDOMAIN кэшэлбэл Монголын хэрэглэгчид ороход DNS_PROBE_FINISHED_NXDOMAIN.
# Гарах код: 0 = бүрэн OK, 1 = apex алдаа, 2 = apex OK гэхдээ www NXDOMAIN,
#            3 = олон улсын resolver OK гэхдээ дотоодын ISP NXDOMAIN
set -euo pipefail

DOMAIN="${1:-manage.dornogovi.gov.mn}"
EXPECT_IP="${2:-202.37.109.67}"
WWW_DOMAIN="www.${DOMAIN}"
NS_LIST=(ns.gov.mn ns1.gov.mn ns3.gov.mn ns4.gov.mn)
PUB_LIST=(8.8.8.8 1.1.1.1)
# Дотоодын ISP resolver — хэрэглэгчид ЭНДЭЭС шийддэг тул заавал шалгана
ISP_LIST=(59.153.112.2 103.206.153.188 202.179.0.10)
ISP_NAME=("Univision" "Skytel" "MobiCom")

echo "== DNS check: ${DOMAIN} → ${EXPECT_IP} =="
fail=0
www_warn=0
isp_fail=0
serial_stale=0

query_a() {
  local name="$1"
  local at="$2"
  dig +time=3 +tries=2 +short A "${name}" "@${at}" 2>/dev/null | awk 'NF && $1 !~ /[Tt]imeout/ {print; exit}'
}

# dig-ийн толгой мөр: «;; ->>HEADER<<- opcode: QUERY, status: NXDOMAIN, id: 1»
# — «status:»-ийн дараах үгийг таслал хүртэл нь салгаж авна.
query_status() {
  local name="$1"
  local at="$2"
  dig +time=3 +tries=2 "${name}" "@${at}" 2>/dev/null \
    | awk '/status: /{sub(/.*status: /,""); sub(/,.*/,""); print; exit}'
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
[[ -n "${soa}" ]] || soa="$(dig +time=3 +tries=2 +short SOA "${DOMAIN}" @8.8.8.8 2>/dev/null | head -n1 || true)"
echo "SOA  ${soa:-TIMEOUT/EMPTY}"
if echo "${soa}" | grep -qi 'misconfigured'; then
  echo "FAIL SOA primary буруу (a.misconfigured.dns.server.invalid)"
  echo "     → ${DOMAIN} нь тусдаа delegated бүс болсон бөгөөд primary нь"
  echo "       байхгүй нэр рүү заасан тул NOTIFY/AXFR ажиллахгүй."
  echo "       Зарим NS бүсийг аваагүй → эцэг бүсээс NXDOMAIN хариулна."
  fail=1
fi

# Serial хуучирсан эсэх — secondary (ns3/ns4) зөвхөн serial өссөн үед л
# зоныг дахин татдаг. Serial зогссон бол шинэ бичлэг тэдэн дээр хэзээ ч очихгүй.
serial="$(echo "${soa}" | awk '{print $3}')"
neg_ttl="$(echo "${soa}" | awk '{print $NF}')"
if [[ -n "${serial}" ]]; then
  echo "     serial=${serial}  negative-TTL=${neg_ttl:-?} сек"
  if [[ "${serial}" =~ ^20[0-9]{6} ]]; then
    year="${serial:0:4}"
    now_year="$(date +%Y)"
    if (( now_year - year >= 1 )); then
      serial_stale=1
      echo "FAIL SOA serial ${serial} — ${year} оноос хойш өсөөгүй."
      echo "     → ns3/ns4 зон дамжуулалт (AXFR) хийхгүй тул шинэ A бичлэгийг мэдэхгүй."
      fail=1
    fi
  fi
  if [[ "${neg_ttl}" =~ ^[0-9]+$ ]] && (( neg_ttl > 3600 )); then
    echo "WARN negative-TTL ${neg_ttl} сек — нэг удаа NXDOMAIN кэшлэгдвэл"
    echo "     $(( neg_ttl / 3600 )) цаг хүртэл resolver дээр «байхгүй» гэж үлдэнэ."
  fi
fi

for pub in "${PUB_LIST[@]}"; do
  out="$(query_a "${DOMAIN}" "${pub}" || true)"
  echo "PUB  @${pub} A=${out:-TIMEOUT/EMPTY}"
  if [[ "${out}" != "${EXPECT_IP}" ]]; then
    fail=1
  fi
done

# Дотоодын ISP — хэрэглэгчийн компьютер/утас ЭНДЭЭС нэр шийддэг.
# Олон улсын resolver зөв атал энд NXDOMAIN бол сөрөг кэш хордсон гэсэн үг.
echo
echo "== Дотоодын ISP resolver (хэрэглэгчид эндээс шийддэг) =="
for i in "${!ISP_LIST[@]}"; do
  isp="${ISP_LIST[$i]}"
  name="${ISP_NAME[$i]:-ISP}"
  status="$(query_status "${DOMAIN}" "${isp}" || true)"
  out="$(query_a "${DOMAIN}" "${isp}" || true)"
  if [[ "${out}" == "${EXPECT_IP}" ]]; then
    echo "OK   @${isp} (${name}) A=${out}"
  elif [[ "${status}" == "NXDOMAIN" ]]; then
    echo "FAIL @${isp} (${name}) NXDOMAIN — Chrome «DNS_PROBE_FINISHED_NXDOMAIN»"
    isp_fail=1
  elif [[ -z "${out}" ]]; then
    echo "WARN @${isp} (${name}) TIMEOUT/EMPTY (энэ сүлжээнээс хаалттай байж болно)"
  else
    echo "FAIL @${isp} (${name}) A=${out} (хүлээгдэж буй ${EXPECT_IP} биш)"
    isp_fail=1
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
  # ISP-ийн унал илүү ноцтой тул www-г зөвхөн ISP зөв үед дүгнэнэ (доор exit 3)
  if [[ "${www_warn}" -eq 1 && "${isp_fail}" -eq 0 ]]; then
    echo
    echo "ҮР ДҮН: apex DNS OK — www бичлэг БАЙХГҮЙ (NXDOMAIN)"
    echo "  → nginx redirect www→apex ажиллахгүй (DNS-ээс өмнө хүрэхгүй)"
    echo "  → НДТ: www A 202.37.109.67 эсвэл CNAME manage.dornogovi.gov.mn"
    echo "  → deploy/dns-request-email.md-ийг mcloud.gov.mn руу илгээнэ үү"
    exit 2
  fi
  if [[ "${isp_fail}" -eq 1 ]]; then
    echo
    echo "ҮР ДҮН: 8.8.8.8/1.1.1.1 зөв — ДОТООДЫН ISP NXDOMAIN кэшэлсэн"
    echo "  → Сервер, nginx, сертификат зөв. Асуудал зөвхөн нэр шийдэлт."
    echo "  → Түр арга (хэрэглэгч тал): DNS-ээ 8.8.8.8 / 1.1.1.1 болгоно"
    echo "     powershell -ExecutionPolicy Bypass -File deploy/set-dns-client.ps1"
    echo "  → Бүрэн шийдэл: НДТ 4 NS дээр A бичлэгийг ижил болгож, SOA serial-ыг өсгөнө"
    if [[ "${www_warn}" -eq 1 ]]; then
      echo "  → www бичлэг ч бас байхгүй — цуг заслаа хүснэ"
    fi
    echo "     deploy/dns-request-email.md-ийг mcloud.gov.mn руу илгээнэ үү"
    exit 3
  fi
  echo "ҮР ДҮН: DNS OK — apex болон www хоёулаа зөв"
  exit 0
fi

echo
echo "ҮР ДҮН: DNS ДОГОЛДОЛ — илэрсэн шалтгаанууд:"
if [[ "${serial_stale}" -eq 1 ]]; then
  echo "  • SOA serial хөдөлгөөнгүй → ns3/ns4 зоныг дахин татахгүй (гол шалтгаан)"
fi
if [[ "${isp_fail}" -eq 1 ]]; then
  echo "  • Дотоодын ISP resolver NXDOMAIN → хэрэглэгчид сайтад орж чадахгүй"
fi
if [[ "${www_warn}" -eq 1 ]]; then
  echo "  • www бичлэг байхгүй → утасны Chrome www руу оролдоод унана"
fi
echo "  → Сервер/nginx/сертификат биш. НДТ засна: deploy/dns-request-email.md"
if [[ "${isp_fail}" -eq 1 ]]; then
  echo "  → Түр арга: powershell -File deploy/set-dns-client.ps1 (DNS-ийг 8.8.8.8 болгоно)"
fi
exit 1
