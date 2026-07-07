<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiInsightService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(private readonly AiInsightService $ai) {}

    public function summarizeDeal(Request $request, int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->summarizeDeal($id, false, $request->user()));
    }

    public function summarizeContact(Request $request, int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->summarizeContact($id, false, $request->user()));
    }

    public function nextActionDeal(Request $request, int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->nextActionDeal($id, false, $request->user()));
    }

    public function scoreDeal(Request $request, int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->scoreDeal($id, false, $request->user()));
    }

    private function wrap(callable $fn): JsonResponse
    {
        try {
            $result = $fn();
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Ressource introuvable.'], 404);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'not configured') ? 503 : 500;

            return response()->json(['message' => $e->getMessage()], $status);
        }

        return response()->json(array_merge($result, ['generated_at' => now()->toIso8601String()]));
    }
}
