<?php

namespace App\Support;

use InvalidArgumentException;
use JsonSerializable;
use Stringable;

/**
 * An amount of money in whole kopecks.
 *
 * Floats are never accepted: the database stores money as decimal(12,2)
 * and hands it over as a string, which is parsed exactly.
 */
final readonly class Money implements JsonSerializable, Stringable
{
    private function __construct(public int $kopecks) {}

    public static function ofKopecks(int $kopecks): self
    {
        return new self($kopecks);
    }

    public static function ofRubles(int $rubles): self
    {
        return new self($rubles * 100);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parses a decimal with a dot separator: "383995", "48605.8", "-12.50".
     */
    public static function fromDecimal(string $value): self
    {
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $value, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid money amount [{$value}].");
        }

        $kopecks = (int) $matches[2] * 100 + (int) str_pad($matches[3] ?? '0', 2, '0');

        return new self($matches[1] === '-' ? -$kopecks : $kopecks);
    }

    public function toDecimal(): string
    {
        $absolute = abs($this->kopecks);

        return ($this->kopecks < 0 ? '-' : '')
            .intdiv($absolute, 100)
            .'.'
            .str_pad((string) ($absolute % 100), 2, '0', STR_PAD_LEFT);
    }

    public function add(self $other): self
    {
        return new self($this->kopecks + $other->kopecks);
    }

    public function subtract(self $other): self
    {
        return new self($this->kopecks - $other->kopecks);
    }

    public function multiply(int $factor): self
    {
        return new self($this->kopecks * $factor);
    }

    /**
     * Adds a markup, rounding fractions of a kopeck up: 1000 ₽ + 35% = 1350 ₽.
     */
    public function withMarkup(Percent $percent): self
    {
        return new self(self::ceilDiv($this->kopecks * (Percent::SCALE + $percent->basisPoints), Percent::SCALE));
    }

    /**
     * Subtracts a discount, rounding fractions of a kopeck up: 1000 ₽ − 10% = 900 ₽.
     */
    public function withDiscount(Percent $percent): self
    {
        return new self(self::ceilDiv($this->kopecks * (Percent::SCALE - $percent->basisPoints), Percent::SCALE));
    }

    /**
     * Rounds up to a multiple of the given number of whole rubles.
     */
    public function roundUpToRubles(int $rubles = 1): self
    {
        if ($rubles < 1) {
            throw new InvalidArgumentException("Rounding step must be at least 1 ruble, [{$rubles}] given.");
        }

        return new self(self::ceilDiv($this->kopecks, $rubles * 100) * $rubles * 100);
    }

    public function isZero(): bool
    {
        return $this->kopecks === 0;
    }

    public function isNegative(): bool
    {
        return $this->kopecks < 0;
    }

    public function equals(self $other): bool
    {
        return $this->kopecks === $other->kopecks;
    }

    public function greaterThan(self $other): bool
    {
        return $this->kopecks > $other->kopecks;
    }

    public function lessThan(self $other): bool
    {
        return $this->kopecks < $other->kopecks;
    }

    public function jsonSerialize(): string
    {
        return $this->toDecimal();
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private static function ceilDiv(int $dividend, int $divisor): int
    {
        $quotient = intdiv($dividend, $divisor);

        if ($dividend % $divisor !== 0 && ($dividend > 0) === ($divisor > 0)) {
            return $quotient + 1;
        }

        return $quotient;
    }
}
