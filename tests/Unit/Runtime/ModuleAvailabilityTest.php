<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Runtime;

use Phalanx\Argos\NetworkConfig;
use Phalanx\Argos\NetworkServiceBundle;
use Phalanx\Grammata\FilesystemServiceBundle;
use Phalanx\Hermes\Client\WsClientConfig;
use Phalanx\Hermes\WsServiceBundle;
use Phalanx\Hydra\HydraServiceBundle;
use Phalanx\Iris\HttpServiceBundle;
use Phalanx\Skopos\Skopos;
use Phalanx\Themis\ValidationContext;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleAvailabilityTest extends TestCase
{
    #[Test]
    public function auto_registered_module_bundles_exist(): void
    {
        self::assertTrue(class_exists(NetworkServiceBundle::class));
        self::assertTrue(class_exists(WsServiceBundle::class));
        self::assertTrue(class_exists(HttpServiceBundle::class));
        self::assertTrue(class_exists(FilesystemServiceBundle::class));
    }

    #[Test]
    public function opt_in_module_bundle_exists(): void
    {
        self::assertTrue(class_exists(HydraServiceBundle::class));
    }

    #[Test]
    public function skopos_is_class_available(): void
    {
        self::assertTrue(class_exists(Skopos::class));
    }

    #[Test]
    public function argos_config_passes_validation_with_defaults(): void
    {
        $config = new NetworkConfig();

        self::assertTrue($config->configured);
        self::assertEmpty($config->validate(new ValidationContext()));
    }

    #[Test]
    public function hermes_client_config_constructs_with_defaults(): void
    {
        $config = WsClientConfig::default();

        self::assertIsFloat($config->connectTimeout);
        self::assertGreaterThan(0, $config->connectTimeout);
    }
}
