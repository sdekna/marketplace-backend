<?php

/**
 * File name: web.php
 * Last modified: 2020.06.11 at 15:08:31
 * Author: SmarterVision - https://codecanyon.net/user/smartervision
 * Copyright (c) 2020
 */

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::get('test/whatsapp/{phone?}/{msg?}', function ($phone, $msg) {
    $respone = [
        "phone" => $phone,
        "message" => $msg,
        "response" => send_whatsapp_msg($phone, $msg)
    ];
    return $respone;
});


Route::get('register-restaurant', 'RegisterRestaurantController@show');
Route::post('register-restaurant', 'RegisterRestaurantController@register');

Route::get('login/{service}', 'Auth\LoginController@redirectToProvider');
Route::get('login/{service}/callback', 'Auth\LoginController@handleProviderCallback');
Auth::routes();

Route::view('privacy', 'privacy');

Route::get('payments/failed', 'PayPalController@index')->name('payments.failed');
Route::get('payments/razorpay/checkout', 'RazorPayController@checkout');
Route::post('payments/razorpay/pay-success/{userId}/{deliveryAddressId?}/{couponCode?}', 'RazorPayController@paySuccess');
Route::get('payments/razorpay', 'RazorPayController@index');

Route::get('payments/paypal/express-checkout', 'PayPalController@getExpressCheckout')->name('paypal.express-checkout');
Route::get('payments/paypal/express-checkout-success', 'PayPalController@getExpressCheckoutSuccess');
Route::get('payments/paypal', 'PayPalController@index')->name('paypal.index');

Route::get('firebase/sw-js', 'AppSettingController@initFirebase');


