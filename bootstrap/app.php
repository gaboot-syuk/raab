<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Header keamanan dipasang GLOBAL, bukan hanya di grup `web`: halaman
         * galat, berkas yang diunduh, dan jawaban JSON juga perlu membawanya.
         */
        $middleware->append(\App\Http\Middleware\HeaderKeamanan::class);

        // Inertia: berbagi data ke seluruh halaman panel admin & dashboard
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // Alias middleware — WAJIB dalam SATU panggilan alias().
        // Panggilan alias() kedua akan MENGGANTI isi yang pertama, sehingga
        // alias lokalisasi bisa hilang dan memicu galat
        // "Target class [localizationRedirect] does not exist".
        $middleware->alias([
            // Lokalisasi URL (Indonesia tanpa prefiks, Inggris berprefiks /en)
            'localize' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRoutes::class,
            'localeSessionRedirect' => \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            'localizationRedirect' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            'localeViewPath' => \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,

            // Hak akses berbasis role & permission (Spatie)
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
