<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\Icd10CodeResource\Pages;
use App\Models\Icd10Code;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class Icd10CodeResource extends Resource
{
    protected static ?string $model = Icd10Code::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-book-open';

    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'code';

    protected static ?string $navigationLabel = 'ICD-10 Codes';

    protected static ?string $modelLabel = 'ICD-10 Code';

    protected static ?string $pluralModelLabel = 'ICD-10 Codes';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('ICD-10 Diagnosis Information')
                ->description('Standard International Classification of Diseases 10th Revision code.')
                ->icon('heroicon-o-book-open')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('code')
                        ->label('ICD-10 Code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(20)
                        ->placeholder('e.g., Z00.0')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('category')
                        ->label('Category / Chapter')
                        ->maxLength(100)
                        ->placeholder('e.g., Special Examinations')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name_en')
                        ->label('Diagnosis Name (English)')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., General medical examination')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('name_id')
                        ->label('Diagnosis Name (Indonesian)')
                        ->maxLength(255)
                        ->placeholder('e.g., Pemeriksaan medis umum')
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
                    ->label('ICD Code')
                    ->fontFamily('mono')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name_en')
                    ->label('English Name')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\TextColumn::make('name_id')
                    ->label('Indonesian Name')
                    ->searchable()
                    ->placeholder('-')
                    ->wrap()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->searchable()
                    ->badge()
                    ->color('info')
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options(fn () => Icd10Code::query()->whereNotNull('category')->distinct()->pluck('category', 'category')),

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
            'index'  => Pages\ListIcd10Codes::route('/'),
            'create' => Pages\CreateIcd10Code::route('/create'),
            'view'   => Pages\ViewIcd10Code::route('/{record}'),
            'edit'   => Pages\EditIcd10Code::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
