<?php

namespace App\Models;

use CodeIgniter\Model;

class PaymentProofModel extends Model
{
    protected $table            = 'payment_proofs';
    protected $primaryKey       = 'id';

    protected $allowedFields = [
        'transaction_code',
        'file_name',
        'file_path',
        'file_url',
    ];

    protected $useTimestamps = false;
}
