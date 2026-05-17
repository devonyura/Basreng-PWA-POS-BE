<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class TransaksiSeeder extends Seeder
{
  public function run()
  {
    $db = \Config\Database::connect();

    // RESET TABLE
    $db->query('SET FOREIGN_KEY_CHECKS=0');
    $db->table('transaction_details')->truncate();
    $db->table('transactions')->truncate();
    $db->table('payment_proofs')->truncate(); // Truncate proof table to keep everything clean and synced
    $db->query('SET FOREIGN_KEY_CHECKS=1');

    $products = $db->table('products')->get()->getResultArray();
    $variants = $db->table('product_variants')->get()->getResultArray();

    // CACHE VARIANT BY ID (ANTI N+1 QUERY)
    $variantById = [];
    $variantMap = [];
    foreach ($variants as $v) {
      $variantById[$v['id']] = $v;
      $variantMap[$v['product_id']][] = $v;
    }

    // Requirement 1: Only seed users.role = 'kasir'. All cashier accounts must get a turn.
    $cashiers = $db->table('users')->where('role', 'kasir')->get()->getResultArray();
    if (empty($cashiers)) {
      throw new \Exception("No cashiers (users with role = 'kasir') found in database. Please seed users first.");
    }

    // Requirement 2: Dynamically query branch IDs from database
    $branchesFromDb = $db->table('branch')->get()->getResultArray();
    if (empty($branchesFromDb)) {
      throw new \Exception("No branches found in database. Please seed branches first.");
    }
    $branches = array_column($branchesFromDb, 'branch_id');

    // Requirement 7: Get all categories dynamically from database and map names to IDs
    $categoriesFromDb = $db->table('categories')->get()->getResultArray();
    $categoryByName = [];
    foreach ($categoriesFromDb as $cat) {
      $categoryByName[strtolower($cat['name'])] = $cat['id'];
    }

    $cemilanId    = $categoryByName['cemilan'] ?? null;
    $mochiId      = $categoryByName['mochi'] ?? null;
    $sushiId      = $categoryByName['sushi'] ?? null;
    $paketId      = $categoryByName['paket'] ?? null;
    $donatMochiId = $categoryByName['donat mochi'] ?? null;

    // Build categories dynamic grouping
    $kategori = [];
    foreach ($products as $p) {
      $kategori[$p['category_id']][] = $p;
    }

    $start = strtotime('-3 months');
    $end   = time();

    $dayIndex = 0;
    $paketDay = rand(0, 6); // Initial seed day for package promos

    $shopeeSchedule = [];

    $startMonth = strtotime(date('Y-m-01', $start));
    $endMonth   = strtotime(date('Y-m-01', $end));

    $current = $startMonth;

    while ($current <= $endMonth) {
      $monthKey = date('Y-m', $current);
      $count = rand(1, 3);
      $days = [];

      while (count($days) < $count) {
        $days[] = rand(1, date('t', $current));
        $days = array_unique($days);
      }

      $shopeeSchedule[$monthKey] = $days;
      $current = strtotime('+1 month', $current);
    }

    // Customer lists for Requirement 4: Populating dynamic customer data for online orders
    $customerNames = [
      'Budi Santoso', 'Siti Aminah', 'Rian Hidayat', 'Dewi Lestari', 'Agus Prasetyo', 
      'Mega Utami', 'Joko Widodo', 'Eka Sari', 'Rizky Pratama', 'Putri Ayu', 
      'Andi Wijaya', 'Fitriani', 'Hendra Wijaya', 'Yanti', 'Taufik Hidayat', 
      'Lusi', 'Doni', 'Sari', 'Bambang', 'Wulan', 'Indah Permatasari', 'Rudi Hermawan', 
      'Santi Widjaja', 'Dedi Kurniawan', 'Novianti', 'Eko Prasetyo', 'Lilis', 'Sony'
    ];
    $customerAddresses = [
      'Jl. Merdeka', 'Jl. Mawar', 'Jl. Melati', 'Jl. Anggrek', 'Jl. Kamboja', 
      'Jl. Flamboyan', 'Jl. Dahlia', 'Jl. Tulip', 'Jl. Sakura', 'Jl. Kenanga',
      'Jl. Sudirman', 'Jl. Gatot Subroto', 'Jl. Pemuda', 'Jl. Pahlawan', 'Jl. Diponegoro'
    ];
    $cities = ['Jakarta', 'Bandung', 'Surabaya', 'Yogyakarta', 'Semarang', 'Medan', 'Makassar', 'Palembang', 'Denpasar', 'Balikpapan'];

    while ($start <= $end) {
      $date = date('Y-m-d', $start);
      $monthKey = date('Y-m', $start);
      $dayOfMonth = date('j', $start);

      $sushiDailyLimit = 100;
      $sushiSoldToday = 0;

      $isWeekend = date('N', $start) >= 6;
      $totalTransaksi = $isWeekend ? rand(20, 65) : rand(30, 45);

      if ($dayIndex % 7 === 0) {
        $paketDay = rand(0, 6);
      }

      // Generate unique chronological times throughout the day
      $times = [];
      for ($i = 0; $i < $totalTransaksi; $i++) {
        $hour = $this->generateRealisticHour();
        $minute = rand(0, 59);
        $second = rand(0, 59);
        $times[] = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
      }
      sort($times);

      // Requirement 4: Exactly 3 to 7 online orders per day (excluding/including Shopee appropriately)
      $numOnlineOrders = rand(3, 7);
      $onlineOrderIndices = [];
      if ($totalTransaksi > 0) {
        $possibleIndices = range(0, $totalTransaksi - 1);
        $isShopeeActiveToday = isset($shopeeSchedule[$monthKey]) && in_array($dayOfMonth, $shopeeSchedule[$monthKey]);

        if ($isShopeeActiveToday) {
          $onlineOrderIndices[] = 0; // Forced online order for Shopee at index 0
          $possibleIndices = array_values(array_diff($possibleIndices, [0]));
          shuffle($possibleIndices);
          $remainingOrders = $numOnlineOrders - 1;
          if ($remainingOrders > 0) {
            $extraIndices = array_slice($possibleIndices, 0, min($remainingOrders, count($possibleIndices)));
            $onlineOrderIndices = array_merge($onlineOrderIndices, $extraIndices);
          }
        } else {
          shuffle($possibleIndices);
          $onlineOrderIndices = array_slice($possibleIndices, 0, min($numOnlineOrders, $totalTransaksi));
        }
      }

      for ($i = 0; $i < $totalTransaksi; $i++) {
        $details = [];
        $totalPrice = 0;

        // Choose branch dynamically
        $branchId = $branches[array_rand($branches)];

        // Choose cashier dynamically (Requirement 1: cashiers get equal turn)
        $randomCashier = $cashiers[array_rand($cashiers)];
        $userId = $randomCashier['id'];
        $username = $randomCashier['username'];

        $isShopee = isset($shopeeSchedule[$monthKey]) &&
          in_array($dayOfMonth, $shopeeSchedule[$monthKey]) &&
          $i === 0;
        $isPaket  = ($dayIndex % 7 === $paketDay && $i === 0 && !$isShopee);

        $dateTimeStr = "$date " . $times[$i];

        // ======================
        // SHOPEE / PAKET (DIGABUNG)
        // ======================
        if (($isShopee || $isPaket) && $paketId && !empty($kategori[$paketId])) {
          $product = $kategori[$paketId][array_rand($kategori[$paketId])];

          if (empty($variantMap[$product['id']])) continue;
          $variant = $variantMap[$product['id']][0];

          $details[] = [
            'variant_id' => $variant['id'],
            'quantity'   => 1
          ];

          $totalPrice += $variant['price'];
        }
        // ======================
        // NORMAL TRANSACTION
        // ======================
        else {
          $totalItem = rand(2, 8);

          for ($j = 0; $j < $totalItem; $j++) {
            $jenis = $this->weightedCategory($categoryByName);

            if (empty($kategori[$jenis])) continue;
            $product = $kategori[$jenis][array_rand($kategori[$jenis])];

            if (empty($variantMap[$product['id']])) continue;
            $variant = $variantMap[$product['id']][array_rand($variantMap[$product['id']])];

            // Requirement 6: Dynamic Sushi checking by name
            $isSushi = (stripos($product['name'], 'sushi') !== false);
            if ($isSushi) {
              if ($sushiSoldToday >= $sushiDailyLimit) continue;

              $remaining = $sushiDailyLimit - $sushiSoldToday;
              $qty = min(rand(4, 12), $remaining);
              $sushiSoldToday += $qty;
            } else {
              // Requirement 7: Quantity logic dynamic categories
              if ($jenis === $cemilanId) {
                $qty = rand(1, 3);
              } elseif ($jenis === $mochiId) {
                $qty = rand(1, 2);
              } elseif ($jenis === $sushiId) {
                $qty = rand(4, 12);
              } elseif ($jenis === $donatMochiId) {
                $qty = rand(1, 2);
              } else {
                $qty = 1;
              }
            }

            $details[] = [
              'variant_id' => $variant['id'],
              'quantity'   => $qty
            ];

            $totalPrice += $variant['price'] * $qty;
          }
        }

        if (empty($details) || $totalPrice <= 0) continue;

        // PAYMENT METHOD & TRANSACTION TYPE
        if ($isShopee) {
          $transactionType = 'shopee';
          $paymentMethod = 'transfer_bank';
        } else {
          $randPay = rand(1, 100);

          if ($randPay <= 60) $paymentMethod = 'cash';
          elseif ($randPay <= 85) $paymentMethod = 'qris';
          else $paymentMethod = 'transfer_bank';

          $transactionType = 'POS';
        }

        if ($paymentMethod === 'cash') {
          $cashAmount = $this->generateCash($totalPrice);
          $change = $cashAmount - $totalPrice;
        } else {
          $cashAmount = $totalPrice;
          $change = 0;
        }

        // Requirement 3: Format transaction_code following FE Logic exactly:
        // C{branchID}-{ddmmyy}-{hhiiss}-{USERNAME}
        $dt = new \DateTime($dateTimeStr);
        $ddmmyy = $dt->format('dmy');
        $hhiiss = $dt->format('His');
        $transactionCode = "C{$branchId}-{$ddmmyy}-{$hhiiss}-" . strtoupper($username);

        // Requirement 4: Populating dynamic customer_* fields
        $isOnlineOrder = $isShopee || in_array($i, $onlineOrderIndices);
        if ($isOnlineOrder) {
          $customerName = $customerNames[array_rand($customerNames)];
          $customerAddress = $customerAddresses[array_rand($customerAddresses)] . ' No. ' . rand(1, 150) . ', ' . $cities[array_rand($cities)];
          $customerPhone = '08' . rand(11, 19) . rand(1000000, 9999999);
        } else {
          $customerName = '';
          $customerAddress = '';
          $customerPhone = '';
        }

        $transactionData = [
          'transaction' => [
            'transaction_code' => $transactionCode,
            'user_id'          => $userId,
            'branch_id'        => $branchId,
            'date_time'        => $dateTimeStr,
            'total_price'      => $totalPrice,
            'cash_amount'      => $cashAmount,
            'change_amount'    => $change,
            'payment_method'   => $paymentMethod,
            'is_online_order'  => $isOnlineOrder ? 1 : 0,
            'customer_name'    => $customerName,
            'customer_address' => $customerAddress,
            'customer_phone'   => $customerPhone,
            'transaction_type' => $transactionType,
            'is_reseller'      => 0
          ],
          'transaction_details' => $details
        ];

        $this->insertTransaction($transactionData, $variantById);
      }

      $start = strtotime('+1 day', $start);
      $dayIndex++;
    }
  }

  private function insertTransaction($data, $variantById)
  {
    $db = \Config\Database::connect();

    $trxModel = new \App\Models\TransactionModel();
    $detailModel = new \App\Models\TransactionDetailsModel();

    $db->transBegin();

    try {
      $trxModel->insert($data['transaction']);
      $trxId = $trxModel->getInsertID();

      foreach ($data['transaction_details'] as $item) {
        $variant = $variantById[$item['variant_id']] ?? null;
        if (!$variant) continue;

        $detailModel->insert([
          'transaction_id'      => $trxId,
          'product_variant_id' => $variant['id'],
          'quantity'           => $item['quantity'],
          'price'              => $variant['price'],
          'subtotal'           => $variant['price'] * $item['quantity'],
        ]);
      }

      // Requirement 5: Create payment proof if payment method is "transfer_bank" or "qris"
      $paymentMethod = $data['transaction']['payment_method'];
      if ($paymentMethod === 'transfer_bank' || $paymentMethod === 'qris') {
        $proofModel = new \App\Models\PaymentProofModel();
        $proofModel->insert([
          'transaction_code' => $data['transaction']['transaction_code'],
          'file_name'        => 'proof.png',
          'file_path'        => 'uploads/payment_proofs/proof.png',
          'file_url'         => 'http://localhost:8080/uploads/payment_proofs/proof.png'
        ]);
      }

      if ($db->transStatus() === false) {
        $db->transRollback();
        return;
      }

      $db->transCommit();
    } catch (\Throwable $e) {
      $db->transRollback();
    }
  }

  private function generateRealisticHour()
  {
    $rand = rand(1, 100);

    if ($rand <= 10) return rand(8, 10);
    if ($rand <= 40) return rand(11, 14);
    if ($rand <= 80) return rand(15, 18);
    return rand(19, 22);
  }

  // Requirement 7: Make weightedCategory dynamically choose from the database categories
  private function weightedCategory($categoryByName)
  {
    $cemilanId    = $categoryByName['cemilan'] ?? null;
    $mochiId      = $categoryByName['mochi'] ?? null;
    $sushiId      = $categoryByName['sushi'] ?? null;
    $donatMochiId = $categoryByName['donat mochi'] ?? null;

    $pool = [];
    if ($cemilanId)    $pool = array_merge($pool, array_fill(0, 5, $cemilanId));
    if ($mochiId)      $pool = array_merge($pool, array_fill(0, 3, $mochiId));
    if ($sushiId)      $pool = array_merge($pool, array_fill(0, 2, $sushiId));
    if ($donatMochiId) $pool = array_merge($pool, array_fill(0, 2, $donatMochiId));

    if (empty($pool)) {
      return array_values($categoryByName)[0] ?? null;
    }

    return $pool[array_rand($pool)];
  }

  private function generateCash($total)
  {
    $options = [
      ceil($total / 1000) * 1000,
      ceil($total / 5000) * 5000,
      ceil($total / 10000) * 10000,
      ceil($total / 20000) * 20000,
    ];

    return $options[array_rand($options)];
  }
}
