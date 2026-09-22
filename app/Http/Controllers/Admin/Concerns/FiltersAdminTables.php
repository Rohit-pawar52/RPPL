<?php

namespace App\Http\Controllers\Admin\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Small shared helpers for admin index()/export() actions: a validated
 * sort-column allow-list (never raw orderBy($request->sort)) and an
 * inclusive from/to date-range filter. Each controller still defines its
 * own $filters/query building explicitly — this is not a generic
 * table/query framework.
 */
trait FiltersAdminTables
{
    /**
     * @param  array<int, string>  $allowedColumns  real column names a caller may sort by
     * @return array{0: string, 1: string} [column, direction]
     */
    protected function allowedSort(Request $request, array $allowedColumns, string $defaultColumn, string $defaultDirection = 'desc'): array
    {
        $column = $request->string('sort')->value();
        $column = in_array($column, $allowedColumns, true) ? $column : $defaultColumn;

        $direction = strtolower((string) $request->string('direction'));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : $defaultDirection;

        return [$column, $direction];
    }

    protected function dateRangeFilter(Builder $query, string $column, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn ($query, $value) => $query->whereDate($column, '>=', $value))
            ->when($to, fn ($query, $value) => $query->whereDate($column, '<=', $value));
    }

    /**
     * @return array{from_date: ?string, to_date: ?string}
     */
    protected function validateDateRange(Request $request): array
    {
        return $request->validate([
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
        ]);
    }
}
