<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/** Reads a CSV of students, checks every row, and creates the accounts for the rows that are fine. */
class StudentImporter
{
    /** Password hashing is slow on purpose, so one import is kept small: about 0.3 seconds per student, well inside the usual 30 second web limit at 50 rows. */
    public const MAX_ROWS = 50;

    public const COLUMNS = ['name', 'email', 'student_id', 'programme'];

    /**
     * Read the file into rows. Nothing is saved.
     *
     * @return array{rows: list<array<string, mixed>>, error: ?string}
     */
    public function parse(string $path): array
    {
        $handle = fopen($path, 'r');
        if (! $handle) {
            return ['rows' => [], 'error' => 'The file could not be read.'];
        }

        $first = fgets($handle);
        if ($first === false || trim($first) === '') {
            fclose($handle);

            return ['rows' => [], 'error' => 'The file is empty.'];
        }

        $first = $this->utf8(preg_replace('/^\xEF\xBB\xBF/', '', $first));
        $delimiter = $this->delimiter($first);
        rewind($handle);
        // Skip the byte order mark that Excel adds, if any.
        if (fread($handle, 3) !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $header = fgetcsv($handle, 0, $delimiter);
        $columns = array_map(fn ($h) => $this->normalizeHeader((string) $h), $header ?: []);

        $missing = array_values(array_diff(self::COLUMNS, $columns));
        if ($missing) {
            fclose($handle);

            return ['rows' => [], 'error' => 'Missing column(s): '.implode(', ', $missing).'. The first row must be: '.implode(', ', self::COLUMNS).'.'];
        }

        $rows = [];
        $line = 1;

        while (($cells = fgetcsv($handle, 0, $delimiter)) !== false) {
            $line++;

            // Skip blank lines.
            if (count(array_filter($cells, fn ($c) => trim((string) $c) !== '')) === 0) {
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'error' => 'Too many rows. Import at most '.self::MAX_ROWS.' students at a time.'];
            }

            $row = ['line' => $line];
            foreach (self::COLUMNS as $name) {
                $value = $cells[array_search($name, $columns, true)] ?? '';
                $row[$name] = trim($this->utf8((string) $value));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows
            ? ['rows' => $rows, 'error' => null]
            : ['rows' => [], 'error' => 'The file has a header row but no students.'];
    }

    /**
     * Clean up every row and add an "errors" list to it (empty when the row is fine).
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function check(array $rows): array
    {
        $rows = array_map(function (array $row) {
            $row['email'] = strtolower($row['email']);
            $row['student_id'] = strtoupper($row['student_id']);

            return $row;
        }, $rows);

        $takenEmails = User::whereIn('email', array_column($rows, 'email'))->pluck('email')->map(fn ($e) => strtolower($e))->all();
        $takenIds = User::whereIn('student_id', array_column($rows, 'student_id'))->pluck('student_id')->all();

        $seenEmails = [];
        $seenIds = [];

        foreach ($rows as &$row) {
            $validator = Validator::make($row, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255'],
                'student_id' => ['required', 'string', 'max:20'],
                'programme' => ['required', 'string', 'max:255'],
            ], [], ['student_id' => 'student ID']);

            $errors = $validator->errors()->all();

            if (in_array($row['email'], $takenEmails, true)) {
                $errors[] = 'This email already has an account.';
            } elseif (isset($seenEmails[$row['email']])) {
                $errors[] = 'Same email as row '.$seenEmails[$row['email']].' in this file.';
            }

            if (in_array($row['student_id'], $takenIds, true)) {
                $errors[] = 'This student ID already has an account.';
            } elseif ($row['student_id'] !== '' && isset($seenIds[$row['student_id']])) {
                $errors[] = 'Same student ID as row '.$seenIds[$row['student_id']].' in this file.';
            }

            $seenEmails[$row['email']] ??= $row['line'];
            $seenIds[$row['student_id']] ??= $row['line'];

            $row['errors'] = $errors;
        }
        unset($row);

        return $rows;
    }

    /**
     * Create an account (with a random password) for each row without errors.
     *
     * @param  list<array<string, mixed>>  $rows  rows that went through check()
     * @return list<array{user: User, password: string}>
     */
    public function create(array $rows): array
    {
        $created = [];

        DB::transaction(function () use ($rows, &$created) {
            foreach ($rows as $row) {
                $password = Str::password(10, letters: true, numbers: true, symbols: false);

                $user = User::create([
                    'name' => $row['name'],
                    'email' => $row['email'],
                    'student_id' => $row['student_id'],
                    'programme' => $row['programme'],
                    'role' => User::ROLE_STUDENT,
                    'password' => Hash::make($password),
                ]);

                $created[] = ['user' => $user, 'password' => $password];
            }
        });

        return $created;
    }

    /** "Student ID", "student-id" and "STUDENT_ID" all mean student_id. */
    private function normalizeHeader(string $header): string
    {
        return Str::of($this->utf8(preg_replace('/^\xEF\xBB\xBF/', '', $header)))->lower()->trim()->replace([' ', '-'], '_')->toString();
    }

    /** Excel in many countries saves CSV with ";" instead of ",". */
    private function delimiter(string $firstLine): string
    {
        $counts = [',' => substr_count($firstLine, ','), ';' => substr_count($firstLine, ';'), "\t" => substr_count($firstLine, "\t")];
        arsort($counts);

        return array_key_first($counts) ?: ',';
    }

    /** Files saved by older Excel versions use Windows-1252, not UTF-8. */
    private function utf8(string $text): string
    {
        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }
}
