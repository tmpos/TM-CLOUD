<?php
declare(strict_types=1);
namespace App\Services;

final class FiscalLineTaxes
{
    public static function calculate(array $payload, array $items): ?array
    {
        $lines = $payload['itemDetails'] ?? [];
        if (!$lines || count($lines) !== count($items)) return null;
        foreach ($lines as $i => $line) {
            if ((int) ($line['lineNumber'] ?? 0) !== $i + 1
                || mb_strtoupper(trim((string) ($line['itemName'] ?? ''))) !== mb_strtoupper(trim((string) ($items[$i]['nombre'] ?? $items[$i]['descripcion'] ?? '')))
                || (float) ($line['quantityItem'] ?? 0) !== (float) ($items[$i]['cantidad'] ?? $items[$i]['quantity'] ?? 0)
                || !is_numeric($line['itemAmount'] ?? null) || (float) $line['itemAmount'] < 0
                || !in_array((int) ($line['billingIndicator'] ?? 0), [1, 2, 3, 4], true)) return null;
        }
        $taxes = array_fill(0, count($items), 0.0);
        foreach ([1, 2, 3] as $group) {
            $indices = array_keys(array_filter($lines, static fn ($line) => (int) $line['billingIndicator'] === $group));
            if (!$indices) continue;
            $tax = $payload['totals']['itbis' . $group . 'Total'] ?? ($group === 3 ? 0 : null);
            if (!is_numeric($tax) || (float) $tax < 0) return null;
            $cents = (int) round((float) $tax * 100);
            $base = array_sum(array_map(static fn ($i) => (float) $lines[$i]['itemAmount'], $indices));
            if (!$base && $cents) return null;
            $cumulative = 0;
            $allocated = 0;
            foreach ($indices as $i) {
                $cumulative += (float) $lines[$i]['itemAmount'];
                $next = $base ? (int) round($cents * $cumulative / $base) : 0;
                $taxes[$i] = ($next - $allocated) / 100;
                $allocated = $next;
            }
        }
        return round(array_sum($taxes) * 100) === round((float) ($payload['totals']['itbisTotal'] ?? -1) * 100) ? $taxes : null;
    }
}
