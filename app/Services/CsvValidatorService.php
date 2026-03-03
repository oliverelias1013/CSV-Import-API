<?php

namespace App\Services;

use Carbon\Carbon;

class CsvValidatorService
{
    private array $errors = [];
    private array $seenEmails = [];

    /**
     * Validate a single CSV row. Returns array of error messages, or empty array if valid.
     */
    public function validateRow(array $row, int $rowNumber, array $existingEmails): array
    {
        $rowErrors = [];

        $name = trim($row['name'] ?? '');
        $email = trim($row['email'] ?? '');
        $dob = trim($row['date_of_birth'] ?? '');
        $income = trim($row['annual_income'] ?? '');

        // Required: name
        if ($name === '') {
            $rowErrors[] = ['field' => 'name', 'message' => 'Name is required.'];
        }

        // Required: email
        if ($email === '') {
            $rowErrors[] = ['field' => 'email', 'message' => 'Email is required.'];
        } else {
            // Email format
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = ['field' => 'email', 'message' => 'Email must be a valid email address.'];
            } else {
                // Duplicate within file
                if (isset($this->seenEmails[$email])) {
                    $rowErrors[] = [
                        'field' => 'email',
                        'message' => "Email '{$email}' is a duplicate (first seen on row {$this->seenEmails[$email]}).",
                    ];
                } elseif (in_array($email, $existingEmails)) {
                    // Duplicate against database
                    $rowErrors[] = [
                        'field' => 'email',
                        'message' => "Email '{$email}' already exists in the database.",
                    ];
                } else {
                    $this->seenEmails[$email] = $rowNumber;
                }
            }
        }

        // Optional: date of birth
        if ($dob !== '') {
            try {
                $parsed = Carbon::parse($dob);
                if ($parsed->isFuture()) {
                    $rowErrors[] = ['field' => 'date_of_birth', 'message' => 'Date of birth must be in the past.'];
                }
            } catch (\Exception $e) {
                $rowErrors[] = ['field' => 'date_of_birth', 'message' => 'Date of birth must be a valid date.'];
            }
        }

        // Optional: annual income
        if ($income !== '') {
            if (!is_numeric($income) || (float)$income <= 0) {
                $rowErrors[] = ['field' => 'annual_income', 'message' => 'Annual income must be a positive number.'];
            }
        }

        return $rowErrors;
    }

    /**
     * Mark an email as seen (used when we need to also flag the first occurrence of a duplicate).
     */
    public function getSeenEmails(): array
    {
        return $this->seenEmails;
    }

    public function reset(): void
    {
        $this->seenEmails = [];
    }
}