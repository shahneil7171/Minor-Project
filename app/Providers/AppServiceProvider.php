<?php

namespace App\Providers;

use App\Models\Address;
use App\Models\Category;
use App\Policies\AddressPolicy;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        Address::class => AddressPolicy::class,
    ];

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
        // Share the active navigation categories (top-level, with their subcategories)
        // with any view that renders the main header/layout.
        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            $navCategories = collect();

            try {
                $navCategories = Category::query()
                    ->active()
                    ->parent()
                    ->ordered()
                    ->with(['children' => function ($query) {
                        $query->active()->ordered();
                    }])
                    ->get();
            } catch (\Throwable $e) {
                // Categories table may not exist yet on a fresh install; render menu without it.
                $navCategories = collect();
            }

            $view->with('navCategories', $navCategories);
        });
    }
}
