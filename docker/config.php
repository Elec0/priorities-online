<?php
// Docker local dev config — do not deploy to production.
define('DB_HOST', 'db');
define('DB_NAME', 'priorities');
define('DB_USER', 'priorities-game');
define('DB_PASS', 'TestingDBPassword');
define('DEV_MULTI_SESSION', true);
