#!/bin/bash
# CEP smoke test: login as demo community user, then GET every community page.
BASE="http://127.0.0.1:8081"
JAR=/tmp/cep_cookies.txt
rm -f $JAR

echo "=== 1. GET login page (grab CSRF) ==="
CSRF=$(curl -s -c $JAR "$BASE/community/login.php" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
echo "csrf: ${CSRF:0:12}..."

echo "=== 2. POST login ==="
RES=$(curl -s -b $JAR -c $JAR -o /tmp/login_out.html -w "%{http_code}|%{redirect_url}" \
  -d "csrf_token=$CSRF" -d "email=priya.sharma@community.demo" -d "password=Community@123" \
  "$BASE/community/login.php")
echo "$RES"

echo "=== 3. Authenticated GETs ==="
for p in dashboard events my-events challenges resources surveys requests volunteer my-feedback profile impact; do
  CODE=$(curl -s -b $JAR -o /tmp/page_$p.html -w "%{http_code}" -L "$BASE/community/$p.php")
  ERR=$(grep -cE "(Fatal error|Parse error|Warning:|Deprecated:|Notice:)" /tmp/page_$p.html || true)
  AUTHOK=$(grep -c "c-topbar" /tmp/page_$p.html || true)
  echo "$p.php -> HTTP $CODE | php-issues: $ERR | authed-layout: $AUTHOK"
done
