<?php

require __DIR__ . '/lib/bootstrap.php';
require __DIR__ . '/lib/migrations.php';

$verbose = !in_array('--quiet', $argv, true);
run_migrations(db(), $verbose);
