<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class StelePointer
{
    public function __construct(
        private(set) string $path,
    ) {
    }
}
