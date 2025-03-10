<?php

namespace Moox\Item\Moox\Entities\Items\Item;

use Camya\Filament\Forms\Components\TitleWithSlugInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Moox\Core\Entities\Items\Item\BaseItemResource;
use Moox\Core\Traits\Taxonomy\HasResourceTaxonomy;
use Moox\Item\Models\Item;

class ItemResource extends BaseItemResource
{
    use HasResourceTaxonomy;

    protected static ?string $model = Item::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getModelLabel(): string
    {
        return config('item.single');
    }

    public static function getPluralModelLabel(): string
    {
        return config('item.plural');
    }

    public static function getNavigationLabel(): string
    {
        return config('item.plural');
    }

    public static function getBreadcrumb(): string
    {
        return config('item.single');
    }

    public static function getNavigationGroup(): ?string
    {
        return config('item.navigation_group');
    }

    public static function getNavigationSort(): ?int
    {
        return config('item.navigation_sort') + 1;
    }

    public static function form(Form $form): Form
    {
        // Add direct logging
        \Log::channel('daily')->info('=== ITEM RESOURCE FORM METHOD CALLED ===');

        // Debug the taxonomy fields
        $taxonomyFields = static::getTaxonomyFields();
        \Log::channel('daily')->info('Taxonomy fields count: '.count($taxonomyFields));
        \Log::channel('daily')->info('Taxonomy fields: '.json_encode($taxonomyFields));

        // Debug the trait methods
        \Log::channel('daily')->info('Has trait: '.(in_array('Moox\Core\Traits\Taxonomy\HasResourceTaxonomy', class_uses_recursive(static::class)) ? 'Yes' : 'No'));

        // Debug the model
        $model = static::getModel();
        \Log::channel('daily')->info('Model: '.$model);

        // Try to get resource name
        try {
            $resourceName = $model::getResourceName();
            \Log::channel('daily')->info('Resource name: '.$resourceName);
        } catch (\Exception $e) {
            \Log::channel('daily')->info('Error getting resource name: '.$e->getMessage());
        }

        // Debug config
        \Log::channel('daily')->info('Config item.taxonomies: '.json_encode(config('item.taxonomies')));

        return $form->schema([
            Grid::make(2)
                ->schema([
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    TitleWithSlugInput::make(
                                        fieldTitle: 'title',
                                        fieldSlug: 'slug',
                                    ),
                                    Toggle::make('is_active')
                                        ->label('Active'),
                                    RichEditor::make('description')
                                        ->label('Description'),
                                    MarkdownEditor::make('content')
                                        ->label('Content'),
                                    KeyValue::make('data')
                                        ->label('Data (JSON)'),
                                    FileUpload::make('image')
                                        ->label('Image')
                                        ->directory('items')
                                        ->image(),
                                ]),
                        ])
                        ->columnSpan(['lg' => 2]),
                    Grid::make()
                        ->schema([
                            Section::make()
                                ->schema([
                                    static::getFormActions(),
                                ]),
                            Section::make('')
                                ->schema([
                                    Select::make('type')
                                        ->label('Type')
                                        ->options(['Post' => 'Post', 'Page' => 'Page']),

                                    Select::make('status')
                                        ->label('Status')
                                        ->placeholder(__('core::core.status'))
                                        ->options(['Probably' => 'Probably', 'Never' => 'Never', 'Done' => 'Done', 'Maybe' => 'Maybe']),
                                ]),
                            Section::make('Taxonomies')
                                ->schema($taxonomyFields),
                            Section::make('')
                                ->schema([
                                    Select::make('author_id')
                                        ->label('Author')
                                        ->relationship('author', 'name'),
                                    DateTimePicker::make('due_at')
                                        ->label('Due'),
                                    ColorPicker::make('color')
                                        ->label('Color'),
                                ]),
                            Section::make('')
                                ->schema([
                                    Placeholder::make('id')
                                        ->label('ID')
                                        ->content(fn ($record): string => $record->id ?? ''),
                                    Placeholder::make('uuid')
                                        ->label('UUID')
                                        ->content(fn ($record): string => $record->uuid ?? ''),
                                    Placeholder::make('ulid')
                                        ->label('ULID')
                                        ->content(fn ($record): string => $record->ulid ?? ''),
                                    Placeholder::make('created_at')
                                        ->label('Created')
                                        ->content(fn ($record): string => $record->created_at ?
                                            $record->created_at.' ('.$record->created_at->diffForHumans().')' : ''),
                                    Placeholder::make('updated_at')
                                        ->label('Last Updated')
                                        ->content(fn ($record): string => $record->updated_at ?
                                            $record->updated_at.' ('.$record->updated_at->diffForHumans().')' : ''),
                                ])
                                ->hidden(fn ($record) => $record === null),
                        ])
                        ->columnSpan(['lg' => 1]),
                ])
                ->columns(['lg' => 3]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active')
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('content')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('type')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                ColorColumn::make('color')
                    ->toggleable(),
                TextColumn::make('uuid')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ulid')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('section')
                    ->sortable()
                    ->toggleable(),
                ...static::getTaxonomyColumns(),
                TextColumn::make('status')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),
            ])
            ->defaultSort('title', 'desc')
            ->actions([...static::getTableActions()])
            ->bulkActions([...static::getBulkActions()])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active'),
                Filter::make('title')
                    ->form([
                        TextInput::make('title')
                            ->label('Title')
                            ->placeholder(__('core::core.filter').' Title'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['title'],
                            fn (Builder $query, $value): Builder => $query->where('title', 'like', "%{$value}%"),
                        );
                    })
                    ->indicateUsing(function (array $data): ?string {
                        if (! $data['title']) {
                            return null;
                        }

                        return 'Title: '.$data['title'];
                    }),
                SelectFilter::make('status')
                    ->label('Status')
                    ->placeholder(__('core::core.filter').' Status')
                    ->options(['Probably' => 'Probably', 'Never' => 'Never', 'Done' => 'Done', 'Maybe' => 'Maybe']),
                SelectFilter::make('type')
                    ->label('Type')
                    ->placeholder(__('core::core.filter').' Type')
                    ->options(['Post' => 'Post', 'Page' => 'Page']),
                SelectFilter::make('section')
                    ->label('Section')
                    ->placeholder(__('core::core.filter').' Section')
                    ->options(['Header' => 'Header', 'Main' => 'Main', 'Footer' => 'Footer']),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListItems::route('/'),
            'create' => Pages\CreateItem::route('/create'),
            'edit' => Pages\EditItem::route('/{record}/edit'),
            'view' => Pages\ViewItem::route('/{record}'),
        ];
    }
}
