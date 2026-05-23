<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class InfoController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'name'          => 'CRM Ultimate API',
            'version'       => 'v1',
            'status'        => 'ok',
            'documentation' => '/docs/openapi.yaml',
            'frontend'      => '/',
            'endpoints'     => [
                'login'     => 'POST /api/v1/auth/login',
                'me'        => 'GET /api/v1/auth/me',
                'companies' => 'GET|POST /api/v1/companies',
                'contacts'  => 'GET|POST /api/v1/contacts',
                'deals'     => 'GET|POST /api/v1/deals',
                'activities' => 'GET|POST /api/v1/activities',
                'pipelines' => 'GET|POST /api/v1/pipelines',
            ],
        ]);
    }
}
