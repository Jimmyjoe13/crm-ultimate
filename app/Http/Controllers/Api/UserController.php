<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role'])
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $users]);
    }
}