Route::get('storage/app/public/{id}/{conversion}/{filename?}', 'UploadController@storage');
Route::middleware('auth')->group(function () {
    Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
    Route::get('/', 'DashboardController@index')->name('dashboard');

    Route::post('uploads/store', 'UploadController@store')->name('medias.create');
    Route::get('users/profile', 'UserController@profile')->name('users.profile');
    Route::post('users/remove-media', 'UserController@removeMedia');
    Route::resource('users', 'UserController');
    Route::get('dashboard', 'DashboardController@index')->name('dashboard');

    Route::group(['middleware' => ['permission:medias']], function () {
        Route::get('uploads/all/{collection?}', 'UploadController@all');
        Route::get('uploads/collectionsNames', 'UploadController@collectionsNames');
        Route::post('uploads/clear', 'UploadController@clear')->name('medias.delete');
        Route::get('medias', 'UploadController@index')->name('medias');
        Route::get('uploads/clear-all', 'UploadController@clearAll');
    });

    Route::group(['middleware' => ['permission:permissions.index']], function () {
        Route::get('permissions/role-has-permission', 'PermissionController@roleHasPermission');
        Route::get('permissions/refresh-permissions', 'PermissionController@refreshPermissions');
    });
    Route::group(['middleware' => ['permission:permissions.index']], function () {
        Route::post('permissions/give-permission-to-role', 'PermissionController@givePermissionToRole');
        Route::post('permissions/revoke-permission-to-role', 'PermissionController@revokePermissionToRole');
    });

    Route::group(['middleware' => ['permission:app-settings']], function () {
        Route::prefix('settings')->group(function () {
            Route::resource('permissions', 'PermissionController');
            Route::resource('roles', 'RoleController');
            Route::resource('customFields', 'CustomFieldController');
            Route::resource('currencies', 'CurrencyController')->except([
                'show'
            ]);
            Route::get('users/login-as-user/{id}', 'UserController@loginAsUser')->name('users.login-as-user');
            Route::patch('update', 'AppSettingController@update');
            Route::patch('translate', 'AppSettingController@translate');
            Route::get('sync-translation', 'AppSettingController@syncTranslation');
            Route::get('clear-cache', 'AppSettingController@clearCache');
            Route::get('check-update', 'AppSettingController@checkForUpdates');
            // disable special character and number in route params
            Route::get('/{type?}/{tab?}', 'AppSettingController@index')
                ->where('type', '[A-Za-z]*')->where('tab', '[A-Za-z]*')->name('app-settings');
        });
    });

    Route::post('cuisines/remove-media', 'CuisineController@removeMedia');
    Route::resource('cuisines', 'CuisineController')->except([
        'show'
    ]);

    Route::post('restaurants/remove-media', 'RestaurantController@removeMedia');
    Route::get('requestedRestaurants', 'RestaurantController@requestedRestaurants')->name('requestedRestaurants.index'); //adeed
    Route::resource('restaurants', 'RestaurantController')->except([
        'show'
    ]);
    Route::resource('restaurantDistancePrices', 'RestaurantDistancePriceController')->except([
        'show'
    ]);

    Route::post('categories/remove-media', 'CategoryController@removeMedia');
    Route::resource('categories', 'CategoryController')->except([
        'show'
    ]);

    Route::resource('faqCategories', 'FaqCategoryController')->except([
        'show'
    ]);

    Route::resource('orderStatuses', 'OrderStatusController')->except([
        'create', 'store', 'destroy'
    ]);;

    Route::post('foods/remove-media', 'FoodController@removeMedia');
    Route::resource('foods', 'FoodController')->except([
        'show'
    ]);

    Route::post('galleries/remove-media', 'GalleryController@removeMedia');
    Route::resource('galleries', 'GalleryController')->except([
        'show'
    ]);

    Route::resource('foodReviews', 'FoodReviewController')->except([
        'show'
    ]);


    Route::resource('nutrition', 'NutritionController')->except([
        'show'
    ]);

    Route::post('extras/remove-media', 'ExtraController@removeMedia');
    Route::resource('extras', 'ExtraController');

    Route::resource('payments', 'PaymentController')->except([
        'create', 'store', 'edit', 'destroy'
    ]);;

    Route::resource('faqs', 'FaqController')->except([
        'show'
    ]);
    Route::resource('restaurantReviews', 'RestaurantReviewController')->except([
        'show'
    ]);

    Route::resource('favorites', 'FavoriteController')->except([
        'show'
    ]);


    Route::get('orders/waitting-drivers', 'OrderController@ordersWaittingForDrivers')->name('orders.waitting_drivers');
    Route::get('orders/set-driver/{order_id}/{driver_id}', 'OrderController@setDriverForOrder');

    Route::resource('orders', 'OrderController');

    Route::resource('notifications', 'NotificationController')->except([
        'create', 'store', 'update', 'edit',
    ]);;

    Route::resource('carts', 'CartController')->except([
        'show', 'store', 'create'
    ]);
    Route::resource('deliveryAddresses', 'DeliveryAddressController')->except([
        'show'
    ]);

    Route::resource('drivers', 'DriverController')->except([
        'show'
    ]);

    Route::get('drivers/map', 'DriverController@map')->name('drivers.map');
    Route::get('drivers/update-data-in-firestore', 'DriverController@updateDataInFirestore')->name('drivers.update_data_in_firestore');

    Route::resource('driverTypes', 'DriverTypeController');

    Route::resource('driverReviews', 'DriverReviewController');

    Route::resource('earnings', 'EarningController')->except([
        'show', 'edit', 'update'
    ]);

    Route::resource('driversPayouts', 'DriversPayoutController')->except([
        'show', 'edit', 'update'
    ]);

    Route::resource('restaurantsPayouts', 'RestaurantsPayoutController')->except([
        'show', 'edit', 'update'
    ]);

    Route::resource('extraGroups', 'ExtraGroupController')->except([
        'show'
    ]);

    Route::post('extras/remove-media', 'ExtraController@removeMedia');

    Route::resource('extras', 'ExtraController')->except([
        'show'
    ]);
    Route::resource('coupons', 'CouponController'); //->except(['show']);
    Route::post('slides/remove-media', 'SlideController@removeMedia');
    Route::resource('slides', 'SlideController')->except([
        'show'
    ]);

    Route::get('settlementDrivers/available', 'SettlementDriverController@indexAvailable')->name('settlementDrivers.indexAvailable');
    Route::get('settlementDrivers/available/{driver_id}', 'SettlementDriverController@showAvailable')->name('settlementDrivers.showAvailable');
    Route::resource('settlementDrivers', 'SettlementDriverController');
    Route::get('settlementDrivers/print/{id}', 'SettlementDriverController@print');


    Route::get('settlementManagers/available', 'SettlementManagerController@indexAvailable')->name('settlementManagers.indexAvailable');
    Route::get('settlementManagers/available/{driver_id}', 'SettlementManagerController@showAvailable')->name('settlementManagers.showAvailable');
    Route::resource('settlementManagers', 'SettlementManagerController');
    Route::get('settlementManagers/print/{id}', 'SettlementManagerController@print');

    //// new Dashboard for operations
    // Route::group(['middleware' => ['permission:operations']], function () {
        Route::prefix('operations')->group(function () {
            Route::get('users/profile/{userId}/info', 'Operations\ClientController@profile')->name('operations.users.profile.info');
            Route::get('users/profile/{userId}/favorites', 'Operations\ClientController@favorites')->name('operations.users.profile.favorites');
            Route::get('users/profile/{userId}/orders', 'Operations\ClientController@orders')->name('operations.users.profile.orders');
            Route::get('users/profile/{userId}/notes', 'Operations\ClientController@notes')->name('operations.users.profile.notes');
            Route::get('users/profile/{userId}/orders/{orderId}', 'Operations\ClientController@viewOrders')->name('operations.users.profile.orders.view');

            Route::resource('users', 'Operations\ClientController',['names' => 'operations.users']);

        });

    // });


});
