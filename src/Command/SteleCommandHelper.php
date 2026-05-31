<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Command\CommandContext;
use Phalanx\Archon\Command\Opt;
use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Stele\PointerResolver;
use Phalanx\Dory\Stele\Stele;
use Phalanx\Dory\Stele\SteleFileFinder;
use Phalanx\Dory\Stele\SteleParser;

final class SteleCommandHelper
{
    /** @return list<Opt> */
    public static function commonOptions(): array
    {
        return [
            Opt::value('stele', 's', 'Path to stele file'),
            Opt::value('project', 'p', 'Project root path'),
            Opt::value('vault', '', 'Vault root (.aimind target)'),
            Opt::value('redex', '', 'Redex root (70-redex/)'),
        ];
    }

    /** @return array{0: Stele|null, 1: PointerResolver} */
    public static function load(CommandContext $ctx, StreamOutput $output): array
    {
        $explicitProject = $ctx->options->get('project');
        $searchRoot = $explicitProject ?? SteleFileFinder::findProjectRoot(getcwd() ?: '.');

        if ($searchRoot === null) {
            $output->persist('Not inside a project.');
            $output->persist('  Stele needs a project root — a directory with .aimind/ or .git/.');
            $output->persist('  Run from inside your project, or use --project to specify one.');
            $resolver = new PointerResolver('.');
            return [null, $resolver];
        }

        $explicitStele = $ctx->options->get('stele');

        if ($explicitStele !== null) {
            $stelePath = $explicitStele;
            $projectRoot = $explicitProject ?? $searchRoot;
        } else {
            $found = SteleFileFinder::find($searchRoot);

            if ($found === null) {
                $output->persist("No stele file in: {$searchRoot}");
                $output->persist('  Create a CORE.md or CORE.draft.md, or use --stele to point at one.');
                $resolver = new PointerResolver($searchRoot);
                return [null, $resolver];
            }

            $stelePath = $found['path'];
            $projectRoot = $explicitProject ?? $found['projectRoot'];
        }

        if (!file_exists($stelePath)) {
            $output->persist("Stele file does not exist: {$stelePath}");
            $resolver = new PointerResolver($projectRoot);
            return [null, $resolver];
        }

        $resolver = new PointerResolver(
            projectRoot: $projectRoot,
            vaultRoot: $ctx->options->get('vault'),
            redexRoot: $ctx->options->get('redex'),
        );

        $content = file_get_contents($stelePath);

        if ($content === false) {
            $output->persist("Cannot read stele file: {$stelePath}");
            return [null, $resolver];
        }

        $stele = (new SteleParser())->parse($content);

        return [$stele, $resolver];
    }
}
