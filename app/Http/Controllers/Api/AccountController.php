<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function index(Request $request, Customer $customer): JsonResponse
    {
        $accounts = $customer->accounts()
            ->orderBy('account_number')
            ->paginate(min(max((int) $request->query('per_page', 15), 1), 100));

        return response()->json([
            'success' => true,
            'message' => 'Customer accounts retrieved successfully.',
            'data' => $accounts->items(),
            'meta' => [
                'current_page' => $accounts->currentPage(),
                'per_page' => $accounts->perPage(),
                'total' => $accounts->total(),
                'last_page' => $accounts->lastPage(),
            ],
        ]);
    }

    public function store(StoreAccountRequest $request, Customer $customer): JsonResponse
    {
        $account = $customer->accounts()->create([
            ...$request->validated(),
            'balance' => '0.00',
            'status' => 'ACTIVE',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer account created successfully.',
            'data' => $account,
        ], 201);
    }
}