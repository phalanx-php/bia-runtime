<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\SectionKind;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SectionKindTest extends TestCase
{
    #[Test]
    public function all_six_kinds_exist(): void
    {
        $cases = SectionKind::cases();

        self::assertCount(6, $cases);
    }

    #[Test]
    public function string_values_are_plural_labels(): void
    {
        self::assertSame('Constraints', SectionKind::Constraint->value);
        self::assertSame('Intents', SectionKind::Intent->value);
        self::assertSame('Forms', SectionKind::Form->value);
        self::assertSame('Lenses', SectionKind::Lens->value);
        self::assertSame('Passages', SectionKind::Passage->value);
        self::assertSame('State', SectionKind::State->value);
    }

    #[Test]
    public function try_from_returns_null_for_unknown(): void
    {
        self::assertNull(SectionKind::tryFrom('Unknown'));
        self::assertNull(SectionKind::tryFrom('constraints')); // case-sensitive
    }

    #[Test]
    public function try_from_matches_exact_values(): void
    {
        foreach (SectionKind::cases() as $case) {
            self::assertSame($case, SectionKind::tryFrom($case->value));
        }
    }
}
