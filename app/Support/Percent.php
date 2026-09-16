<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * A non-negative percentage in whole basis points: 35.5% is 3550.
 *
 * Mirrors a decimal(5,2) column, so the largest value is 999.99%.
 */
final readonly class Percent implements JsonSerializable, Stringable
{
    public const int SCALE = 10000;

    public const int MAX_BASIS_POINTS = 99999;

    private function __construct(public int $basisPoints) {}

    public static function ofBasisPoints(int $basisPoints): self
    {
        if ($basisPoints < 0 || $basisPoints > self::MAX_BASIS_POINTS) {
            throw new InvalidArgumentException("Percent must be between 0 and 999.99, [{$basisPoints}] basis points given.");
        }

        return new self($basisPoints);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses a decimal with a dot separator: "35", "35.5", "10.00".
     */
    public static function fromDecimal(string $value): self
    {
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid percent [{$value}].");
        }

        return self::ofBasisPoints((int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '0', 2, '0'));
    }

    public function toDecimal(): string
    {
        return intdiv($this->basisPoints, 100).'.'.str_pad((string) ($this->basisPoints % 100), 2, '0', STR_PAD_LEFT);
    }

    public function isZero(): bool
    {
        return $this->basisPoints === 0;
    }

    public function min(self $other): self
    {
        return $this->basisPoints <= $other->basisPoints ? $this : $other;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}
