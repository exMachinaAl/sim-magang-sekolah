<?php

namespace App\Support;

use Illuminate\Http\Request;

final class JournalDateFilter
{
    public static function rules(Request $request): array
    {
        return [
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => array_filter(['nullable', 'date_format:Y-m-d',
                $request->filled('date_from') ? 'after_or_equal:date_from' : null]),
        ];
    }
}
