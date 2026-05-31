<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class Stele
{
    /** @param list<SteleSection> $sections */
    public function __construct(
        private(set) string $preamble,
        private(set) array $sections,
    ) {
    }

    /** @return list<SteleEntry> */
    public function entries(): array
    {
        $all = [];
        foreach ($this->sections as $section) {
            foreach ($section->entries as $entry) {
                $all[] = $entry;
            }
        }
        return $all;
    }
}
