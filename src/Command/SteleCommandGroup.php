<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandGroup;

final class SteleCommandGroup
{
    public static function commands(): CommandGroup
    {
        return CommandGroup::of([
            'lint' => SteleLintCommand::class,
            'sync' => SteleSyncCommand::class,
            'stale' => SteleStaleCommand::class,
            'stats' => SteleStatsCommand::class,
            'dump' => SteleDumpCommand::class,
        ], 'stele');
    }
}
