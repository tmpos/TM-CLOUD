<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

if (!class_exists(\Mpdf\Mpdf::class)) {
    fwrite(STDERR, "mPDF is not available.\n");
    exit(1);
}
if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
    fwrite(STDERR, "PHPMailer is not available.\n");
    exit(1);
}

$pdf = new \Mpdf\Mpdf([
    'tempDir' => sys_get_temp_dir(),
    'format' => 'Letter',
]);
$pdf->WriteHTML('<h1>Prueba TMPBase</h1><p>Generador PDF funcionando.</p><barcode code="https://fc.dgii.gov.do/ecf/ConsultaTimbreFC?RncEmisor=133130343&amp;ENCF=E320000000005&amp;MontoTotal=4487.78&amp;CodigoSeguridad=AJIz2R" type="QR" size="1" error="M" disableborder="1" />');
$content = $pdf->Output('', 'S');

if (!str_starts_with($content, '%PDF-') || strlen($content) < 1000) {
    fwrite(STDERR, "mPDF did not generate a valid PDF document.\n");
    exit(1);
}

echo 'PDF_SMOKE=OK bytes=' . strlen($content) . PHP_EOL;
echo 'PHPMAILER=OK' . PHP_EOL;
