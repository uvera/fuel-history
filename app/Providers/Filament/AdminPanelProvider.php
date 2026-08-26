<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\AllCarsAnalyticsWidget;
use App\Filament\Widgets\AllCarsFuelSummaryStatsWidget;
use App\Filament\Widgets\GlobalEfficiencyChart;
use App\Filament\Widgets\GlobalSpendChart;
use App\Filament\Widgets\GlobalVolumeChart;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->profile()
            ->favicon(asset('favicon.svg'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
                AllCarsFuelSummaryStatsWidget::class,
                AllCarsAnalyticsWidget::class,
                GlobalSpendChart::class,
                GlobalVolumeChart::class,
                GlobalEfficiencyChart::class,
            ])
            ->plugins([
                FilamentSpatieLaravelBackupPlugin::make()
                    ->authorize(fn () => auth()->user()?->isAdmin() ?? false)
                    // No queue worker is deployed for this app -- run the manual
                    // "Create Backup" button's job inline via the sync connection
                    // rather than standing up worker infrastructure for one button.
                    ->usingQueueConnection('sync')
                    ->timeout(300),
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ], isRequired: true);
    }
}
