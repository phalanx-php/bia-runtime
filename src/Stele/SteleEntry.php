<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class SteleEntry
{
    /**
     * @param list<string> $when
     * @param list<string> $aspects
     * @param list<StelePointer> $pointers
     * @param list<SteleRef> $refs
     */
    public function __construct(
        private(set) string $name,
        private(set) Epistemic $epistemic,
        private(set) array $when = [],
        private(set) array $aspects = [],
        private(set) array $pointers = [],
        private(set) array $refs = [],
        private(set) string $body = '',
    ) {
    }
}
