<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Data;

final readonly class Icon
{
    private function __construct(public string $type, public string $value)
    {
    }

    public static function identifier(string $value): self
    {
        return new self('identifier', $value);
    }

    public static function trustedSvg(string $value): self
    {
        return new self('trusted_svg', $value);
    }

    /** @return array{type: string, value: string} */
    public function toArray(): array
    {
        return ['type' => $this->type, 'value' => $this->value];
    }
}
