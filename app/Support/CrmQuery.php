<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CrmQuery
{
    public static function apply(Builder $query, Request $request, array $searchable = []): Builder
    {
        if ($search = $request->query('search')) {
            $query->where(function (Builder $nested) use ($searchable, $search): void {
                foreach ($searchable as $field) {
                    $nested->orWhere($field, 'ilike', '%'.$search.'%');
                }
            });
        }

        foreach ((array) $request->query('filter', []) as $field => $value) {
            if ($value !== null && $value !== '') {
                $query->where($field, $value);
            }
        }

        $sort = $request->query('sort', '-created_at');
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $field = ltrim($sort, '-');

        return $query->orderBy($field, $direction);
    }
}
