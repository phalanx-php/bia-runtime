<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

use Phalanx\Bia\Runtime\Host\EnvFacts;

final class EnvContext
{
    /** @var list<EnvDemand> */
    private array $demands = [];

    public function __construct(
        private readonly EnvFacts $facts,
    ) {
    }

    public function string(string $key, ?string $default = null): string
    {
        $this->record($key, 'string', $default !== null);

        return $this->facts->values[$key]['value'] ?? $default ?? '';
    }

    public function int(string $key, ?int $default = null): int
    {
        $this->record($key, 'int', $default !== null);

        $value = $this->facts->values[$key]['value'] ?? null;
        if ($value === null) {
            return $default ?? 0;
        }

        return filter_var($value, FILTER_VALIDATE_INT) === false ? $default ?? 0 : (int) $value;
    }

    public function bool(string $key, ?bool $default = null): bool
    {
        $this->record($key, 'bool', $default !== null);

        $value = $this->facts->values[$key]['value'] ?? null;
        if ($value === null) {
            return $default ?? false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default ?? false;
    }

    /** @return list<EnvDemand> */
    public function demands(): array
    {
        return $this->demands;
    }

    private function record(string $key, string $type, bool $hasDefault): void
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $this->demands[] = new EnvDemand(
            key: $key,
            type: $type,
            source: ($trace['file'] ?? 'unknown') . ':' . ($trace['line'] ?? 0),
            required: !$hasDefault,
        );
    }
}
