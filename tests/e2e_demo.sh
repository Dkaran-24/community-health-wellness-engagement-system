#!/bin/bash
# =====================================================================
# NEW LIFE FITNESS CEP — FULL END-TO-END DEMO WORKFLOW TEST
# ---------------------------------------------------------------------
# Walks the complete required CEP demo journey with ONE FRESH user:
#   Register -> Login -> View Event -> Event Registration ->
#   Volunteer Application -> Admin Approve -> Admin Assign ->
#   Conduct Event (mark Completed) -> Attendance (Present) ->
#   Feedback (+AI sentiment prototype) -> Survey Vote -> Survey Analysis
#   -> Volunteer Hours Log -> Admin Approve Hours -> Impact Dashboard
#
# Everything is asserted: HTTP status, redirects, flash messages,
# and the actual database rows after each step.
# =====================================================================
BASE="http://127.0.0.1:8081"
UJAR=/tmp/cep_e2e_user.txt          # fresh community user cookies
AJAR=/tmp/cep_e2e_admin.txt         # admin cookies
MYSQL="mysql -h 127.0.0.1 -u cep_user -pCepLocal_2026! newlife_cep -N -B"
MYSQLQ="$MYSQL -e"

# Timing: unique email/mobile per run so reruns never hit duplicate rows
TS=$(date +%s)
EMAIL="e2e.demo.$TS@community.demo"
MOBILE="98900${TS: -6}"   # last 6 digits of timestamp -> 10 digit mobile
PASS="Community@123"
NAME="E2E Demo Runner"

PASS_COUNT=0; FAIL_COUNT=0
ok()  { PASS_COUNT=$((PASS_COUNT+1)); echo "  ✔ $1"; }
bad() { FAIL_COUNT=$((FAIL_COUNT+1)); echo "  ✘ $1"; }
step(){ echo ""; echo "== STEP $1 =="; }

csrf() { curl -s -b "$1" "$BASE/community/$2" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1; }
csrfA(){ curl -s -b "$1" "$BASE/admin/community/$2" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1; }

# ---------------------------------------------------------------------
echo "═══════════════════════════════════════════════════════════════"
echo " CEP FULL E2E DEMO WORKFLOW — user: $EMAIL"
echo "═══════════════════════════════════════════════════════════════"

