#!/usr/bin/env bash
# End-to-end HTTP tests for Lab 7 (cookies, sessions, CSRF, redirects, role and ownership
# rules as a browser would experience them). Needs: bash, curl, php.
#
#   bash tests/e2e-http-tests.sh            (run from the lab7 folder)
#
# The script builds its OWN throw-away database in a temp folder (demo accounts, see README),
# starts its own PHP server on 127.0.0.1:${E2E_PORT:-8899} against it, runs the checks, and
# stops the server. database/tutoring.db is never opened. Exit status 0 = every check passed.

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="${E2E_PORT:-8899}"
TMP="$(mktemp -d)"
BASE="http://127.0.0.1:$PORT/php"
PASS=0
FAIL=0
SERVER_PID=""

cleanup() {
    [ -n "$SERVER_PID" ] && kill "$SERVER_PID" 2>/dev/null
    rm -rf "$TMP"
}
trap cleanup EXIT

# ----------------------------------------------------------------- helpers ----
ok()   { PASS=$((PASS + 1)); echo "PASS: $1"; }
bad()  { FAIL=$((FAIL + 1)); echo "FAIL: $1"; }
eq()   { if [ "$2" = "$3" ]; then ok "$1"; else bad "$1 (got '$2', expected '$3')"; fi; }
has()  { if grep -qiF -- "$2" "$TMP/body"; then ok "$1"; else bad "$1 (body lacks '$2')"; fi; }
hasnt(){ if grep -qiF -- "$2" "$TMP/body"; then bad "$1 (body unexpectedly has '$2')"; else ok "$1"; fi; }
hdr()  { if grep -qiF -- "$2" "$TMP/hdr"; then ok "$1"; else bad "$1 (headers lack '$2')"; fi; }
loc()  { if printf '%s' "$LOC" | grep -qF -- "$2"; then ok "$1"; else bad "$1 (Location '$LOC' lacks '$2')"; fi; }

# fetch JAR PATH [curl args]  -> sets CODE and LOC; writes $TMP/body and $TMP/hdr
fetch() {
    local jar="$1" path="$2"
    shift 2
    CODE=$(curl -s -o "$TMP/body" -D "$TMP/hdr" -b "$jar" -c "$jar" -w '%{http_code}' "$@" "$BASE/$path")
    LOC=$(grep -i '^location:' "$TMP/hdr" | tr -d '\r' | sed 's/^[Ll]ocation: *//')
}
# token JAR PATH -> prints the CSRF token of the page (same token for every form in a session)
token() {
    fetch "$1" "$2"
    grep -o 'name="csrf_token" value="[a-f0-9]*"' "$TMP/body" | head -1 | sed 's/.*value="//;s/"//'
}
# post JAR PATH TOKEN [curl args...]
post() {
    local jar="$1" path="$2" tok="$3"
    shift 3
    fetch "$jar" "$path" --data-urlencode "csrf_token=$tok" "$@"
}
# login JAR EMAIL PASSWORD
login() {
    local tok
    tok=$(token "$1" login.php)
    post "$1" login.php "$tok" --data-urlencode "email=$2" --data-urlencode "password=$3"
}
sid() { awk '$6 == "PHPSESSID" { print $7 }' "$1"; }
J() { echo "$TMP/jar_$1"; }

# ------------------------------------------------------------------- server ----
mkdir -p "$TMP/sess"
php "$ROOT/database/setup-database.php" --db="$TMP/e2e.db" --demo > "$TMP/setup.log" 2>&1 || { echo "FAIL: database setup"; cat "$TMP/setup.log"; exit 1; }
if curl -s -o /dev/null "http://127.0.0.1:$PORT/"; then
    echo "FAIL: port $PORT is already in use; stop the other server or set E2E_PORT=<free port>"
    exit 1
fi
# exec replaces the subshell with php, so $! is the PHP server's own PID and cleanup() really stops it.
(cd "$ROOT" && TUTORING_DB="$TMP/e2e.db" exec php -d session.save_path="$TMP/sess" -d display_errors=0 -d log_errors=1 -d error_log="$TMP/php-errors.log" -S "127.0.0.1:$PORT" php/router.php > "$TMP/server.log" 2>&1) &
SERVER_PID=$!
for i in $(seq 1 50); do
    curl -s -o /dev/null "http://127.0.0.1:$PORT/php/login.php" && break
    sleep 0.1
