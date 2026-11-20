<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class TablePagination
{
    public const OPTIONS = ['10' => '10', '15' => '15', '20' => '20', '50' => '50', 'all' => 'Semua'];

    public static function sizeName(string $pageName = 'page'): string
    {
        return $pageName === 'page' ? 'per_page' : preg_replace('/_page$/', '', $pageName).'_per_page';
    }

    public static function selected(string $pageName = 'page'): string
    {
        $value = request()->query(self::sizeName($pageName), '10');

        return is_string($value) && array_key_exists($value, self::OPTIONS) ? $value : '10';
    }

    public static function query(Builder $query, string $pageName = 'page'): LengthAwarePaginator
    {
        $selected = self::selected($pageName);
        if ($selected === 'all') {
            // Count the already scoped query. Never use an arbitrary high limit
            // or remove authorization/search scopes for the All option.
            $total = (clone $query)->toBase()->getCountForPagination();
            $paginator = $query->paginate(max(1, $total), ['*'], $pageName, 1, $total);
        } else {
            $paginator = $query->paginate((int) $selected, ['*'], $pageName);
        }

        return $paginator->withQueryString()->appends([self::sizeName($pageName) => $selected]);
    }

    public static function collection(Collection $items, string $pageName = 'page'): LengthAwarePaginator
    {
        $selected = self::selected($pageName);
        $perPage = $selected === 'all' ? max(1, $items->count()) : (int) $selected;
        $page = $selected === 'all' ? 1 : LengthAwarePaginator::resolveCurrentPage($pageName);

        return (new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(), $items->count(), $perPage, $page,
            ['path' => request()->url(), 'pageName' => $pageName],
        ))->withQueryString()->appends([self::sizeName($pageName) => $selected]);
    }
}
