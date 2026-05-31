<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandConfig;
use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\DescribesCommand;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\Stele;
use Phalanx\Dory\Stele\SteleEntry;
use Phalanx\Dory\Stele\SteleSection;
use Phalanx\Task\Scopeable;

final class SteleDumpCommand implements Scopeable, DescribesCommand
{
    public function __invoke(CommandContext $ctx): int
    {
        $output = $ctx->service(StreamOutput::class);
        [$stele, ] = SteleCommandHelper::load($ctx, $output);

        if ($stele === null) {
            return 1;
        }

        $output->persist(var_export(self::toArray($stele), true));

        return 0;
    }

    public static function commandConfig(): CommandConfig
    {
        return new CommandConfig(
            description: 'Dump parsed stele AST',
            options: SteleCommandHelper::commonOptions(),
            examples: [
                'dory stele dump',
            ],
        );
    }

    /** @return array<string, mixed> */
    private static function toArray(Stele $stele): array
    {
        return [
            'preamble' => $stele->preamble,
            'sections' => array_map(static fn(SteleSection $s) => [
                'kind' => $s->kind->name,
                'entries' => array_map(static fn(SteleEntry $e) => [
                    'name' => $e->name,
                    'epistemic' => $e->epistemic->label(),
                    'when' => $e->when,
                    'aspects' => $e->aspects,
                    'pointers' => array_map(static fn($p) => $p->path, $e->pointers),
                    'refs' => array_map(static fn($r) => ['base' => $r->base, 'subs' => $r->subs], $e->refs),
                    'body' => $e->body,
                ], $s->entries),
            ], $stele->sections),
        ];
    }
}