done
curl -s -o /dev/null "http://127.0.0.1:$PORT/php/login.php" || { echo "FAIL: server did not start"; cat "$TMP/server.log"; exit 1; }

FUTURE="$(date -d '+10 days' +%F 2>/dev/null || date -v+10d +%F)"
ANON="$(J anon)"; ADM="$(J adm)"; ALEX="$(J alex)"; MARIA="$(J maria)"; TUT="$(J tut)"; NEWU="$(J newu)"

echo "== E2E-01 Anonymous visitors cannot reach protected pages (direct URL, no menu needed)"
for p in profile.php edit-profile.php change-password.php users.php "user-details.php?user_id=1" "user-role.php?user_id=1" "user-status.php?user_id=1" "user-delete.php?user_id=1" requests.php request-form.php "request-details.php?request_id=1" "edit-request.php?request_id=1" "delete-request.php?request_id=1"; do
    fetch "$ANON" "$p"
    eq "anonymous GET $p -> 302" "$CODE" "302"
    loc "anonymous GET $p -> redirected to login" "login.php"
done
tok=$(token "$ANON" login.php)
fetch "$ANON" process-request.php --data-urlencode "csrf_token=$tok"
eq "anonymous POST process-request.php -> 302 login" "$CODE" "302"
eq "index.php -> 302 (profile, then login)" "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/index.php")" "302"

echo "== E2E-02 Router allow-list: internal files cannot be fetched or executed by URL"
for p in includes/auth.php config/db.php services/userService.php repositories/userPDO.php database/tutoring.db database/setup-database.sql database/setup-database.php tests/integration-tests.php README.md docs/TRACEABILITY.md php/router.php; do
    eq "GET /$p -> 404" "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/$p")" "404"
done
eq "path traversal -> 404" "$(curl -s --path-as-is -o /dev/null -w '%{http_code}' "http://127.0.0.1:$PORT/php/../database/tutoring.db")" "404"

echo "== E2E-03 Session cookie settings and login page"
fetch "$(J fresh)" login.php
eq "login page loads" "$CODE" "200"
hdr "session cookie is HttpOnly" "HttpOnly"
hdr "session cookie has SameSite=Lax" "SameSite=Lax"
hdr "X-Frame-Options sent" "X-Frame-Options: DENY"
has "login form has a CSRF field" 'name="csrf_token"'
FWD_HDR="$(curl -s -D - -o /dev/null -H 'X-Forwarded-Proto: https' "$BASE/login.php" | tr -d '\r')"
if printf '%s' "$FWD_HDR" | grep -i '^set-cookie' | grep -qi 'secure'; then ok "Secure flag is set behind an HTTPS proxy (X-Forwarded-Proto)"; else bad "Secure flag missing behind HTTPS proxy"; fi

echo "== E2E-04 CSRF protection on login and the generic failure message (UM-09)"
fetch "$ANON" login.php --data-urlencode "email=admin@example.edu" --data-urlencode "password=Admin123!"
eq "login POST without CSRF token -> 403" "$CODE" "403"
fetch "$ANON" login.php --data-urlencode "csrf_token=deadbeef" --data-urlencode "email=admin@example.edu" --data-urlencode "password=Admin123!"
eq "login POST with wrong CSRF token -> 403" "$CODE" "403"
login "$ANON" admin@example.edu 'wrong-password1'
eq "wrong password -> 401" "$CODE" "401"
has "wrong password: generic message" "Invalid email or password."
login "$ANON" nobody@example.edu 'Admin123!'
eq "unknown email -> 401" "$CODE" "401"
has "unknown email: same generic message" "Invalid email or password."
login "$ANON" inactive@example.edu 'Student123!'
eq "inactive account (correct password) -> 401" "$CODE" "401"
has "inactive account: same generic message" "Invalid email or password."
hasnt "no hint that the account is inactive" "is not active"

