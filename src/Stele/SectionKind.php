<?php

declare(strict_types=1);

namespace Phalanx\Dory\Stele;

enum SectionKind: string
{
    case Constraint = 'Constraints';
    case Intent = 'Intents';
    case Form = 'Forms';
    case Lens = 'Lenses';
    case Passage = 'Passages';
    case State = 'State';
}
