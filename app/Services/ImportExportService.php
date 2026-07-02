<?php

declare(strict_types=1);

namespace App\Services;

final class ImportExportService
{
    public function __construct(private RecordService $records)
    {
    }

    public function parse(string $content, string $format): array
    {
        if ($format === 'json') {
            $rows = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($rows) || (!array_is_list($rows) && $rows !== [])) {
                throw new \InvalidArgumentException('JSON import must be an array of objects.');
            }
            return $rows;
        }
        if ($format !== 'csv') {
            throw new \InvalidArgumentException('Only JSON and CSV imports are supported.');
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);
        $headers = fgetcsv($stream);
        if (!$headers) {
            return [];
        }
        $rows = [];
        while (($values = fgetcsv($stream)) !== false) {
            if (count($values) !== count($headers)) {
                throw new \InvalidArgumentException('A CSV row has a different number of columns than its header.');
            }
            $rows[] = array_combine($headers, $values);
        }
        fclose($stream);
        return $rows;
    }

    public function export(array $rows, string $format): string
    {
        if ($format === 'json') {
            return json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        }
        if ($format !== 'csv') {
            throw new \InvalidArgumentException('Only JSON and CSV exports are supported.');
        }
        $stream = fopen('php://temp', 'r+');
        if ($rows) {
            fputcsv($stream, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($stream, $row);
            }
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return (string) $content;
    }
}
