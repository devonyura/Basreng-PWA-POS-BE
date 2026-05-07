<?php

namespace App\Models;

use CodeIgniter\Model;

class ResellerModel extends Model
{
  protected $table = 'resellers';
  protected $primaryKey = 'id';
  protected $allowedFields = [
    'name',
    'phone',
    'address',
  ];
  protected $useTimestamps = true;
  protected $dateFormat    = 'datetime';
  protected $createdField  = 'created_at';
  protected $updatedField  = 'updated_at';
}
