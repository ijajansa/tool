<?php

namespace App\Services;

class InvoiceCalculator
{
    public function calculate(array $items, float $shipping = 0, float $additional = 0): array
    {
        $subtotal = $discount = $tax = 0.0;
        $lines = [];
        foreach ($items as $item) {
            $base = round((float) $item['quantity'] * (float) $item['price'], 2);
            $lineDiscount = round($base * (float) ($item['discount'] ?? 0) / 100, 2);
            $taxable = $base - $lineDiscount;
            $lineTax = round($taxable * (float) ($item['tax'] ?? 0) / 100, 2);
            $lines[] = array_merge($item, compact('base', 'taxable'), ['discount_amount' => $lineDiscount, 'tax_amount' => $lineTax, 'total' => round($taxable + $lineTax, 2)]);
            $subtotal += $base;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }
        $total = round($subtotal - $discount + $tax + $shipping + $additional, 2);

        return compact('lines', 'subtotal', 'discount', 'tax', 'shipping', 'additional', 'total');
    }
}
