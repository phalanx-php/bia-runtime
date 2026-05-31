<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Command\Opt;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\SteleOperations;
use Phalanx\Task\Scopeable;

final class SteleStaleCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        [$stele, $resolver] = SteleCommandHelper::load($ctx, $output);

        if ($stele === null) {
            return 1;
        }

        $days = (int) ($ctx->options->get('days') ?? 30);
        $output->persist('Stale entries...');

        $results = SteleOperations::stale($stele, $resolver, $days);

        if ($results === []) {
            $output->persist('Nothing stale.');
            return 0;
        }

        foreach ($results as $result) {
            $sigil = $result['epistemic']->sigil();
            $output->persist("  {$result['entry']} {$sigil} — {$result['daysOld']}d — {$result['path']}");
        }

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Find entries whose pointer targets are older than a threshold',
            options: [
                ...SteleCommandHelper::commonOptions(),
                Opt::value('days', 'd', 'Staleness threshold in days', '30'),
            ],
            examples: [
                'dory stele stale',
                'dory stele stale --days 14',
            ],
        );
    }
}
