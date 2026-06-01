<?php

declare(strict_types=1);

namespace Phalanx\Dory\Code;

final class ParseResult
{
    /**
     * @param list<ParseError> $errors
     * @param list<TokenRecord> $tokens
     * @param list<DeclarationRecord> $declarations
     */
    public function __construct(
        public SourceFileRecord $file,
        public bool $hasErrors,
        public array $errors,
        public array $tokens,
        public array $declarations,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            SourceFileRecord::fromArray(PayloadReader::object($data, 'file')),
            PayloadReader::bool($data, 'has_errors'),
            array_map(ParseError::fromArray(...), PayloadReader::listOfObjects($data, 'errors')),
            array_map(TokenRecord::fromArray(...), PayloadReader::listOfObjects($data, 'tokens')),
            array_map(DeclarationRecord::fromArray(...), PayloadReader::listOfObjects($data, 'declarations')),
        );
    }
}
