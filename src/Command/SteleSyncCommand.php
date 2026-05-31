<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\SteleOperations;
use Phalanx\Task\Scopeable;

final class SteleSyncCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        [$stele, $resolver] = SteleCommandHelper::load($ctx, $output);

        if ($stele === null) {
            return 1;
        }

        $output->persist('Syncing aspects...');

        $results = SteleOperations::sync($stele, $resolver);
        $changed = array_filter($results, static fn(array $r) => $r['changed']);

        if ($changed === []) {
            $output->persist('All aspects current.');
            return 0;
        }

        foreach ($changed as $result) {
            $output->persist("  {$result['entry']}");
            $output->persist('    current:  ' . implode(' ', array_map(static fn(string $a) => ".{$a}", $result['current'])));
            $output->persist('    proposed: ' . implode(' ', array_map(static fn(string $a) => ".{$a}", $result['proposed'])));
        }

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Propose aspect tag updates from pointer target headings',
            options: SteleCommandHelper::commonOptions(),
            examples: [
                'dory stele sync',
            ],
        );
    }
}
