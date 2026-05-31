<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

final class PointerResolver
{
    private ?string $vaultRoot;
    private ?string $redexRoot;

    public function __construct(
        private(set) string $projectRoot,
        ?string $vaultRoot = null,
        ?string $redexRoot = null,
    ) {
        $this->vaultRoot = $vaultRoot ?? self::discoverVault($projectRoot);
        $this->redexRoot = $redexRoot ?? self::discoverRedex($this->vaultRoot);
    }

    public function resolve(string $path): ?string
    {
        if (str_starts_with($path, '.aimind/')) {
            if ($this->vaultRoot === null) {
                return null;
            }
            return $this->vaultRoot . '/' . substr($path, 8);
        }

        if (str_starts_with($path, '70-redex/')) {
            if ($this->redexRoot === null) {
                return null;
            }
            return $this->redexRoot . '/' . substr($path, 9);
        }

        return $this->projectRoot . '/' . $path;
    }

    public function vault(): ?string
    {
        return $this->vaultRoot;
    }

    public function redex(): ?string
    {
        return $this->redexRoot;
    }

    private static function discoverVault(string $projectRoot): ?string
    {
        $aimind = $projectRoot . '/.aimind';

        if (!is_dir($aimind)) {
            return null;
        }

        $real = realpath($aimind);

        return $real !== false ? $real : null;
    }

    private static function discoverRedex(?string $vaultRoot): ?string
    {
        if ($vaultRoot === null) {
            return null;
        }

        $parent = dirname(dirname($vaultRoot));
        $candidate = $parent . '/70-redex';

        return is_dir($candidate) ? $candidate : null;
    }
}
