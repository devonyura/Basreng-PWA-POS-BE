<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductVariantModel extends Model
{
  protected $table = 'product_variants';
  protected $primaryKey = 'id';

  protected $allowedFields = [
    'product_id',
    'weight_grams',
    'price'
  ];

  protected $useTimestamps = true;
  protected $createdField  = 'created_at';
  protected $updatedField  = 'updated_at';
  protected $useSoftDeletes = true;
  protected $deletedField  = 'deleted_at';
}