echo "== E2E-05 Successful login regenerates the session ID (UM-09, session fixation)"
ATT="attackerchosensessionid0123456789"
curl -s -o /dev/null -D "$TMP/hdr" -H "Cookie: PHPSESSID=$ATT" "$BASE/login.php"
if grep -qi '^set-cookie: PHPSESSID=' "$TMP/hdr" && ! grep -qi "PHPSESSID=$ATT" "$TMP/hdr"; then ok "server refuses an attacker-chosen session ID (strict mode)"; else bad "server accepted an attacker-chosen session ID"; fi
tok=$(token "$ADM" login.php)
BEFORE="$(sid "$ADM")"
post "$ADM" login.php "$tok" --data-urlencode "email=admin@example.edu" --data-urlencode "password=Admin123!"
AFTER="$(sid "$ADM")"
eq "admin login -> 303" "$CODE" "303"
loc "login redirects to the profile page" "profile.php"
if [ -n "$BEFORE" ] && [ -n "$AFTER" ] && [ "$BEFORE" != "$AFTER" ]; then ok "session ID changed at login"; else bad "session ID did not change at login"; fi
fetch "$ADM" profile.php
eq "profile after login -> 200" "$CODE" "200"
hdr "protected pages send Cache-Control: no-store" "no-store"
has "profile shows the account's role" "Administrator"
for who in "alex@example.edu Student123! $ALEX" "maria.lopez@example.com Student123! $MARIA" "tutor@example.edu Tutor123! $TUT"; do
    set -- $who
    login "$3" "$1" "$2"
    eq "login as $1 -> 303" "$CODE" "303"
done

echo "== E2E-06 UM-02 Only the signed-in user's profile is shown"
fetch "$ALEX" "profile.php?user_id=1"
has "profile?user_id=1 still shows Alex's own email" "alex@example.edu"
hasnt "...and never the administrator's" "admin@example.edu"
hasnt "profile never contains a password hash" '$2y$'

echo "== E2E-07 Role gating by direct URL (authorization, not just hidden menus)"
fetch "$ALEX" users.php;                          eq "Student GET users.php -> 403" "$CODE" "403"
fetch "$ALEX" "user-details.php?user_id=1";       eq "Student GET user-details.php -> 403" "$CODE" "403"
fetch "$ALEX" "user-role.php?user_id=2";          eq "Student GET user-role.php -> 403" "$CODE" "403"
fetch "$ALEX" "user-delete.php?user_id=2";        eq "Student GET user-delete.php -> 403" "$CODE" "403"
fetch "$TUT" users.php;                           eq "Tutor GET users.php -> 403" "$CODE" "403"
fetch "$TUT" requests.php;                        eq "Tutor GET requests.php -> 403" "$CODE" "403"
fetch "$TUT" request-form.php;                    eq "Tutor GET request-form.php -> 403" "$CODE" "403"
fetch "$TUT" profile.php;                         eq "Tutor GET own profile -> 200" "$CODE" "200"
has "tutor profile explains there is no request workflow" "no tutoring-request workflow"
fetch "$ADM" request-form.php;                    eq "Administrator GET request-form.php -> 403 (cannot submit)" "$CODE" "403"
tok=$(token "$ALEX" profile.php)
post "$ALEX" user-role.php "$tok" --data-urlencode "user_id=2" --data-urlencode "new_role=Administrator"
eq "Student forging POST user-role.php -> 403" "$CODE" "403"
post "$ALEX" user-status.php "$tok" --data-urlencode "user_id=1" --data-urlencode "new_status=Inactive"
eq "Student forging POST user-status.php -> 403" "$CODE" "403"
post "$ALEX" user-delete.php "$tok" --data-urlencode "user_id=1"
eq "Student forging POST user-delete.php -> 403" "$CODE" "403"

echo "== E2E-08 UM-03 Administrator view and search"
fetch "$ADM" users.php
eq "admin GET users.php -> 200" "$CODE" "200"
has "list shows Alex" "alex@example.edu"
has "list shows the tutor" "tutor@example.edu"
fetch "$ADM" "users.php?q=alex"
has "search 'alex' finds Alex" "alex@example.edu"
hasnt "search 'alex' excludes others" "tutor@example.edu"
fetch "$ADM" "users.php?role=Tutor"
has "role filter finds the tutor" "tutor@example.edu"
hasnt "role filter excludes students" "alex@example.edu"
fetch "$ADM" "users.php?status=Inactive"
has "status filter finds the inactive account" "inactive@example.edu"
fetch "$ADM" "users.php?q=zzz-no-match"
has "no matches: helpful message" "No matching users."
fetch "$ADM" "users.php?role=Root"
eq "invalid role filter -> 400" "$CODE" "400"
fetch "$ADM" "user-details.php?user_id=3"
eq "user details -> 200" "$CODE" "200"
has "details show the account" "maria.lopez@example.com"
hasnt "details never show a password hash" '$2y$'
fetch "$ADM" "user-details.php?user_id=9999"
eq "details for a missing account -> 404" "$CODE" "404"
fetch "$ADM" "user-details.php?user_id=abc"
eq "non-numeric user_id -> 400" "$CODE" "400"

