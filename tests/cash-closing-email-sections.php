<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Services/MailService.php';

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$reflection = new ReflectionClass(\App\Services\MailService::class);
$mail = $reflection->newInstanceWithoutConstructor();
$render = $reflection->getMethod('renderCashClosing');
$data = [
    'company_name' => 'Empresa de prueba', 'shift_id' => 2,
    'cashier' => 'Ana', 'closed_at' => '2026-10-30 10:00:00',
    'sales_total' => 100, 'expected_cash' => 100, 'counted_cash' => 99,
    'observation' => 'Nota de prueba',
];

[$subject, $defaultHtml, $defaultText] = $render->invoke($mail, $data);
check(str_contains($subject, 'Empresa de prueba'), 'company missing from subject');
foreach (['Resumen financiero', 'Ventas del turno', 'TALLER', 'Cuentas por cobrar', 'Gastos', 'Entradas y retiros', 'Nota de prueba'] as $label) {
    check(str_contains($defaultHtml, $label), 'default section missing: ' . $label);
}
check(str_contains($defaultText, 'Efectivo esperado'), 'default plain text missing');

$data['email_sections'] = array_fill_keys(
    ['overview', 'financial', 'sales', 'workshop', 'receivables', 'expenses', 'movements', 'observation'],
    false
);
[$ignored, $emptyHtml, $emptyText] = $render->invoke($mail, $data);
foreach (['Resumen financiero', 'Ventas del turno', 'TALLER', 'Cuentas por cobrar', 'Gastos', 'Entradas y retiros', 'Nota de prueba', 'CAJERO'] as $label) {
    check(!str_contains($emptyHtml, $label), 'disabled section still present: ' . $label);
}
check(str_contains($emptyHtml, 'Empresa de prueba'), 'company header removed');
check(str_contains($emptyHtml, 'Reporte generado automaticamente'), 'footer removed');
check(!str_contains($emptyText, 'Efectivo esperado') && !str_contains($emptyText, 'Taller total'), 'disabled text still present');

$data['email_sections']['observation'] = true;
[$ignored, $html] = $render->invoke($mail, $data);
check(str_contains($html, 'Nota de prueba'), 'observation lost when movements disabled');
check(!str_contains($html, 'Entradas y retiros'), 'movement section still present');

echo "cash closing email sections: ok\n";
