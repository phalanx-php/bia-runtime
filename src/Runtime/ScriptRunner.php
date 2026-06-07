<?php

declare(strict_types=1);

namespace Phalanx\Bia\Runtime;

final class ScriptRunner
{
    public static function execute(ScriptContext $context): mixed
    {
        return ScriptContextHolder::run(
            $context,
            static function () use ($context): mixed {
                $result = (static function (string $scriptPath): mixed {
                    return require $scriptPath;
                })($context->scriptPath);

                if ($result === 1 || $result === true) {
                    return null;
                }

                return $result;
            },
        );
    }
}
