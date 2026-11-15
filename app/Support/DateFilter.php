<?php

namespace App\Support;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Http\Request;

/** The "from" and "to" date filter used on list pages. */
class DateFilter
{
    /**
     * Validate ?from= and ?to= and keep only rows whose $column falls between them (both days included).
     *
     * @template T of BuilderContract
     *
     * @param  T  $query
     * @return T
     */
    public static function apply($query, Request $request, string $column)
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        return $query
            ->when($request->filled('from'), fn ($q) => $q->where($column, '>=', $request->date('from')->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where($column, '<=', $request->date('to')->endOfDay()));
    }
}
