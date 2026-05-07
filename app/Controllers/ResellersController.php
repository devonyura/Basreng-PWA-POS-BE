<?php

namespace App\Controllers;

use App\Models\ActivityLogModel;
use App\Helpers\JwtHelper;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Exception;

class ResellersController extends ResourceController
{
  protected $modelName = 'App\Models\ResellerModel';
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

  // GET /resellers
  public function index()
  {
    try {
      $data = $this->model->findAll();
      if (empty($data)) {
        $this->createLog('READ_ALL_RESELLERS', 'Tidak ada data reseller.');
        return $this->failNotFound('Tidak ada data reseller.');
      }
      $this->createLog('READ_ALL_RESELLERS', ['SUCCESS']);
      return $this->respond([
        'status' => 'success',
        'data'   => $data,
      ]);
    } catch (Exception $e) {
      $this->createLog('READ_ALL_RESELLERS', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage(),
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // GET /resellers/{id}
  public function show($id = null)
  {
    $data = $this->model->find($id);
    if (!$data) {
      return $this->failNotFound('Detail reseller tidak ditemukan');
    }
    return $this->respond([
      'status' => 'success',
      'data'   => $data,
    ]);
  }

  // POST /resellers
  public function create()
  {
    $rules = [
      'name'    => 'required|min_length[3]',
      'phone'   => 'permit_empty|min_length[6]',
      'address' => 'permit_empty',
    ];

    if (!$this->validate($rules)) {
      return $this->failValidationErrors($this->validator->getErrors());
    }

    $data = $this->request->getJSON();
    $resellerData = [
      'name'    => $data->name,
      'phone'   => $data->phone ?? null,
      'address' => $data->address ?? null,
    ];

    try {
      if (!$this->model->insert($resellerData)) {
        $this->createLog('CREATE_RESELLER', ['ERROR']);
        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Gagal menambahkan reseller.',
            'errors'  => $this->model->errors(),
          ])
          ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
      }
      $this->createLog('CREATE_RESELLER', ['SUCCESS']);
      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Reseller berhasil ditambahkan',
          'data'    => $resellerData,
        ])
        ->setStatusCode(ResponseInterface::HTTP_CREATED);
    } catch (Exception $e) {
      $this->createLog('CREATE_RESELLER', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage(),
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // PUT /resellers/{id}
  public function update($id = null)
  {
    $rules = [
      'name'    => 'required|min_length[3]',
      'phone'   => 'permit_empty|min_length[6]',
      'address' => 'permit_empty',
    ];

    $data = $this->request->getJSON();

    if (!$this->model->find($id)) {
      return Services::response()
        ->setJSON(['status' => 'error', 'message' => 'Reseller tidak ditemukan'])
        ->setStatusCode(404);
    }

    if (!$this->validate($rules)) {
      $this->createLog('UPDATE_RESELLER', ['ERROR: Validasi gagal']);
      return $this->failValidationErrors($this->validator->getErrors());
    }

    $resellerData = [
      'name'    => $data->name,
      'phone'   => $data->phone ?? null,
      'address' => $data->address ?? null,
    ];

    try {
      $this->model->update($id, $resellerData);
      $this->createLog('UPDATE_RESELLER', ['SUCCESS']);
      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Reseller berhasil diperbarui',
          'data'    => $resellerData,
        ])
        ->setStatusCode(200);
    } catch (Exception $e) {
      $this->createLog('UPDATE_RESELLER', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Gagal memperbarui reseller',
          'error'   => $e->getMessage(),
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // DELETE /resellers/{id}
  public function delete($id = null)
  {
    try {
      $db = \Config\Database::connect();

      if (!$this->model->find($id)) {
        $this->createLog('DELETE_RESELLER', ['ERROR: Reseller tidak ditemukan.']);
        return $this->failNotFound('Reseller tidak ditemukan.');
      }

      $usedByTransactions = $db->table('transactions')
        ->where('reseller_id', $id)
        ->countAllResults();

      if ($usedByTransactions > 0) {
        $this->createLog('DELETE_RESELLER', ['ERROR: Digunakan oleh tabel transactions.']);
        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Reseller tidak dapat dihapus karena masih digunakan oleh transaksi.'
          ])
          ->setStatusCode(ResponseInterface::HTTP_BAD_REQUEST);
      }

      if (!$this->model->delete($id)) {
        $this->createLog('DELETE_RESELLER', ['ERROR saat menghapus.']);
        return Services::response()
          ->setJSON([
            'status'  => 'error',
            'message' => 'Gagal menghapus reseller.'
          ])
          ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
      }

      $this->createLog('DELETE_RESELLER', ['SUCCESS']);
      return Services::response()
        ->setJSON([
          'status'  => 'success',
          'message' => 'Reseller berhasil dihapus.'
        ])
        ->setStatusCode(200);
    } catch (Exception $e) {
      $this->createLog('DELETE_RESELLER', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage(),
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }
}
