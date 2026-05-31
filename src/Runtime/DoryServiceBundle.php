<?php

declare(strict_types=1);

namespace Phalanx\Dory\Runtime;

use Phalanx\Boot\AppContext;
use Phalanx\Dory\Rendering\EchoSink;
use Phalanx\Dory\Rendering\OutputSink;
use Phalanx\Dory\Rendering\SettlementRenderer;
use Phalanx\Dory\Rendering\ThrowableRenderer;
use Phalanx\Dory\Rendering\ValueRendererPipeline;
use Phalanx\Service\ServiceBundle;
use Phalanx\Service\Services;

final class DoryServiceBundle extends ServiceBundle
{
    #[\Override]
    public static function configs(): array
    {
        return [DoryConfig::class];
    }

    public function services(Services $services, AppContext $context): void
    {
        $services->singleton(ValueRendererPipeline::class)
            ->factory(static fn(): ValueRendererPipeline => new ValueRendererPipeline([
                new SettlementRenderer(),
                new ThrowableRenderer(),
            ]));

        $services->scoped(OutputSink::class)
            ->factory(static fn(): OutputSink => new EchoSink());
    }
}
