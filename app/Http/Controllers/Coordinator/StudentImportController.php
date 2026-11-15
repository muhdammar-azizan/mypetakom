<?php

namespace App\Http\Controllers\Coordinator;

use App\Http\Controllers\Controller;
use App\Services\StudentImporter;
use App\Support\Activity;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Create many student accounts from one CSV file: upload, check the preview, then confirm. */
class StudentImportController extends Controller
{
    private const SESSION_KEY = 'student_import';

    public function __construct(private StudentImporter $importer) {}

    /** Upload form, or the preview when a file has been checked. */
    public function create(Request $request): View
    {
        return view('users.import', [
            'preview' => $request->session()->get(self::SESSION_KEY),
            'maxRows' => StudentImporter::MAX_ROWS,
        ]);
    }

    /** An empty CSV with the right columns and two example rows. */
    public function template(): StreamedResponse
    {
        return Csv::download('students-import-template.csv', StudentImporter::COLUMNS, [
            ['Aina Sofea binti Ahmad', 'cb24001@student.example.edu.my', 'CB24001', 'BCS (Software Engineering)'],
            ['Muhammad Hakim bin Rosli', 'cb24002@student.example.edu.my', 'CB24002', 'BCS (Cyber Security)'],
        ]);
    }

    /** Read and check the file. Nothing is saved yet. */
    public function preview(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:512'],
        ], [
            'file.mimes' => 'Please upload a .csv file.',
            'file.max' => 'The file is too large (500 KB at most).',
        ]);

        $parsed = $this->importer->parse($request->file('file')->getRealPath());

        if ($parsed['error']) {
            $request->session()->forget(self::SESSION_KEY);

            return back()->withErrors(['file' => $parsed['error']]);
        }

        $rows = $this->importer->check($parsed['rows']);

        $request->session()->put(self::SESSION_KEY, [
            'token' => Str::random(40),
            'filename' => $request->file('file')->getClientOriginalName(),
            'rows' => $rows,
        ]);

        return redirect()->route('users.import');
    }

    /** Create the accounts for the rows that passed. */
    public function store(Request $request): Response|RedirectResponse
    {
        $request->validate(['token' => ['required', 'string']]);

        $saved = $request->session()->get(self::SESSION_KEY);

        // The preview is used once: a refresh or a second click cannot import the same file again.
        if (! $saved || ! hash_equals($saved['token'], (string) $request->input('token'))) {
            return redirect()->route('users.import')->with('toast', 'That import has already been done or has expired. Upload the file again.');
        }

        $request->session()->forget(self::SESSION_KEY);

        // Check again: someone may have registered with the same email or ID since the preview.
        $rows = $this->importer->check(array_map(fn ($row) => array_diff_key($row, ['errors' => 1]), $saved['rows']));
        $valid = array_values(array_filter($rows, fn ($row) => ! $row['errors']));
        $skipped = array_values(array_filter($rows, fn ($row) => $row['errors']));

        $created = $valid ? $this->importer->create($valid) : [];

        if ($created) {
            Activity::log('user.imported', 'Imported '.count($created).' student '.Str::plural('account', count($created)).' from "'.$saved['filename'].'" ('.count($skipped).' skipped).');
        }

        $credentials = Csv::toString(
            ['Name', 'Student ID', 'Email', 'Password'],
            array_map(fn ($c) => [$c['user']->name, $c['user']->student_id, $c['user']->email, $c['password']], $created),
        );

        // Rendered straight from this request and never cached: the passwords exist nowhere else.
        return response()->view('users.import-result', [
            'created' => $created,
            'skipped' => $skipped,
            'credentials' => $credentials,
            'filename' => $saved['filename'],
        ])->header('Cache-Control', 'no-store, private');
    }

    /** Throw the preview away. */
    public function cancel(Request $request): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('users.import');
    }
}
