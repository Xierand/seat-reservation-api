<?php

namespace App\Services;

use App\Enums\SeatLabelSequence;
use InvalidArgumentException;

class SeatLabelGenerator
{
    public function value(?SeatLabelSequence $sequence, int $index): string
    {
        if ($sequence === null) {
            return '';
        }

        if ($index < 1) {
            throw new InvalidArgumentException('Sequence index must be >= 1.');
        }

        return match ($sequence) {
            SeatLabelSequence::ALPHABET => $this->alphabet($index),
            SeatLabelSequence::NUMBER => (string) $index,
            SeatLabelSequence::ROMAN => $this->roman($index),
        };
    }

    public function compose(
        ?SeatLabelSequence $prefix,
        ?string $name,
        ?SeatLabelSequence $suffix,
        int $index,
    ): string {
        return $this->value($prefix, $index)
            .($name ?? '')
            .$this->value($suffix, $index);
    }

    public function labels(
        ?SeatLabelSequence $prefix,
        ?string $name,
        ?SeatLabelSequence $suffix,
        int $count,
    ): array {
        $labels = [];

        for ($i = 1; $i <= $count; $i++) {
            $labels[] = $this->compose($prefix, $name, $suffix, $i);
        }

        return $labels;
    }

    private function alphabet(int $index): string
    {
        $label = '';

        while ($index > 0) {
            $index--;
            $label = chr(65 + ($index % 26)).$label;
            $index = intdiv($index, 26);
        }

        return $label;
    }

    private function roman(int $number): string
    {
        $map = [
            1000 => 'M',
            900 => 'CM',
            500 => 'D',
            400 => 'CD',
            100 => 'C',
            90 => 'XC',
            50 => 'L',
            40 => 'XL',
            10 => 'X',
            9 => 'IX',
            5 => 'V',
            4 => 'IV',
            1 => 'I',
        ];

        $result = '';

        foreach ($map as $value => $numeral) {
            while ($number >= $value) {
                $result .= $numeral;
                $number -= $value;
            }
        }

        return $result;
    }
}
