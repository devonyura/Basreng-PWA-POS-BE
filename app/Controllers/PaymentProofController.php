<?php

namespace App\Controllers;

use App\Models\PaymentProofModel;
use App\Models\TransactionModel;
use CodeIgniter\RESTful\ResourceController;

class PaymentProofController extends ResourceController
{
    public function upload()
    {
        $file = $this->request->getFile('file');
        $transactionCode = $this->request->getPost('transaction_code');

        // Validasi file
        if (!$file || !$file->isValid()) {
            return $this->fail('File tidak valid!');
        }

        // Validasi transaction_code
        if (!$transactionCode) {
            return $this->fail('transaction_code wajib diisi!');
        }

        // 🔥 VALIDASI: pastikan transaksi ada
        $transactionModel = new TransactionModel();
        $transaction = $transactionModel
            ->where('transaction_code', $transactionCode)
            ->first();

        if (!$transaction) {
            return $this->fail('Transaksi tidak ditemukan!');
        }

        // Validasi tipe file
        $allowedTypes = ['image/jpg', 'image/png', 'image/jpeg'];
        if (!in_array($file->getMimeType(), $allowedTypes)) {
            return $this->fail('Format file harus jpg/png!');
        }

        // Validasi ukuran (2MB)
        if ($file->getSize() > 2 * 1024 * 1024) {
            return $this->fail('Ukuran file maksimal 2MB!');
        }

        $uploadPath = FCPATH . 'uploads/payment_proofs';

        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0777, true);
        }

        $newName = $file->getRandomName();

        // ✅ FIXED move
        $file->move($uploadPath, $newName);

        $filePath = 'uploads/payment_proofs/' . $newName;

        $model = new PaymentProofModel();

        // 🔥 BONUS: cek kalau sudah ada → replace (biar 1 transaksi 1 bukti)
        $existing = $model->where('transaction_code', $transactionCode)->first();

        if ($existing) {
            // hapus file lama
            $oldPath = FCPATH . $existing['file_path'];
            if (file_exists($oldPath)) {
                unlink($oldPath);
            }

            // update data
            $model->update($existing['id'], [
                'file_name' => $newName,
                'file_path' => $filePath,
                'file_url'  => base_url($filePath),
            ]);
        } else {
            // insert baru
            $model->insert([
                'transaction_code' => $transactionCode,
                'file_name' => $newName,
                'file_path' => $filePath,
                'file_url' => base_url($filePath),
            ]);
        }

        return $this->respondCreated([
            'status' => 'success',
            'message' => 'Bukti pembayaran berhasil diupload',
            'data' => [
                'file_url' => base_url($filePath)
            ]
        ]);
    }

    public function getByTransaction($transactionCode)
    {
        $model = new PaymentProofModel();

        $data = $model
            ->where('transaction_code', $transactionCode)
            ->first();

        return $this->respond([
            'status' => 'success',
            'data' => $data
        ]);
    }
}
