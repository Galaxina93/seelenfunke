#!/usr/bin/env bash
# ==============================================================================
# Mein-Seelenfunke | Autarker 24/7 Synology-NAS Watchtower (Zero-Dependency)
# Prüft alle 2 Minuten Shop/SSL, Cronjobs, Queue-Worker und Backups von außen.
# ==============================================================================
set -u

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/watchtower.env"
STATE_DIR="${SCRIPT_DIR}/.state"
mkdir -p "${STATE_DIR}"
chmod 700 "${STATE_DIR}" 2>/dev/null || true

if [[ -f "${ENV_FILE}" ]]; then
    # shellcheck disable=SC1090
    source "${ENV_FILE}"
fi

SHOP_PULSE_URL="${SHOP_PULSE_URL:-https://mein-seelenfunke.de/api/system/health-pulse?source=synology_nas}"
WATCHTOWER_BEARER_TOKEN="${WATCHTOWER_BEARER_TOKEN:-}"
TELEGRAM_BOT_TOKEN="${TELEGRAM_BOT_TOKEN:-}"
TELEGRAM_CHAT_ID="${TELEGRAM_CHAT_ID:-}"
ALERT_WEBHOOK_URL="${ALERT_WEBHOOK_URL:-}"
COOLDOWN_SECONDS="${COOLDOWN_SECONDS:-3600}"

STRIKES_FILE="${STATE_DIR}/strikes.count"
OPEN_FILE="${STATE_DIR}/alert.open"
LAST_ALERT_FILE="${STATE_DIR}/last_alert.ts"
LOG_FILE="${STATE_DIR}/watchtower.log"

log_line() {
    local ts
    ts="$(date '+%Y-%m-%d %H:%M:%S')"
    printf '[%s] %s\n' "${ts}" "$1" >> "${LOG_FILE}"
    # Halte Log-Datei schlank (max. 500 Zeilen)
    if [[ -f "${LOG_FILE}" ]] && [[ "$(wc -l < "${LOG_FILE}")" -gt 600 ]]; then
        tail -n 400 "${LOG_FILE}" > "${LOG_FILE}.tmp" && mv "${LOG_FILE}.tmp" "${LOG_FILE}"
    fi
}

check_home_internet() {
    if curl -fsSI --max-time 4 "https://connectivitycheck.gstatic.com/generate_204" >/dev/null 2>&1; then
        return 0
    fi
    if curl -fsSI --max-time 4 "https://1.1.1.1" >/dev/null 2>&1; then
        return 0
    fi
    return 1
}

