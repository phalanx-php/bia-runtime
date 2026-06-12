<?php

declare(strict_types=1);

namespace Phalanx\Bia\Tests\Unit\Runtime;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BiaBinaryTest extends TestCase
{
    #[Test]
    public function no_args_show_top_level_help(): void
    {
        $result = self::runBia();

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertStringContainsString('Usage:', $result->stdout);
        self::assertStringContainsString('contract', $result->stdout);
        self::assertStringContainsString('run', $result->stdout);
    }

    #[Test]
    public function contract_prints_the_active_bootstrap_contract(): void
    {
        $result = self::runBia('contract');

        self::assertSame(0, $result->exitCode, $result->stderr);

        $payload = json_decode($result->stdout, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('2.0', $payload['contract']);
        self::assertSame('Phalanx\\Phalanx', $payload['entrypoint']);
        self::assertSame('phalanx-php/phalanx', $payload['package']);
        self::assertSame('2.0-dev', $payload['version']);
    }

    #[Test]
    public function runtime_contract_requires_the_native_bia_path(): void
    {
        $result = self::runBia('runtime:contract');

        self::assertSame(126, $result->exitCode);
        self::assertStringContainsString('runtime:contract requires native Bia/ripht host facts.', $result->stderr);
    }

    #[Test]
    public function run_executes_script_files(): void
    {
        $result = self::runBia('run', dirname(__DIR__, 2) . '/Fixtures/return-42.php');

        self::assertSame(42, $result->exitCode, $result->stderr);
    }

    #[Test]
    public function env_check_reports_missing_required_demands(): void
    {
        $result = self::runBia('env:check', dirname(__DIR__, 2) . '/Fixtures/env-config.php');

        self::assertSame(1, $result->exitCode);
        self::assertStringContainsString('SESSION_SIGNING_KEY missing', $result->stderr);
        self::assertStringContainsString('SURREAL_POOL_SIZE missing', $result->stderr);
        self::assertStringNotContainsString('FEATURE_FLAG missing', $result->stderr);
    }

    #[Test]
    public function env_check_passes_when_required_demands_exist(): void
    {
        $result = self::runBiaWithEnv(
            [
                'SESSION_SIGNING_KEY' => 'secret',
                'SURREAL_POOL_SIZE' => '16',
            ],
            'env:check',
            dirname(__DIR__, 2) . '/Fixtures/env-config.php',
        );

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertStringContainsString('env ok', $result->stdout);
    }

    #[Test]
    public function env_example_derives_keys_from_demands(): void
    {
        $result = self::runBia('env:example', dirname(__DIR__, 2) . '/Fixtures/env-config.php');

        self::assertSame(0, $result->exitCode, $result->stderr);
        self::assertSame("FEATURE_FLAG=\nSESSION_SIGNING_KEY=\nSURREAL_POOL_SIZE=\n", $result->stdout);
    }

    private static function runBia(string ...$args): CommandResult
    {
        return self::runBiaWithEnv([], ...$args);
    }

    /** @param array<string, string> $env */
    private static function runBiaWithEnv(array $env, string ...$args): CommandResult
    {
        $command = [PHP_BINARY, dirname(__DIR__, 3) . '/bin/bia', ...$args];
        $process = proc_open(
            $command,
            [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            dirname(__DIR__, 3),
            $env === [] ? null : $env,
        );

        self::assertIsResource($process);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        self::assertIsString($stdout);
        self::assertIsString($stderr);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return new CommandResult(proc_close($process), $stdout, $stderr);
    }
}

final class CommandResult
{
    public function __construct(
        public int $exitCode,
        public string $stdout,
        public string $stderr,
    ) {
    }
}
