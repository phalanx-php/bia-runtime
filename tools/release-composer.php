<?php

declare(strict_types=1);

require_once __DIR__ . '/ReleaseComposer.php';

$release = new BiaRuntimeReleaseComposer(dirname(__DIR__));
fwrite(STDOUT, $release->encode($release->publishComposer()));
