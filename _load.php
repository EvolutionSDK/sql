<?php

namespace Evolution\SQL;
use Evolution\Kernel\Service;
use Evolution\Kernel\Configure;


Service::bind('Evolution\SQL\Bundle::build_architecture', 'bundles:loaded');
Configure::add('sql.connection', 'default', 'mysql://root@localhost/test');