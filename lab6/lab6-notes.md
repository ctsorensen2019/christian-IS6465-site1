**Part 1: Set up**
 php -r '$p=new PDO("sqlite:database/tutoring.db"); $p->exec(file_get_contents("database/setup-database.sql")); foreach($p->query("SELECT name FROM sqlite_master WHERE type=\"table\" AND name NOT LIKE \"sqlite_%\"") as $r) echo $r["name"],"\n";'
    -Had to use AI to generate this command because sqlite3 was refusing to download the way you showed us last week. I could confirm the tables both appeared with this: php -r '$p=new PDO("sqlite:database/tutoring.db"); foreach(["students","tutoring_requests"] as $t){echo "== $t ==\n"; foreach($p->query("SELECT * FROM $t", PDO::FETCH_ASSOC) as $r) echo implode(" | ",$r),"\n";}'
        >Results: == students ==
            1 | Maria Lopez | maria.lopez@example.com
            == tutoring_requests ==
            1 | 1 | IS 6465 | PHP arrays and forms | 2026-11-03 | 14:00 | Open | 2026-11-16 04:27:59

**Part 2: Run app**
-The URL you opened in the browser: https://super-duper-space-broccoli-rj6rw95gr6jc5rx7-8000.app.github.dev/php/request-form.php
-A short statement confirming that your request was saved and displayed: The request was saved and displayed as expected.
-A screenshot of the request detail page: In the screenshots folder of the repo titled: "part2.png"

**Part 2: Run app**

**Part 2: Run app**

**Part 2: Run app**

**Part 2: Run app**

**Part 2: Run app**