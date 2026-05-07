<?php

namespace App\Models;

use CodeIgniter\Model;

class PackageModel extends Model
{
  protected $table = 'package';
  protected $primaryKey = 'id';
  protected $allowedFields = [
    'name',
    'price',
  ];
  protected $useTimestamps = true;
  protected $dateFormat    = 'datetime';
  protected $createdField  = 'created_at';
  protected $updatedField = '';
}
