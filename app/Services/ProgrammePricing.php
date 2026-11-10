<?php

namespace App\Services;

class ProgrammePricing
{
    public function cents(string|int|float $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');
        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function preview(array $data): array
    {
        $individual = $data['regular_format'] === 'individual';
        $lessons = $individual ? 1 : (int) $data['lessons_per_term'];
        $prices = $individual ? ['Individual lesson' => $data['individual_price']]
            : ['Standard group' => $data['standard_price'], 'Small group' => $data['small_group_price']];
        $rows = [];
        foreach ($prices as $label => $price) {
            $lesson = $this->cents($price);
            $regular = $lesson * $lessons;
            $base = ($data['discount_applies'] ?? 'lesson') === 'term' ? $regular : $lesson;
            $discount = match ($data['discount_type'] ?? 'none') {
                'percent' => intdiv($base * $this->cents($data['discount_value']) + 5000, 10000),
                'fixed' => $this->cents($data['discount_value']),
                default => 0,
            };
            $discount = min($base, max(0, $discount));
            $rows[] = ['label' => $label, 'regular' => $regular, 'discount' => $discount,
                'total' => $regular - $discount, 'period' => $individual ? 'First lesson' : 'First term'];
        }
        return $rows;
    }
}
