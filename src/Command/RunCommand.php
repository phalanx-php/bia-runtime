<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\Arg;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Boot\AppContext;
use Phalanx\Dory\Runtime\DoryBuilder;
use Phalanx\Task\Scopeable;

class RunCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $input = (string) $ctx->args->required('script');
        $scriptPath = self::resolveScript($input);

        $env = [];

        if ($ctx->options->flag('verbose')) {
            $env['DORY_VERBOSE'] = '1';
        }

        $timeout = $ctx->options->get('timeout');

        if ($timeout !== null) {
            $env['DORY_SCRIPT_TIMEOUT'] = $timeout;
        }

        $builder = new DoryBuilder(new AppContext(['env' => $env]));
        $builder->script($scriptPath);

        return $builder->run();
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Run a Dory script or inline PHP code',
            arguments: [
                Arg::required('script', 'Script path or inline PHP code'),
            ],
            options: [
                Opt::flag('verbose', 'v', 'Show detailed output'),
                Opt::value('timeout', 't', 'Script timeout in seconds', '30'),
            ],
            examples: [
                'dory run deploy.php',
                "dory run '1 + 1'",
                'dory run sync.php --timeout=120',
                'dory r migrate.php -v',
            ],
            aliases: ['r'],
        );
    }

    private static function resolveScript(string $input): string
    {
        $resolved = realpath($input);

        if ($resolved !== false && file_exists($resolved)) {
            return $resolved;
        }

        if (self::looksLikePath($input)) {
            throw new \RuntimeException("Script not found: {$input}");
        }

        return self::writeInlineScript($input);
    }

    private static function looksLikePath(string $input): bool
    {
        return str_contains($input, '/') || str_contains($input, '\\') || str_ends_with($input, '.php');
    }

    private static function writeInlineScript(string $code): string
    {
        $code = trim($code);
        $isExpression = !str_contains($code, ';') && !str_contains($code, '{');

        if ($isExpression) {
            $body = "\$__r = ({$code});\nif (\$__r !== null) { dory()->dump(\$__r); }\nreturn 0;";
        } else {
            $body = str_ends_with($code, ';') || str_ends_with($code, '}') || str_ends_with($code, '?>')
                ? $code
                : "{$code};";
            $body .= "\nreturn 0;";
        }

        $php = "<?php declare(strict_types=1);\n{$body}\n";
        $tmp = tempnam(sys_get_temp_dir(), 'dory_') . '.php';
        file_put_contents($tmp, $php);
        register_shutdown_function(static fn() => @unlink($tmp));

        return $tmp;
    }
}
