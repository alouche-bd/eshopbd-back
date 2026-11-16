#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
ENV_FILE="${ROOT_DIR}/.env"
INTERACTIVE=1
CHECK_UUID=""

BASE_URL="${GALAXY_API_BASE_URL:-}"
KEYCLOAK_URL="${GALAXY_KEYCLOAK_URL:-}"
REALM="${GALAXY_KEYCLOAK_REALM:-}"
CLIENT_ID="${GALAXY_KEYCLOAK_CLIENT_ID:-}"
CLIENT_SECRET="${GALAXY_KEYCLOAK_CLIENT_SECRET:-}"
REDIRECT_URI="${GALAXY_LOGIN_REDIRECT_URI:-}"
MODE="${GALAXY_API_MODE:-}"

usage() {
  cat <<'USAGE'
Usage: scripts/setup-galaxy-api.sh [options]

Configures the galaxy-api integration in .env, validates the Keycloak s4a
client and checks galaxy-api connectivity.

Options:
  --env-file PATH          .env file to update (default: ./.env)
  --base-url URL           GALAXY_API_BASE_URL
  --keycloak-url URL       GALAXY_KEYCLOAK_URL (Keycloak base, before /realms)
  --realm NAME             GALAXY_KEYCLOAK_REALM (default: s4a)
  --client-id ID           GALAXY_KEYCLOAK_CLIENT_ID (default: biotech-dental-eshop)
  --client-secret-stdin    read GALAXY_KEYCLOAK_CLIENT_SECRET from stdin
  --redirect-uri URL       GALAXY_LOGIN_REDIRECT_URI
  --mode legacy|new        GALAXY_API_MODE (default: legacy; cutover: new, rollback: legacy)
  --check-user UUID        also read the smiley balance of this Galaxy user
  --non-interactive        never prompt; keep current values when not given
  -h, --help               show this help

The secret can also come from the GALAXY_KEYCLOAK_CLIENT_SECRET environment
variable. It is never printed.
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env-file) ENV_FILE="$2"; shift 2 ;;
    --base-url) BASE_URL="$2"; shift 2 ;;
    --keycloak-url) KEYCLOAK_URL="$2"; shift 2 ;;
    --realm) REALM="$2"; shift 2 ;;
    --client-id) CLIENT_ID="$2"; shift 2 ;;
    --client-secret-stdin) IFS= read -r CLIENT_SECRET; shift ;;
    --redirect-uri) REDIRECT_URI="$2"; shift 2 ;;
    --mode) MODE="$2"; shift 2 ;;
    --check-user) CHECK_UUID="$2"; shift 2 ;;
    --non-interactive) INTERACTIVE=0; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage >&2; exit 2 ;;
  esac
done

RESULTS=()
FAILED=0

record() {
  RESULTS+=("$1 $2")
  if [[ "$1" == "KO" ]]; then
    FAILED=1
  fi
}

summary() {
  echo
  echo "=== galaxy-api setup summary ==="
  for line in "${RESULTS[@]}"; do
    echo "  ${line}"
  done
  if [[ "${FAILED}" -eq 0 ]]; then
    echo "Result: OK"
  else
    echo "Result: KO"
  fi
}

trap summary EXIT

for bin in curl php; do
  if command -v "${bin}" >/dev/null 2>&1; then
    record OK "prerequisite ${bin}"
  else
    record KO "prerequisite ${bin} is missing"
    exit 1
  fi
done

if [[ ! -f "${ENV_FILE}" ]]; then
  if [[ -f "${ROOT_DIR}/.env.example" ]]; then
    cp "${ROOT_DIR}/.env.example" "${ENV_FILE}"
    record OK ".env created from .env.example"
  else
    touch "${ENV_FILE}"
    record OK ".env created"
  fi
fi
chmod 600 "${ENV_FILE}"

current() {
  local line
  line="$(grep -E "^$1=" "${ENV_FILE}" | tail -n 1 || true)"
  printf '%s' "${line#*=}"
}

ask() {
  local name="$1" value="$2" fallback="$3" answer
  if [[ -z "${value}" ]]; then
    value="$(current "${name}")"
  fi
  if [[ -z "${value}" ]]; then
    value="${fallback}"
  fi
  if [[ "${INTERACTIVE}" -eq 1 && -t 0 ]]; then
    read -r -p "${name} [${value}]: " answer
    value="${answer:-${value}}"
  fi
  printf '%s' "${value}"
}

ask_secret() {
  local name="$1" value="$2" answer
  if [[ -z "${value}" ]]; then
    value="$(current "${name}")"
  fi
  if [[ "${INTERACTIVE}" -eq 1 && -t 0 ]]; then
    if [[ -n "${value}" ]]; then
      read -r -s -p "${name} [keep current]: " answer
    else
      read -r -s -p "${name}: " answer
    fi
    echo >&2
    value="${answer:-${value}}"
  fi
  printf '%s' "${value}"
}

set_env() {
  local name="$1" value="$2"
  ENV_FILE="${ENV_FILE}" NAME="${name}" VALUE="${value}" php -r '
    $file = getenv("ENV_FILE");
    $name = getenv("NAME");
    $value = getenv("VALUE");
    $lines = file_exists($file) ? file($file, FILE_IGNORE_NEW_LINES) : [];
    $found = false;
    foreach ($lines as $i => $line) {
        if (strpos($line, $name . "=") === 0) {
            $lines[$i] = $name . "=" . $value;
            $found = true;
        }
    }
    if (!$found) {
        $lines[] = $name . "=" . $value;
    }
    file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL);
  '
}

