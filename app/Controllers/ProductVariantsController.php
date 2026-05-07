<?php

namespace App\Controllers;

use App\Models\ProductVariantModel;
use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;
use Exception;

class ProductVariantsController extends ResourceController
{
  protected $modelName = ProductVariantModel::class;
  protected $format = 'json';


  public function index()
  {
    try {
      $data = $this->model->findAll();
      if (empty($data)) {

        return $this->failNotFound('Tidak ada data produk.');
      }

      return $this->respond([
        'status' => 'success',
        'data'   => $data
      ]);
    } catch (Exception $e) {
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage()
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  public function create()
  {
    $data = $this->request->getJSON(true);

    if (!$data) {
      return $this->fail('Invalid JSON', 400);
    }

    $insertData = [
      'product_id'   => $data['product_id'] ?? null,
      'weight_grams' => $data['weight_grams'] ?? null,
      'price'        => $data['price'] ?? null,
    ];

    if (!$this->model->insert($insertData)) {
      return $this->failValidationErrors($this->model->errors());
    }

    return $this->respondCreated([
      'status' => 'success',
      'data'   => $insertData
    ]);
  }

  public function byProduct($productId)
  {
    $variants = $this->model
      ->where('product_id', $productId)
      ->findAll();

    return $this->respond($variants);
  }

  // GET /product-variant/{id}
  public function show($id = null)
  {
    $data = $this->model->find($id);
    if (!$data) {
      return $this->failNotFound('Detail Product Variant tidak ditemukan');
    }
    return $this->respond($data);
  }

  // PUT /product-variants/{id}
  public function update($id = null)
  {
    $data = $this->request->getJSON(true);
    // dd($data['weight_grams']);
    if (!$data) {
      return $this->fail("Invalid JSON", 400);
    }

    $updateData = [
      'product_id'   => $data['product_id'] ?? null,
      'weight_grams' => $data['weight_grams'] ?? null,
      'price'        => $data['price'] ?? null,
    ];

    if (!$this->model->update($id, $updateData)) {
      return $this->failValidationErrors($this->model->errors());
    }

    return $this->respond([
      'status' => 'success',
      'data' => $updateData
    ]);
  }

  public function delete($id = null)
  {
    try {
      $db = \Config\Database::connect();

      // Cek apakah ProuductVariants ditemukan
      if (!$this->model->find($id)) {
        return $this->failNotFound('ProuductVariants tidak ditemukan.');
      }

      // Lanjut hapus ProuductVariants
      if (!$this->model->delete($id)) {
        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Gagal menghapus ProuductVariants.'
          ])
          ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
      }


      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'ProuductVariants berhasil dihapus.'
        ])
        ->setStatusCode(200);
    } catch (Exception $e) {

      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage()
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}
