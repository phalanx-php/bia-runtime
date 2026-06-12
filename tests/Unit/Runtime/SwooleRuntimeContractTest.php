<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use Phalanx\Bia\Runtime\SwooleRuntimeContract;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SwooleRuntimeContractTest extends TestCase
{
    #[Test]
    public function serializesTheNativeSwooleRuntimeShape(): void
    {
        $contract = new SwooleRuntimeContract(
            native: true,
            extensionLoaded: true,
            version: '6.0.0',
            features: array_fill_keys(array_keys(SwooleRuntimeContract::FEATURES), true),
        );

        self::assertSame([
            'contract' => SwooleRuntimeContract::CONTRACT,
            'native' => true,
            'extension' => 'swoole',
            'extension_loaded' => true,
            'version' => '6.0.0',
            'features' => [
                'http_server' => true,
                'http_request' => true,
                'http_response' => true,
                'table' => true,
                'timer' => true,
                'coroutine' => true,
            ],
        ], $contract->toArray());
    }

    #[Test]
    public function refusesTheNonNativeSystemPhpPath(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('runtime:contract requires native Bia/ripht host facts.');

        new SwooleRuntimeContract(
            native: false,
            extensionLoaded: true,
            version: '6.0.0',
            features: array_fill_keys(array_keys(SwooleRuntimeContract::FEATURES), true),
        );
    }

    #[Test]
    public function refusesMissingSwooleFeatures(): void
    {
        $features = array_fill_keys(array_keys(SwooleRuntimeContract::FEATURES), true);
        $features['timer'] = false;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Bia Swoole runtime is missing features: timer');

        new SwooleRuntimeContract(
            native: true,
            extensionLoaded: true,
            version: '6.0.0',
            features: $features,
        );
    }
}
