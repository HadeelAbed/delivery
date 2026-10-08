<?php

use App\Http\Controllers\Admin\ApprovalController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\DeactivateController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Customer\BrowseController;
use App\Http\Controllers\Customer\CartController;
use App\Http\Controllers\Customer\CheckoutController;
use App\Http\Controllers\Customer\HomeController;
use App\Http\Controllers\Customer\OrderController as CustomerOrderController;
use App\Http\Controllers\Customer\ProfileController as CustomerProfileController;
use App\Http\Controllers\Customer\RatingController;
use App\Http\Controllers\Customer\TrackingController;
use App\Http\Controllers\Driver\AvailabilityController;
use App\Http\Controllers\Driver\DashboardController as DriverDashboardController;
use App\Http\Controllers\Driver\DeliveryController;
use App\Http\Controllers\Driver\LocationController;
use App\Http\Controllers\Merchant\CategoryController;
use App\Http\Controllers\Merchant\DashboardController as MerchantDashboardController;
use App\Http\Controllers\Merchant\OrderController as MerchantOrderController;
use App\Http\Controllers\Merchant\ProductController;
use App\Http\Controllers\Merchant\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('welcome'))->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login');
});

Route::post('/logout', [LogoutController::class, 'store'])->middleware('auth')->name('logout');

Route::post('/account/deactivate', [DeactivateController::class, 'store'])->middleware('auth')->name('account.deactivate');

Route::middleware(['auth', 'role:customer'])->group(function () {
    Route::get('/customer', [HomeController::class, 'index'])->name('customer.home');
});

Route::middleware(['auth', 'role:merchant', 'approved'])->group(function () {
    Route::get('/merchant/dashboard', [MerchantDashboardController::class, 'index'])->name('merchant.dashboard');
});

Route::middleware(['auth', 'role:driver', 'approved'])->group(function () {
    Route::get('/driver/dashboard', [DriverDashboardController::class, 'index'])->name('driver.dashboard');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/approvals', [ApprovalController::class, 'index'])->name('approvals');
    Route::post('/users/{user}/approve', [ApprovalController::class, 'approve'])->name('users.approve');
    Route::post('/users/{user}/reject', [ApprovalController::class, 'reject'])->name('users.reject');
    Route::get('/users', [AdminUserController::class, 'index'])->name('users');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::get('/reports/sales', [ReportController::class, 'sales'])->name('reports.sales');
});

Route::middleware(['auth', 'role:merchant', 'approved'])->prefix('merchant')->name('merchant.')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/products', [ProductController::class, 'index'])->name('products');
    Route::get('/products/create', [ProductController::class, 'create'])->name('products.create');
    Route::post('/products', [ProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [ProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [ProductController::class, 'update'])->name('products.update');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->name('products.destroy');

    Route::get('/orders', [MerchantOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [MerchantOrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{order}/accept', [MerchantOrderController::class, 'accept'])->name('orders.accept');
    Route::post('/orders/{order}/reject', [MerchantOrderController::class, 'reject'])->name('orders.reject');
    Route::post('/orders/{order}/ready', [MerchantOrderController::class, 'markReady'])->name('orders.ready');
});

Route::middleware(['auth', 'role:driver', 'approved'])->prefix('driver')->name('driver.')->group(function () {
    Route::get('/dashboard', [DriverDashboardController::class, 'index'])->name('dashboard');
    Route::post('/toggle-online', [AvailabilityController::class, 'toggle'])->name('toggle-online');
    Route::post('/location', [LocationController::class, 'store'])->name('location.store');
    Route::get('/deliveries/history', [DeliveryController::class, 'history'])->name('deliveries.history');
    Route::get('/deliveries', [DeliveryController::class, 'index'])->name('deliveries');
    Route::get('/deliveries/{delivery}', [DeliveryController::class, 'show'])->name('deliveries.show');
    Route::post('/deliveries/{delivery}/pickup', [DeliveryController::class, 'pickup'])->name('deliveries.pickup');
    Route::post('/deliveries/{delivery}/deliver', [DeliveryController::class, 'deliver'])->name('deliveries.deliver');
    Route::post('/deliveries/{delivery}/reject', [DeliveryController::class, 'reject'])->name('deliveries.reject');
});

Route::middleware(['auth', 'role:customer'])->prefix('customer')->name('customer.')->group(function () {
    Route::get('/browse', [BrowseController::class, 'index'])->name('browse');
    Route::get('/merchants/{merchant}', [BrowseController::class, 'show'])->name('merchants.show');

    Route::get('/cart', [CartController::class, 'index'])->name('cart');
    Route::post('/cart/add', [CartController::class, 'add'])->name('cart.add');
    Route::post('/cart/remove/{product}', [CartController::class, 'remove'])->name('cart.remove');

    Route::get('/profile', [CustomerProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [CustomerProfileController::class, 'update'])->name('profile.update');
    Route::get('/favorites', [CustomerProfileController::class, 'favorites'])->name('favorites');
    Route::post('/favorites/{merchant}', [CustomerProfileController::class, 'toggleFavorite'])->name('favorites.toggle');

    Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
    Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');

    Route::get('/orders', [CustomerOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [CustomerOrderController::class, 'show'])->name('orders.show');
    Route::get('/orders/{order}/tracking', [TrackingController::class, 'show'])->name('orders.tracking');
    Route::get('/orders/{order}/rate', [RatingController::class, 'index'])->name('orders.rate');
    Route::post('/orders/{order}/rate', [RatingController::class, 'store'])->name('orders.rate.submit');
});