send_out_of_band_alert() {
    local title="$1"
    local body="$2"
    local full_msg="${title}: ${body}"

    if [[ -n "${TELEGRAM_BOT_TOKEN}" ]] && [[ -n "${TELEGRAM_CHAT_ID}" ]]; then
        curl -fsS --max-time 8 -X POST \
            "https://api.telegram.org/bot${TELEGRAM_BOT_TOKEN}/sendMessage" \
            --data-urlencode "chat_id=${TELEGRAM_CHAT_ID}" \
            --data-urlencode "text=${full_msg}" >/dev/null 2>&1 || true
    fi

    if [[ -n "${ALERT_WEBHOOK_URL}" ]]; then
        local safe_payload
        safe_payload="$(printf '{"title":"%s","message":"%s","source":"synology_nas"}' \
            "${title//\"/\'}" "${body//\"/\'}")"
        curl -fsS --max-time 8 -X POST "${ALERT_WEBHOOK_URL}" \
            -H "Content-Type: application/json" \
            -d "${safe_payload}" >/dev/null 2>&1 || true
    fi
}

if [[ "${1:-}" == "--self-test" ]]; then
    echo "WATCHTOWER_SCRIPT_SYNTAX_OK"
    exit 0
fi

if [[ -z "${WATCHTOWER_BEARER_TOKEN}" ]]; then
    log_line "HINWEIS: WATCHTOWER_BEARER_TOKEN in watchtower.env noch leer."
    exit 0
fi

NOW_TS="$(date +%s)"
PREV_STRIKES=0
[[ -f "${STRIKES_FILE}" ]] && PREV_STRIKES="$(cat "${STRIKES_FILE}" 2>/dev/null || echo 0)"
WAS_OPEN=0
[[ -f "${OPEN_FILE}" ]] && WAS_OPEN="$(cat "${OPEN_FILE}" 2>/dev/null || echo 0)"
LAST_ALERT_TS=0
[[ -f "${LAST_ALERT_FILE}" ]] && LAST_ALERT_TS="$(cat "${LAST_ALERT_FILE}" 2>/dev/null || echo 0)"

TMP_BODY="${STATE_DIR}/last_response.json"
HTTP_CODE="$(curl -sS --max-time 10 -o "${TMP_BODY}" -w '%{http_code}' \
    -H "Authorization: Bearer ${WATCHTOWER_BEARER_TOKEN}" \
    -H "Accept: application/json" \
    "${SHOP_PULSE_URL}" 2>/dev/null || echo "000")"
chmod 600 "${TMP_BODY}" 2>/dev/null || true

if [[ "${HTTP_CODE}" == "000" ]] || [[ "${HTTP_CODE}" -ge 500 ]]; then
    if ! check_home_internet; then
        log_line "Heimnetz-Reconnect erkannt (Gegenprüfung offline). Kein Fehlalarm."
        exit 0
    fi

    NEW_STRIKES=$((PREV_STRIKES + 1))
    echo "${NEW_STRIKES}" > "${STRIKES_FILE}"

    ELAPSED=$((NOW_TS - LAST_ALERT_TS))
    if [[ "${NEW_STRIKES}" -ge 2 ]] && { [[ "${WAS_OPEN}" -eq 0 ]] || [[ "${ELAPSED}" -ge "${COOLDOWN_SECONDS}" ]]; }; then
        echo "1" > "${OPEN_FILE}"
        echo "${NOW_TS}" > "${LAST_ALERT_FILE}"
        DETAIL="Shop antwortet mit HTTP ${HTTP_CODE} trotz aktiver Internetleitung."
        log_line "ALARM: ${DETAIL}"
        send_out_of_band_alert "Seelenfunke Live-Shop Alarm" "${DETAIL}"
    else
        log_line "Verbindungsfehler #${NEW_STRIKES} (HTTP ${HTTP_CODE}), warte auf Gegenprüfung."
    fi
    exit 0
fi

echo "0" > "${STRIKES_FILE}"

if [[ "${HTTP_CODE}" == "401" ]] || [[ "${HTTP_CODE}" == "403" ]]; then
    log_line "AUTH-HINWEIS: Watchtower-Token meldet HTTP ${HTTP_CODE}."
    exit 0
fi

PULSE_STATUS="$(grep -o '"status"[[:space:]]*:[[:space:]]*"[^"]*"' "${TMP_BODY}" | head -n 1 | sed 's/.*"status"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')"
PULSE_HEADLINE="$(grep -o '"headline"[[:space:]]*:[[:space:]]*"[^"]*"' "${TMP_BODY}" | head -n 1 | sed 's/.*"headline"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/')"

if [[ "${PULSE_STATUS}" == "critical" ]]; then
    ELAPSED=$((NOW_TS - LAST_ALERT_TS))
    if [[ "${WAS_OPEN}" -eq 0 ]] || [[ "${ELAPSED}" -ge "${COOLDOWN_SECONDS}" ]]; then
        echo "1" > "${OPEN_FILE}"
        echo "${NOW_TS}" > "${LAST_ALERT_FILE}"
        MSG="${PULSE_HEADLINE:-Kritische Abweichung in Hintergrunddiensten}"
        log_line "KRITISCH: ${MSG}"
        send_out_of_band_alert "Seelenfunke System-Wächter" "${MSG}"
    fi
    exit 0
fi

if [[ "${WAS_OPEN}" -eq 1 ]]; then
    echo "0" > "${OPEN_FILE}"
    echo "0" > "${LAST_ALERT_FILE}"
    log_line "ENTWARNUNG: Alle 4 Säulen wieder grün."
    send_out_of_band_alert "Entwarnung: Shop wieder grün" "Alle 4 Säulen (Shop, Cronjobs, Queue, Backups) laufen wieder regulär."
else
    log_line "OK (${PULSE_STATUS:-ok}) | ${PULSE_HEADLINE:-Alle 4 Säulen grün}"
fi
