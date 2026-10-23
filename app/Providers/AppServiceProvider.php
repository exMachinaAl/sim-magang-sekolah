<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Date;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {}
    public function boot(): void { date_default_timezone_set('Asia/Jakarta'); }
}
