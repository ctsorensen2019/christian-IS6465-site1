<?php
// Runs every CLI test file and reports one overall result.
//   php tests/run-all.php
// (The HTTP end-to-end tests need a shell: bash tests/e2e-http-tests.sh)
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}
$files = array('user-management-tests.php', 'integration-tests.php', 'database-scripts-tests.php');
$worst = 0;
foreach ($files as $file) {
    echo PHP_EOL . '######## ' . $file . ' ########' . PHP_EOL;
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/' . $file), $status);
    $worst = max($worst, $status);
}
echo PHP_EOL . ($worst === 0 ? 'ALL CLI TEST FILES PASSED' : 'AT LEAST ONE CLI TEST FILE FAILED') . PHP_EOL;
exit($worst === 0 ? 0 : 1);
