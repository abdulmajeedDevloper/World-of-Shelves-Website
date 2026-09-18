<?php

require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/config/init.php';

define('BYPASS_ADMIN_AUTH', true);
require_once dirname(__DIR__) . '/config/admin_init.php';

admin_destroy_session();
