#!/usr/bin/env bash
# End-to-end walkthrough of the API against a running instance.
# Usage: BASE_URL=http://localhost:8080 ./scripts/smoke.sh
set -euo pipefail

BASE_URL="${BASE_URL:-http://localhost:8000}"
API="$BASE_URL/api/v1"
SUFFIX="$(date +%s)"

need() { command -v "$1" >/dev/null || { echo "missing dependency: $1" >&2; exit 1; }; }
need curl; need jq

step() { printf '\n\033[1;34m== %s\033[0m\n' "$*"; }
post() { curl -sS -X POST "$API$1" -H 'Content-Type: application/json' "${@:2}"; }

step "Register an owner"
OWNER=$(post /auth/register -d "{\"name\":\"Owner\",\"email\":\"owner-$SUFFIX@example.com\",\"password\":\"secret-123\",\"role\":\"owner\"}")
echo "$OWNER" | jq '{role: .user.role, credit: .user.credit}'
OWNER_TOKEN=$(echo "$OWNER" | jq -r .token)

step "Register a regular user (20 credits) and a premium user (40 credits)"
REGULAR=$(post /auth/register -d "{\"name\":\"Reg\",\"email\":\"reg-$SUFFIX@example.com\",\"password\":\"secret-123\",\"role\":\"regular\"}")
PREMIUM=$(post /auth/register -d "{\"name\":\"Prem\",\"email\":\"prem-$SUFFIX@example.com\",\"password\":\"secret-123\",\"role\":\"premium\"}")
echo "$REGULAR" | jq '{role: .user.role, credit: .user.credit}'
echo "$PREMIUM" | jq '{role: .user.role, credit: .user.credit}'
REGULAR_TOKEN=$(echo "$REGULAR" | jq -r .token)

step "Owner adds two kosts"
K1=$(post /owner/kosts -H "Authorization: Bearer $OWNER_TOKEN" -d '{"name":"Kost Melati","location":"Yogyakarta","price":1500000,"available_rooms":2,"description":"Near UGM"}')
K2=$(post /owner/kosts -H "Authorization: Bearer $OWNER_TOKEN" -d '{"name":"Kost Mawar","location":"Bandung","price":900000,"available_rooms":0}')
echo "$K1" | jq '{id, name, price}'; echo "$K2" | jq '{id, name, price}'
K1_ID=$(echo "$K1" | jq -r .id)

step "Owner updates a kost"
curl -sS -X PUT "$API/owner/kosts/$K1_ID" -H "Authorization: Bearer $OWNER_TOKEN" -H 'Content-Type: application/json' \
  -d '{"name":"Kost Melati","location":"Yogyakarta","price":1600000,"available_rooms":1}' | jq '{id, price, available_rooms}'

step "Owner lists their kosts"
curl -sS "$API/owner/kosts" -H "Authorization: Bearer $OWNER_TOKEN" | jq '.meta'

step "Public search: name=melati"
curl -sS "$API/kosts?name=melati" | jq '.data[] | {id, name, location, price}'

step "Public search: price between 800000 and 1000000, sorted by price desc"
curl -sS "$API/kosts?min_price=800000&max_price=1000000&sort=price&order=desc" | jq '.data[] | {name, price}'

step "Kost detail"
curl -sS "$API/kosts/$K1_ID" | jq '{id, name, available_rooms}'

step "Regular user asks about availability (costs 5 credits)"
post "/kosts/$K1_ID/availability-inquiries" -H "Authorization: Bearer $REGULAR_TOKEN" | jq '{available_rooms, is_available, credits_charged, remaining_credit}'

step "Owner tries to ask (forbidden)"
post "/kosts/$K1_ID/availability-inquiries" -H "Authorization: Bearer $OWNER_TOKEN" | jq '{status, title}'

step "Anonymous request to an owner endpoint (unauthorized)"
curl -sS "$API/owner/kosts" | jq '{status, title}'

step "Current user"
curl -sS "$API/auth/me" -H "Authorization: Bearer $REGULAR_TOKEN" | jq '{email, role, credit}'

step "Delete a kost"
curl -sS -o /dev/null -w 'HTTP %{http_code}\n' -X DELETE "$API/owner/kosts/$K1_ID" -H "Authorization: Bearer $OWNER_TOKEN"

printf '\n\033[1;32mSmoke test finished.\033[0m\n'