echo "== E2E-09 UM-01 Registration"
tok=$(token "$NEWU" register.php)
has "registration page offers Student and Tutor only" 'value="Tutor"'
hasnt "registration page does not offer Administrator" 'value="Administrator"'
post "$NEWU" register.php "$tok" --data-urlencode "full_name=Nora New" --data-urlencode "email=nora@example.edu" --data-urlencode "password=Nora12345" --data-urlencode "confirm_password=Nora12345" --data-urlencode "role=Student"
eq "valid Student registration -> 303" "$CODE" "303"
loc "redirects to login with confirmation flag" "login.php?registered=1"
fetch "$ANON" "login.php?registered=1"
has "confirmation message shown" "Account created."
post "$NEWU" register.php "$tok" --data-urlencode "full_name=Nora Again" --data-urlencode "email=NORA@example.edu" --data-urlencode "password=Nora12345" --data-urlencode "confirm_password=Nora12345" --data-urlencode "role=Student"
eq "duplicate email (different case) -> 422" "$CODE" "422"
has "duplicate: correction message" "already exists"
post "$NEWU" register.php "$tok" --data-urlencode "full_name=Evil Admin" --data-urlencode "email=evil@example.edu" --data-urlencode "password=Evil12345" --data-urlencode "confirm_password=Evil12345" --data-urlencode "role=Administrator"
eq "tampered role=Administrator -> 403" "$CODE" "403"
has "denial message shown" "cannot be created through public registration"
login "$ANON" evil@example.edu 'Evil12345'
eq "no account was created for the tampered registration" "$CODE" "401"
post "$NEWU" register.php "$tok" --data-urlencode "full_name=Weak Pw" --data-urlencode "email=weak@example.edu" --data-urlencode "password=short" --data-urlencode "confirm_password=short" --data-urlencode "role=Tutor"
eq "weak password -> 422" "$CODE" "422"
has "weak password: rule explained" "Password must be 8 to 72 characters"
hasnt "passwords are never echoed back into the form" 'value="short"'
post "$NEWU" register.php "$tok" --data-urlencode "full_name=Mis Match" --data-urlencode "email=mis@example.edu" --data-urlencode "password=Match1234" --data-urlencode "confirm_password=Match9999" --data-urlencode "role=Tutor"
eq "mismatched confirmation -> 422" "$CODE" "422"
fetch "$NEWU" register.php --data-urlencode "full_name=No Token" --data-urlencode "email=notoken@example.edu" --data-urlencode "password=Token1234" --data-urlencode "confirm_password=Token1234" --data-urlencode "role=Student"
eq "registration POST without CSRF token -> 403" "$CODE" "403"
post "$NEWU" register.php "$tok" --data-urlencode "full_name=<script>alert(1)</script>" --data-urlencode "email=xss@example.edu" --data-urlencode "password=Xss123456" --data-urlencode "confirm_password=Xss123456" --data-urlencode "role=Tutor"
eq "name containing HTML is accepted as text" "$CODE" "303"
fetch "$ADM" "users.php?q=xss"
has "output encoding: name is escaped in the admin list" "&lt;script&gt;alert(1)&lt;/script&gt;"
hasnt "output encoding: no raw script tag" "<script>alert(1)"
login "$NEWU" nora@example.edu 'Nora12345'
eq "new Student can log in immediately (Active)" "$CODE" "303"

