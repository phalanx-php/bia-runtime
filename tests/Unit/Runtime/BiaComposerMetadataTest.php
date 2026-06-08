<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Testing\FixtureFile;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaComposerMetadataTest extends TestCase
{
    #[Test]
    public function local_path_repository_versions_cover_required_phalanx_packages(): void
    {
        $composer = self::composer();

        $required = array_filter(
            array_keys($composer['require'] ?? []),
            static fn(string $package): bool => str_starts_with($package, 'phalanx-php/'),
        );

        $versions = $composer['repositories'][0]['options']['versions'] ?? [];
        self::assertIsArray($versions);
        self::assertSame('../../phalanx/src/*', $composer['repositories'][0]['url']);
        self::assertTrue($composer['repositories'][0]['options']['symlink']);

        sort($required);
        $versioned = array_keys($versions);
        sort($versioned);

        self::assertSame($required, $versioned);

        foreach ($versions as $package => $version) {
            self::assertIsString($package);
            self::assertSame('0.7.x-dev', $version);
        }
    }

    #[Test]
    public function publish_metadata_uses_released_package_constraints_without_local_repositories(): void
    {
        $composer = self::publishComposer();

        self::assertArrayNotHasKey('repositories', $composer);

        foreach ($composer['require'] as $package => $constraint) {
            if (!is_string($package) || !str_starts_with($package, 'phalanx-php/')) {
                continue;
            }

            self::assertSame('^0.7', $constraint, "{$package} must publish with a released package constraint.");
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        $composer = json_decode(
            FixtureFile::read(dirname(__DIR__, 3) . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        self::assertIsArray($composer);

        return $composer;
    }

    /**
     * @return array<string, mixed>
     */
    private static function publishComposer(): array
    {
        $composer = self::composer();
        unset($composer['repositories']);

        return $composer;
    }
}
