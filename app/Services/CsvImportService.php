<?php

namespace App\Services;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

class CsvImportService
{
    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $this->fatalError('Could not read the uploaded file.');
        }

        // Read header row
        $header = fgetcsv($handle);

        if ($header === false || $header === null) {
            fclose($handle);
            return $this->fatalError('The file is empty.');
        }

        // Normalize headers

        $header = array_filter(
            array_map(fn($h) => strtolower(trim($h)), $header),
            fn($h) => $h !== ''
        );
        $header = array_values($header);
        $expectedColumnCount = count($header);
        $expectedHeaders = ['name', 'email', 'date_of_birth', 'annual_income'];

        if (array_diff($expectedHeaders, $header) !== []) {
            fclose($handle);
            return $this->fatalError(
                'Invalid CSV format. Expected headers: ' . implode(', ', $expectedHeaders) . '. Got: ' . implode(', ', $header)
            );
        }

        // Read all rows first (to detect in-file duplicates on both occurrences)
        $rawRows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rawRows[] = $row;
        }
        fclose($handle);

        if (count($rawRows) === 0) {
            return $this->fatalError('The file contains only a header row with no data.');
        }

        $emailRowMap = [];
        $csvRows = [];
        $rowNumber = 1;

        foreach ($rawRows as $raw) {
            $rowNumber++;

            $isEmpty = count(array_filter($raw, fn($v) => trim($v) !== '')) === 0;
            if ($isEmpty) {
                continue;
            }
            
            $nonEmptyKeys = array_keys(array_filter($raw, fn($v) => trim($v) !== ''));
            $trimmedRaw = array_slice($raw, 0, max($nonEmptyKeys) + 1);

            if (count($trimmedRaw) !== $expectedColumnCount) {
                $csvRows[] = [
                    'row_number' => $rowNumber,
                    'data' => null,
                    'malformed' => true,
                    'column_count' => count($trimmedRaw),
                ];
                continue;
            }

            $data = array_combine($header, $trimmedRaw);
            $email = strtolower(trim($data['email'] ?? ''));

            if ($email !== '') {
                $emailRowMap[$email][] = $rowNumber;
            }

            $csvRows[] = [
                'row_number' => $rowNumber,
                'data' => $data,
                'malformed' => false,
            ];
        }

        if (count($csvRows) === 0) {
            return $this->fatalError('The file contains only empty rows.');
        }

        $inFileDuplicates = array_filter($emailRowMap, fn($rows) => count($rows) > 1);
        $existingEmails = Customer::pluck('email')->map(fn($e) => strtolower($e))->toArray();

        $errors = [];
        $imported = [];
        $failedRowNumbers = [];

        foreach ($csvRows as $csvRow) {
            $rowNumber = $csvRow['row_number'];
            if ($csvRow['malformed']) {
                $errors[] = [
                    'row' => $rowNumber,
                    'errors' => [[
                        'field' => 'row',
                        'message' => "Row has {$csvRow['column_count']} columns but expected {$expectedColumnCount}.",
                    ]],
                ];
                $failedRowNumbers[] = $rowNumber;
                continue;
            }

            $data = $csvRow['data'];
            $email = strtolower(trim($data['email'] ?? ''));
            $rowErrors = [];

            // Check in-file duplicate for this row
            if ($email !== '' && isset($inFileDuplicates[$email])) {
                $otherRows = array_filter($inFileDuplicates[$email], fn($r) => $r !== $rowNumber);
                $rowErrors[] = [
                    'field' => 'email',
                    'message' => "Email '{$email}' is duplicated in this file (also on row(s): " . implode(', ', $otherRows) . ').',
                ];
                // Validate other fields too
                $otherErrors = $this->validateOtherFields($data, $existingEmails, skipEmailCheck: true);
                $rowErrors = array_merge($rowErrors, $otherErrors);
            } else {
                $rowErrors = $this->validateAllFields($data, $existingEmails);
            }

            if (count($rowErrors) > 0) {
                $errors[] = ['row' => $rowNumber, 'errors' => $rowErrors];
                $failedRowNumbers[] = $rowNumber;
            } else {
                // Import the row
                $customer = Customer::create([
                    'name' => trim($data['name']),
                    'email' => $email,
                    'date_of_birth' => $data['date_of_birth'] !== '' ? Carbon::parse($data['date_of_birth'])->toDateString() : null,
                    'annual_income' => $data['annual_income'] !== '' ? (float)$data['annual_income'] : null,
                ]);
                $imported[] = ['id' => $customer->id, 'name' => $customer->name, 'email' => $customer->email];
            }
        }

        return [
            'total_rows_processed' => count($csvRows),
            'imported_count' => count($imported),
            'failed_count' => count($errors),
            'imported' => $imported,
            'errors' => $errors,
        ];
    }

    private function validateAllFields(array $data, array $existingEmails): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        $email = trim($data['email'] ?? '');
        $dob = trim($data['date_of_birth'] ?? '');
        $income = trim($data['annual_income'] ?? '');

        if ($name === '') {
            $errors[] = ['field' => 'name', 'message' => 'Name is required.'];
        }
        if ($email === '') {
            $errors[] = ['field' => 'email', 'message' => 'Email is required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = ['field' => 'email', 'message' => 'Email must be a valid email address.'];
        } elseif (in_array(strtolower($email), $existingEmails)) {
            $errors[] = ['field' => 'email', 'message' => "Email '{$email}' already exists in the database."];
        }

        $errors = array_merge($errors, $this->validateDateAndIncome($dob, $income));

        return $errors;
    }

    private function validateOtherFields(array $data, array $existingEmails, bool $skipEmailCheck = false): array
    {
        $errors = [];
        $name = trim($data['name'] ?? '');
        $dob = trim($data['date_of_birth'] ?? '');
        $income = trim($data['annual_income'] ?? '');

        if ($name === '') {
            $errors[] = ['field' => 'name', 'message' => 'Name is required.'];
        }

        $errors = array_merge($errors, $this->validateDateAndIncome($dob, $income));

        return $errors;
    }

    private function validateDateAndIncome(string $dob, string $income): array
    {
        $errors = [];

        if ($dob !== '') {
            try {
                $parsed = Carbon::parse($dob);
                if ($parsed->isFuture()) {
                    $errors[] = ['field' => 'date_of_birth', 'message' => 'Date of birth must be in the past.'];
                }
            } catch (\Exception) {
                $errors[] = ['field' => 'date_of_birth', 'message' => 'Date of birth must be a valid date.'];
            }
        }

        if ($income !== '') {
            if (!is_numeric($income) || (float)$income <= 0) {
                $errors[] = ['field' => 'annual_income', 'message' => 'Annual income must be a positive number.'];
            }
        }

        return $errors;
    }

    private function fatalError(string $message): array
    {
        return ['fatal' => true, 'message' => $message];
    }
}