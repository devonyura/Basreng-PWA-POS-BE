<?php

namespace App\Models;

use CodeIgniter\Model;

class BranchModel extends Model
{
  protected $table = 'branch';
  protected $primaryKey = 'branch_id';
  protected $allowedFields = [
    'branch_name',
    'branch_address',
    'latitude',
    'longitude',
  ];
  protected $useTimestamps = true;
  protected $dateFormat    = 'datetime';
  protected $createdField  = 'created_at';
  protected $updatedField = '';
}
