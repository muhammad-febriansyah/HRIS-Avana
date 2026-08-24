<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Shapes a Laravel paginator into what the shared `DataTable` React component
 * (resources/js/components/avana-ui/data-table.tsx) expects: its rows plus a
 * flat pagination `meta`, and the search term currently applied so the search
 * box can be pre-filled on reload. Shared by every controller that hands a
 * searchable/paginated table to that component.
 */
final class PaginatedTable
{
    /**
     * @return array{data: array<int, array<string, mixed>>, meta: array<string, int>, search: string}
     */
    public static function shape(LengthAwarePaginator $paginator, Request $request, string $searchKey): array
    {
        return [
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
            ],
            'search' => $request->string($searchKey)->toString(),
        ];
    }
}
