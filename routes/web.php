<?php

use App\Http\Controllers\Admin\WeatherController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoogleAuthController;
use App\Http\Controllers\LeaderboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'pages.welcome')->name('home');
Route::get('/login', [AuthController::class, 'loginForm'])->middleware('guest')->name('login');
Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
Route::get('/register', [AuthController::class, 'registerForm'])->middleware('guest')->name('register');
Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->middleware('guest')->name('google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->middleware('guest')->name('google.callback');

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/services/{service}', [DashboardController::class, 'showService'])->name('services.show');
    Route::post('/services', [DashboardController::class, 'storeService'])->name('services.store');
    Route::post('/services/{service}/request', [DashboardController::class, 'requestService'])->name('services.request');
    Route::patch('/requests/{serviceRequest}', [DashboardController::class, 'updateRequest'])->name('requests.update');
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profile/{user}', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/preferences', [ProfileController::class, 'savePreferences'])->name('profile.preferences');
    Route::post('/profile/feedback', [ProfileController::class, 'storeFeedback'])->middleware('throttle:5,1')->name('profile.feedback.store');
    Route::post('/notifications/{notification}/read', [ProfileController::class, 'markNotificationAsRead'])->name('notifications.read');
    Route::get('/account/setup', [ProfileController::class, 'setup'])->name('account.setup');
    Route::put('/account/setup', [ProfileController::class, 'saveSetup'])->name('account.setup.save');
    Route::post('/requests/{serviceRequest}/review', [ProfileController::class, 'storeReview'])->name('reviews.store');
    Route::get('/leaderboard', [LeaderboardController::class, 'index'])->name('leaderboard');
    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations/start/{user}', [ConversationController::class, 'start'])->name('conversations.start');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::post('/conversations/{conversation}/messages', [ConversationController::class, 'store'])->name('conversations.messages.store');
    Route::get('/chat/{serviceRequest}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{serviceRequest}', [ChatController::class, 'store'])->name('chat.store');
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'index'])->name('index');
    Route::get('/users', [AdminController::class, 'users'])->name('users.index');
    Route::get('/services', [AdminController::class, 'services'])->name('services.index');
    Route::get('/feedback', [AdminController::class, 'feedback'])->name('feedback.index');
    Route::get('/weather', [WeatherController::class, 'index'])->name('weather.index');
    Route::get('/weather/data', [WeatherController::class, 'show'])->name('weather.data');
    Route::patch('/users/bulk', [AdminController::class, 'bulkUpdateUsers'])->name('users.bulk');
    Route::patch('/users/{user}/role', [AdminController::class, 'toggleRole'])->name('users.role');
    Route::delete('/users/{user}', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::patch('/services/bulk', [AdminController::class, 'bulkToggleServices'])->name('services.bulk');
    Route::patch('/services/{service}', [AdminController::class, 'toggleService'])->name('services.toggle');
    Route::delete('/services/{service}', [AdminController::class, 'destroyService'])->name('services.destroy');
    Route::patch('/feedback/{userFeedback}', [AdminController::class, 'updateFeedbackStatus'])->name('feedback.update');
    Route::post('/announcements', [AdminController::class, 'storeAnnouncement'])->name('announcements.store');
});
