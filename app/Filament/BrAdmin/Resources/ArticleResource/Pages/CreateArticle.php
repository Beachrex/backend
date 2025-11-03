<?php

namespace App\Filament\BrAdmin\Resources\ArticleResource\Pages;

use App\Filament\BrAdmin\Resources\ArticleResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;
}
