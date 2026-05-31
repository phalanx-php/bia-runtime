<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

class SteleRef
{
    /** @param list<string> $subs */
    public function __construct(
        private(set) string $base,
        private(set) array $subs = [],
    ) {
    }
}
