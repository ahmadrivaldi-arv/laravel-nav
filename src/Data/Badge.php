<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Data;

final readonly class Badge
{
    public function __construct(public string|int|float $label, public string $color)
    {
    }

    /** @return array{label: string|int|float, color: string} */
    public function toArray(): array
    {
        return ['label' => $this->label, 'color' => $this->color];
    }
}
