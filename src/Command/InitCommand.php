<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\Arg;
use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Task\Scopeable;

class InitCommand implements Scopeable, DescribesCommand
{
    private const string SAMPLE_SCRIPT = <<<'PHP'
        <?php

        declare(strict_types=1);

        dory()->println('Greetings from Olympus.');

        $result = dory()->attempt(static fn(): string => 'The phalanx holds.')
            ->timeout(5.0)
            ->run();

        dory()->dump($result);

        return 0;
        PHP;

    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);

        $directory = (string) $ctx->args->get('directory', '.');
        $absolute = realpath($directory) ?: $directory;

        if (!is_dir($absolute) && !mkdir($absolute, 0755, recursive: true)) {
            fwrite(STDERR, "Could not create directory: {$directory}\n");
            return 1;
        }

        $scriptName = (string) ($ctx->options->get('name') ?? 'hello.php');
        $scriptPath = rtrim($absolute, '/') . '/' . $scriptName;

        if (file_exists($scriptPath) && !$ctx->options->flag('force')) {
            $output->persist("File already exists: {$scriptPath}");
            $output->persist('Use --force to overwrite.');
            return 0;
        }

        file_put_contents($scriptPath, self::SAMPLE_SCRIPT . "\n");

        $output->persist("Created: {$scriptPath}");
        $output->persist('');
        $output->persist('Run it with:');
        $output->persist("  dory run {$scriptPath}");

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Scaffold a new Dory project with a sample script',
            arguments: [
                Arg::optional('directory', 'Target directory', '.'),
            ],
            options: [
                Opt::value('name', 'n', 'Script filename', 'hello.php'),
                Opt::flag('force', 'f', 'Overwrite existing files'),
            ],
            examples: [
                'dory init my-project',
                'dory new . --name=sync.php',
                'dory init scripts -f',
            ],
            aliases: ['new'],
        );
    }
}
