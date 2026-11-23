<?php
// Entry point. The profile page is the post-login landing page; it sends visitors who are
// not signed in on to the login page.
header('Location: php/profile.php', true, 302);
exit;
