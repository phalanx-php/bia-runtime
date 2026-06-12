<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

final class EnvDemand
{
    public function __construct(
        private(set) string $key,
        private(set) string $type,
        private(set) string $source,
        private(set) bool $required,
    ) {
    }
}
