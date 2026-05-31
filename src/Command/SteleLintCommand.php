<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\LintIssue;
use Phalanx\Dory\Stele\SteleOperations;
use Phalanx\Task\Scopeable;

class SteleLintCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        [$stele, $resolver] = SteleCommandHelper::load($ctx, $output);

        if ($stele === null) {
            return 1;
        }

        $output->persist("Linting... (project: {$resolver->projectRoot}, vault: {$resolver->vault()}, redex: {$resolver->redex()})");

        $issues = SteleOperations::lint($stele, $resolver);

        if ($issues === []) {
            $output->persist('Clean.');
            return 0;
        }

        foreach ($issues as $issue) {
            $output->persist($issue->format());
        }

        $total = count($stele->entries());
        $clean = $total - count(array_unique(array_map(static fn(LintIssue $i) => $i->entry, $issues)));
        $output->persist("Clean entries: {$clean}/{$total}");

        return 1;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Validate stele entries — pointers, triggers, aspects, epistemic markers',
            options: SteleCommandHelper::commonOptions(),
            examples: [
                'dory stele lint',
                'dory stele lint --project /path/to/project',
            ],
        );
    }
}
