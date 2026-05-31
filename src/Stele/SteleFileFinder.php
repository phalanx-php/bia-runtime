<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class SteleFileFinder
{
    private const array SEARCH_PATHS = [
        'CORE.draft.md',
        'CORE.md',
        'tools/stele/CORE.draft.md',
        'tools/stele/CORE.md',
        'phalanx/tools/stele/CORE.draft.md',
        'phalanx/tools/stele/CORE.md',
        'phalanx/CORE.draft.md',
        'phalanx/CORE.md',
    ];

    public static function findProjectRoot(string $startDir): ?string
    {
        $dir = realpath($startDir) ?: $startDir;

        while (true) {
            if (is_dir($dir . '/.aimind') || is_dir($dir . '/.git')) {
                return $dir;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }
    }

    /** @return array{path: string, projectRoot: string}|null */
    public static function find(string $searchRoot): ?array
    {
        foreach (self::SEARCH_PATHS as $candidate) {
            $path = $searchRoot . '/' . $candidate;

            if (file_exists($path)) {
                $projectRoot = str_contains($candidate, 'phalanx/')
                    ? $searchRoot . '/phalanx'
                    : $searchRoot;

                return ['path' => $path, 'projectRoot' => $projectRoot];
            }
        }

        return null;
    }
}
