<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->get('/', 'Home::index');
$routes->options('api/ping', 'PingController::index');
$routes->head('api/ping', 'PingController::index');

$routes->group('api/auth', function ($routes) {
  $routes->post('login', 'AuthController::login');
  $routes->post('register', 'AuthController::register');
});

$routes->group('api', ['filter' => 'jwtAuth'], function ($routes) {
  $routes->get('report/summary', 'ReportController::summary');
});

$routes->group('api', ['filter' => 'auth'], function ($routes) {
  // Reports endpoint
  $routes->get('reports/daily', 'ReportsController::daily');
  $routes->get('reports/range', 'ReportsController::range');
  $routes->get('reports/monthly', 'ReportsController::monthly');

  $routes->get('report/sendDailyReport', 'ReportController::sendDailyReport');
  $routes->get('report/sendDailyReport/(:num)', 'ReportController::sendDailyReport/$1');


  // Report
  $routes->get('report/getDetailReport/(:any)', 'ReportController::getDetailReport/$1');
  $routes->get('report/getAllReports', 'ReportController::getAllReports');
  $routes->get('report/getTransactionsReport', 'ReportController::getTransactionsReport');
  $routes->get('report/getTransactionsReport/(:num)', 'ReportController::getTransactionsReport/$1');
  // ===============================
  $routes->get('report/summary', 'ReportController::summary');
  $routes->get('report/getBranchReport', 'ReportController::getBranchReport');
  $routes->get('report/top-selling', 'ReportController::topSelling');

  // users data
  $routes->resource('users', ['controller' => 'UsersController']);
  $routes->post('users/reset-password', 'UsersController::resetPassword');

  $routes->resource('siswa', ['controller' => 'SiswaController']);

  $routes->resource('transactions', ['controller' => 'TransactionsController']);
  $routes->post('transactions/create-transaction', 'TransactionsController::createTransaction');
  $routes->post('transactions/get-receipt', 'TransactionsController::get_receipt');

  $routes->get('products/get-with-variant', 'ProductsController::getWithVariant');
  $routes->resource('products', ['controller' => 'ProductsController']);
  $routes->post('products/update/(:num)', 'ProductsController::update/$1');

  $routes->resource('packages', ['controller' => 'PackageController']);
  $routes->resource('categories', ['controller' => 'CategoriesController']);
  $routes->resource('subcategories', ['controller' => 'SubCategoriesController']);
  $routes->resource('branch', ['controller' => 'BranchController']);
  $routes->resource('resellers', ['controller' => 'ResellersController']);

  $routes->get('product-variants/product/(:num)', 'ProductVariantsController::byProduct/$1');
  $routes->resource('product-variants', ['controller' => 'ProductVariantsController']);


  $routes->get('transaction-details/transaction/(:num)', 'TransactionsDetailsController::showByTransactionId/$1');
  $routes->resource('transaction-details', ['controller' => 'TransactionsDetailsController']);

  // ============= PaymentProofs
  $routes->post('payment-proofs/upload', 'PaymentProofController::upload');
  $routes->get('payment-proofs/transaction/(:segment)', 'PaymentProofController::getByTransaction/$1');
});

$routes->get('api/logs', 'LogController::index');
