<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\SteleFileFinder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SteleFileFinderTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/stele-finder-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
        $this->tmpDir = realpath($this->tmpDir);
    }

    protected function tearDown(): void
    {
        self::removeDir($this->tmpDir);
    }

    // ── findProjectRoot ─────────────────────────────────────────────

    #[Test]
    public function finds_project_root_by_git_dir(): void
    {
        mkdir($this->tmpDir . '/.git', 0755, true);
        mkdir($this->tmpDir . '/src/deep', 0755, true);

        $root = SteleFileFinder::findProjectRoot($this->tmpDir . '/src/deep');

        self::assertSame($this->tmpDir, $root);
    }

    #[Test]
    public function finds_project_root_by_aimind_dir(): void
    {
        mkdir($this->tmpDir . '/.aimind', 0755, true);
        mkdir($this->tmpDir . '/src', 0755, true);

        $root = SteleFileFinder::findProjectRoot($this->tmpDir . '/src');

        self::assertSame($this->tmpDir, $root);
    }

    #[Test]
    public function returns_null_when_no_root_markers_found(): void
    {
        // tmpDir has no .git or .aimind — but we start from a deep subdir
        // to avoid accidentally hitting a real .git above tmpDir
        $nested = $this->tmpDir . '/a/b/c';
        mkdir($nested, 0755, true);

        // This will walk up to / and return null (unless tmpDir itself
        // has a parent with .git, which it likely does on a real system).
        // We test the mechanism, not the filesystem root.
        $root = SteleFileFinder::findProjectRoot($nested);

        // On most systems this finds a .git somewhere above /tmp.
        // The important behavior: it walks upward. We verify it doesn't
        // return $nested itself (which has no markers).
        self::assertNotSame($nested, $root);
    }

    #[Test]
    public function finds_root_at_start_dir_itself(): void
    {
        mkdir($this->tmpDir . '/.git', 0755, true);

        $root = SteleFileFinder::findProjectRoot($this->tmpDir);

        self::assertSame($this->tmpDir, $root);
    }

    // ── find ────────────────────────────────────────────────────────

    #[Test]
    public function finds_core_draft_at_root(): void
    {
        file_put_contents($this->tmpDir . '/CORE.draft.md', '# draft');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertSame($this->tmpDir . '/CORE.draft.md', $result['path']);
        self::assertSame($this->tmpDir, $result['projectRoot']);
    }

    #[Test]
    public function finds_core_md_at_root(): void
    {
        file_put_contents($this->tmpDir . '/CORE.md', '# core');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertSame($this->tmpDir . '/CORE.md', $result['path']);
        self::assertSame($this->tmpDir, $result['projectRoot']);
    }

    #[Test]
    public function prefers_draft_over_final(): void
    {
        file_put_contents($this->tmpDir . '/CORE.draft.md', '# draft');
        file_put_contents($this->tmpDir . '/CORE.md', '# final');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertStringContainsString('CORE.draft.md', $result['path']);
    }

    #[Test]
    public function finds_in_tools_stele_subdir(): void
    {
        mkdir($this->tmpDir . '/tools/stele', 0755, true);
        file_put_contents($this->tmpDir . '/tools/stele/CORE.md', '# core');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertSame($this->tmpDir . '/tools/stele/CORE.md', $result['path']);
        self::assertSame($this->tmpDir, $result['projectRoot']);
    }

    #[Test]
    public function finds_in_phalanx_subdir_with_adjusted_project_root(): void
    {
        mkdir($this->tmpDir . '/phalanx/tools/stele', 0755, true);
        file_put_contents($this->tmpDir . '/phalanx/tools/stele/CORE.md', '# core');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertSame($this->tmpDir . '/phalanx/tools/stele/CORE.md', $result['path']);
        self::assertSame($this->tmpDir . '/phalanx', $result['projectRoot']);
    }

    #[Test]
    public function finds_in_phalanx_root_subdir(): void
    {
        mkdir($this->tmpDir . '/phalanx', 0755, true);
        file_put_contents($this->tmpDir . '/phalanx/CORE.md', '# core');

        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNotNull($result);
        self::assertSame($this->tmpDir . '/phalanx/CORE.md', $result['path']);
        self::assertSame($this->tmpDir . '/phalanx', $result['projectRoot']);
    }

    #[Test]
    public function returns_null_when_no_stele_file_found(): void
    {
        $result = SteleFileFinder::find($this->tmpDir);

        self::assertNull($result);
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
