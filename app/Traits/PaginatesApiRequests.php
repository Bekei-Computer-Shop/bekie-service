<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;

trait PaginatesApiRequests
{
    protected function paginate(Builder $query, int $defaultPerPage = 20): array
    {
        $limit = min((int) request()->input('limit', $defaultPerPage), 100);
        $offset = (int) request()->input('offset', 0);

        $total = $query->count();
        $items = $query->offset($offset)->limit($limit)->get();

        return [
            'items' => $items,
            'pagination' => [
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'count' => $items->count(),
            ],
        ];
    }
}
