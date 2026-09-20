<?php

namespace App\Filament\Widgets;

use App\Models\Pigeon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class LoftOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('În crescătorie', Pigeon::where('is_owned', true)->where('archived', false)->where('status', 'in_loft')->count()),
            Stat::make('Strămoși de referință', Pigeon::where('is_owned', false)->count()),
            Stat::make('Fără părinți completați', Pigeon::where('is_owned', true)->where('archived', false)->where(fn ($q) => $q->whereNull('father_id')->orWhereNull('mother_id'))->count()),
        ];
    }
}