echo "== E2E-10 UM-04 Update own account"
tok=$(token "$NEWU" edit-profile.php)
has "edit form shows role as read only" "(read only)"
post "$NEWU" edit-profile.php "$tok" --data-urlencode "full_name=Nora Newer" --data-urlencode "email=nora.newer@example.edu"
eq "valid update -> 303" "$CODE" "303"
fetch "$NEWU" profile.php
has "profile shows the new name" "Nora Newer"
has "success message shown once" "Your profile was updated."
post "$NEWU" edit-profile.php "$tok" --data-urlencode "full_name=Nora Newer" --data-urlencode "email=alex@example.edu"
eq "email already used by someone else -> 422" "$CODE" "422"
post "$NEWU" edit-profile.php "$tok" --data-urlencode "full_name=Nora Admin" --data-urlencode "email=nora.newer@example.edu" --data-urlencode "role=Administrator"
eq "forged role field -> 403" "$CODE" "403"
fetch "$NEWU" profile.php
has "role is still Student" "Student (read only)"
hasnt "name was not changed by the denied request" "Nora Admin"
post "$NEWU" edit-profile.php "" --data-urlencode "full_name=x"
eq "profile update without CSRF token -> 403" "$CODE" "403"

echo "== E2E-11 UM-05 Change password"
tok=$(token "$NEWU" change-password.php)
post "$NEWU" change-password.php "$tok" --data-urlencode "current_password=WrongOld123" --data-urlencode "new_password=Brand9876" --data-urlencode "confirm_password=Brand9876"
eq "wrong current password -> 422" "$CODE" "422"
has "message says current password is incorrect" "Current password is incorrect."
post "$NEWU" change-password.php "$tok" --data-urlencode "current_password=Nora12345" --data-urlencode "new_password=Brand9876" --data-urlencode "confirm_password=Other9876"
eq "mismatched confirmation -> 422" "$CODE" "422"
SID_BEFORE="$(sid "$NEWU")"
post "$NEWU" change-password.php "$tok" --data-urlencode "current_password=Nora12345" --data-urlencode "new_password=Brand9876" --data-urlencode "confirm_password=Brand9876"
eq "valid change -> 303" "$CODE" "303"
if [ "$SID_BEFORE" != "$(sid "$NEWU")" ]; then ok "session ID regenerated after a password change"; else bad "session ID unchanged after password change"; fi
fetch "$NEWU" profile.php
eq "user stays signed in after changing password" "$CODE" "200"
login "$(J nora_old)" nora.newer@example.edu 'Nora12345'
eq "old password no longer works" "$CODE" "401"
login "$(J nora_new)" nora.newer@example.edu 'Brand9876'
eq "new password works" "$CODE" "303"

echo "== E2E-12 Tutoring request workflow: ownership and role rules"
tok=$(token "$ALEX" request-form.php)
has "request form shows the account name read-only" 'readonly'
has "request form shows the signed-in name" "Alex Morgan"
fetch "$ALEX" process-request.php
eq "GET process-request.php -> 405" "$CODE" "405"
fetch "$ALEX" process-request.php --data-urlencode "form_name=tutoring_request" --data-urlencode "course=IS 6465" --data-urlencode "reason=Please help me with PHP sessions." --data-urlencode "topics[]=Exam review" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Morning"
eq "request POST without CSRF token -> 403" "$CODE" "403"
post "$ALEX" process-request.php "$tok" --data-urlencode "form_name=tutoring_request" --data-urlencode "student_name=Maria Lopez" --data-urlencode "student_email=maria.lopez@example.com" --data-urlencode "course=IS 6465" --data-urlencode "reason=Please help me with PHP sessions." --data-urlencode "topics[]=Exam review" --data-urlencode "topics[]=Study plan" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Morning"
eq "Student submits a request -> 303" "$CODE" "303"
loc "redirects to the request details page" "request-details.php?request_id="
RID="$(printf '%s' "$LOC" | sed 's/.*request_id=//')"
fetch "$ALEX" "request-details.php?request_id=$RID"
eq "owner opens the request -> 200" "$CODE" "200"
has "request belongs to the signed-in student, not the spoofed name" "Alex Morgan"
hasnt "spoofed name from the form was ignored" "Maria Lopez"
has "status is Open" "Open"
post "$ALEX" process-request.php "$tok" --data-urlencode "form_name=tutoring_request" --data-urlencode "course=IS 6465" --data-urlencode "reason=short" --data-urlencode "topics[]=Exam review" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Morning"
eq "invalid request -> 422" "$CODE" "422"

