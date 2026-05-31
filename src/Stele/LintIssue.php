<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class LintIssue
{
    public function __construct(
        private(set) string $entry,
        private(set) string $issue,
        private(set) ?string $path = null,
        private(set) ?string $resolved = null,
    ) {
    }

    public function format(): string
    {
        $out = "  {$this->entry} -> {$this->issue}";

        if ($this->path !== null) {
            $out .= " ({$this->path})";
        }

        if ($this->resolved !== null) {
            $out .= " [resolved: {$this->resolved}]";
        }

        return $out;
    }
}