MODE="$(ask GALAXY_API_MODE "${MODE}" "legacy")"
MODE="$(printf '%s' "${MODE}" | tr '[:upper:]' '[:lower:]' | tr -d '[:space:]')"
if [[ "${MODE}" != "new" && "${MODE}" != "legacy" ]]; then
  record KO "GALAXY_API_MODE must be legacy or new (got: ${MODE})"
  exit 1
fi
BASE_URL="$(ask GALAXY_API_BASE_URL "${BASE_URL}" "https://01.fr.api.biotechgalaxy.com")"
KEYCLOAK_URL="$(ask GALAXY_KEYCLOAK_URL "${KEYCLOAK_URL}" "https://01.fr.oauth.biotechgalaxy.com/auth")"
REALM="$(ask GALAXY_KEYCLOAK_REALM "${REALM}" "s4a")"
CLIENT_ID="$(ask GALAXY_KEYCLOAK_CLIENT_ID "${CLIENT_ID}" "biotech-dental-eshop")"
REDIRECT_URI="$(ask GALAXY_LOGIN_REDIRECT_URI "${REDIRECT_URI}" "https://shop.biotech-dental.com/login-sso")"
CLIENT_SECRET="$(ask_secret GALAXY_KEYCLOAK_CLIENT_SECRET "${CLIENT_SECRET}")"

set_env GALAXY_API_MODE "${MODE}"
set_env GALAXY_API_BASE_URL "${BASE_URL%/}"
set_env GALAXY_KEYCLOAK_URL "${KEYCLOAK_URL%/}"
set_env GALAXY_KEYCLOAK_REALM "${REALM}"
set_env GALAXY_KEYCLOAK_CLIENT_ID "${CLIENT_ID}"
set_env GALAXY_KEYCLOAK_CLIENT_SECRET "${CLIENT_SECRET}"
set_env GALAXY_LOGIN_REDIRECT_URI "${REDIRECT_URI}"
if [[ -z "$(current GALAXY_API_TIMEOUT)" ]]; then
  set_env GALAXY_API_TIMEOUT "15"
fi
record OK ".env updated (${ENV_FILE}, GALAXY_API_MODE=${MODE})"

if [[ -z "${CLIENT_SECRET}" ]]; then
  record KO "GALAXY_KEYCLOAK_CLIENT_SECRET is empty"
  exit 1
fi

json_field() {
  FIELD="$1" php -r '
    $data = json_decode(stream_get_contents(STDIN), true);
    $value = $data;
    foreach (explode(".", getenv("FIELD")) as $key) {
        $value = is_array($value) && array_key_exists($key, $value) ? $value[$key] : null;
    }
    echo is_scalar($value) ? (is_bool($value) ? ($value ? "true" : "false") : $value) : "";
  '
}

TOKEN_URL="${KEYCLOAK_URL%/}/realms/${REALM}/protocol/openid-connect/token"
TOKEN_RESPONSE="$(printf '%s' "${CLIENT_SECRET}" | curl -sS --max-time 20 -X POST "${TOKEN_URL}" \
  --data-urlencode "grant_type=client_credentials" \
  --data-urlencode "client_id=${CLIENT_ID}" \
  --data-urlencode "client_secret@-" || true)"
ACCESS_TOKEN="$(printf '%s' "${TOKEN_RESPONSE}" | json_field access_token)"

if [[ -n "${ACCESS_TOKEN}" ]]; then
  record OK "Keycloak ${REALM} client_credentials token for ${CLIENT_ID}"
elif [[ -z "${TOKEN_RESPONSE}" ]]; then
  record KO "Keycloak unreachable on ${TOKEN_URL}"
else
  record KO "Keycloak token refused for ${CLIENT_ID}: $(printf '%s' "${TOKEN_RESPONSE}" | json_field error_description)"
fi

HEALTH="$(curl -sS --max-time 20 "${BASE_URL%/}/health" || true)"
if [[ "$(printf '%s' "${HEALTH}" | json_field status)" == "true" ]]; then
  record OK "galaxy-api reachable (${BASE_URL%/}/health, version $(printf '%s' "${HEALTH}" | json_field result.version))"
else
  record KO "galaxy-api health check failed on ${BASE_URL%/}/health"
fi

if [[ -n "${CHECK_UUID}" && -n "${ACCESS_TOKEN}" ]]; then
  BALANCE="$(printf 'Authorization: Bearer %s' "${ACCESS_TOKEN}" | curl -sS --max-time 20 -H @- \
    "${BASE_URL%/}/integrations/eshop/users/${CHECK_UUID}/smileys/balance" || true)"
  if [[ "$(printf '%s' "${BALANCE}" | json_field status)" == "true" ]]; then
    record OK "smiley balance of ${CHECK_UUID}: $(printf '%s' "${BALANCE}" | json_field result.balance)"
  else
    record KO "smiley balance of ${CHECK_UUID}: $(printf '%s' "${BALANCE}" | json_field statusCode) $(printf '%s' "${BALANCE}" | json_field code) $(printf '%s' "${BALANCE}" | json_field message)"
  fi
fi

[[ "${FAILED}" -eq 0 ]]
