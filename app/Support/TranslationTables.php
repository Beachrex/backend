<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

/**
 * Discovers the `*_translation` tables that hold a translatable `name` per locale, with their parent
 * table and the column linking them (`beach_type_id`, `country_code`, …). Used by `translations:audit`
 * and `translations:ai`.
 */
class TranslationTables
{
    /**
     * @param  list<string>  $only  limit to these translation tables
     * @return list<array{table: string, parent: string, fk: string, parentKey: string}>
     */
    public static function all(array $only = []): array
    {
        $result = [];
        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if (! str_ends_with($table, '_translation') || ($only !== [] && ! in_array($table, $only, true))) {
                continue;
            }
            $columns = Schema::getColumnListing($table);
            if (! in_array('name', $columns, true) || ! in_array('locale', $columns, true)) {
                continue;
            }
            $prefix = substr($table, 0, -strlen('_translation'));
            $parent = collect([$prefix, $prefix.'s', preg_replace('/y$/', 'ies', $prefix)])
                ->first(fn ($t) => Schema::hasTable($t));
            if (! $parent) {
                continue;
            }
            $fk = collect([$prefix.'_id', $prefix.'_code', rtrim($prefix, 's').'_id'])
                ->first(fn ($c) => in_array($c, $columns, true));
            if (! $fk) {
                continue;
            }
            $parentKey = str_ends_with($fk, '_code') ? 'code' : 'id';
            $result[] = ['table' => $table, 'parent' => $parent, 'fk' => $fk, 'parentKey' => $parentKey];
        }
        usort($result, fn ($a, $b) => strcmp($a['table'], $b['table']));

        return $result;
    }
}
