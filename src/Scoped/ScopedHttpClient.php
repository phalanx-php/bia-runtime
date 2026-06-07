<?php

declare(strict_types=1);

namespace Phalanx\Bia\Scoped;

use Phalanx\HttpClient\Client;
use Phalanx\HttpClient\Request;
use Phalanx\HttpClient\Response;
use Phalanx\HttpClient\Stream;
use Phalanx\Scope\ExecutionScope;

class ScopedHttpClient
{
    public function __construct(private ExecutionScope $ctx)
    {
    }

    /** @param array<string, list<string>> $headers */
    public function get(string $url, array $headers = []): Response
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): Response => $scope->service(Client::class)->get($scope, $url, $headers),
        );
    }

    /** @param array<string, list<string>> $headers */
    public function post(string $url, string $body, array $headers = []): Response
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): Response => $scope->service(Client::class)->post($scope, $url, $body, $headers),
        );
    }

    public function request(Request $request): Response
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): Response => $scope->service(Client::class)->request($scope, $request),
        );
    }

    public function stream(Request $request): Stream
    {
        return $this->ctx->execute(
            static fn(ExecutionScope $scope): Stream => $scope->service(Client::class)->stream($scope, $request),
        );
    }
}
