<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandGroup;

class DoryCommandGroup
{
    public static function commands(): CommandGroup
    {
        $commands = [
            'run' => RunCommand::class,
            'r' => RunCommand::class,
            'init' => InitCommand::class,
            'new' => InitCommand::class,
            'doctor' => DoctorCommand::class,
            'check' => DoctorCommand::class,
            'stele' => SteleCommandGroup::commands(),
        ];

        if (class_exists(\Phalanx\DoryBin\Command\BuildCommandGroup::class)) {
            $commands['build'] = \Phalanx\DoryBin\Command\BuildCommandGroup::commands();
        }

        if (class_exists(\Phalanx\Skopos\FileWatcher::class)) {
            $commands['serve'] = ServeCommand::class;
        }

        return CommandGroup::of($commands, 'dory');
    }
}
