<?php

declare(strict_types=1);

namespace Phalanx\Dory\Scoped;

use Phalanx\Dory\Code\CodeParser;
use Phalanx\Dory\Code\DeclarationIndex;
use Phalanx\Dory\Code\NativeCodeParser;
use Phalanx\Dory\Code\ParseResult;
use Phalanx\Dory\Code\TokenIndex;
use Phalanx\Scope\ExecutionScope;

class ScopedCode
{
    public function __construct(
        private ?ExecutionScope $ctx = null,
        private ?CodeParser $parser = null,
    ) {
    }

    public function parse(string $source, ?string $name = null): ParseResult
    {
        return $this->parser()->parseSource($source, $name);
    }

    public function parseFile(string $path): ParseResult
    {
        return $this->parser()->parseFile($path);
    }

    public function declarationsForFile(string $path): DeclarationIndex
    {
        return DeclarationIndex::fromParseResult($this->parseFile($path));
    }

    public function tokensForSource(string $source, ?string $name = null): TokenIndex
    {
        return TokenIndex::fromParseResult($this->parse($source, $name));
    }

    private function parser(): CodeParser
    {
        if ($this->parser !== null) {
            return $this->parser;
        }

        if ($this->ctx === null) {
            return $this->parser = new NativeCodeParser();
        }

        return $this->parser = $this->ctx->service(CodeParser::class);
    }
}
