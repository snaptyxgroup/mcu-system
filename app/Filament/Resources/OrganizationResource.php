<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Models\Organization;
use Filament\Actions;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-office-2';

    protected static string | \UnitEnum | null $navigationGroup = 'Master Data';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Organizations';

    protected static ?string $modelLabel = 'Organization';

    protected static ?string $pluralModelLabel = 'Organizations';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Organization Information')
                ->description('Basic company / organization details and classification.')
                ->icon('heroicon-o-building-office-2')
                ->columns(2)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Company / Organization Name')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('e.g., PT Snaptyx Indonesia')
                        ->columnSpan(1),

                    Forms\Components\Select::make('org_type')
                        ->label('Organization Type')
                        ->required()
                        ->options([
                            'CORPORATE'  => '🏢 Corporate Client',
                            'CLINIC_LAB' => '🔬 Clinic / Laboratory',
                            'HOSPITAL'   => '🏥 Hospital',
                            'INTERNAL'   => '⚙️ Snaptyx Internal',
                        ])
                        ->default('CORPORATE')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('pic_name')
                        ->label('Person-in-Charge (PIC) Name')
                        ->maxLength(150)
                        ->placeholder('e.g., Budi Santoso')
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('contact_number')
                        ->label('Contact Number / Phone')
                        ->tel()
                        ->maxLength(30)
                        ->placeholder('e.g., +62 812-3456-7890')
                        ->columnSpan(1),

                    Forms\Components\Textarea::make('address')
                        ->label('Address')
                        ->rows(3)
                        ->placeholder('Full office or clinic address...')
                        ->columnSpanFull(),
                ]),

            Section::make('Custom Registration Fields Template')
                ->description('Define key-value pairs for demographic / registration fields specific to this organization.')
                ->icon('heroicon-o-adjustments-horizontal')
                ->collapsible()
                ->schema([
                    Forms\Components\KeyValue::make('registration_field_template')
                        ->label('Template Fields')
                        ->keyLabel('Field Key (e.g. blood_type)')
                        ->valueLabel('Field Label (e.g. Blood Type)')
                        ->keyPlaceholder('blood_type')
                        ->valuePlaceholder('Blood Type')
                        ->addActionLabel('Add Template Field')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organization Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('org_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'CORPORATE'  => 'Corporate Client',
                        'CLINIC_LAB' => 'Clinic / Lab',
                        'HOSPITAL'   => 'Hospital',
                        'INTERNAL'   => 'Snaptyx Internal',
                        default      => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'CORPORATE'  => 'info',
                        'CLINIC_LAB' => 'warning',
                        'HOSPITAL'   => 'success',
                        'INTERNAL'   => 'gray',
                        default      => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('pic_name')
                    ->label('PIC Name')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('contact_number')
                    ->label('Contact')
                    ->searchable()
                    ->placeholder('-')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('mcu_registrations_count')
                    ->label('Total Registrations')
                    ->counts('mcuRegistrations')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('org_type')
                    ->label('Organization Type')
                    ->options([
                        'CORPORATE'  => 'Corporate Client',
                        'CLINIC_LAB' => 'Clinic / Laboratory',
                        'HOSPITAL'   => 'Hospital',
                        'INTERNAL'   => 'Snaptyx Internal',
                    ]),

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
            ->defaultSort('name', 'asc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'view'   => Pages\ViewOrganization::route('/{record}'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