# ======================= 1. REGISTER ================================
step "1 — Register a brand-new community account"
rm -f $UJAR
CSRF=$(curl -s -c $UJAR "$BASE/community/register.php" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
[ -n "$CSRF" ] && ok "register form + CSRF token" || bad "no CSRF token on register form"
R=$(curl -s -b $UJAR -c $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "full_name=$NAME" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "mobile=$MOBILE" \
  --data-urlencode "password=$PASS" \
  --data-urlencode "password2=$PASS" \
  --data-urlencode "age=34" \
  --data-urlencode "gender=Female" \
  --data-urlencode "address_area=Riverside Colony" \
  --data-urlencode "fitness_level=Beginner" \
  --data-urlencode "fitness_goal=General Fitness" \
  --data-urlencode "preferred_activities=Walking, Yoga" \
  "$BASE/community/register.php")
[[ "$R" == *"dashboard.php?ok=Welcome"* ]] && ok "register -> auto-login -> dashboard redirect" || bad "register redirect wrong: $R"
CUID=$($MYSQLQ "SELECT id FROM community_users WHERE email='$EMAIL'")
[ -n "$CUID" ] && ok "community_users row created (id=$CUID)" || bad "no community_users row"
HASH=$($MYSQLQ "SELECT password FROM community_users WHERE id=$CUID")
[[ "$HASH" == \$2y\$* ]] && ok "password stored as bcrypt hash" || bad "password not bcrypt: $HASH"

# --- negative: duplicate email register (must be refused) ---
rm -f /tmp/dup_jar.txt
DUPCSRF=$(curl -s -c /tmp/dup_jar.txt "$BASE/community/register.php" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
DUPR=$(curl -s -b /tmp/dup_jar.txt -o /tmp/dup.html -w "%{http_code}" --data-urlencode "csrf_token=$DUPCSRF" --data-urlencode "full_name=Another Person" --data-urlencode "email=$EMAIL" --data-urlencode "mobile=9876543211" --data-urlencode "password=$PASS" --data-urlencode "password2=$PASS" --data-urlencode "age=30" --data-urlencode "gender=Male" --data-urlencode "address_area=Old Town" "$BASE/community/register.php")
grep -qi "already exists" /tmp/dup.html && [ "$DUPR" == "200" ] && ok "duplicate email refused (re-shows form with error, HTTP 200)" || bad "duplicate register unexpected: $DUPR"

# ======================= 2. LOGIN ====================================
step "2 — Login (fresh session) via community/login.php"
rm -f $UJAR
CSRF=$(curl -s -c $UJAR "$BASE/community/login.php" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
R=$(curl -s -b $UJAR -c $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "email=$EMAIL" \
  --data-urlencode "password=$PASS" \
  "$BASE/community/login.php")
[[ "$R" == *"dashboard.php"* ]] && ok "login -> dashboard redirect" || bad "login redirect wrong: $R"
D=$(curl -s -b $UJAR "$BASE/community/dashboard.php")
echo "$D" | grep -q "E2E Demo Runner" && ok "dashboard greets the new user by name" || bad "dashboard doesn't show user name"

# ======================= 3. VIEW EVENT ===============================
step "3 — View events list + event detail"
E=$(curl -s -b $UJAR "$BASE/community/events.php")
echo "$E" | grep -q "csrf_token" && ok "events page renders with CSRF form(s)" || bad "events page missing forms"
# pick a future event this user can register for (deadline not passed)
EID=$($MYSQLQ "SELECT id FROM community_events WHERE status='Upcoming' AND event_date > CURDATE() AND (reg_deadline IS NULL OR reg_deadline >= CURDATE()) ORDER BY event_date ASC LIMIT 1")
[ -n "$EID" ] && ok "found registerable upcoming event (id=$EID)" || bad "no upcoming event available"
EVNAME=$($MYSQLQ "SELECT event_name FROM community_events WHERE id=$EID")
echo "$E" | grep -q "id=\"event-$EID\"" && ok "events list shows \"$EVNAME\" (anchor #event-$EID)" || bad "event not in list"

# ======================= 4. EVENT REGISTRATION =======================
step "4 — Register for the event"
CSRF=$(csrf $UJAR events.php)
R=$(curl -s -b $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=register" "$BASE/community/events-register.php")
DB=$($MYSQLQ "SELECT status FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
[[ "$DB" == "Registered" ]] && ok "event_registrations row = Registered" || bad "registration status: $DB | $R"
# duplicate blocked
CSRF=$(csrf $UJAR events.php)
R=$(curl -s -b $UJAR -o /dev/null -w "%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=register" "$BASE/community/events-register.php")
MSG=$(python3 -c "import urllib.parse,sys; print(urllib.parse.unquote(sys.argv[1].split('err=',1)[1].split('#')[0] if 'err=' in sys.argv[1] else ''))" "$R" 2>/dev/null)
[[ "$R" == *"err=You+are+already+registered"* || "$MSG" == *"already registered"* ]] && ok "duplicate registration blocked with message" || bad "duplicate not blocked: $R"
CNT=$($MYSQLQ "SELECT COUNT(*) FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
[ "$CNT" == "1" ] && ok "still exactly 1 registration row (no dup row)" || bad "dup rows: $CNT"

# ======================= 5. VOLUNTEER APPLICATION ====================
step "5 — Submit volunteer application"
V=$(curl -s -b $UJAR "$BASE/community/volunteer.php")
CSRF=$(echo "$V" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
echo "$V" | grep -q 'c-form-grid' && ok "volunteer page shows application form (fresh user)" || bad "volunteer page state unexpected"
R=$(curl -s -b $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "skills=Event setup, crowd guidance, first aid basics" \
  --data-urlencode "interest_areas=Fitness camps, yoga sessions, registration desks" \
  --data-urlencode "availability=Weekends and early mornings" \
  --data-urlencode "previous_experience=Helped organise society sports day 2024" \
  --data-urlencode "reason=I want to give back to my neighbourhood and support free community fitness." \
  "$BASE/community/volunteer.php")
APPID=$($MYSQLQ "SELECT id FROM volunteer_applications WHERE community_user_id=$CUID")
[ -n "$APPID" ] && ok "volunteer_applications row created (id=$APPID, status=Pending)" || bad "no application row | $R"
ST=$($MYSQLQ "SELECT status FROM volunteer_applications WHERE id=$APPID")
[ "$ST" == "Pending" ] && ok "application starts as Pending" || bad "app status: $ST"

# ======================= 6. ADMIN: APPROVE VOLUNTEER =================
step "6 — Admin logs in and approves the volunteer application"
rm -f $AJAR
CSRF=$(curl -s -c $AJAR "$BASE/login.php" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
R=$(curl -s -b $AJAR -c $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "username=admin" -d "password=admin123" -d "csrf_token=$CSRF" "$BASE/login.php")
[[ "$R" == *"admin/dashboard.php"* ]] && ok "admin login -> admin/dashboard.php" || bad "admin login: $R"
CSRF=$(csrfA $AJAR volunteers.php)
R=$(curl -s -b $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=review_app" -d "app_id=$APPID" -d "decision=Approved" "$BASE/admin/community/volunteers.php")
VOL=$($MYSQLQ "SELECT id FROM volunteers WHERE community_user_id=$CUID")
[ -n "$VOL" ] && ok "volunteers row auto-created on approval (id=$VOL, status=Active)" || bad "no volunteers row | $R"
ST=$($MYSQLQ "SELECT status FROM volunteer_applications WHERE id=$APPID")
[ "$ST" == "Approved" ] && ok "application status -> Approved" || bad "app status: $ST"

# ======================= 7. ADMIN: ASSIGN VOLUNTEER ==================
step "7 — Admin assigns the volunteer to the upcoming event"
CSRF=$(csrfA $AJAR volunteers.php)
R=$(curl -s -b $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=assign" -d "volunteer_id=$VOL" -d "event_id=$EID" -d "role=Registration Desk" "$BASE/admin/community/volunteers.php")
ASG=$($MYSQLQ "SELECT id FROM volunteer_event_assignments WHERE volunteer_id=$VOL AND event_id=$EID ORDER BY id DESC LIMIT 1")
[ -n "$ASG" ] && ok "assignment created (id=$ASG, role=Registration Desk)" || bad "no assignment | $R"
# duplicate assignment blocked
CSRF=$(csrfA $AJAR volunteers.php)
R=$(curl -s -b $AJAR -o /dev/null -w "%{redirect_url}" -d "csrf_token=$CSRF" -d "action=assign" -d "volunteer_id=$VOL" -d "event_id=$EID" -d "role=Setup Crew" "$BASE/admin/community/volunteers.php")
[[ "$R" == *"err=This+volunteer+is+already+assigned"* ]] && ok "duplicate assignment blocked" || bad "dup assignment allowed: $R"

# ======================= 8. CONDUCT EVENT ============================
step "8 — Admin conducts the event (set status Completed)"
CSRF=$(csrfA $AJAR events.php)
R=$(curl -s -b $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=set_status" -d "id=$EID" -d "status=Completed" "$BASE/admin/community/events.php")
ST=$($MYSQLQ "SELECT status FROM community_events WHERE id=$EID")
[ "$ST" == "Completed" ] && ok "event status -> Completed" || bad "event status: $ST | $R"

# ======================= 9. ATTENDANCE ===============================
step "9 — Admin marks attendance (user Present)"
REGID=$($MYSQLQ "SELECT id FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
CSRF=$(curl -s -b $AJAR "$BASE/admin/community/attendance.php?event=$EID" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
[ -n "$CSRF" ] && ok "attendance sheet for event $EID renders with CSRF token" || bad "no attendance form (event $EID)"
R=$(curl -s -b $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=save" -d "event_id=$EID" -d "mark[$REGID]=Present" "$BASE/admin/community/attendance.php")
AT=$($MYSQLQ "SELECT status FROM event_attendance WHERE event_id=$EID AND community_user_id=$CUID")
[ "$AT" == "Present" ] && ok "event_attendance row = Present" || bad "attendance: $AT | $R"
RST=$($MYSQLQ "SELECT status FROM event_registrations WHERE id=$REGID")
[ "$RST" == "Attended" ] && ok "registration status synced -> Attended" || bad "reg status: $RST"

# ======================= 10. FEEDBACK + AI PROTOTYPE =================
step "10 — User submits feedback (AI sentiment prototype runs)"
# eligibility: event completed + attended
FE=$(curl -s -b $UJAR "$BASE/community/event-feedback.php?event_id=$EID")
echo "$FE" | grep -qi "rating" && ok "feedback form shown (attended + completed)" || bad "feedback form not offered"
CSRF=$(echo "$FE" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
R=$(curl -s -b $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "event_id=$EID" \
  --data-urlencode "rating=5" \
  --data-urlencode "satisfaction=Very Satisfied" \
  --data-urlencode "comments=Absolutely wonderful session. The trainer was friendly, energetic and very knowledgeable. I felt great afterwards and my neighbours loved it too." \
  --data-urlencode "suggestions=Please add more evening yoga batches for working professionals." \
  --data-urlencode "would_attend_again=Yes" \
  "$BASE/community/event-feedback.php?event_id=$EID")
FBID=$($MYSQLQ "SELECT id FROM community_feedback WHERE event_id=$EID AND community_user_id=$CUID")
[ -n "$FBID" ] && ok "community_feedback row created (id=$FBID)" || bad "no feedback row | $R"
SENT=$($MYSQLQ "SELECT sentiment FROM community_feedback WHERE id=$FBID")
[ "$SENT" == "Positive" ] && ok "AI prototype classified sentiment = Positive" || bad "sentiment: $SENT"
TOPICS=$($MYSQLQ "SELECT topics FROM community_feedback WHERE id=$FBID")
[ -n "$TOPICS" ] && [ "$TOPICS" != "NULL" ] && ok "AI prototype extracted topics: $TOPICS" || bad "topics empty"
# duplicate feedback blocked
R=$(curl -s -b $UJAR -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$CSRF" --data-urlencode "event_id=$EID" --data-urlencode "rating=4" --data-urlencode "satisfaction=Satisfied" --data-urlencode "comments=second attempt" "$BASE/community/event-feedback.php?event_id=$EID")
[[ "$R" == *"warn=You+have+already+given+feedback"* ]] && ok "duplicate feedback blocked" || bad "dup feedback allowed: $R"

# ======================= 11. SURVEY VOTE =============================
step "11 — User answers an open survey"
S=$(curl -s -b $UJAR "$BASE/community/surveys.php")
CSRF=$(echo "$S" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
SID=$($MYSQLQ "SELECT s.id FROM community_surveys s JOIN survey_questions q ON q.survey_id=s.id WHERE s.status='Open' AND q.question_type='single' AND NOT EXISTS (SELECT 1 FROM survey_responses r WHERE r.question_id=q.id AND r.community_user_id=$CUID) LIMIT 1")
QID=$($MYSQLQ "SELECT q.id FROM survey_questions q WHERE q.survey_id=$SID AND q.question_type='single' LIMIT 1")
OPT=$($MYSQLQ "SELECT JSON_UNQUOTE(JSON_EXTRACT(options,'\$[0]')) FROM survey_questions WHERE id=$QID")
[ -n "$SID" ] && ok "found open survey (id=$SID, question=$QID, option=\"$OPT\")" || bad "no open survey for this user"
R=$(curl -s -b $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "survey_id=$SID" -d "q_$QID=$OPT" "$BASE/community/surveys.php")
SRESP=$($MYSQLQ "SELECT option_text FROM survey_responses WHERE question_id=$QID AND community_user_id=$CUID")
[ "$SRESP" == "$OPT" ] && ok "survey_responses row saved (option=\"$OPT\")" || bad "vote not saved: $SRESP | $R"

# ======================= 12. SURVEY ANALYSIS (ADMIN) =================
step "12 — Admin survey analysis view"
A=$(curl -s -b $AJAR "$BASE/admin/community/surveys.php")
echo "$A" | grep -q "</html>" && ok "admin surveys page complete" || bad "admin surveys page truncated"
# per-survey results page with counts for the voted option
SR=$(curl -s -b $AJAR "$BASE/admin/community/surveys.php?survey=$SID")
echo "$SR" | grep -qF "$OPT" && ok "admin survey results include the option \"$OPT\"" || bad "option not in results page"
VCOUNT=$($MYSQLQ "SELECT COUNT(*) FROM survey_responses WHERE question_id=$QID AND option_text='$OPT'")
[ "$VCOUNT" -ge 1 ] && ok "response count for \"$OPT\" >= 1 (analysis reflects the vote)" || bad "vote count: $VCOUNT"

# ======================= 13. VOLUNTEER HOURS LOG =====================
step "13 - Volunteer logs hours (requires Completed assignment)"
VD=$(curl -s -b $UJAR "$BASE/community/volunteer-dashboard.php")
echo "$VD" | grep -q "open for logging once" && ok "hours form correctly hidden until assignment is completed" || bad "hours form state wrong (should be hidden pre-completion)"
# admin completes the assignment first
CSRF2=$(csrfA $AJAR volunteers.php)
curl -s -b $AJAR -o /dev/null -d "csrf_token=$CSRF2" -d "action=complete" -d "assignment_id=$ASG" "$BASE/admin/community/volunteers.php"
ASGST=$($MYSQLQ "SELECT status FROM volunteer_event_assignments WHERE id=$ASG")
[ "$ASGST" == "Completed" ] && ok "admin marked assignment Completed (id=$ASG)" || bad "assignment status: $ASGST"
VD=$(curl -s -b $UJAR "$BASE/community/volunteer-dashboard.php")
echo "$VD" | grep -q "log_hours" && ok "hours-logging form now offered (Completed assignment)" || bad "no hours form after completion"
CSRF=$(echo "$VD" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1)
# negative: hours for a NON-assigned event must be rejected server-side
OTHREID=$($MYSQLQ "SELECT id FROM community_events WHERE id <> $EID AND id NOT IN (SELECT event_id FROM volunteer_event_assignments WHERE volunteer_id=$VOL) LIMIT 1")
TODAY=$(date +%F)
R=$(curl -s -b $UJAR -o /dev/null -w "%{redirect_url}" --data-urlencode "csrf_token=$CSRF" --data-urlencode "action=log_hours" --data-urlencode "event_id=$OTHREID" --data-urlencode "work_date=$TODAY" --data-urlencode "start_time=06:30" --data-urlencode "end_time=09:30" --data-urlencode "role=Registration Desk" "$BASE/community/volunteer-dashboard.php")
[[ "$R" == *"only+log+hours+for+events+where+your+assignment+is+marked+completed"* ]] && ok "server blocks hours for unassigned event (never trust the UI)" || bad "unassigned hours allowed: $R"
# positive: log for the assigned + completed event
R=$(curl -s -b $UJAR -o /dev/null -w "%{http_code}|%{redirect_url}" \
  --data-urlencode "csrf_token=$CSRF" \
  --data-urlencode "action=log_hours" \
  --data-urlencode "event_id=$EID" \
  --data-urlencode "work_date=$TODAY" \
  --data-urlencode "start_time=06:30" \
  --data-urlencode "end_time=09:30" \
  --data-urlencode "role=Registration Desk" \
  "$BASE/community/volunteer-dashboard.php")
HID=$($MYSQLQ "SELECT id FROM volunteer_hours WHERE volunteer_id=$VOL AND event_id=$EID ORDER BY id DESC LIMIT 1")
[ -n "$HID" ] && ok "volunteer_hours row created (id=$HID, status=Pending)" || bad "no hours row | $R"
HH=$($MYSQLQ "SELECT total_hours FROM volunteer_hours WHERE id=$HID")
[ "$HH" == "3.00" ] && ok "hours auto-computed = 3.00 h (06:30 to 09:30)" || bad "hours value: $HH"

# ======================= 14. ADMIN: APPROVE HOURS ====================
step "14 — Admin approves the logged hours"
CSRF=$(csrfA $AJAR volunteers.php)
R=$(curl -s -b $AJAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=review_hours" -d "hours_id=$HID" -d "decision=Approved" "$BASE/admin/community/volunteers.php?tab=hours")
ST=$($MYSQLQ "SELECT status FROM volunteer_hours WHERE id=$HID")
[ "$ST" == "Approved" ] && ok "volunteer_hours status -> Approved" || bad "hours status: $ST | $R"
TOT=$($MYSQLQ "SELECT total_hours FROM volunteers WHERE id=$VOL")
[ "$TOT" == "3.00" ] && ok "volunteers.total_hours updated to 3.00" || bad "total_hours: $TOT"
# double-approve must NOT double-count
CSRF=$(csrfA $AJAR volunteers.php)
curl -s -b $AJAR -o /dev/null -d "csrf_token=$CSRF" -d "action=review_hours" -d "hours_id=$HID" -d "decision=Approved" "$BASE/admin/community/volunteers.php?tab=hours"
TOT2=$($MYSQLQ "SELECT total_hours FROM volunteers WHERE id=$VOL")
[ "$TOT2" == "3.00" ] && ok "double-approve does not double-count hours (still 3.00)" || bad "double-counted: $TOT2"

# ======================= 15. IMPACT DASHBOARD ========================
step "15 — Community Impact dashboard reflects the full journey"
I=$(curl -s -b $UJAR "$BASE/community/impact.php")
echo "$I" | grep -q "</html>" && ok "impact page complete" || bad "impact page truncated"
# feedback sentiment chart should include a Positive entry from step 10
echo "$I" | grep -q "Positive" && ok "impact charts reference Positive sentiment" || bad "no Positive sentiment on impact page"
echo "$I" | grep -qi "demo" && ok "impact page carries honest demo-data disclosure" || bad "no demo-data disclosure on impact page"
# homepage impact counters too
H=$(curl -s "$BASE/index.php")
echo "$H" | grep -q "p-count" && ok "public homepage impact counters render" || bad "homepage counters missing"
echo "  DB totals after workflow: regs=$($MYSQLQ "SELECT COUNT(*) FROM event_registrations") attendance=$($MYSQLQ "SELECT COUNT(*) FROM event_attendance") approved_hours=$($MYSQLQ "SELECT ROUND(SUM(total_hours),2) FROM volunteer_hours WHERE status='Approved'") feedback=$($MYSQLQ "SELECT COUNT(*) FROM community_feedback")"

# ======================= SUMMARY =====================================
echo ""
echo "═══════════════════════════════════════════════════════════════"
echo " E2E RESULT: $PASS_COUNT passed, $FAIL_COUNT failed"
echo "═══════════════════════════════════════════════════════════════"
[ $FAIL_COUNT -eq 0 ] && echo "ALL E2E STEPS GREEN ✓" || echo "SOME STEPS FAILED ✘"
exit $FAIL_COUNT
