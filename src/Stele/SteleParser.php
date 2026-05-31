<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class SteleParser
{
    public function parse(string $text): Stele
    {
        $lines = explode("\n", $text);
        $firstSection = null;

        foreach ($lines as $i => $line) {
            if (preg_match('/^## .+/', $line)) {
                $firstSection = $i;
                break;
            }
        }

        $preambleLines = $firstSection !== null
            ? array_slice($lines, 0, $firstSection)
            : $lines;

        $preamble = self::parsePreamble($preambleLines);

        if ($firstSection === null) {
            return new Stele($preamble, []);
        }

        $sectionLines = array_slice($lines, $firstSection);
        $chunks = self::splitAtPrefix('## ', $sectionLines);
        $sections = array_values(array_filter(array_map(
            static fn(array $chunk) => self::parseSection($chunk),
            $chunks,
        )));

        return new Stele($preamble, $sections);
    }

    /** @param list<string> $lines */
    private static function parsePreamble(array $lines): string
    {
        $kept = [];
        foreach ($lines as $line) {
            if (str_starts_with($line, '> ')) {
                $kept[] = substr($line, 2);
            } elseif ($line === '>') {
                $kept[] = '';
            }
        }
        return implode("\n", $kept);
    }

    /**
     * @param list<string> $lines
     * @return list<list<string>>
     */
    private static function splitAtPrefix(string $prefix, array $lines): array
    {
        $chunks = [];
        $current = null;

        foreach ($lines as $line) {
            if (str_starts_with($line, $prefix)) {
                if ($current !== null) {
                    $chunks[] = $current;
                }
                $current = [$line];
            } elseif ($current !== null) {
                $current[] = $line;
            }
        }

        if ($current !== null) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /** @param list<string> $lines */
    private static function parseSection(array $lines): ?SteleSection
    {
        if ($lines === []) {
            return null;
        }

        $heading = $lines[0];

        if (!preg_match('/^## (.+)\s*$/', $heading, $m)) {
            return null;
        }

        $kind = SectionKind::tryFrom(trim($m[1]));

        if ($kind === null) {
            return null;
        }

        $entryChunks = self::splitAtPrefix('### ', array_slice($lines, 1));
        $entries = array_values(array_filter(array_map(
            static fn(array $chunk) => self::parseEntry($chunk),
            $entryChunks,
        )));

        return new SteleSection($kind, $entries);
    }

    /** @param list<string> $lines */
    private static function parseEntry(array $lines): ?SteleEntry
    {
        if ($lines === []) {
            return null;
        }

        $heading = $lines[0];

        if (!preg_match('/^### (.+?)\s+@([!~_])\s*$/', $heading, $m)) {
            return null;
        }

        $name = $m[1];
        $epistemic = Epistemic::from($m[2]);

        $rest = array_slice($lines, 1);

        $metaLines = [];
        $afterMeta = [];
        $inMeta = true;

        foreach ($rest as $line) {
            if ($inMeta && str_starts_with($line, '> ')) {
                $metaLines[] = substr($line, 2);
            } elseif ($inMeta && $line === '>') {
                $metaLines[] = '';
            } else {
                $inMeta = false;
                $afterMeta[] = $line;
            }
        }

        $bodyLines = [];
        $pastBlanks = false;

        foreach ($afterMeta as $line) {
            if (!$pastBlanks && $line === '') {
                continue;
            }
            $pastBlanks = true;
            $bodyLines[] = $line;
        }

        $meta = self::consolidateMeta($metaLines);

        return new SteleEntry(
            name: $name,
            epistemic: $epistemic,
            when: $meta['when'],
            aspects: $meta['aspects'],
            pointers: $meta['pointers'],
            refs: $meta['refs'],
            body: trim(implode("\n", $bodyLines)),
        );
    }

    /**
     * @param list<string> $lines
     * @return array{when: list<string>, aspects: list<string>, pointers: list<StelePointer>, refs: list<SteleRef>}
     */
    private static function consolidateMeta(array $lines): array
    {
        $when = [];
        $aspects = [];
        $pointers = [];

        /** @var list<array{base: string, subs: list<string>}> */
        $refBuilders = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if (str_starts_with($trimmed, 'when: ')) {
                foreach (explode(',', substr($trimmed, 6)) as $t) {
                    $t = trim($t);
                    if ($t !== '') {
                        $when[] = $t;
                    }
                }
                continue;
            }

            if (str_starts_with($trimmed, '<- ')) {
                $pointers[] = new StelePointer(path: trim(substr($trimmed, 3)));
                continue;
            }

            if (str_starts_with($trimmed, 'ref: ')) {
                $refBuilders[] = ['base' => trim(substr($trimmed, 5)), 'subs' => []];
                continue;
            }

            if (preg_match('/^\s+\//', $line) && $refBuilders !== []) {
                $refBuilders[count($refBuilders) - 1]['subs'][] = trim($trimmed);
                continue;
            }

            if (str_starts_with($trimmed, '.')) {
                if (preg_match_all('/\.([a-z][\w-]*)/', $trimmed, $matches)) {
                    foreach ($matches[1] as $aspect) {
                        $aspects[] = $aspect;
                    }
                }
            }
        }

        $refs = array_map(
            static fn(array $r) => new SteleRef(base: $r['base'], subs: $r['subs']),
            $refBuilders,
        );

        return compact('when', 'aspects', 'pointers', 'refs');
    }
}
