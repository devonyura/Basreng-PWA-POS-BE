<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
  protected $table = 'products';
  protected $primaryKey = 'id';
  protected $allowedFields = [
    'category_id',
    'name',
    'descriptions',
    'img',
  ];
  protected $useTimestamps = true;
  protected $dateFormat = 'datetime';
  protected $createdField  = 'created_at';
  protected $updatedField = '';
  protected $useSoftDeletes = true;
  protected $deletedField  = 'deleted_at';
}
