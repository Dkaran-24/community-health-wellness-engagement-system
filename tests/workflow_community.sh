#!/bin/bash
# CEP workflow test — POST flows with business rule verification.
BASE="http://127.0.0.1:8081"
JAR=/tmp/cep_cookies.txt   # still logged in as priya.sharma@community.demo

csrf() { curl -s -b $JAR "$BASE/community/$1" | grep -oP 'name="csrf_token" value="\K[a-f0-9]+' | head -1; }

echo "=== A. EVENT REGISTRATION ==="
CSRF=$(csrf events.php)
EID=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT id FROM community_events WHERE status='Upcoming' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 1")
CUID=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT id FROM community_users WHERE email='priya.sharma@community.demo'")
echo "event=$EID user=$CUID  (already registered? $(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT COUNT(*) FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID AND status<>'Cancelled'"))"

# A1: register
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=register" "$BASE/community/events-register.php")
echo "A1 register -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT status FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
echo "A1 db status: $DB"

# A2: duplicate attempt
CSRF=$(csrf events.php)
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=register" "$BASE/community/events-register.php")
echo "A2 duplicate -> $R (must contain 'already')"

# A3: cancel
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=cancel" "$BASE/community/events-register.php")
echo "A3 cancel -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT status FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
echo "A3 db status: $DB"

# A4: re-register (revive cancelled row)
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "event_id=$EID" -d "action=register" "$BASE/community/events-register.php")
echo "A4 re-register -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT status FROM event_registrations WHERE event_id=$EID AND community_user_id=$CUID")
echo "A4 db status: $DB (final state: Registered)"

# A5: CSRF rejection
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}" -d "csrf_token=WRONG" -d "event_id=$EID" -d "action=cancel" "$BASE/community/events-register.php")
echo "A5 bad CSRF -> HTTP $R (must be 400)"

echo ""
echo "=== B. SURVEY VOTE ==="
CSRF=$(csrf surveys.php)
SID=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT s.id FROM community_surveys s JOIN survey_questions q ON q.survey_id=s.id WHERE s.status='Open' AND q.question_type='single' AND NOT EXISTS (SELECT 1 FROM survey_responses r WHERE r.question_id=q.id AND r.community_user_id=$CUID) LIMIT 1")
QID=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT q.id FROM survey_questions q WHERE q.survey_id=$SID AND q.question_type='single' LIMIT 1")
OPT=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT JSON_UNQUOTE(JSON_EXTRACT(options,'$[0]')) FROM survey_questions WHERE id=$QID")
echo "survey=$SID question=$QID option=$OPT"
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "survey_id=$SID" -d "q_$QID=$OPT" "$BASE/community/surveys.php")
echo "B1 vote -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT CONCAT(option_text,' / ',COALESCE(response_text,'')) FROM survey_responses WHERE question_id=$QID AND community_user_id=$CUID")
echo "B1 db: $DB"

echo ""
echo "=== C. CHALLENGE JOIN + LOG ==="
CSRF=$(csrf challenges.php)
CID=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT c.id FROM fitness_challenges c WHERE c.status='Active' AND c.end_date>=CURDATE() AND NOT EXISTS (SELECT 1 FROM challenge_participants p WHERE p.challenge_id=c.id AND p.community_user_id=$CUID) LIMIT 1")
echo "challenge=$CID"
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=join" -d "challenge_id=$CID" "$BASE/community/challenges.php")
echo "C1 join -> $R"
TODAY=$(date +%F)
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" -d "csrf_token=$CSRF" -d "action=log" -d "challenge_id=$CID" -d "progress_date=$TODAY" -d "value_logged=3" -d "notes=test log" "$BASE/community/challenges.php")
echo "C2 log -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT CONCAT('cur=',p.current_value,' done=',p.completed) FROM challenge_participants p WHERE p.challenge_id=$CID AND p.community_user_id=$CUID")
echo "C2 db: $DB"

echo ""
echo "=== D. REQUEST SUBMISSION ==="
CSRF=$(csrf requests.php)
R=$(curl -s -b $JAR -o /dev/null -w "%{http_code}|%{redirect_url}" --data-urlencode "csrf_token=$CSRF" --data-urlencode "request_type=Yoga Sessions" --data-urlencode "title=Evening yoga at Riverside Park" --data-urlencode "description=Many seniors in our society would like gentle evening yoga sessions twice a week." "$BASE/community/requests.php")
echo "D1 submit -> $R"
DB=$(mysql -u cep_user -p'CepLocal_2026!' -h 127.0.0.1 newlife_cep -N -e "SELECT CONCAT(id,'|',status,'|',title) FROM community_requests WHERE community_user_id=$CUID ORDER BY id DESC LIMIT 1")
echo "D1 db: $DB"
