<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\SteleOperations;
use Phalanx\Task\Scopeable;

final class SteleStatsCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        [$stele, ] = SteleCommandHelper::load($ctx, $output);

        if ($stele === null) {
            return 1;
        }

        $stats = SteleOperations::stats($stele);

        $output->persist("Entries: {$stats['totalEntries']}");

        foreach ($stats['bySection'] as $kind => $count) {
            $output->persist("  {$kind}: {$count}");
        }

        $output->persist('Epistemic:');

        foreach ($stats['byEpistemic'] as $sigil => $count) {
            $output->persist("  {$sigil} {$count}");
        }

        $output->persist("Pointers: {$stats['totalPointers']}");
        $output->persist("Aspects: {$stats['totalAspects']} ({$stats['uniqueAspects']} unique)");

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Show stele file statistics',
            options: SteleCommandHelper::commonOptions(),
            examples: [
                'dory stele stats',
            ],
        );
    }
}
