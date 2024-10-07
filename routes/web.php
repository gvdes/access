<?php


/** @var \Laravel\Lumen\Routing\Router $router */

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

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->group(['prefix' => 'warehouse'], function () use ($router){
    $router->post('/stocks', 'WarehouseController@getStocks');
});

$router->group(['prefix' => 'sale'], function () use ($router){
    $router->get('/all', 'SalesController@getVentas');
    $router->post('/new', 'SalesController@getNewVentasTime');
    $router->get('/getSellers', 'SalesController@getSellers');
    $router->post('/folio', 'SalesController@getTicket');
});

$router->group(['prefix' => 'salidas'], function () use ($router){
    $router->get('/all', 'SalidasController@getSalidas');
    $router->post('/new', 'SalidasController@getNewSalidas');
});

$router->group(['prefix' => 'entradas'], function () use ($router){
    $router->post('/all', 'SalidasController@getEntradas');
    $router->post('/new', 'SalidasController@getNewEntradas');
});

$router->group(['prefix' => 'product'], function () use ($router){
    $router->post('/all', 'ProductController@getProducts');
    $router->post('/sync', 'ProductController@sync');
    $router->post('/info', 'ProductController@UpdatedProductAccess');
    $router->post('/validate', 'ProductController@checkDistinctProducts');
    $router->get('/prices', 'ProductController@getPrices');
    $router->get('/related', 'ProductController@getRelatedCodes');
    $router->post('/update', 'ProductController@updatedProducts');
    $router->get('/movimientos', 'DevolucionesController@getAmount');
    $router->get('/compare','ProductController@compareProductVsStock');
    $router->post('/createStocks','ProductController@createStocks');
    $router->get('/replace','ProductController@ReplaceProducts');
    $router->get('/insertar','ProductController@insart');
    $router->get('/replyp','ProductController@replypub');
    $router->get('/replydio','ProductController@replydio');
    $router->post('/insertpub','ProductController@insertpub');
    $router->post('/insertpricespub','ProductController@insertpricespub');
    $router->get('/pricesart','ProductController@pricesart');
    $router->get('/fam','ProductController@familiarizacion');
    $router->get('/depure','ProductController@productDepure');
    $router->post('/depurestor','ProductController@depureStore');
    $router->post('/insart','ProductController@insertart');
});

$router->group(['prefix' => 'client'], function () use ($router){
    $router->post('/all', 'ClientController@getClients');
    $router->post('/raw', 'ClientController@getRawClients');
    $router->post('/sync', 'ClientController@syncClients');
    $router->get('/replyespecial', 'ClientController@replyespecial');
    $router->post('/repes', 'ClientController@repes');
});

$router->group(['prefix' => 'provider'], function () use ($router){
    $router->post('/', 'ProviderController@getProviders');
    $router->post('/raw', 'ProviderController@getRawProviders');
    $router->post('/sync', 'ProviderController@syncProviders');
});

$router->group(['prefix' => 'user'], function () use ($router){
    $router->post('/', 'UserController@getUsers');
    $router->post('/raw', 'UserController@getRawUsers');
    $router->post('/sync', 'UserController@syncUsers');
    $router->get('/permission', 'UserController@permission');
    $router->post('/replypermission', 'UserController@replypermission');
    $router->post('/high', 'UserController@highusers');
});

$router->group(['prefix' => 'compras'], function () use ($router){
    $router->get('/', 'ComprasController@getTotal');
});

$router->group(['prefix' => 'preventa'], function () use ($router){
    $router->post('/folio', 'PreventaController@getTicket');
});

$router->group(['prefix' => 'connection'], function () use ($router){
    $router->get('/', 'PreventaController@getTicket');
});

$router->group(['prefix' => 'withdrawals'], function () use ($router){
    $router->get('/all', 'WithdrawalsController@getall');
    $router->post('/latest', 'WithdrawalsController@getLatest');
});

$router->group(['prefix' => 'clientOrder'], function () use ($router){
    $router->post('/create', 'ClientOrderController@createHeader');
    $router->post('/createRequisition', 'ClientOrderController@createHeaderRequisition');
    $router->post('/modifyfac','ClientOrderController@especialprice');
});

$router->group(['prefix' => 'providerOrder'], function () use ($router){
    $router->get('/all', 'ProviderOrderController@getAll');
});

$router->group(['prefix' => 'invoicesReceived'], function () use ($router){
    $router->get('/all', 'InvoicesReceivedController@getAll');
    $router->post('/new', 'InvoicesReceivedController@getNew');
});

$router->group(['prefix' => 'accounting'], function () use ($router){
    $router->get('/all', 'AccountingController@getAll');
    $router->post('/new', 'AccountingController@updated');
    $router->get('/concept', 'AccountingController@concepts');
});

$router->group(['prefix' => 'origin'], function () use ($router){
    $router->post('/changeCodes', 'OriginController@changeCodes');
    $router->post('/deleteCodes', 'OriginController@deleteCodes');
});

$router->group(['prefix' => 'Received'], function () use ($router){
    $router->post('/Received', 'ReceivedController@invoice');
});

$router->group(['prefix' => 'Required'], function () use ($router){
    $router->post('/Required', 'RequiredController@invoice_received');
});

$router->group(['prefix' => 'Facturas'], function () use ($router){
    $router->post('/Facturas', 'ClientOrderController@InvoiceRequired');
});

$router->group(['prefix' => 'iva'], function () use ($router){
    $router->get('/ticket', 'ClientOrderController@ticket');
    $router->post('/create', 'ClientOrderController@iva');
    $router->get('/prueba', 'ClientOrderController@index');
});

$router->group(['prefix' => 'modify'], function () use ($router){
    $router->get('/getTicket', 'ClientOrderController@getTicket');
    $router->get('/getPrinter', 'TicketController@getPrinter');
    $router->get('/getProduct', 'TicketController@getProduct');
    $router->get('/getClient', 'TicketController@getClient');
    $router->get('/getPrices', 'TicketController@getPrices');
    $router->get('/vales', 'TicketController@vales');
    $router->get('/getTicket/{ticket}', 'TicketController@getTicket');
    $router->post('/newmod', 'TicketController@newMod');
    $router->post('/modificacion', 'TicketController@modificacion');
    $router->post('/nwtck', 'TicketController@nwtck');
    $router->post('/retirada', 'TicketController@retirada');
    $router->post('/createVal', 'TicketController@CreateVale');

});

$router->group(['prefix' => 'reports'], function () use ($router){
    $router->get('/getCash', 'ReportController@getCash');
    $router->get('/getCashCard', 'ReportController@getCashCard');
    $router->get('/getCashCard/{date}', 'ReportController@getCashOrDateCard');
    $router->get('/getSales', 'ReportController@getSales');
    $router->get('/getSalesPerMonth/{month}', 'ReportController@getSalesPerMonth');
    $router->post('/filter', 'ReportController@filter');
    $router->post('/OpenCash', 'ReportController@OpenBoxes');
});
