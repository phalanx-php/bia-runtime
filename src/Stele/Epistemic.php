<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

enum Epistemic: string
{
    case Certain = '!';
    case Approximate = '~';
    case Stale = '_';

    public function sigil(): string
    {
        return '@' . $this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Certain => 'certain',
            self::Approximate => 'approximate',
            self::Stale => 'stale',
        };
    }
}
