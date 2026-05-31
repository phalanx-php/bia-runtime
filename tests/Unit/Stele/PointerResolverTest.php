<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\PointerResolver;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PointerResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/stele-ptr-' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
        $this->tmpDir = realpath($this->tmpDir);
    }

    protected function tearDown(): void
    {
        self::removeDir($this->tmpDir);
    }

    // ── Project-relative paths ──────────────────────────────────────

    #[Test]
    public function resolves_plain_path_relative_to_project_root(): void
    {
        $resolver = new PointerResolver($this->tmpDir);

        self::assertSame($this->tmpDir . '/src/Config.php', $resolver->resolve('src/Config.php'));
    }

    #[Test]
    public function resolves_nested_project_path(): void
    {
        $resolver = new PointerResolver($this->tmpDir);

        self::assertSame(
            $this->tmpDir . '/src/Runtime/Boot.php',
            $resolver->resolve('src/Runtime/Boot.php'),
        );
    }

    // ── .aimind/ paths ──────────────────────────────────────────────

    #[Test]
    public function resolves_aimind_path_when_vault_root_set(): void
    {
        $vaultRoot = $this->tmpDir . '/vault-project';
        mkdir($vaultRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, vaultRoot: $vaultRoot);

        self::assertSame($vaultRoot . '/knowledge.md', $resolver->resolve('.aimind/knowledge.md'));
    }

    #[Test]
    public function aimind_path_returns_null_without_vault_root(): void
    {
        $resolver = new PointerResolver($this->tmpDir, vaultRoot: null);

        self::assertNull($resolver->resolve('.aimind/knowledge.md'));
    }

    #[Test]
    public function aimind_path_strips_prefix_correctly(): void
    {
        $vaultRoot = $this->tmpDir . '/vault';
        mkdir($vaultRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, vaultRoot: $vaultRoot);

        self::assertSame($vaultRoot . '/plans/roadmap.md', $resolver->resolve('.aimind/plans/roadmap.md'));
    }

    // ── 70-redex/ paths ─────────────────────────────────────────────

    #[Test]
    public function resolves_redex_path_when_redex_root_set(): void
    {
        $redexRoot = $this->tmpDir . '/redex';
        mkdir($redexRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, redexRoot: $redexRoot);

        self::assertSame($redexRoot . '/data/index.md', $resolver->resolve('70-redex/data/index.md'));
    }

    #[Test]
    public function redex_path_returns_null_without_redex_root(): void
    {
        $resolver = new PointerResolver($this->tmpDir, vaultRoot: null, redexRoot: null);

        self::assertNull($resolver->resolve('70-redex/data/index.md'));
    }

    #[Test]
    public function redex_path_strips_prefix_correctly(): void
    {
        $redexRoot = $this->tmpDir . '/redex';
        mkdir($redexRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, redexRoot: $redexRoot);

        self::assertSame($redexRoot . '/nested/path.md', $resolver->resolve('70-redex/nested/path.md'));
    }

    // ── Accessors ───────────────────────────────────────────────────

    #[Test]
    public function vault_accessor_returns_configured_root(): void
    {
        $vaultRoot = $this->tmpDir . '/vault';
        mkdir($vaultRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, vaultRoot: $vaultRoot);

        self::assertSame($vaultRoot, $resolver->vault());
    }

    #[Test]
    public function vault_accessor_returns_null_when_not_configured(): void
    {
        $resolver = new PointerResolver($this->tmpDir, vaultRoot: null);

        self::assertNull($resolver->vault());
    }

    #[Test]
    public function redex_accessor_returns_configured_root(): void
    {
        $redexRoot = $this->tmpDir . '/redex';
        mkdir($redexRoot, 0755, true);

        $resolver = new PointerResolver($this->tmpDir, redexRoot: $redexRoot);

        self::assertSame($redexRoot, $resolver->redex());
    }

    #[Test]
    public function project_root_is_readable(): void
    {
        $resolver = new PointerResolver($this->tmpDir);

        self::assertSame($this->tmpDir, $resolver->projectRoot);
    }

    // ── Discovery (filesystem-dependent) ────────────────────────────

    #[Test]
    public function discovers_vault_from_aimind_symlink(): void
    {
        $vaultTarget = $this->tmpDir . '/vault-target';
        mkdir($vaultTarget, 0755, true);
        symlink($vaultTarget, $this->tmpDir . '/.aimind');

        $resolver = new PointerResolver($this->tmpDir);

        self::assertSame($vaultTarget, $resolver->vault());
    }

    #[Test]
    public function discovers_vault_from_aimind_directory(): void
    {
        mkdir($this->tmpDir . '/.aimind', 0755, true);

        $resolver = new PointerResolver($this->tmpDir);

        self::assertSame(realpath($this->tmpDir . '/.aimind'), $resolver->vault());
    }

    #[Test]
    public function no_aimind_means_null_vault(): void
    {
        $resolver = new PointerResolver($this->tmpDir);

        self::assertNull($resolver->vault());
    }

    #[Test]
    public function discovers_redex_from_vault_sibling(): void
    {
        // Structure: /parent/50-projects/project-slug/.aimind -> vault target
        // Redex: /parent/70-redex/
        $parent = $this->tmpDir . '/parent';
        $vaultProject = $parent . '/50-projects/my-project';
        $redexDir = $parent . '/70-redex';
        mkdir($vaultProject, 0755, true);
        mkdir($redexDir, 0755, true);

        // Resolver with explicit vault pointing into the project slug
        $resolver = new PointerResolver($this->tmpDir, vaultRoot: $vaultProject, redexRoot: null);

        // discoverRedex goes dirname(dirname($vaultRoot)) + /70-redex
        // dirname(dirname($vaultProject)) = $parent
        // $parent/70-redex exists
        // But since we passed redexRoot: null explicitly, discovery runs
        // Actually discovery only runs when redexRoot is not passed — let's test the auto path
        $resolver2 = new PointerResolver($this->tmpDir, vaultRoot: $vaultProject);

        self::assertSame($redexDir, $resolver2->redex());
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
            if ($item->isLink() || $item->isFile()) {
                unlink($item->getPathname());
            } elseif ($item->isDir()) {
                rmdir($item->getPathname());
            }
        }

        rmdir($dir);
    }
}
