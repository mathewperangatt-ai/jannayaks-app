<?php

namespace App\Providers\Filament;

use App\Filament\Widgets\PendingAttentionWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                // Jannayaks deep Ashoka blue (matches the public site).
                'primary' => '#214d68',
            ])
            ->font('DM Sans')
            ->darkMode(false)
            ->brandName('Jannayaks')
            ->brandLogo(asset('branding/jannayaks-logo.jpg'))
            ->brandLogoHeight('2.25rem')
            ->renderHook(
                PanelsRenderHook::STYLES_BEFORE,
                fn (): string => Blade::render(<<<'CSS'
                    <style>
                        /* Jannayaks admin design language (public-site tokens) */
                        .fi-layout{background:#f3f8f0}
                        .fi-topbar{background:#fff;border-bottom:2px solid #C0762E}
                        .fi-sidebar{background:#fff;border-right:1px solid #d3ddd1}
                        .fi-body{color:#1f2924}
                    </style>
                CSS),
            )
            ->renderHook(
                PanelsRenderHook::STYLES_AFTER,
                fn (): string => Blade::render(<<<'CSS'
                    <style>
                        /* Primary actions render in the deep Ashoka blue, not the generated tint ramp. */
                        .fi-btn.fi-color-primary{--bg:#214d68;--text:#fff}
                        .fi-btn.fi-color-primary:hover{--bg:#1a3e55}
                        .fi-icon-btn.fi-color-primary{--bg:#214d68;--text:#fff}
                    </style>
                CSS),
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                PendingAttentionWidget::class,
                AccountWidget::class,
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
            ]);
    }
}
