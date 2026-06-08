<?php

declare(strict_types=1);

final class BiaRuntimeReleaseComposerCheck
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly string $root,
    ) {
    }

    public function __invoke(): int
    {
        $composer = $this->composer();

        $this->assertLocalPathRepository($composer);
        $this->assertPublishMetadata($this->publishComposer($composer));

        if ($this->errors === []) {
            fwrite(STDOUT, "Bia runtime Composer release checks passed.\n");

            return 0;
        }

        fwrite(STDERR, "Bia runtime Composer release checks failed:\n");
        foreach ($this->errors as $error) {
            fwrite(STDERR, "  - {$error}\n");
        }

        return 1;
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function assertLocalPathRepository(array $composer): void
    {
        $repositories = $composer['repositories'] ?? [];
        $repository = is_array($repositories) ? ($repositories[0] ?? null) : null;

        if (!is_array($repository)) {
            $this->errors[] = 'composer.json must keep the local Phalanx path repository.';

            return;
        }

        if (($repository['type'] ?? null) !== 'path') {
            $this->errors[] = 'Local Phalanx repository must be type path.';
        }

        if (($repository['url'] ?? null) !== '../../phalanx/src/*') {
            $this->errors[] = 'Local Phalanx repository must point at ../../phalanx/src/*.';
        }

        if (($repository['options']['symlink'] ?? null) !== true) {
            $this->errors[] = 'Local Phalanx repository must symlink source packages.';
        }

        $required = $this->phalanxRequires($composer);
        $versions = $repository['options']['versions'] ?? [];
        if (!is_array($versions)) {
            $this->errors[] = 'Local Phalanx repository must define package versions.';

            return;
        }

        ksort($required);
        ksort($versions);

        if (array_keys($versions) !== array_keys($required)) {
            $this->errors[] = 'Local Phalanx path versions must exactly cover required Phalanx packages.';
        }

        foreach ($versions as $package => $version) {
            if (!is_string($package) || $version !== '0.7.x-dev') {
                $this->errors[] = "Local path version for {$package} must be 0.7.x-dev.";
            }
        }
    }

    /**
     * @param array<string, mixed> $composer
     */
    private function assertPublishMetadata(array $composer): void
    {
        if (array_key_exists('repositories', $composer)) {
            $this->errors[] = 'Publish metadata must not include local repositories.';
        }

        foreach ($this->phalanxRequires($composer) as $package => $constraint) {
            if ($constraint !== '^0.7') {
                $this->errors[] = "Publish constraint for {$package} must be ^0.7.";
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function composer(): array
    {
        $composer = json_decode(
            $this->read($this->root . '/composer.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        if (!is_array($composer)) {
            throw new RuntimeException('composer.json did not decode to an object.');
        }

        return $composer;
    }

    /**
     * @param array<string, mixed> $composer
     * @return array<string, string>
     */
    private function phalanxRequires(array $composer): array
    {
        $requires = $composer['require'] ?? [];
        if (!is_array($requires)) {
            return [];
        }

        $packages = [];
        foreach ($requires as $package => $constraint) {
            if (!is_string($package) || !str_starts_with($package, 'phalanx-php/')) {
                continue;
            }

            $packages[$package] = is_string($constraint) ? $constraint : '';
        }

        return $packages;
    }

    /**
     * @param array<string, mixed> $composer
     * @return array<string, mixed>
     */
    private function publishComposer(array $composer): array
    {
        unset($composer['repositories']);

        return $composer;
    }

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if (!is_string($contents)) {
            throw new RuntimeException("Unable to read {$path}");
        }

        return $contents;
    }
}

exit((new BiaRuntimeReleaseComposerCheck(dirname(__DIR__)))());
