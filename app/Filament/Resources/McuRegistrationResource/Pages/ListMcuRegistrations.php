<?php

declare(strict_types=1);

namespace App\Filament\Resources\McuRegistrationResource\Pages;

use App\Filament\Resources\McuRegistrationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Components\Tab;
use Illuminate\Database\Eloquent\Builder;

/**
 * ListMcuRegistrations Page
 *
 * Provides tabbed filtering by status for quick dashboard-style overview.
 */
class ListMcuRegistrations extends ListRecords
{
    protected static string $resource = McuRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Register Patient')
                ->icon('heroicon-o-plus'),
        ];
    }

    /**
     * Status tabs — provides a fast overview without needing to use filters.
     * Each tab scopes the table query to the relevant status.
     */
    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All')
                ->icon('heroicon-o-list-bullet'),

            'registered' => Tab::make('Registered')
                ->icon('heroicon-o-clipboard-document')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'REGISTERED'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'REGISTERED')->count()),

            'in_progress' => Tab::make('In Progress')
                ->icon('heroicon-o-arrow-right-circle')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'IN_PROGRESS'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'IN_PROGRESS')->count()),

            'completed' => Tab::make('Completed')
                ->icon('heroicon-o-check-circle')
                ->modifyQueryUsing(fn (Builder $q) => $q->where('status', 'COMPLETED'))
                ->badge(fn () => \App\Models\McuRegistration::where('status', 'COMPLETED')->count()),
        ];
    }
}