tokM=$(token "$MARIA" profile.php)
fetch "$MARIA" "request-details.php?request_id=$RID"
eq "another student cannot open the request (404, not 403)" "$CODE" "404"
fetch "$MARIA" "edit-request.php?request_id=$RID"
eq "another student cannot open the edit form -> 404" "$CODE" "404"
post "$MARIA" update-request.php "$tokM" --data-urlencode "request_id=$RID" --data-urlencode "course=IS 6495" --data-urlencode "reason=Hijacked by another student." --data-urlencode "topics[]=Study plan" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Evening"
eq "another student cannot update it -> 404" "$CODE" "404"
post "$MARIA" delete-request.php "$tokM" --data-urlencode "request_id=$RID"
eq "another student cannot cancel it -> 404" "$CODE" "404"
fetch "$MARIA" requests.php
hasnt "another student's list does not contain it" "Alex Morgan"
fetch "$ALEX" requests.php
has "owner's list contains it" "Alex Morgan"
fetch "$TUT" "request-details.php?request_id=$RID"
eq "Tutor cannot open the request -> 403" "$CODE" "403"
fetch "$ADM" "request-details.php?request_id=$RID"
eq "Administrator can open any request -> 200" "$CODE" "200"
fetch "$ADM" requests.php
has "administrator list shows every student's requests" "Alex Morgan"

fetch "$ALEX" "edit-request.php?request_id=$RID"
eq "owner opens the edit form" "$CODE" "200"
post "$ALEX" update-request.php "$tok" --data-urlencode "request_id=$RID" --data-urlencode "course=IS 6495" --data-urlencode "reason=Updated reason for my request." --data-urlencode "topics[]=Practice problems" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Evening"
eq "owner edits the Open request -> 303" "$CODE" "303"
fetch "$ALEX" "request-details.php?request_id=$RID"
has "edit persisted" "Updated reason for my request."
fetch "$ALEX" "delete-request.php?request_id=$RID"
eq "GET delete-request.php only shows a confirmation" "$CODE" "200"
fetch "$ALEX" "request-details.php?request_id=$RID"
has "request still Open after the GET confirmation" "Open"
fetch "$ALEX" delete-request.php --data-urlencode "request_id=$RID"
eq "cancel POST without CSRF token -> 403" "$CODE" "403"
post "$ALEX" delete-request.php "$tok" --data-urlencode "request_id=$RID"
eq "owner cancels the request -> 303" "$CODE" "303"
fetch "$ALEX" "request-details.php?request_id=$RID"
has "request retained with status Cancelled" "Cancelled"
post "$ALEX" delete-request.php "$tok" --data-urlencode "request_id=$RID"
eq "cancelling a Cancelled request -> 409" "$CODE" "409"
fetch "$ALEX" "request-details.php?request_id=0"
eq "invalid request id -> 400" "$CODE" "400"

# A request for Maria so UM-08 can show the related-records block, and one for the admin edit test.
tokM=$(token "$MARIA" request-form.php)
post "$MARIA" process-request.php "$tokM" --data-urlencode "form_name=tutoring_request" --data-urlencode "course=IS 6465" --data-urlencode "reason=Maria needs help with SQL joins." --data-urlencode "topics[]=Exam review" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Afternoon"
MRID="$(printf '%s' "$LOC" | sed 's/.*request_id=//')"
tokA=$(token "$ADM" profile.php)
post "$ADM" update-request.php "$tokA" --data-urlencode "request_id=$MRID" --data-urlencode "course=IS 6465" --data-urlencode "reason=Edited by an administrator today." --data-urlencode "topics[]=Exam review" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Afternoon"
eq "Administrator edits another student's Open request -> 303" "$CODE" "303"
post "$ADM" process-request.php "$tokA" --data-urlencode "form_name=tutoring_request" --data-urlencode "course=IS 6465" --data-urlencode "reason=Admin trying to submit one." --data-urlencode "topics[]=Exam review" --data-urlencode "preferreddate=$FUTURE" --data-urlencode "preferredtime=Morning"
eq "Administrator cannot submit a request -> 403" "$CODE" "403"

