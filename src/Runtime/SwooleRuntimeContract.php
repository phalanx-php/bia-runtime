<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use RuntimeException;

final class SwooleRuntimeContract
{
    public const int CONTRACT = 1;

    /** @var array<string, non-empty-string> */
    public const array FEATURES = [
        'http_server' => 'Swoole\Http\Server',
        'http_request' => 'Swoole\Http\Request',
        'http_response' => 'Swoole\Http\Response',
        'table' => 'Swoole\Table',
        'timer' => 'Swoole\Timer',
        'coroutine' => 'Swoole\Coroutine',
    ];

    /** @param array<string, bool> $features */
    public function __construct(
        private(set) bool $native,
        private(set) bool $extensionLoaded,
        private(set) string $version,
        private(set) array $features,
    ) {
        if (!$this->native) {
            throw new RuntimeException('runtime:contract requires native Bia/ripht host facts.');
        }

        if (!$this->extensionLoaded) {
            throw new RuntimeException('Bia must provide the Swoole extension.');
        }

        if ($this->version === '') {
            throw new RuntimeException('Bia must expose a Swoole extension version.');
        }

        $missing = array_keys(array_filter(
            self::FEATURES,
            fn (string $_class, string $name): bool => ($this->features[$name] ?? false) !== true,
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing !== []) {
            throw new RuntimeException('Bia Swoole runtime is missing features: ' . implode(', ', $missing));
        }
    }

    public static function probeNative(): self
    {
        $version = phpversion('swoole');

        return new self(
            native: function_exists('phalanx_host_facts'),
            extensionLoaded: extension_loaded('swoole'),
            version: is_string($version) ? $version : '',
            features: self::probeFeatures(),
        );
    }

    /** @return array<string, bool> */
    private static function probeFeatures(): array
    {
        $features = [];

        foreach (self::FEATURES as $name => $class) {
            $features[$name] = class_exists($class, false);
        }

        return $features;
    }

    /** @return array{contract: int, native: bool, extension: string, extension_loaded: bool, version: string, features: array<string, bool>} */
    public function toArray(): array
    {
        return [
            'contract' => self::CONTRACT,
            'native' => $this->native,
            'extension' => 'swoole',
            'extension_loaded' => $this->extensionLoaded,
            'version' => $this->version,
            'features' => $this->features,
        ];
    }
}
