<?php

namespace App\Models\Traits;

use App\Support\AsciiPath;

/**
 * TASK-002 prevention: storage path attributes listed in $asciiPathAttributes are written as ASCII.
 *
 * Only new or changed values are converted. An unchanged legacy value (e.g. "hr-114-o-brač") is left
 * as is when the model is re-saved, so images keep working until `storage:ascii-paths` migrates the
 * keys and the DB together.
 */
trait HasAsciiPaths
{
    public static function bootHasAsciiPaths(): void
    {
        static::saving(function ($model): void {
            foreach ($model->asciiPathAttributes as $attribute) {
                $value = $model->getAttribute($attribute);
                if (is_string($value) && $model->isDirty($attribute)) {
                    $model->setAttribute($attribute, AsciiPath::fromPath($value));
                }
            }
        });
    }
}
