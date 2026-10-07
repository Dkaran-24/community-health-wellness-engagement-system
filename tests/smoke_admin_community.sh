#!/bin/bash
# tests/smoke_admin_community.sh — smoke test admin/community/* pages.
# Requires PHP dev server on :8081 with CEP env vars and MariaDB running.
BASE="http://127.0.0.1:8081"
CJ="/tmp/cep_admin_cookies.txt"
PASS=0; FAIL=0

# 1. Login as admin (no CSRF on admin login — original convention)
curl -s -c "$CJ" -o /dev/null "$BASE/login.php"
LOCATION=$(curl -s -b "$CJ" -c "$CJ" -o /dev/null -w "%{redirect_url}" \
  -d "username=admin&password=admin123" "$BASE/login.php")
if [[ "$LOCATION" == *"dashboard.php"* ]]; then echo "ADMIN LOGIN: OK"; else echo "ADMIN LOGIN: FAIL ($LOCATION)"; exit 1; fi

# 2. GET each admin community page
PAGES="index.php users.php events.php attendance.php volunteers.php feedback.php surveys.php requests.php resources.php announcements.php challenges.php"
for p in $PAGES; do
  OUT="/tmp/admin_$(basename $p .php).html"
  CODE=$(curl -s -b "$CJ" -o "$OUT" -w "%{http_code}" "$BASE/admin/community/$p")
  ISSUES=$(grep -cE "Fatal error|Parse error|Warning:|Deprecated:|Notice:" "$OUT" 2>/dev/null || true)
  AUTHED=$(grep -c "sidebar" "$OUT" 2>/dev/null || true)
  if [[ "$CODE" == "200" && "$ISSUES" == "0" && "$AUTHED" -gt 0 ]]; then
    echo "  $p: OK ($CODE)"; PASS=$((PASS+1))
  else
    echo "  $p: FAIL ($CODE, php-issues=$ISSUES, sidebar=$AUTHED)"; FAIL=$((FAIL+1))
    head -c 400 "$OUT"; echo
  fi
done

echo "RESULT: $PASS passed, $FAIL failed"
[ $FAIL -eq 0 ]
