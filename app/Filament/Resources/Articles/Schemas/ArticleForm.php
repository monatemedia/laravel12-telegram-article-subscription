<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Models\Article;
use App\Models\Collection;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(function ($state, $set, $record) {
                    $set('slug', Article::generateSlug($state, $record?->id));
                })
                ->columnSpanFull(),

            TextInput::make('slug')
                ->disabled()
                ->dehydrated()
                ->maxLength(255)
                ->columnSpanFull(),

            Select::make('status')
                ->options([
                    'draft'     => 'Draft',
                    'scheduled' => 'Scheduled',
                    'published' => 'Published',
                ])
                ->required()
                ->default('draft')
                ->live(),

            DateTimePicker::make('published_at')
                ->label('Publish At')
                ->required(fn (Get $get) => in_array($get('status'), ['scheduled', 'published']))
                ->hidden(fn (Get $get) => ! in_array($get('status'), ['scheduled', 'published'])),

            Select::make('collection_id')
                ->label('Collection')
                ->options(Collection::all()->pluck('title', 'id'))
                ->searchable()
                ->nullable()
                ->live(),

            TextInput::make('order')
                ->numeric()
                ->nullable()
                ->hidden(fn (Get $get) => ! filled($get('collection_id'))),

            Textarea::make('synopsis')
                ->rows(3)
                ->columnSpanFull(),

            MarkdownEditor::make('body')
                ->required()
                ->columnSpanFull(),
        ]);
    }
}
