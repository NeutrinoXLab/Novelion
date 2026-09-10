<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\MyOrderController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\ReturnRequestController;


/*
|--------------------------------------------------------------------------
| Magazin
|--------------------------------------------------------------------------
*/

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/search', [HomeController::class, 'search'])
    ->name('products.search');

Route::get('/produse', [ProductController::class, 'index'])
    ->name('products.index');

Route::get('/produse-noi', [ProductController::class, 'newProducts'])
    ->name('products.new');

    Route::post('/newsletter/subscribe', [NewsletterController::class, 'subscribe'])
    ->name('newsletter.subscribe');

    Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])
    ->name('newsletter.unsubscribe');

Route::get('/promotii', [ProductController::class, 'promotions'])
    ->name('products.promotions');

Route::get('/produs/{product:slug}', [ProductController::class, 'show'])
    ->name('products.show');

Route::get('/categorii', [CategoryController::class, 'index'])
    ->name('categories.index');

Route::get('/categorie/{category:slug}', [CategoryController::class, 'show'])
    ->name('categories.show');


/*
|--------------------------------------------------------------------------
| Pagini informaționale
|--------------------------------------------------------------------------
*/

Route::get('/despre-noi', [PageController::class, 'about'])
    ->name('pages.about');

Route::get('/contact', [PageController::class, 'contact'])
    ->name('pages.contact');

Route::get('/livrare', [PageController::class, 'shipping'])
    ->name('pages.shipping');

Route::get('/retur', [PageController::class, 'returns'])
    ->name('pages.returns');

Route::get('/termeni-si-conditii', [PageController::class, 'terms'])
    ->name('pages.terms');

Route::get('/politica-de-confidentialitate', [PageController::class, 'privacy'])
    ->name('pages.privacy');


/*
|--------------------------------------------------------------------------
| Chat
|--------------------------------------------------------------------------
*/

Route::get('/chat', [ChatController::class, 'index'])
    ->name('chat.index');

Route::post('/chat/send', [ChatController::class, 'send'])
    ->name('chat.send');


/*
|--------------------------------------------------------------------------
| Coș
|--------------------------------------------------------------------------
*/

Route::get('/cart', [CartController::class, 'index'])
    ->name('cart.index');

Route::post('/cart/add/{product}', [CartController::class, 'add'])
    ->name('cart.add');

Route::post('/cart/update/{product}', [CartController::class, 'update'])
    ->name('cart.update');

Route::delete('/cart/remove/{product}', [CartController::class, 'remove'])
    ->name('cart.remove');

Route::post('/cart/clear', [CartController::class, 'clear'])
    ->name('cart.clear');


/*
|--------------------------------------------------------------------------
| Rute protejate
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Checkout
    |--------------------------------------------------------------------------
    */

    Route::get('/checkout', [CheckoutController::class, 'index'])
        ->name('checkout.index');

    Route::post('/checkout', [CheckoutController::class, 'store'])
        ->name('checkout.store');

    Route::get('/checkout/success/{order}', [CheckoutController::class, 'success'])
        ->name('checkout.success');

    Route::get('/checkout/cancel/{order}', [CheckoutController::class, 'cancel'])
        ->name('checkout.cancel');


    /*
    |--------------------------------------------------------------------------
    | Comenzi
    |--------------------------------------------------------------------------
    */

    Route::get('/my-orders', [MyOrderController::class, 'index'])
        ->name('my-orders.index');

    Route::get('/my-orders/{order}', [MyOrderController::class, 'show'])
        ->name('my-orders.show');


    /*
    |--------------------------------------------------------------------------
    | Retururi
    |--------------------------------------------------------------------------
    */

    Route::get('/my-orders/{order}/return', [ReturnRequestController::class, 'create'])
        ->name('returns.create');

    Route::post('/my-orders/{order}/return', [ReturnRequestController::class, 'store'])
        ->name('returns.store');


    /*
    |--------------------------------------------------------------------------
    | Favorite
    |--------------------------------------------------------------------------
    */

    Route::get('/wishlist', [WishlistController::class, 'index'])
        ->name('wishlist.index');

    Route::post('/wishlist/{product}', [WishlistController::class, 'toggle'])
        ->name('wishlist.toggle');


    /*
    |--------------------------------------------------------------------------
    | Recenzii
    |--------------------------------------------------------------------------
    */

    Route::post('/reviews/{product}', [ReviewController::class, 'store'])
        ->name('reviews.store');
});


/*
|--------------------------------------------------------------------------
| Dashboard
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');


/*
|--------------------------------------------------------------------------
| Profil
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {

    Route::get('/profile', [ProfileController::class, 'edit'])
        ->name('profile.edit');

    Route::patch('/profile', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::delete('/profile', [ProfileController::class, 'destroy'])
        ->name('profile.destroy');

});


/*
|--------------------------------------------------------------------------
| Stripe Webhook
|--------------------------------------------------------------------------
*/

Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');





require __DIR__.'/auth.php';