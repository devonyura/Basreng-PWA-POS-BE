<?php

namespace App\Controllers;

use App\Models\PackageModel;
use App\Models\ActivityLogModel;
use App\Helpers\JwtHelper;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Exception;

class PackageController extends ResourceController
{
  protected $modelName = 'App\Models\PackageModel';
  protected $format    = 'json';

  // GET /package
  public function index()
  {
    try {
      $data = $this->model->findAll();
      if (empty($data)) {
        return $this->failNotFound('Tidak ada data package.');
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

  // SHOW /package/{id}
  public function show($id = null)
  {
    $data = $this->model->find($id);
    if (!$data) {
      return $this->failNotFound('Detail package tidak ditemukan');
    }
    return $this->respond([
      'status' => 'success',
      'data'   => [
        'name' => $data['name'],
        'price' => $data['price'],
      ]
    ]);
  }

  // POST /package
  public function create()
  {
    $rules = [
      'name'        => 'required|min_length[3]|is_unique[package.name]',
      'price'       => 'required|decimal'
    ];

    if (!$this->validate($rules)) {
      return $this->failValidationErrors($this->validator->getErrors());
    }

    $data = $this->request->getJSON();
    $productData = [
      'name'        => $data->name,
      'price'       => $data->price
    ];

    try {
      if (!$this->model->insert($productData)) {

        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Gagal menambahkan Package.',
            'errors'  => $this->model->errors()
          ])
          ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
      }
      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Package berhasil ditambahkan',
          'data'    => $productData
        ])
        ->setStatusCode(ResponseInterface::HTTP_CREATED);
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

  // PUT /package/{id}
  public function update($id = null)
  {
    $rules = [
      'name'        => 'required|min_length[3]',
      'price'       => 'required|decimal'
    ];

    $data = $this->request->getJSON();

    if (!$this->model->find($id)) {
      return Services::response()
        ->setJSON(['status' => 'error', 'message' => 'Package tidak ditemukan'])
        ->setStatusCode(404);
    }

    if (!$this->validate($rules)) {
      return $this->failValidationErrors($this->validator->getErrors());
    }

    $productData = [
      'name'        => $data->name,
      'price'       => $data->price,
    ];

    try {
      $this->model->update($id, $productData);
      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Package berhasil diperbarui',
          'data'    => $productData
        ])
        ->setStatusCode(200);
    } catch (Exception $e) {
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Gagal memperbarui Package',
          'error'   => $e->getMessage()
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // DELETE /package/{id}
  public function delete($id = null)
  {
    try {
      if (!$this->model->find($id)) {
        return $this->failNotFound('Package tidak ditemukan.');
      }

      if (!$this->model->delete($id)) {
        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Gagal menghapus Package.'
          ])
          ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
      }

      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Package berhasil dihapus.'
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
