<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Runtime\DoryConfig;
use Phalanx\Task\Scopeable;
use Phalanx\Themis\ValidationContext;

final class DoctorCommand implements Scopeable, DescribesCommand
{
    private const string PASS = '[pass]';
    private const string FAIL = '[fail]';
    private const string INFO = '[info]';

    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        $ok = true;

        $output->persist('Runtime');

        $ok = self::check($output, 'PHP >= 8.4', version_compare(PHP_VERSION, '8.4.0', '>=')) && $ok;
        self::info($output, 'PHP version', PHP_VERSION);
        $sapi = php_sapi_name();
        $sapiLabel = getenv('DORY_EMBEDDED') ? "{$sapi} (ripht)" : $sapi;

        self::info($output, 'SAPI', $sapiLabel);

        $swooleLoaded = extension_loaded('swoole');

        $ok = self::check($output, 'Swoole loaded', $swooleLoaded) && $ok;

        if ($swooleLoaded) {
            self::info($output, 'Swoole version', SWOOLE_VERSION);
        }

        self::info($output, 'Embedded', getenv('DORY_EMBEDDED') ?: 'no');

        $output->persist('');
        $output->persist('Extensions (' . count(get_loaded_extensions()) . ' loaded)');

        $extensions = get_loaded_extensions();

        sort($extensions);

        foreach ($extensions as $ext) {
            $version = phpversion($ext);
            $label = $version && $version !== PHP_VERSION ? "{$ext} ({$version})" : $ext;

            self::info($output, $label);
        }

        $output->persist('');
        $output->persist('Configuration');

        self::checkDoryConfig($ctx, $output, $ok);

        return $ok ? 0 : 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Check runtime environment and extensions',
            examples: [
                'dory doctor',
                'dory check',
            ],
            aliases: ['check'],
        );
    }

    private static function check(StreamOutput $output, string $label, bool $ok): bool
    {
        $marker = $ok ? self::PASS : self::FAIL;
        $output->persist("  {$marker} {$label}");

        return $ok;
    }

    private static function info(StreamOutput $output, string $label, string $value = ''): void
    {
        $text = $value !== '' ? "{$label}: {$value}" : $label;
        $output->persist("  " . self::INFO . " {$text}");
    }

    private static function checkDoryConfig(CommandContext $ctx, StreamOutput $output, bool &$ok): void
    {
        $config = $ctx->service(DoryConfig::class);
        $issues = $config->validate(new ValidationContext());

        if ($issues === []) {
            self::check($output, 'Dory config valid', true);

            return;
        }

        $ok = false;

        self::check($output, 'Dory config', false);

        foreach ($issues as $issue) {
            $output->persist("    [{$issue->code}] {$issue->message}");
        }
    }
}
