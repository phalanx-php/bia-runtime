<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class SteleSection
{
    /** @param list<SteleEntry> $entries */
    public function __construct(
        private(set) SectionKind $kind,
        private(set) array $entries = [],
    ) {
    }
}
