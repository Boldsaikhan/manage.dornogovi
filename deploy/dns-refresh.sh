#!/usr/bin/env bash
# Хуучин DNS кэшийг цэвэрлээд зөв A бичлэгээр дахин асууна.
# auto-update.sh (2 мин тутам) дуудна. ns4 бүсийг НДТ л шинэчилнэ —
# энд зөвхөн энэ сервер болон нийтийн resolver-ийн кэшийг шинэчилнэ.
set -uo pipefail

DOMAIN="${1:-manage.dornogovi.gov.mn}"
EXPECT_IP="${2:-202.37.109.67}"
WWW_DOMAIN="www.${DOMAIN}"
PARENT="dornogovi.gov.mn"

flush_local_cache() {
    if command -v resolvectl >/dev/null 2>&1; then
        resolvectl flush-caches >/dev/null 2>&1 || true
    fi
    if command -v systemd-resolve >/dev/null 2>&1; then
        systemd-resolve --flush-caches >/dev/null 2>&1 || true
    fi
    nscd -i hosts >/dev/null 2>&1 || true
    if [ -f /etc/init.d/nscd ]; then
        /etc/init.d/nscd reload >/dev/null 2>&1 || true
    fi
}

query_a() {
    local name="$1"
    local at="$2"
    dig +time=2 +tries=1 +short A "${name}" "@${at}" 2>/dev/null \
        | awk 'NF && $1 !~ /[Tt]imeout/ && $1 !~ /\.$/ {print; exit}'
}

query_serial() {
    local at="$1"
    dig +time=2 +tries=1 +short SOA "${PARENT}" "@${at}" 2>/dev/null \
        | awk '{print $3; exit}'
}

warm() {
    local at="$1"
    dig +time=2 +tries=1 A "${DOMAIN}" "@${at}" >/dev/null 2>&1 || true
    dig +time=2 +tries=1 A "${WWW_DOMAIN}" "@${at}" >/dev/null 2>&1 || true
}

flush_local_cache

# Нэрийн сервер + нийтийн resolver — хуучин NXDOMAIN-ыг шинэ A-гаар солих оролдлого
for ns in ns.gov.mn ns1.gov.mn ns3.gov.mn ns4.gov.mn 8.8.8.8 1.1.1.1; do
    warm "${ns}"
done

serial_primary="$(query_serial ns.gov.mn || true)"
serial_ns4="$(query_serial ns4.gov.mn || true)"
a_ns="$(query_a "${DOMAIN}" ns.gov.mn || true)"
a_ns4="$(query_a "${DOMAIN}" ns4.gov.mn || true)"
a_google="$(query_a "${DOMAIN}" 8.8.8.8 || true)"
a_cf="$(query_a "${DOMAIN}" 1.1.1.1 || true)"

status="OK"
if [ "${a_ns4}" != "${EXPECT_IP}" ]; then
    status="NS4_STALE"
fi
if [ -n "${serial_primary}" ] && [ -n "${serial_ns4}" ] && [ "${serial_primary}" != "${serial_ns4}" ]; then
    status="NS4_STALE"
fi

echo "$(date '+%F %T') ${status} serial_ns=${serial_primary:-?} serial_ns4=${serial_ns4:-?} a_ns=${a_ns:-?} a_ns4=${a_ns4:-MISSING} google=${a_google:-?} cf=${a_cf:-?}"

if [ "${status}" = "OK" ]; then
    exit 0
fi
exit 3
