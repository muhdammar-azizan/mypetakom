<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV downloads that open correctly in Excel and cannot run formulas. */
class Csv
{
    /**
     * @param  list<string>  $headings
     * @param  iterable<list<mixed>>  $rows  each row is a plain list, in the same order as $headings
     */
    public static function download(string $filename, array $headings, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headings, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\u{FEFF}"); // UTF-8 byte order mark, so Excel reads names correctly
            fputcsv($out, $headings);

            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($cell) => self::safeCell($cell), array_values($row)));
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** The same CSV text as download(), as a string. */
    public static function toString(array $headings, iterable $rows): string
    {
        $out = fopen('php://temp', 'w+');
        fwrite($out, "\u{FEFF}");
        fputcsv($out, $headings);

        foreach ($rows as $row) {
            fputcsv($out, array_map(fn ($cell) => self::safeCell($cell), array_values($row)));
        }

        rewind($out);
        $text = stream_get_contents($out);
        fclose($out);

        return $text;
    }

    /** A cell that starts with = + - @ is stored as plain text, so a spreadsheet does not treat it as a formula. */
    public static function safeCell(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
