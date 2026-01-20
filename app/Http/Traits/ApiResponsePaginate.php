<?php

namespace App\Http\Traits;

use Illuminate\Http\JsonResponse;

trait ApiResponsePaginate
{
    /**
     * @param $data
     * @return JsonResponse
     */
    protected function paginate($data): JsonResponse
    {
        return response()->json([
            'success' => true,
            'page' => $data->currentPage(),
            'of' => $data->lastPage(),
            'data' => $data->items(),
            'per_page' => $data->perPage(),
            'total' => $data->total(),
            'next_page_url' => $data->nextPageUrl(),
            'prev_page_url' => $data->previousPageUrl(),
        ]);
    }
}