echo "== E2E-13 UM-06 Update role"
tokA=$(token "$ADM" "user-role.php?user_id=5")
has "role form shows the current role" "Current role:"
fetch "$ADM" user-role.php --data-urlencode "user_id=5" --data-urlencode "new_role=Tutor"
eq "role change without CSRF token -> 403" "$CODE" "403"
post "$ADM" user-role.php "$tokA" --data-urlencode "user_id=5" --data-urlencode "new_role=Superuser"
eq "unknown role -> 422" "$CODE" "422"
post "$ADM" user-role.php "$tokA" --data-urlencode "user_id=5" --data-urlencode "new_role=Tutor"
eq "administrator changes Jordan Kim to Tutor -> 303" "$CODE" "303"
JORDAN="$(J jordan)"
login "$JORDAN" jordan.kim@example.edu 'Student123!'
fetch "$JORDAN" profile.php
has "role change is visible immediately" "Tutor (read only)"
fetch "$JORDAN" request-form.php
eq "...and the new role's access rules apply immediately (Tutor -> 403)" "$CODE" "403"
post "$ADM" user-role.php "$tokA" --data-urlencode "user_id=1" --data-urlencode "new_role=Student"
eq "the last active administrator cannot be demoted -> 409" "$CODE" "409"
has "409 explains why" "last active administrator"

echo "== E2E-14 UM-07 Activate / deactivate"
fetch "$ADM" "user-status.php?user_id=4"
eq "confirmation screen -> 200" "$CODE" "200"
has "confirmation shows the new status" "New status:"
fetch "$ALEX" profile.php
eq "Alex is signed in before deactivation" "$CODE" "200"
post "$ADM" user-status.php "$tokA" --data-urlencode "user_id=4" --data-urlencode "new_status=Inactive"
eq "administrator deactivates Alex -> 303" "$CODE" "303"
fetch "$ALEX" profile.php
eq "Alex's open session ends on the next request -> 302" "$CODE" "302"
loc "...redirected to login with the inactive notice" "login.php?inactive=1"
login "$ANON" alex@example.edu 'Student123!'
eq "deactivated user cannot log in -> 401" "$CODE" "401"
post "$ADM" user-status.php "$tokA" --data-urlencode "user_id=4" --data-urlencode "new_status=Inactive"
eq "Inactive -> Inactive is an invalid transition -> 422" "$CODE" "422"
post "$ADM" user-status.php "$tokA" --data-urlencode "user_id=1" --data-urlencode "new_status=Inactive"
eq "the last active administrator cannot be deactivated -> 409" "$CODE" "409"
post "$ADM" user-status.php "$tokA" --data-urlencode "user_id=4" --data-urlencode "new_status=Active"
eq "administrator reactivates Alex -> 303" "$CODE" "303"
login "$ALEX" alex@example.edu 'Student123!'
eq "reactivated user can log in again" "$CODE" "303"

echo "== E2E-15 UM-08 Delete account"
fetch "$ADM" "user-delete.php?user_id=3"
eq "warning screen for Maria -> 200" "$CODE" "200"
has "warning names the related requests" "Related tutoring requests: 1"
hasnt "no confirm button when deletion is blocked" "Confirm Delete"
post "$ADM" user-delete.php "$tokA" --data-urlencode "user_id=3"
eq "forced delete of an account with requests -> 409" "$CODE" "409"
has "block is explained" "related tutoring request"
fetch "$ADM" "user-details.php?user_id=3"
eq "Maria still exists" "$CODE" "200"
fetch "$ADM" "request-details.php?request_id=$MRID"
eq "Maria's request still exists" "$CODE" "200"
fetch "$ADM" "user-delete.php?user_id=6"
has "warning for an account with no requests offers Confirm Delete" "Confirm Delete"
fetch "$ADM" user-delete.php --data-urlencode "user_id=6"
eq "delete without CSRF token -> 403" "$CODE" "403"
post "$ADM" user-delete.php "$tokA" --data-urlencode "user_id=6"
eq "delete of an account with no requests -> 303" "$CODE" "303"
fetch "$ADM" "user-details.php?user_id=6"
eq "deleted account is gone -> 404" "$CODE" "404"
post "$ADM" user-delete.php "$tokA" --data-urlencode "user_id=1"
eq "the last active administrator cannot be deleted -> 409" "$CODE" "409"
fetch "$ADM" "user-delete.php?user_id=9999"
eq "delete screen for a missing account -> 404" "$CODE" "404"

