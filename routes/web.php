<?php

/** @var Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

use Laravel\Lumen\Routing\Router;

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'api'], function () use ($router) {
    $router->group(['prefix' => 'middleware'], function () use ($router) {
        $router->get('/shippingAddresses/{clientCode}', 'MiddlewareController@getShippingAddresses');
        $router->get('/billingStatus/{billNumber}', 'MiddlewareController@getBillingStatus');
        $router->get('/bills/{clientCode}', 'MiddlewareController@getBills');
        $router->get('/billsAndStatus/{clientCode}', 'MiddlewareController@getBillsAndStatus');
        $router->get('/billLines/{clientCode}/{billNumber}', 'MiddlewareController@getBillLines');
        $router->get('/orderLines/{clientCode}/{billNumber}', 'MiddlewareController@getOrderLines');
        $router->get('/webOrderLines/{clientCode}/{id}', 'MiddlewareController@getShipmentWeb');
        $router->get('/quoteLines/{clientCode}/{quoteNumber}', 'MiddlewareController@getQuoteLines');
        $router->get('/credits/{clientCode}', 'MiddlewareController@getCredits');
        $router->get('/creditsSold/{clientCode}', 'MiddlewareController@getCreditsSold');
        $router->post('/repartition', 'MiddlewareController@postRepartion');
        $router->post('/document', 'MiddlewareController@postAndGetDocument');
        $router->get('/shipments/{clientCode}', 'MiddlewareController@getShipments');
        $router->get('/products', 'MiddlewareController@getAllProducts');
        $router->get('/products/{reference}', 'MiddlewareController@getOneProduct');
        $router->get('/productsByCatalogue/{CatNiv1}[/{CatNiv2}[/{CatNiv3}[/{CatNiv4}[/{CatNiv5}[/{CatNiv6}[/{CatNiv7}]]]]]]', 'MiddlewareController@getProductsByCatalogue');
        $router->get('/productsBySearchQuery/{searchQuery}', 'MiddlewareController@getSearchProducts');
        $router->get('/productsByCode/{searchQuery}', 'MiddlewareController@getSearchProductsByCode');
        $router->get('/productsBySearchQueryTop/{searchQuery}', 'MiddlewareController@getTopTenSearchProducts');
        $router->get('/catalogue', 'MiddlewareController@getCatalogue');
        $router->get('/catalogueTopTenProducts/{cat}', 'MiddlewareController@getTopTenProductsByCatalogue');
        $router->get('/checkEmail/{email}', 'MiddlewareController@getEmailExists');
        $router->get('/clientInfos/{clientCode}', 'MiddlewareController@getClientInfos');
        $router->get('/unlockClient/{clientCode}', 'MiddlewareController@getUnlockClient');
        $router->get('/contacts/{clientCode}', 'MiddlewareController@getClientContacts');
        $router->post('/partnersFclient', 'MiddlewareController@postGetPartnerFromClient');
        $router->post('/clientsFpartner', 'MiddlewareController@postGetCLientFromPartner');
        $router->post('/partner', 'MiddlewareController@postPartner');
        $router->post('/partnership', 'MiddlewareController@postPartnership');
        $router->get('/contactFunction', 'MiddlewareController@getContactFunction');
        $router->post('/postAddress', 'MiddlewareController@postAddress');
        $router->get('/bill/{billNumber}/{clientCode}', 'MiddlewareController@getBillInPdf');
        $router->get('/credit/{billNumber}/{clientCode}', 'MiddlewareController@getCreditInPdf');
        $router->post('/post-order', 'MiddlewareController@postOrder');
        $router->post('/post-order-lda', 'MiddlewareController@postOrderLda');
        $router->get('/getSmileys/{uuid}', 'MiddlewareController@getSmileys');
        $router->post('/consume-smileys', 'MiddlewareController@consumeSmileys');
        $router->post('/confirm-order', 'OrderconfirmController@confirmOrder');
        $router->post('/ask-registration', 'AuthController@askRegistration');
        $router->post('/fake-registration', 'AuthController@registerFake');
        $router->get('/get-network-links/{sfid}', 'MiddlewareController@getNetworkLinks');
        $router->get('/stock-lda/{ref}', 'MiddlewareController@getItemsStockLDA');
        $router->post('/test', 'GenerateDataController@postCredits');

        // Distributor middleware proxies (§5)
        $router->get('/product-authorization/{reference}/{billingCountry}', 'DistributorOrderController@productAuthorization');
        $router->get('/client-info-v3/{clientCode}', 'MiddlewareController@getClientInfosV3');
    });

    // Distributor checkout (§7)
    $router->group(['prefix' => 'distributor'], function () use ($router) {
        $router->post('/order', 'DistributorOrderController@store');
        $router->get('/my-pending-orders', 'DistributorOrderController@myPendingOrders');
    });

    // ADV_INTER workflow (§10–§11, §14)
    // Numeric constraint on {id} prevents the dynamic route from swallowing
    // string paths like /orders/template.
    $router->group(['prefix' => 'adv-inter'], function () use ($router) {
        $router->get('/orders', 'AdvInterController@index');
        $router->post('/orders', 'AdvInterController@store');
        $router->get('/orders/template', 'AdvInterController@template');
        $router->get('/orders/{id:[0-9]+}', 'AdvInterController@show');
        $router->put('/orders/{id:[0-9]+}', 'AdvInterController@update');
        $router->get('/orders/{id:[0-9]+}/preview-zsoh', 'AdvInterController@previewZsoh');
        $router->post('/orders/{id:[0-9]+}/send', 'AdvInterController@send');
        $router->post('/orders/{id:[0-9]+}/archive', 'AdvInterController@archive');
        $router->post('/orders/{id:[0-9]+}/unarchive', 'AdvInterController@unarchive');
        $router->post('/orders/{id:[0-9]+}/duplicate', 'AdvInterController@duplicate');
        $router->post('/upload', 'AdvInterController@upload');
    });

    $router->post('/reverse-login', 'AuthController@reverseLoginSSO');
    $router->post('/login', 'AuthController@login');
    $router->post('/login-admin', 'AuthController@loginAdmin');
    $router->post('/login-sso', 'AuthController@loginWithSso');
    $router->post('/register-admin', 'AuthController@registerAdmin');
    $router->post('/check-email', 'AuthController@checkEmail');
    $router->post('/register', 'AuthController@register');
    $router->post('/reverse-login-sso', 'AuthController@reverseLoginSSO');
    $router->get('/refresh-token', 'AuthController@refresh');
    $router->post('/forgot-password', 'AuthController@forgotPassword');
    $router->post('/change-password', 'AuthController@changePassword');
    $router->get('/logout', 'AuthController@logout');
    $router->get('/check-login', 'AuthController@checkLogin');
    $router->post('/update-password', 'AuthController@updatePassword');
    $router->group(['prefix' => 'wishlist'], function () use ($router) {
        $router->get('/', 'WishlistController@index');
        $router->get('/{id}', 'WishlistController@show');
        $router->post('/', 'WishlistController@store');
        $router->put('/{id}', 'WishlistController@update');
        $router->delete('/{id}', 'WishlistController@destroy');
    });
    $router->group(['prefix' => 'orderConfirm'], function () use ($router) {
        $router->post('/', 'OrderconfirmController@store');
    });
    $router->group(['prefix' => 'sso'], function () use ($router) {
        $router->post('/', 'AuthController@authSSO');
        $router->post('/attach', 'AuthController@attachSSO');
    });

    $router->group(['prefix' => 'order'], function () use ($router) {
        $router->post('/', 'OrderconfirmController@confirmOrder');
        $router->get('/', 'OrderController@index');
        $router->get('/{id}', 'OrderController@show');
        $router->post('/', 'OrderController@store');
    });
});
