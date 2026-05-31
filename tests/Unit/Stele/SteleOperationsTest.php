<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\Epistemic;
use Phalanx\Dory\Stele\LintIssue;
use Phalanx\Dory\Stele\PointerResolver;
use Phalanx\Dory\Stele\Stele;
use Phalanx\Dory\Stele\SteleEntry;
use Phalanx\Dory\Stele\SteleOperations;
use Phalanx\Dory\Stele\SteleParser;
use Phalanx\Dory\Stele\StelePointer;
use Phalanx\Dory\Stele\SteleSection;
use Phalanx\Dory\Stele\SectionKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SteleOperationsTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/stele-ops-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        self::removeDir($this->tmpDir);
    }

    // ── Lint ────────────────────────────────────────────────────────

    #[Test]
    public function lint_clean_entry_produces_no_issues(): void
    {
        $file = $this->tmpDir . '/src/Config.php';
        mkdir(dirname($file), 0755, true);
        file_put_contents($file, '<?php // config');

        $stele = $this->buildStele([
            $this->buildEntry('Config injection', when: ['always'], aspects: ['config'], pointers: ['src/Config.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertSame([], $issues);
    }

    #[Test]
    public function lint_detects_missing_when(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('No triggers', when: [], aspects: ['arch']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(1, $issues);
        self::assertSame('missing-when', $issues[0]->issue);
        self::assertSame('No triggers', $issues[0]->entry);
    }

    #[Test]
    public function lint_detects_missing_aspects(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('No aspects', when: ['always'], aspects: []),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(1, $issues);
        self::assertSame('missing-aspects', $issues[0]->issue);
    }

    #[Test]
    public function lint_detects_both_missing_when_and_aspects(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('Empty meta', when: [], aspects: []),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(2, $issues);
        $issueTypes = array_map(static fn(LintIssue $i) => $i->issue, $issues);
        self::assertContains('missing-when', $issueTypes);
        self::assertContains('missing-aspects', $issueTypes);
    }

    #[Test]
    public function lint_detects_broken_pointer_to_nonexistent_file(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry(
                'Bad pointer',
                when: ['always'],
                aspects: ['arch'],
                pointers: ['src/DoesNotExist.php'],
            ),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(1, $issues);
        self::assertSame('broken-pointer', $issues[0]->issue);
        self::assertSame('src/DoesNotExist.php', $issues[0]->path);
    }

    #[Test]
    public function lint_detects_unresolvable_pointer(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry(
                'Vault pointer',
                when: ['always'],
                aspects: ['arch'],
                pointers: ['.aimind/knowledge.md'],
            ),
        ]);

        // No vault root means .aimind/ pointers can't resolve
        $resolver = new PointerResolver($this->tmpDir, vaultRoot: null);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(1, $issues);
        self::assertSame('unresolvable-pointer', $issues[0]->issue);
        self::assertSame('.aimind/knowledge.md', $issues[0]->path);
    }

    #[Test]
    public function lint_accumulates_issues_across_entries(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('Entry A', when: [], aspects: ['a']),
            $this->buildEntry('Entry B', when: ['always'], aspects: []),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $issues = SteleOperations::lint($stele, $resolver);

        self::assertCount(2, $issues);
        self::assertSame('Entry A', $issues[0]->entry);
        self::assertSame('Entry B', $issues[1]->entry);
    }

    // ── Stats ───────────────────────────────────────────────────────

    #[Test]
    public function stats_counts_empty_stele(): void
    {
        $stele = new Stele('', []);

        $stats = SteleOperations::stats($stele);

        self::assertSame(0, $stats['totalEntries']);
        self::assertSame([], $stats['bySection']);
        self::assertSame([], $stats['byEpistemic']);
        self::assertSame(0, $stats['totalPointers']);
        self::assertSame(0, $stats['totalAspects']);
        self::assertSame(0, $stats['uniqueAspects']);
    }

    #[Test]
    public function stats_counts_entries_by_section(): void
    {
        $stele = new Stele('', [
            new SteleSection(SectionKind::Constraint, [
                $this->buildEntry('A'),
                $this->buildEntry('B'),
            ]),
            new SteleSection(SectionKind::Intent, [
                $this->buildEntry('C'),
            ]),
        ]);

        $stats = SteleOperations::stats($stele);

        self::assertSame(3, $stats['totalEntries']);
        self::assertSame(['Constraint' => 2, 'Intent' => 1], $stats['bySection']);
    }

    #[Test]
    public function stats_counts_entries_by_epistemic(): void
    {
        $stele = new Stele('', [
            new SteleSection(SectionKind::Constraint, [
                $this->buildEntry('A', epistemic: Epistemic::Certain),
                $this->buildEntry('B', epistemic: Epistemic::Certain),
                $this->buildEntry('C', epistemic: Epistemic::Approximate),
            ]),
        ]);

        $stats = SteleOperations::stats($stele);

        self::assertSame(['@!' => 2, '@~' => 1], $stats['byEpistemic']);
    }

    #[Test]
    public function stats_counts_pointers(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('A', pointers: ['file1.php', 'file2.php']),
            $this->buildEntry('B', pointers: ['file3.php']),
        ]);

        $stats = SteleOperations::stats($stele);

        self::assertSame(3, $stats['totalPointers']);
    }

    #[Test]
    public function stats_counts_total_and_unique_aspects(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('A', aspects: ['config', 'boot']),
            $this->buildEntry('B', aspects: ['config', 'async']),
        ]);

        $stats = SteleOperations::stats($stele);

        self::assertSame(4, $stats['totalAspects']);
        self::assertSame(3, $stats['uniqueAspects']);
    }

    // ── Stale ───────────────────────────────────────────────────────

    #[Test]
    public function stale_detects_old_files(): void
    {
        $file = $this->tmpDir . '/old-file.php';
        file_put_contents($file, '<?php');
        touch($file, time() - (100 * 86400)); // 100 days ago

        $stele = $this->buildStele([
            $this->buildEntry('Old entry', pointers: ['old-file.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::stale($stele, $resolver, days: 30);

        self::assertCount(1, $results);
        self::assertSame('Old entry', $results[0]['entry']);
        self::assertGreaterThanOrEqual(99, $results[0]['daysOld']);
        self::assertSame('old-file.php', $results[0]['path']);
    }

    #[Test]
    public function stale_ignores_recent_files(): void
    {
        $file = $this->tmpDir . '/fresh.php';
        file_put_contents($file, '<?php');
        // Default mtime is now, which is fresh

        $stele = $this->buildStele([
            $this->buildEntry('Fresh entry', pointers: ['fresh.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::stale($stele, $resolver, days: 30);

        self::assertSame([], $results);
    }

    #[Test]
    public function stale_reports_epistemic(): void
    {
        $file = $this->tmpDir . '/stale.php';
        file_put_contents($file, '<?php');
        touch($file, time() - (60 * 86400));

        $stele = $this->buildStele([
            $this->buildEntry('Stale thing', epistemic: Epistemic::Stale, pointers: ['stale.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::stale($stele, $resolver, days: 30);

        self::assertCount(1, $results);
        self::assertSame(Epistemic::Stale, $results[0]['epistemic']);
    }

    #[Test]
    public function stale_skips_nonexistent_pointer_targets(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('Ghost', pointers: ['ghost.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::stale($stele, $resolver, days: 1);

        self::assertSame([], $results);
    }

    #[Test]
    public function stale_breaks_after_first_stale_pointer_per_entry(): void
    {
        $old1 = $this->tmpDir . '/old1.php';
        $old2 = $this->tmpDir . '/old2.php';
        file_put_contents($old1, '<?php');
        file_put_contents($old2, '<?php');
        touch($old1, time() - (50 * 86400));
        touch($old2, time() - (50 * 86400));

        $stele = $this->buildStele([
            $this->buildEntry('Multi-pointer', pointers: ['old1.php', 'old2.php']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::stale($stele, $resolver, days: 30);

        // Only one result per entry, even with two stale pointers
        self::assertCount(1, $results);
        self::assertSame('old1.php', $results[0]['path']);
    }

    // ── Sync ────────────────────────────────────────────────────────

    #[Test]
    public function sync_proposes_aspects_from_target_headings(): void
    {
        $file = $this->tmpDir . '/guide.md';
        file_put_contents($file, <<<'MD'
        # Title
        ## Boot Sequence
        ## Event Loop
        ### Fiber Model
        MD);

        $stele = $this->buildStele([
            $this->buildEntry('Runtime guide', aspects: ['old-aspect'], pointers: ['guide.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        self::assertCount(1, $results);
        self::assertSame('Runtime guide', $results[0]['entry']);
        self::assertSame(['old-aspect'], $results[0]['current']);
        self::assertNotNull($results[0]['proposed']);
        self::assertContains('boot-sequence', $results[0]['proposed']);
        self::assertContains('event-loop', $results[0]['proposed']);
        self::assertContains('fiber-model', $results[0]['proposed']);
        self::assertTrue($results[0]['changed']);
    }

    #[Test]
    public function sync_reports_no_change_when_aspects_match(): void
    {
        $file = $this->tmpDir . '/guide.md';
        file_put_contents($file, <<<'MD'
        ## Alpha
        ## Beta
        MD);

        $stele = $this->buildStele([
            $this->buildEntry('Matched', aspects: ['alpha', 'beta'], pointers: ['guide.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        self::assertCount(1, $results);
        self::assertFalse($results[0]['changed']);
    }

    #[Test]
    public function sync_returns_null_proposed_when_no_headings_found(): void
    {
        $file = $this->tmpDir . '/empty.md';
        file_put_contents($file, "Plain text, no headings.\n");

        $stele = $this->buildStele([
            $this->buildEntry('No headings', aspects: ['something'], pointers: ['empty.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        self::assertCount(1, $results);
        self::assertNull($results[0]['proposed']);
        self::assertFalse($results[0]['changed']);
    }

    #[Test]
    public function sync_skips_nonexistent_pointer_targets(): void
    {
        $stele = $this->buildStele([
            $this->buildEntry('Missing target', pointers: ['nope.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        self::assertCount(1, $results);
        self::assertNull($results[0]['proposed']);
        self::assertFalse($results[0]['changed']);
    }

    #[Test]
    public function sync_deduplicates_proposed_aspects(): void
    {
        $file = $this->tmpDir . '/dup.md';
        file_put_contents($file, <<<'MD'
        ## Config
        ## Config
        ## Boot
        MD);

        $stele = $this->buildStele([
            $this->buildEntry('Duped', aspects: [], pointers: ['dup.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        self::assertCount(1, $results);
        self::assertSame(['config', 'boot'], $results[0]['proposed']);
    }

    #[Test]
    public function sync_slugifies_headings_correctly(): void
    {
        $file = $this->tmpDir . '/slugs.md';
        file_put_contents($file, <<<'MD'
        ## Boot Sequence!
        ## Event-Loop (v2)
        ## UPPERCASE Heading
        MD);

        $stele = $this->buildStele([
            $this->buildEntry('Slugs', aspects: [], pointers: ['slugs.md']),
        ]);

        $resolver = new PointerResolver($this->tmpDir);
        $results = SteleOperations::sync($stele, $resolver);

        $proposed = $results[0]['proposed'];
        self::assertContains('boot-sequence', $proposed);
        self::assertContains('event-loop-v2', $proposed);
        self::assertContains('uppercase-heading', $proposed);
    }

    // ── LintIssue format ────────────────────────────────────────────

    #[Test]
    public function lint_issue_format_basic(): void
    {
        $issue = new LintIssue('Entry Name', 'missing-when');

        self::assertSame('  Entry Name -> missing-when', $issue->format());
    }

    #[Test]
    public function lint_issue_format_with_path(): void
    {
        $issue = new LintIssue('Entry', 'broken-pointer', path: 'src/File.php');

        self::assertSame('  Entry -> broken-pointer (src/File.php)', $issue->format());
    }

    #[Test]
    public function lint_issue_format_with_path_and_resolved(): void
    {
        $issue = new LintIssue('Entry', 'broken-pointer', path: 'src/File.php', resolved: '/abs/src/File.php');

        self::assertSame('  Entry -> broken-pointer (src/File.php) [resolved: /abs/src/File.php]', $issue->format());
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function buildEntry(
        string $name,
        Epistemic $epistemic = Epistemic::Certain,
        array $when = [],
        array $aspects = [],
        array $pointers = [],
        string $body = '',
    ): SteleEntry {
        return new SteleEntry(
            name: $name,
            epistemic: $epistemic,
            when: $when,
            aspects: $aspects,
            pointers: array_map(static fn(string $p) => new StelePointer(path: $p), $pointers),
            body: $body,
        );
    }

    private function buildStele(array $entries): Stele
    {
        return new Stele('', [
            new SteleSection(SectionKind::Constraint, $entries),
        ]);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
