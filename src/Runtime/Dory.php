<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Phalanx\Boot\AppContext;

class Dory
{
    /** @param array<string, mixed> $context */
    public static function starting(array $context = []): DoryBuilder
    {
        return new DoryBuilder(new AppContext($context));
    }
}
