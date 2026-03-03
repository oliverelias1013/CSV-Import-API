<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class CsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_example(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }

    private function makeCsv(string $content): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'csv_test_');
        file_put_contents($path, $content);
        return new UploadedFile($path, 'test.csv', 'text/csv', null, true);
    }

    // --- Happy path ---

    public function test_fully_valid_csv_imports_all_rows(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "Alice Smith,alice@example.com,1990-05-10,50000\n";
        $csv .= "Bob Jones,bob@example.com,1985-03-22,75000\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);

        $response->assertStatus(200)
            ->assertJson([
                'total_rows_processed' => 2,
                'imported_count' => 2,
                'failed_count' => 0,
                'errors' => [],
            ]);

        $this->assertDatabaseCount('customers', 2);
    }

    // --- Partial import ---

    public function test_mixed_csv_imports_valid_rows_and_reports_invalid(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "Alice Smith,alice@example.com,1990-05-10,50000\n"; // row 2 - valid
        $csv .= ",bademail,,,\n";                                     // row 3 - invalid (wrong cols)

        // Actually let's do a proper mix:
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "Alice Smith,alice@example.com,1990-05-10,50000\n";   // valid
        $csv .= ",not-an-email,2099-01-01,-100\n";                     // all fields bad

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(2, $data['total_rows_processed']);
        $this->assertEquals(1, $data['imported_count']);
        $this->assertEquals(1, $data['failed_count']);
        $this->assertDatabaseCount('customers', 1);
        $this->assertDatabaseHas('customers', ['email' => 'alice@example.com']);

        // Row 3 should have multiple errors
        $this->assertCount(1, $data['errors']);
        $this->assertEquals(3, $data['errors'][0]['row']);
        $this->assertGreaterThan(1, count($data['errors'][0]['errors']));
    }

    // --- Duplicate emails within file ---

    public function test_duplicate_emails_within_file_both_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "Alice,alice@example.com,,\n";   // row 2
        $csv .= "Bob,bob@example.com,,\n";        // row 3 - valid
        $csv .= "Alice2,alice@example.com,,\n";   // row 4 - duplicate of row 2

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(1, $data['imported_count']); // only bob
        $this->assertEquals(2, $data['failed_count']);   // both alices

        $failedRows = array_column($data['errors'], 'row');
        $this->assertContains(2, $failedRows);
        $this->assertContains(4, $failedRows);
    }

    // --- Duplicate against DB ---

    public function test_duplicate_email_against_existing_db_record(): void
    {
        Customer::create(['name' => 'Existing', 'email' => 'existing@example.com']);

        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "New Person,existing@example.com,,\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertEquals(0, $data['imported_count']);
        $this->assertEquals(1, $data['failed_count']);

        $emailErrors = array_filter($data['errors'][0]['errors'], fn($e) => $e['field'] === 'email');
        $this->assertNotEmpty($emailErrors);
    }

    // --- Individual validation rules ---

    public function test_missing_name_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= ",valid@example.com,,\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $fields = array_column($data['errors'][0]['errors'], 'field');
        $this->assertContains('name', $fields);
    }

    public function test_missing_email_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John Doe,,,\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $fields = array_column($data['errors'][0]['errors'], 'field');
        $this->assertContains('email', $fields);
    }

    public function test_invalid_email_format_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John Doe,not-an-email,,\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $fields = array_column($data['errors'][0]['errors'], 'field');
        $this->assertContains('email', $fields);
    }

    public function test_future_date_of_birth_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John Doe,john@example.com,2099-01-01,\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $fields = array_column($data['errors'][0]['errors'], 'field');
        $this->assertContains('date_of_birth', $fields);
    }

    public function test_negative_annual_income_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John Doe,john@example.com,,-500\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $fields = array_column($data['errors'][0]['errors'], 'field');
        $this->assertContains('annual_income', $fields);
    }

    public function test_zero_annual_income_flagged(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John Doe,john@example.com,,0\n";

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
    }

    // --- Edge cases ---

    public function test_empty_file_returns_error(): void
    {
        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv('')]);
        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_headers_only_returns_error(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_wrong_headers_returns_error(): void
    {
        $csv = "foo,bar,baz\n";
        $csv .= "a,b,c\n";
        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $response->assertStatus(422)->assertJsonStructure(['error']);
    }

    public function test_malformed_row_wrong_column_count(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "John,john@example.com\n"; // only 2 columns

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
    }

    public function test_empty_rows_skipped_silently(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= "Alice,alice@example.com,1990-01-01,50000\n";
        $csv .= ",,, \n"; // empty row
        $csv .= "\n";      // blank line

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['total_rows_processed']);
        $this->assertEquals(1, $data['imported_count']);
    }

    public function test_all_errors_per_row_reported_not_just_first(): void
    {
        $csv = "name,email,date_of_birth,annual_income\n";
        $csv .= ",bad-email,2099-01-01,-50\n"; // 4 errors

        $response = $this->postJson('/api/import', [], [], ['file' => $this->makeCsv($csv)]);
        $data = $response->json();

        $this->assertEquals(1, $data['failed_count']);
        $this->assertGreaterThanOrEqual(4, count($data['errors'][0]['errors']));
    }

    // --- Customers list ---

    public function test_customers_list_paginated(): void
    {
        Customer::factory()->count(20)->create();

        $response = $this->getJson('/api/customers?per_page=5');
        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'current_page', 'total', 'per_page']);

        $this->assertCount(5, $response->json('data'));
    }
}
