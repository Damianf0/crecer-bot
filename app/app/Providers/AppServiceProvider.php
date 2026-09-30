<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Fechas relativas en español en todo el panel ("hace 19 horas", no
        // "19 hours ago"). Solo Carbon: el locale de la app queda en 'en' porque
        // no hay traducciones de validación instaladas.
        \Carbon\Carbon::setLocale('es');
    }
}
