<?php

declare(strict_types=1);

namespace App\Services;

final class PdfService
{
    public function invoice(array $project, string $table, array $record): string
    {
        $recordUid = $record['uid'] ?? bin2hex(random_bytes(8));
        $now = date('d/m/Y H:i:s');

        $rows = '';
        foreach ($record as $key => $value) {
            if ($key === 'id') continue;
            $label = ucfirst(str_replace('_', ' ', $key));
            $display = is_string($value) ? $value : (is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE));
            $rows .= <<<HTML
                <tr>
                    <td style="padding:6px 10px;border:1px solid #ddd;background:#f8f9fa;font-weight:600;color:#333;width:200px">{$label}</td>
                    <td style="padding:6px 10px;border:1px solid #ddd;color:#555">{$display}</td>
                </tr>
            HTML;
        }

        $projectName = $project['name'];

        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>Factura {$recordUid}</title>
            <style>
                body { font-family: 'Helvetica', 'Arial', sans-serif; font-size: 12px; color: #333; margin: 0; padding: 30px; }
                .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #2dd4bf; padding-bottom: 15px; }
                .header h1 { margin: 0; font-size: 24px; color: #111; }
                .header p { margin: 5px 0 0; color: #666; font-size: 14px; }
                table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .footer { margin-top: 40px; text-align: center; color: #999; font-size: 11px; border-top: 1px solid #eee; padding-top: 15px; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>FACTURA</h1>
                <p>{$projectName}</p>
            </div>
            <table>{$rows}</table>
            <div class="footer">
                Generado el {$now} &mdash; {$projectName}
            </div>
        </body>
        </html>
        HTML;

        try {
            $mpdf = new \Mpdf\Mpdf(['tempDir' => sys_get_temp_dir()]);
            $mpdf->WriteHTML($html);
            return $mpdf->Output('', 'S');
        } catch (\Throwable) {
            return $html;
        }
    }
}
