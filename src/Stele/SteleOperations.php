<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class SteleOperations

{
    /** @return list<LintIssue> */
    public static function lint(Stele $stele, PointerResolver $resolver): array
    {
        $issues = [];

        foreach ($stele->entries() as $entry) {
            if ($entry->when === []) {
                $issues[] = new LintIssue($entry->name, 'missing-when');
            }

            if ($entry->aspects === []) {
                $issues[] = new LintIssue($entry->name, 'missing-aspects');
            }

            foreach ($entry->pointers as $ptr) {
                $resolved = $resolver->resolve($ptr->path);

                if ($resolved === null) {
                    $issues[] = new LintIssue($entry->name, 'unresolvable-pointer', $ptr->path);
                    continue;
                }

                if (!file_exists($resolved)) {
                    $issues[] = new LintIssue($entry->name, 'broken-pointer', $ptr->path, $resolved);
                }
            }
        }

        return $issues;
    }

    /**
     * @return list<array{entry: string, current: list<string>, proposed: list<string>|null, changed: bool}>
     */
    public static function sync(Stele $stele, PointerResolver $resolver): array
    {
        $results = [];

        foreach ($stele->entries() as $entry) {
            $targets = [];

            foreach ($entry->pointers as $ptr) {
                $resolved = $resolver->resolve($ptr->path);

                if ($resolved === null || !is_file($resolved)) {
                    continue;
                }

                $targets[] = $resolved;
            }

            $headings = [];
            foreach ($targets as $target) {
                foreach (self::extractHeadings($target) as $h) {
                    $headings[] = $h;
                }
            }

            $proposed = array_map(static fn(string $h) => self::headingToAspect($h), $headings);
            $proposed = array_values(array_unique($proposed));

            $results[] = [
                'entry' => $entry->name,
                'current' => $entry->aspects,
                'proposed' => $proposed !== [] ? $proposed : null,
                'changed' => $proposed !== [] && self::setsDiffer($entry->aspects, $proposed),
            ];
        }

        return $results;
    }

    /**
     * @return list<array{entry: string, epistemic: Epistemic, daysOld: int, path: string}>
     */
    public static function stale(Stele $stele, PointerResolver $resolver, int $days): array
    {
        $thresholdSeconds = $days * 86400;
        $now = time();
        $results = [];

        foreach ($stele->entries() as $entry) {
            foreach ($entry->pointers as $ptr) {
                $resolved = $resolver->resolve($ptr->path);

                if ($resolved === null || !is_file($resolved)) {
                    continue;
                }

                $mtime = filemtime($resolved);

                if ($mtime === false) {
                    continue;
                }

                $age = $now - $mtime;

                if ($age > $thresholdSeconds) {
                    $results[] = [
                        'entry' => $entry->name,
                        'epistemic' => $entry->epistemic,
                        'daysOld' => (int) ($age / 86400),
                        'path' => $ptr->path,
                    ];
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * @return array{
     *     totalEntries: int,
     *     bySection: array<string, int>,
     *     byEpistemic: array<string, int>,
     *     totalPointers: int,
     *     totalAspects: int,
     *     uniqueAspects: int,
     * }
     */
    public static function stats(Stele $stele): array
    {
        $bySection = [];
        $byEpistemic = [];
        $totalPointers = 0;
        $allAspects = [];

        foreach ($stele->sections as $section) {
            $kind = $section->kind->name;
            $bySection[$kind] = ($bySection[$kind] ?? 0) + count($section->entries);
        }

        foreach ($stele->entries() as $entry) {
            $ep = $entry->epistemic->sigil();
            $byEpistemic[$ep] = ($byEpistemic[$ep] ?? 0) + 1;
            $totalPointers += count($entry->pointers);

            foreach ($entry->aspects as $a) {
                $allAspects[] = $a;
            }
        }

        ksort($bySection);
        ksort($byEpistemic);

        return [
            'totalEntries' => count($stele->entries()),
            'bySection' => $bySection,
            'byEpistemic' => $byEpistemic,
            'totalPointers' => $totalPointers,
            'totalAspects' => count($allAspects),
            'uniqueAspects' => count(array_unique($allAspects)),
        ];
    }

    /** @return list<string> */
    private static function extractHeadings(string $path): array
    {
        $content = file_get_contents($path);

        if ($content === false) {
            return [];
        }

        $headings = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^#{2,}\s+(.+)/', $line, $m)) {
                $headings[] = trim($m[1]);
            }
        }

        return $headings;
    }

    private static function headingToAspect(string $heading): string
    {
        $slug = strtolower($heading);
        $slug = trim(preg_replace('/[^a-z0-9\s-]/', '', $slug) ?? '');

        return preg_replace('/\s+/', '-', $slug) ?? $slug;
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     */
    private static function setsDiffer(array $a, array $b): bool
    {
        $sa = array_unique($a);
        $sb = array_unique($b);
        sort($sa);
        sort($sb);

        return $sa !== $sb;
    }
}
