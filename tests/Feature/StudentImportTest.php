<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\StudentImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StudentImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = "name,email,student_id,programme\n";

    private function coordinator(): User
    {
        return User::factory()->coordinator()->create();
    }

    private function csv(string $content, string $name = 'students.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    /** Upload a file as the coordinator and return the preview data shown on the next page. */
    private function preview(string $content, ?User $by = null): array
    {
        $by ??= $this->coordinator();

        $this->actingAs($by)->post('/users/import/preview', ['file' => $this->csv($content)])->assertRedirect('/users/import');

        return $this->actingAs($by)->get('/users/import')->assertOk()->viewData('preview');
    }

    private function confirm(array $preview, ?User $by = null)
    {
        return $this->actingAs($by ?? $this->coordinator())->post('/users/import', ['token' => $preview['token']]);
    }

    private function studentCount(): int
    {
        return User::where('role', 'student')->count();
    }

    // ---- Access ----

    public function test_only_the_coordinator_can_use_the_import(): void
    {
        $this->get('/users/import')->assertRedirect('/login');

        foreach ([User::factory()->create(), User::factory()->advisor()->create()] as $user) {
            $this->actingAs($user)->get('/users/import')->assertForbidden();
            $this->actingAs($user)->get('/users/import/template')->assertForbidden();
            $this->actingAs($user)->post('/users/import/preview', ['file' => $this->csv(self::HEADER)])->assertForbidden();
            $this->actingAs($user)->post('/users/import', ['token' => 'x'])->assertForbidden();
        }

        $this->actingAs($this->coordinator())->get('/users/import')->assertOk()->assertSee('Upload a CSV file');
    }

    public function test_import_is_not_mistaken_for_a_user_id_and_button_is_on_the_users_page(): void
    {
        $coordinator = $this->coordinator();

        $this->actingAs($coordinator)->get('/users')->assertSee('Import students (CSV)')->assertSee(route('users.import'), false);
        $this->actingAs($coordinator)->get('/users/import')->assertOk();   // not 404 from /users/{user}
    }

    public function test_template_has_the_right_columns(): void
    {
        $csv = $this->actingAs($this->coordinator())->get('/users/import/template')->assertOk()->assertDownload('students-import-template.csv')->streamedContent();

        $this->assertStringStartsWith("\u{FEFF}name,email,student_id,programme", $csv);
        $this->assertStringContainsString('CB24001', $csv);
    }

    // ---- Preview ----

    public function test_preview_shows_every_row_and_saves_nothing(): void
    {
        $before = $this->studentCount();

        $preview = $this->preview(self::HEADER."Aina Sofea,aina@example.test,cb24001,BCS (SE)\nHakim Rosli,hakim@example.test,CB24002,BCS (CS)\n");

        $this->assertCount(2, $preview['rows']);
        $this->assertSame([], $preview['rows'][0]['errors']);
        $this->assertSame('CB24001', $preview['rows'][0]['student_id']);     // upper-cased
        $this->assertSame($before, $this->studentCount());                   // nothing saved yet

        $this->actingAs($this->coordinator())->get('/users/import')
            ->assertSee('Aina Sofea')->assertSee('Hakim Rosli')->assertSee('Import 2 students')->assertSee('students.csv');
    }

    public function test_semicolons_bom_header_spelling_and_blank_lines_are_handled(): void
    {
        $content = "\xEF\xBB\xBFName;E-mail;Student ID;Programme\n".   // wrong: header "E-mail" is not "email"
            "A;a@example.test;CB1;BCS\n";
        $bad = $this->coordinator();
        $this->actingAs($bad)->post('/users/import/preview', ['file' => $this->csv($content)])->assertSessionHasErrors('file');

        $good = "\xEF\xBB\xBFName;Email;Student ID;PROGRAMME\n\nAisyah;aisyah@example.test;cb10;BCS\n\n\nBudi;budi@example.test;cb11;BCS\n";
        $preview = $this->preview($good);

        $this->assertCount(2, $preview['rows']);
        $this->assertSame(3, $preview['rows'][0]['line']);   // line numbers match the file, blank lines counted
        $this->assertSame(6, $preview['rows'][1]['line']);
        $this->assertSame('CB10', $preview['rows'][0]['student_id']);
    }

    public function test_windows_1252_files_are_converted(): void
    {
        $content = self::HEADER.mb_convert_encoding("Nur Aisyah Fátimah,fatimah@example.test,CB77,BCS\n", 'Windows-1252', 'UTF-8');

        $preview = $this->preview($content);

        $this->assertSame('Nur Aisyah Fátimah', $preview['rows'][0]['name']);
    }

    public function test_row_problems_are_reported_per_row(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        User::factory()->create(['student_id' => 'CB555']);

        $preview = $this->preview(self::HEADER.implode("\n", [
            'Good One,good@example.test,CB1,BCS',
            ',noname@example.test,CB2,BCS',
            'Bad Email,not-an-email,CB3,BCS',
            'No Programme,np@example.test,CB4,',
            'Taken Email,TAKEN@example.test,CB5,BCS',
            'Taken Id,newid@example.test,cb555,BCS',
            'Dup First,dup@example.test,CB6,BCS',
            'Dup Second,dup@example.test,CB7,BCS',
            'Dup Id,other@example.test,CB6,BCS',
        ])."\n");

        $errors = array_column($preview['rows'], 'errors');

        $this->assertSame([], $errors[0]);
        $this->assertNotEmpty($errors[1]);                                                              // missing name
        $this->assertNotEmpty($errors[2]);                                                              // bad email
        $this->assertNotEmpty($errors[3]);                                                              // no programme
        $this->assertContains('This email already has an account.', $errors[4]);                        // case-insensitive
        $this->assertContains('This student ID already has an account.', $errors[5]);
        $this->assertSame([], $errors[6]);
        $this->assertContains('Same email as row 8 in this file.', $errors[7]);
        $this->assertContains('Same student ID as row 8 in this file.', $errors[8]);

        $this->actingAs($this->coordinator())->get('/users/import')->assertSee('Import 2 students')->assertSee('Show only problems');
    }

    public function test_bad_files_are_rejected_with_a_clear_message(): void
    {
        $coordinator = $this->coordinator();
        $post = fn (UploadedFile $file) => $this->actingAs($coordinator)->post('/users/import/preview', ['file' => $file]);

        $post($this->csv(''))->assertSessionHasErrors(['file' => 'The file is empty.']);
        $post($this->csv("name,email\nA,a@example.test\n"))->assertSessionHasErrors('file');
        $this->assertStringContainsString('student_id, programme', session('errors')->first('file'));
        $post($this->csv(self::HEADER))->assertSessionHasErrors(['file' => 'The file has a header row but no students.']);
        $post(UploadedFile::fake()->image('photo.png'))->assertSessionHasErrors('file');
        $post(UploadedFile::fake()->create('big.csv', 600, 'text/csv'))->assertSessionHasErrors('file');
        $post($this->csv(self::HEADER.str_repeat("Name,x@example.test,CB1,BCS\n", StudentImporter::MAX_ROWS + 1)))
            ->assertSessionHasErrors('file');
        $this->assertStringContainsString('Too many rows', session('errors')->first('file'));

        $this->actingAs($coordinator)->post('/users/import/preview', [])->assertSessionHasErrors('file');
    }

    public function test_a_bad_upload_clears_an_earlier_preview(): void
    {
        $coordinator = $this->coordinator();
        $this->preview(self::HEADER."A,a@example.test,CB1,BCS\n", $coordinator);

        $this->actingAs($coordinator)->post('/users/import/preview', ['file' => $this->csv('')])->assertSessionHasErrors('file');

        $this->actingAs($coordinator)->get('/users/import')->assertSee('Upload a CSV file')->assertDontSee('Ready to import');
    }

    public function test_preview_escapes_what_the_file_contains(): void
    {
        $this->preview(self::HEADER."<script>alert(1)</script>,x@example.test,CB1,BCS\n");

        $html = $this->actingAs($this->coordinator())->get('/users/import')->getContent();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_cancel_throws_the_preview_away(): void
    {
        $coordinator = $this->coordinator();
        $this->preview(self::HEADER."A,a@example.test,CB1,BCS\n", $coordinator);

        $this->actingAs($coordinator)->post('/users/import/cancel')->assertRedirect('/users/import');

        $this->actingAs($coordinator)->get('/users/import')->assertSee('Upload a CSV file');
    }

    // ---- Confirm ----

    public function test_confirm_creates_only_the_good_rows_as_students_without_membership(): void
    {
        User::factory()->create(['email' => 'taken@example.test']);
        $coordinator = $this->coordinator();
        $before = $this->studentCount();

        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS (SE)\nTaken,taken@example.test,CB2,BCS\nHakim,hakim@example.test,CB3,BCS (CS)\n", $coordinator);
        $response = $this->confirm($preview, $coordinator)->assertOk();

        $this->assertSame($before + 2, $this->studentCount());
        $aina = User::where('email', 'aina@example.test')->first();
        $this->assertSame('student', $aina->role);
        $this->assertSame('CB1', $aina->student_id);
        $this->assertSame('BCS (SE)', $aina->programme);
        $this->assertNull($aina->membership);
        $this->assertDatabaseMissing('users', ['student_id' => 'CB2']);

        $response->assertSee('2 accounts created')->assertSee('Skipped rows')->assertSee('This email already has an account.');
    }

    public function test_every_created_student_can_log_in_with_the_password_shown_once(): void
    {
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\nHakim,hakim@example.test,CB3,BCS\n", $coordinator);

        $response = $this->confirm($preview, $coordinator);
        $created = $response->viewData('created');

        $this->assertCount(2, $created);
        foreach ($created as $entry) {
            $this->assertSame(10, strlen($entry['password']));
            $this->assertTrue(Hash::check($entry['password'], $entry['user']->fresh()->password));
            $this->assertNotSame($entry['password'], $entry['user']->fresh()->password);   // stored hashed
            $response->assertSee($entry['password']);
        }
        $this->assertNotSame($created[0]['password'], $created[1]['password']);

        auth()->logout();
        $this->post('/login', ['login' => 'CB1', 'password' => $created[0]['password']])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($created[0]['user']->fresh());
    }

    public function test_result_page_is_never_cached_and_has_a_safe_credentials_download(): void
    {
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."=EVIL(),evil@example.test,CB1,BCS\n", $coordinator);

        $response = $this->confirm($preview, $coordinator);

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $csv = $response->viewData('credentials');
        $this->assertStringStartsWith("\u{FEFF}Name,\"Student ID\",Email,Password", $csv);
        $this->assertStringContainsString("'=EVIL()", $csv);   // formula neutralised
        $response->assertSee('shown only once')->assertSee('Download credentials (CSV)');
    }

    public function test_the_same_preview_cannot_be_imported_twice(): void
    {
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\n", $coordinator);

        $this->confirm($preview, $coordinator)->assertOk();
        $after = $this->studentCount();

        $this->confirm($preview, $coordinator)->assertRedirect('/users/import');
        $this->assertSame($after, $this->studentCount());
    }

    public function test_a_wrong_token_imports_nothing(): void
    {
        $coordinator = $this->coordinator();
        $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\n", $coordinator);
        $before = $this->studentCount();

        $this->actingAs($coordinator)->post('/users/import', ['token' => 'forged-token'])->assertRedirect('/users/import');
        $this->actingAs($coordinator)->post('/users/import', [])->assertSessionHasErrors('token');

        $this->assertSame($before, $this->studentCount());
    }

    public function test_one_coordinators_preview_cannot_be_confirmed_by_another_session(): void
    {
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\n", $this->coordinator());
        $before = $this->studentCount();

        // A fresh session (no preview stored) with the same token does nothing.
        $this->flushSession();
        $this->actingAs($this->coordinator())->post('/users/import', ['token' => $preview['token']])->assertRedirect('/users/import');

        $this->assertSame($before, $this->studentCount());
    }

    public function test_rows_are_checked_again_at_confirm_time(): void
    {
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\nHakim,hakim@example.test,CB3,BCS\n", $coordinator);

        // Someone registers with Aina's email after the preview was made.
        User::factory()->create(['email' => 'aina@example.test']);

        $response = $this->confirm($preview, $coordinator)->assertOk();

        $this->assertCount(1, $response->viewData('created'));
        $this->assertCount(1, $response->viewData('skipped'));
        $this->assertDatabaseHas('users', ['email' => 'hakim@example.test']);
        $this->assertDatabaseMissing('users', ['student_id' => 'CB1']);
    }

    public function test_nothing_is_created_when_every_row_has_a_problem(): void
    {
        User::factory()->create(['email' => 'aina@example.test']);
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\n", $coordinator);
        $before = $this->studentCount();

        $this->confirm($preview, $coordinator)->assertOk()->assertSee('No accounts were created');

        $this->assertSame($before, $this->studentCount());
        $this->assertDatabaseMissing('activity_logs', ['action' => 'user.imported']);
    }

    public function test_the_import_is_written_to_the_activity_log(): void
    {
        $coordinator = $this->coordinator();
        $preview = $this->preview(self::HEADER."Aina,aina@example.test,CB1,BCS\nHakim,hakim@example.test,CB3,BCS\n", $coordinator);

        $this->confirm($preview, $coordinator);

        $log = ActivityLog::where('action', 'user.imported')->first();
        $this->assertNotNull($log);
        $this->assertSame($coordinator->id, $log->user_id);
        $this->assertStringContainsString('Imported 2 student accounts', $log->description);
        $this->assertStringContainsString('students.csv', $log->description);
        // Passwords never go into the log.
        $this->assertStringNotContainsString('password', strtolower($log->description));
    }
}
