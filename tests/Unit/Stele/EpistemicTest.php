<?php

declare(strict_types=1);

namespace Phalanx\Dory\Tests\Unit\Stele;

use Phalanx\Dory\Stele\Epistemic;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EpistemicTest extends TestCase
{
    #[Test]
    public function certain_has_bang_value(): void
    {
        self::assertSame('!', Epistemic::Certain->value);
    }

    #[Test]
    public function approximate_has_tilde_value(): void
    {
        self::assertSame('~', Epistemic::Approximate->value);
    }

    #[Test]
    public function stale_has_underscore_value(): void
    {
        self::assertSame('_', Epistemic::Stale->value);
    }

    #[Test]
    public function sigil_prepends_at_sign(): void
    {
        self::assertSame('@!', Epistemic::Certain->sigil());
        self::assertSame('@~', Epistemic::Approximate->sigil());
        self::assertSame('@_', Epistemic::Stale->sigil());
    }

    #[Test]
    public function label_returns_human_readable_name(): void
    {
        self::assertSame('certain', Epistemic::Certain->label());
        self::assertSame('approximate', Epistemic::Approximate->label());
        self::assertSame('stale', Epistemic::Stale->label());
    }

    #[Test]
    public function from_sigil_character_roundtrips(): void
    {
        foreach (Epistemic::cases() as $case) {
            self::assertSame($case, Epistemic::from($case->value));
        }
    }
}
