<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Config\JWT as JWTConfig;
use Exception;
use CodeIgniter\HTTP\RequestInterface;

class JwtHelper
{
  public static function generateJWT(array $data): string
  {
    $issuedAt = time();
    $expireAt = $issuedAt + JWTConfig::$tokenExpiry;

    $payload = [
      'iat'  => $issuedAt,
      'exp'  => $expireAt,
      'data' => $data
    ];

    return JWT::encode($payload, JWTConfig::$secretKey, JWTConfig::$algorithm);
  }

  public static function decodeToken(string $token): array
  {
    $decoded = JWT::decode(
      $token,
      new Key(JWTConfig::$secretKey, JWTConfig::$algorithm)
    );

    return (array) $decoded->data;
  }

  public static function getUserFromRequest(RequestInterface $request): array
  {
    $authHeader = $request->getHeaderLine('Authorization');

    if (!$authHeader) {
      throw new Exception('Token not provided');
    }

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
      throw new Exception('Invalid token format');
    }

    $token = $matches[1];

    return self::decodeToken($token);
  }

  function validateJWT($token)
  {

    try {
      $decoded = JWT::decode($token, new Key(JWTConfig::$secretKey, JWTConfig::$algorithm));
      return (array) $decoded->data;
    } catch (Exception $e) {
      return false;
    }
  }
}
