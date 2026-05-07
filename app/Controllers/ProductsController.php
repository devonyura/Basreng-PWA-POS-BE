<?php

namespace App\Controllers;

use App\Models\ProductModel;
use App\Models\ActivityLogModel;
use App\Helpers\JwtHelper;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Exception;

class ProductsController extends ResourceController
{
  protected $modelName = 'App\Models\ProductModel';
  protected $format    = 'json';

  private function createLog($action, $details = null)
  {
    $jwtHelper = new JwtHelper();
    $logModel  = new ActivityLogModel();
    $request   = service('request');
    $authHeader = $request->getHeaderLine('Authorization');

    if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
      $token   = $matches[1];
      $decoded = $jwtHelper->validateJWT($token);
      if ($decoded) {
        $logModel->logActivity($decoded['id'], $decoded['username'], $action, $details);
      }
    }
  }


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

  // GET /products
  public function getWithVariant()
  {
    $db = \Config\Database::connect();

    $builder = $db->table('products p');

    $builder->select("
        p.id as product_id,
        p.name,
        p.img,
        p.category_id,
        p.descriptions,

        pv.id as variant_id,
        pv.weight_grams,
        pv.price
    ");

    $builder->join(
      'product_variants pv',
      'pv.product_id = p.id',
      'left'
    );
    $builder->where('pv.deleted_at', null);
    $builder->orderBy('p.id', 'ASC');

    $result = $builder->get()->getResultArray();

    // ===============================
    // GROUPING PRODUCTS -> VARIANTS
    // ===============================

    $products = [];

    foreach ($result as $row) {

      $pid = $row['product_id'];

      if (!isset($products[$pid])) {
        $products[$pid] = [
          'id' => $pid,
          'name' => $row['name'],
          'img' => $row['img'],
          'descriptions' => $row['descriptions'],
          'category_id' => $row['category_id'],
          'variants' => []
        ];
      }

      // jika ada variant
      if ($row['variant_id']) {
        $products[$pid]['variants'][] = [
          'variant_id' => $row['variant_id'],
          'weight_grams' => $row['weight_grams'],
          'price' => $row['price'],
        ];
      }
    }

    return $this->respond([
      'status' => 'success',
      'data' => array_values($products)
    ]);
  }

  // POST /products
  public function create()
  {
    $file = $this->request->getFile('img');

    $imgName = null;

    if ($file && $file->isValid()) {
      $imgName = $file->getRandomName();
      $file->move(FCPATH . 'uploads/products/', $imgName);
    }

    $productData = [
      'category_id'    => $this->request->getPost('category_id'),
      'subcategory_id' => $this->request->getPost('subcategory_id'),
      'name'           => $this->request->getPost('name'),
      'descriptions'   => $this->request->getPost('descriptions'),
      'img'            => $imgName
    ];

    $this->model->insert($productData);

    return $this->respondCreated([
      'status' => 'success',
      'message' => 'Product master created',
      'product' => $productData,
    ]);
  }

  // POST api/products/update/{id}
  public function update($id = null)
  {
    // dd($this->request->getFile('img'));
    $product = $this->model->find($id);

    if (!$product) {
      return $this->failNotFound('Produk tidak ditemukan');
    }

    $file = $this->request->getFile('img');

    $imgName = $product['img'];

    // jika upload gambar baru
    if ($file && $file->isValid()) {

      // hapus gambar lama
      if (
        $product['img'] &&
        file_exists(FCPATH . 'uploads/products/' . $product['img'])
      ) {

        unlink(FCPATH . 'uploads/products/' . $product['img']);
      }

      $imgName = $file->getRandomName();

      $file->move(
        FCPATH . 'uploads/products/',
        $imgName
      );
    }

    $data = [
      'name' => $this->request->getPost('name'),
      'category_id' => $this->request->getPost('category_id'),
      'subcategory_id' => $this->request->getPost('subcategory_id'),
      'price' => $this->request->getPost('price'),
      'descriptions' => $this->request->getPost('descriptions'),
      'weight_grams' => $this->request->getPost('weight_grams'),
      'img' => $imgName
    ];

    $this->model->update($id, $data);

    return $this->respond([
      'status' => 'success'
    ]);
  }

  // DELETE /products/{id}
  public function delete($id = null)
  {
    $product = $this->model->find($id);

    if (!$product) {
      return $this->failNotFound();
    }

    if (
      $product['img'] &&
      file_exists(FCPATH . 'uploads/products/' . $product['img'])
    ) {

      unlink(FCPATH . 'uploads/products/' . $product['img']);
    }

    $this->model->delete($id);

    return $this->respondDeleted([
      'status' => 'success'
    ]);
  }
}
