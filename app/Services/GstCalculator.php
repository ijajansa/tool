<?php

namespace App\Services;

class GstCalculator
{
    public function calculate(array $items, bool $interstate): array
    {
        $taxable = $cgst = $sgst = $igst = 0.0;
        $lines = [];
        foreach ($items as $item) {
            $value = round((float) $item['quantity'] * (float) $item['price'] - (float) ($item['discount'] ?? 0), 2);
            $rate = (float) $item['gst_rate'];
            $lineTax = round($value * $rate / 100, 2);
            $line = array_merge($item, ['taxable' => $value, 'tax' => $lineTax]);
            if ($interstate) {
                $line['igst'] = $lineTax;
                $igst += $lineTax;
            } else {
                $line['cgst'] = round($lineTax / 2, 2);
                $line['sgst'] = round($lineTax - $line['cgst'], 2);
                $cgst += $line['cgst'];
                $sgst += $line['sgst'];
            }
            $taxable += $value;
            $lines[] = $line;
        }
        $gross = round($taxable + $cgst + $sgst + $igst, 2);
        $rounded = round($gross);

        return compact('lines', 'taxable', 'cgst', 'sgst', 'igst', 'gross', 'rounded') + ['round_off' => round($rounded - $gross, 2), 'total' => $rounded];
    }
}
