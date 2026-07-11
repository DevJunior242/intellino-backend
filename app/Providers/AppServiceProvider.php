<?php

namespace App\Providers;

use App\Models\SessionModel;
use Laravel\Sanctum\Sanctum;
use App\Policies\SessionPolicy;
use App\Models\PersonalAccessToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

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
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Relation::morphMap([
            'Club' => \App\Models\Club::class,
            'Ligue' => \App\Models\League::class,
            'Federation' => \App\Models\Federation::class,
        ]);
        //observers
        \App\Models\Course::observe(\App\Observers\CourseObserver::class);
        \App\Models\Examen::observe(\App\Observers\ExamenClubObserver::class);
        \App\Models\Student::observe(\App\Observers\StudentObserver::class);
        \App\Models\SessionModel::observe(\App\Observers\SessionObserver::class);
        \App\Models\Equipment::observe(\App\Observers\EquipmentObserver::class);
        \App\Models\Course::observe(\App\Observers\CourseObserver::class);
        Gate::policy(SessionModel::class, SessionPolicy::class);
    }
}
