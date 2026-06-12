<?php

declare(strict_types=1);

/** @var \Phalanx\Bia\Runtime\EnvContext $context */
$context->string('SESSION_SIGNING_KEY');
$context->int('SURREAL_POOL_SIZE');
$context->bool('FEATURE_FLAG', false);

return [];
