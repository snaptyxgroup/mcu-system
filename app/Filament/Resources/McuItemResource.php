<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\McuItemResource\Pages;
use App\Models\McuItem;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class McuItemResource extends Resource
{
    protected static ?string $model = McuItem::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-beaker';

    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'MCU Test Items';

    protected static ?string $modelLabel = 'MCU Item';

    protected static ?string $pluralModelLabel = 'MCU Test Items';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Item & Station Information')
                ->description('Specify test item code, name, category, and station assignment.')
                ->icon('heroicon-o-beaker')
                ->columns(2)
                ->schema([
                    Forms\Components\Select::make('station_id')
                        ->label('Assigned Station')
                        ->relationship('station', 'name')
                        ->searchable()
                        ->preload()
                        ->placeholder('Select station (optional)')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('code')
                        ->label('Item Code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->placeholder('e.g., LAB_HB')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name')
                        ->label('Item / Parameter Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., Hemoglobin')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('category')
                        ->label('Category')
                        ->maxLength(100)
                        ->placeholder('e.g., Hematologi, Radiologi')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('unit')
                        ->label('Unit of Measurement')
                        ->maxLength(30)
                        ->placeholder('e.g., g/dL, mg/dL, mmHg')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('price')
                        ->label('Price / Cost (IDR)')
                        ->numeric()
                        ->prefix('Rp')
                        ->default(0.00)
                        ->columnSpan(1),
                ]),

            Section::make('Normal Reference Ranges')
                ->description('Standard normal reference values for male and female patients.')
                ->icon('heroicon-o-adjustments-vertical')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('normal_reference_male')
                        ->label('Normal Reference (Male)')
                        ->maxLength(255)
                        ->placeholder('e.g., 13.5 - 17.5')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('normal_reference_female')
                        ->label('Normal Reference (Female)')
                        ->maxLength(255)
                        ->placeholder('e.g., 12.0 - 15.5')
                        ->columnSpan(1),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Active Status')
                        ->default(true)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Item Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Parameter Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('station.name')
                    ->label('Station')
                    ->badge()
                    ->color('warning')
                    ->placeholder('Unassigned')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('unit')
                    ->label('Unit')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('normal_reference_male')
                    ->label('Ref (Male)')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('normal_reference_female')
                    ->label('Ref (Female)')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('price')
                    ->label('Price')
                    ->money('IDR')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('station_id')
                    ->label('Station')
                    ->relationship('station', 'name'),

                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => McuItem::query()->whereNotNull('category')->distinct()->pluck('category', 'category')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                    Actions\RestoreBulkAction::make(),
                    Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMcuItems::route('/'),
            'create' => Pages\CreateMcuItem::route('/create'),
            'view'   => Pages\ViewMcuItem::route('/{record}'),
            'edit'   => Pages\EditMcuItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
