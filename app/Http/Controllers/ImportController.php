<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\CsvImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportController extends Controller
{
    public function __construct(private CsvImportService $importService) {}

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $result = $this->importService->import($request->file('file'));

        if (isset($result['fatal'])) {
            return response()->json(['error' => $result['message']], 422);
        }

        return response()->json($result, 200);
    }

    public function index(Request $request): JsonResponse
    {
        $customers = Customer::paginate($request->integer('per_page', 15));

        return response()->json($customers);
    }
}