echo "== E2E-16 Inactivity timeout (20 minutes) - simulated by ageing the server-side session"
SIDV="$(sid "$TUT")"
SF="$TMP/sess/sess_$SIDV"
if [ -f "$SF" ]; then
    fetch "$TUT" profile.php
    eq "tutor session works before ageing" "$CODE" "200"
    sed -i 's/last_activity|i:[0-9]*;/last_activity|i:1000;/' "$SF"
    fetch "$TUT" profile.php
    eq "session idle > 20 minutes -> 302" "$CODE" "302"
    loc "...redirected to login with the expired notice" "login.php?expired=1"
    fetch "$TUT" profile.php
    loc "the expired session is gone (plain login redirect)" "login.php"
else
    bad "could not find the session file to age ($SF)"
fi
fetch "$ANON" "login.php?expired=1"
has "expired notice text" "session has expired"

echo "== E2E-17 UM-10 Logout"
LOGIN2="$(J logout)"
login "$LOGIN2" maria.lopez@example.com 'Student123!'
OLDSID="$(sid "$LOGIN2")"
fetch "$LOGIN2" logout.php
eq "GET logout.php never logs out (redirect only)" "$CODE" "302"
fetch "$LOGIN2" profile.php
eq "...still signed in after a GET to logout.php" "$CODE" "200"
fetch "$LOGIN2" logout.php --data-urlencode "csrf_token=wrong"
eq "logout POST with a bad CSRF token -> 403" "$CODE" "403"
fetch "$LOGIN2" profile.php
eq "...still signed in after the blocked logout" "$CODE" "200"
tok=$(token "$LOGIN2" profile.php)
post "$LOGIN2" logout.php "$tok"
eq "logout POST with a valid token -> 303" "$CODE" "303"
loc "redirects to the login page with the logged-out notice" "login.php?logged_out=1"
hdr "session cookie is expired in the browser" "PHPSESSID=deleted"
fetch "$LOGIN2" profile.php
eq "protected page after logout -> 302 to login" "$CODE" "302"
loc "...to the login page" "login.php"
fetch "$LOGIN2" "login.php?logged_out=1"
has "logged-out notice shown" "You have been logged out."
curl -s -o /dev/null -D "$TMP/hdr" -H "Cookie: PHPSESSID=$OLDSID" "$BASE/profile.php"
CODE_REPLAY="$(head -1 "$TMP/hdr" | awk '{print $2}')"
eq "replaying the OLD session cookie after logout -> 302 (server-side session destroyed)" "$CODE_REPLAY" "302"
if [ ! -f "$TMP/sess/sess_$OLDSID" ]; then ok "server-side session file removed"; else bad "server-side session file still exists"; fi
fetch "$ANON" logout.php --data-urlencode "csrf_token=whatever"
eq "logout with no session (already expired) -> 303" "$CODE" "303"
loc "...to login with the expired notice" "login.php?expired=1"

echo "== E2E-18 Admin deleting their own account ends their session"
SOLO="$(J solo)"
# create a second administrator through the admin UI path (role change), then let admin #2 delete themselves
tokR=$(token "$ADM" "user-role.php?user_id=2")
post "$ADM" user-role.php "$tokR" --data-urlencode "user_id=2" --data-urlencode "new_role=Administrator"
eq "promote the tutor to Administrator -> 303" "$CODE" "303"
login "$SOLO" tutor@example.edu 'Tutor123!'
fetch "$SOLO" users.php
eq "new administrator can open Manage Users" "$CODE" "200"
tokS=$(token "$SOLO" profile.php)
post "$SOLO" user-delete.php "$tokS" --data-urlencode "user_id=2"
eq "administrator deletes their own account (another active admin exists) -> 303" "$CODE" "303"
loc "...and is sent to the login page" "login.php?deleted=1"
fetch "$SOLO" users.php
eq "their session no longer grants access" "$CODE" "302"

echo "== E2E-19 Server health"
if [ -s "$TMP/php-errors.log" ]; then bad "PHP errors were logged:"; sed 's/^/    /' "$TMP/php-errors.log" | head -20; else ok "no PHP warnings, notices or errors were logged during the run"; fi

echo
echo "Results: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
