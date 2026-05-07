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
        $this->app->bind(
            \App\Domain\Repositories\StudentRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentStudentRepository::class
        );
        $this->app->bind(
            \App\Domain\Repositories\AttendanceRepositoryInterface::class,
            \App\Infrastructure\Repositories\EloquentAttendanceRepository::class
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
