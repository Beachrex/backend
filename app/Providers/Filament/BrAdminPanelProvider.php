<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class BrAdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->domain($this->getDomain())
            ->login()
            ->colors([
                'primary' => Color::Blue,
            ])
            ->brandName('Beachrex Admin')
            ->discoverResources(in: app_path('Filament/BrAdmin/Resources'), for: 'App\\Filament\\BrAdmin\\Resources')
            ->discoverPages(in: app_path('Filament/BrAdmin/Pages'), for: 'App\\Filament\\BrAdmin\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/BrAdmin/Widgets'), for: 'App\\Filament\\BrAdmin\\Widgets')
            ->widgets([
                // Custom widgets are auto-discovered from app/Filament/BrAdmin/Widgets
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    protected function getDomain(): ?string
    {
        // Use dock.beachrex.com in production, null (default) in local
        return app()->environment('production') ? 'dock.beachrex.com' : null;
    }
}
