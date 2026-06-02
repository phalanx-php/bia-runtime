<?php

declare(strict_types=1);

namespace Phalanx\Dory\Command;

use Phalanx\Archon\Console\Output\StreamOutput;
use Phalanx\Dory\Code\CodePayload;
use Phalanx\Dory\Code\CodeProjectIndex;
use Phalanx\Dory\Code\DeclarationQueryResult;
use Phalanx\Dory\Code\NodeQueryResult;
use Phalanx\Dory\Code\ReferenceQueryResult;
use Phalanx\Dory\Code\TokenQueryResult;

final class CodeCommandOutput
{
    /** @return array<string, mixed> */
    public static function projectIndex(CodeProjectIndex $index): array
    {
        return CodePayload::projectIndex($index);
    }

    /** @return array<string, mixed> */
    public static function declarationResult(DeclarationQueryResult $result): array
    {
        return CodePayload::declarationResult($result);
    }

    /** @return array<string, mixed> */
    public static function tokenResult(TokenQueryResult $result): array
    {
        return CodePayload::tokenResult($result);
    }

    /** @return array<string, mixed> */
    public static function nodeResult(NodeQueryResult $result): array
    {
        return CodePayload::nodeResult($result);
    }

    /** @return array<string, mixed> */
    public static function referenceResult(ReferenceQueryResult $result): array
    {
        return CodePayload::referenceResult($result);
    }

    /** @param array<string, mixed> $payload */
    public static function json(StreamOutput $output, array $payload): void
    {
        $output->persist(json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    }

    public static function jsonError(StreamOutput $output, string $message): void
    {
        self::json($output, [
            'ok' => false,
            'message' => $message,
        ]);
    }
}
