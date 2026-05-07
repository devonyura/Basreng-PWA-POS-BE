<?php

namespace App\Filters;

use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Filters\FilterInterface;
use Config\Services;
use App\Helpers\JwtHelper;

class JwtAuth implements FilterInterface
{
  public function before(RequestInterface $request, $arguments = null)
  {
    $header = $request->getHeaderLine('Authorization');

    if (!$header || !str_starts_with($header, 'Bearer ')) {
      return Services::response()
        ->setJSON(['message' => 'Token tidak ditemukan'])
        ->setStatusCode(401);
    }

    $token = substr($header, 7);

    try {
      $jwtHelper = new JwtHelper();
      $decoded = $jwtHelper->validateJWT($token);

      // Inject user ke request
      $request->user = $decoded;
    } catch (\Exception $e) {
      return Services::response()
        ->setJSON(['message' => 'Token tidak valid'])
        ->setStatusCode(401);
    }
  }

  public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
  {
    // Nothing
  }
}
