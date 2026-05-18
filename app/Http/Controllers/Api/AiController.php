<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiInsightService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiController extends Controller
{
    public function __construct(private readonly AiInsightService $ai) {}

    public function summarizeDeal(int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->summarizeDeal($id));
    }

    public function summarizeContact(int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->summarizeContact($id));
    }

    public function nextActionDeal(int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->nextActionDeal($id));
    }

    public function scoreDeal(int $id): JsonResponse
    {
        return $this->wrap(fn () => $this->ai->scoreDeal($id));
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
