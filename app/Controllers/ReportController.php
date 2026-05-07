<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;
use Exception;
use App\Helpers\JwtHelper;

class ReportController extends ResourceController
{

  public function getTransactionsReport($day = 7)
  {
    $db = \Config\Database::connect();

    // FIX: pakai DATE() biar gak tergantung jam
    $query = $db->query("
      SELECT 
        DATE(date_time) AS date, 
        COALESCE(SUM(total_price),0) AS total_sales
      FROM transactions
      WHERE DATE(date_time) >= DATE_SUB(CURDATE(), INTERVAL {$day} DAY)
      GROUP BY DATE(date_time)
      ORDER BY DATE(date_time)
    ");

    return $this->respond($query->getResult());
  }

  public function getProductSellsReport($day = null)
  {
    $db = \Config\Database::connect();

    // ==============================
    // ✅ FIX 1: Handle default day
    // ==============================
    // Jika tidak ada parameter → hanya hari ini
    if (!is_numeric($day)) {
      $day = 0;
    }

    // ==============================
    // ✅ FIX 2: Handle limit (opsional)
    // ==============================
    // contoh: /api/report/product-sells/7?limit=10
    $limit = $this->request->getGet('limit');
    $limitSql = "";

    if (is_numeric($limit) && $limit > 0) {
      $limitSql = "LIMIT {$limit}";
    }

    // ==============================
    // ✅ FIX 3: Query per VARIANT
    // ==============================
    //     -- ==============================
    // -- ✅ FIX 4: Filter tanggal fleksibel
    // -- ==============================
    // -- ==============================
    // -- ✅ FIX 5: GROUP BY VARIANT (bukan product)
    // -- ==============================

    $query = $db->query("
    SELECT 
      pv.id AS variant_id,
      p.name AS product_name,

      -- tampilkan label variant (contoh: Basreng 250gr)
      CASE 
        WHEN pv.weight_grams > 0 
        THEN CONCAT(p.name, ' ', pv.weight_grams, 'gr')
        ELSE p.name
      END AS variant_name,

      SUM(td.quantity) AS total_sold,
      COALESCE(SUM(td.subtotal),0) AS total_sales

    FROM transactions t
    JOIN transaction_details td ON t.id = td.transaction_id
    JOIN product_variants pv ON td.product_variant_id = pv.id
    JOIN products p ON pv.product_id = p.id


    WHERE DATE(t.date_time) >= DATE_SUB(CURDATE(), INTERVAL {$day} DAY)

    GROUP BY pv.id, p.name, pv.weight_grams

    ORDER BY total_sold DESC

    {$limitSql}
  ");

    return $this->respond([
      'status' => 'success',
      'data'   => $query->getResult()
    ]);
  }

  public function getBranchReport($day = 0)
  {
    $db = \Config\Database::connect();

    $startDate = date('Y-m-d', strtotime("-$day days"));
    $endDate = date('Y-m-d');

    // =========================
    // FIX: tambah breakdown payment per cabang
    // =========================
    $query = $db->query("
      SELECT
        b.branch_id,
        b.branch_name,
        COUNT(t.id) AS total_transactions,
        COALESCE(SUM(t.total_price),0) AS total_income,

        COALESCE(SUM(CASE WHEN t.payment_method = 'cash' THEN t.total_price ELSE 0 END),0) AS total_income_cash,
        COALESCE(SUM(CASE WHEN t.payment_method = 'transfer_bank' THEN t.total_price ELSE 0 END),0) AS total_income_transfer_bank,
        COALESCE(SUM(CASE WHEN t.payment_method = 'qris' THEN t.total_price ELSE 0 END),0) AS total_income_qris,
        COALESCE(SUM(CASE WHEN t.payment_method = 'shopee' THEN t.total_price ELSE 0 END),0) AS total_income_shopee

      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE DATE(t.date_time) BETWEEN '$startDate' AND '$endDate'
      GROUP BY b.branch_id, b.branch_name
      ORDER BY total_income DESC
    ");

    return $this->respond($query->getResult());
  }

  public function getDetailReport($date)
  {
    if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
      return $this->failValidationErrors('Tanggal tidak valid. Gunakan format YYYY-MM-DD.');
    }

    $db = \Config\Database::connect();

    /**
     * Details Report
     */
    $detailsQuery = $db->query("
      SELECT 
        b.branch_id,
        b.branch_name,
        t.transaction_code,
        DATE(t.date_time) AS date,
        SUM(td.quantity) AS total_item,
        t.payment_method,
        t.is_online_order,
        t.total_price
      FROM transactions t
      JOIN transaction_details td ON t.id = td.transaction_id
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE DATE(t.date_time) = ?
      GROUP BY 
        b.branch_id, 
        b.branch_name, 
        t.id
      ORDER BY b.branch_id, t.transaction_code
    ", [$date]);

    $results = $detailsQuery->getResult();

    $detailsFormatted = [];
    foreach ($results as $row) {
      $branchName = $row->branch_name;
      $detailsFormatted[$branchName][] = [
        'date'             => $row->date,
        'transaction_code' => $row->transaction_code,
        'total_item'       => $row->total_item,
        'payment_method'   => $row->payment_method,
        'is_online_order'  => $row->is_online_order,
        'total_price'      => $row->total_price
      ];
    }

    /**
     * Product Sells Report
     */
    $productSellsQuery = $db->query("
      SELECT 
        p.id AS product_id, 
        p.name AS product_name, 
        SUM(td.quantity) AS total_sold,
        COALESCE(SUM(td.subtotal),0) AS total_sales
      FROM transactions t
      JOIN transaction_details td ON t.id = td.transaction_id
      JOIN product_variants pv ON td.product_variant_id = pv.id
      JOIN products p ON pv.product_id = p.id
      WHERE DATE(t.date_time) = ?
      GROUP BY p.id, p.name
      ORDER BY total_sold DESC
    ", [$date]);

    /**
     * Branch Report
     */
    $branchQuery = $db->query("
      SELECT 
        b.branch_id, 
        b.branch_name, 
        COUNT(t.id) AS total_transactions, 
        COALESCE(SUM(t.total_price),0) AS total_sales
      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE DATE(t.date_time) = ?
      GROUP BY b.branch_id, b.branch_name
      ORDER BY total_sales DESC
    ", [$date]);

    return $this->respond([
      'transactions_report' => $detailsFormatted,
      'product_sells_report' => $productSellsQuery->getResult(),
      'branch_report' => $branchQuery->getResult()
    ]);
  }

  public function getAllReports()
  {
    $day = $this->request->getGet('day');
    $month = $this->request->getGet('month');
    $year = $this->request->getGet('year');

    if (!is_numeric($day) || $day <= 0) {
      $day = 7;
    }

    if (!is_numeric($year) || $year < 1970) {
      $year = date('Y');
    }

    // FIX: pakai DATE()
    if (is_numeric($month) && $month >= 1 && $month <= 12) {
      $monthCondition = "MONTH(t.date_time) = {$month} AND YEAR(t.date_time) = {$year}";
    } else {
      $monthCondition = "DATE(t.date_time) >= DATE_SUB(CURDATE(), INTERVAL {$day} DAY)";
    }

    $db = \Config\Database::connect();

    /**
     * Transactions Report
     */
    $transactionsQuery = $db->query("
      SELECT 
        b.branch_id,
        b.branch_name,
        DATE(t.date_time) AS date,
        COUNT(t.id) AS total_transactions,
        COALESCE(SUM(t.total_price),0) AS total_sales
      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE {$monthCondition}
      GROUP BY b.branch_id, b.branch_name, DATE(t.date_time)
      ORDER BY DATE(t.date_time)
    ");

    $transactions = $transactionsQuery->getResult();

    $transactionsFormatted = [];
    foreach ($transactions as $row) {
      $branchName = $row->branch_name;
      $transactionsFormatted[$branchName][] = [
        'date' => format_tanggal_lokal($row->date),
        'total_transactions' => $row->total_transactions,
        'total_sales' => $row->total_sales
      ];
    }

    /**
     * Product Sells
     */
    $productSellsQuery = $db->query("
      SELECT 
        p.id AS product_id, 
        p.name AS product_name, 
        SUM(td.quantity) AS total_sold,
        COALESCE(SUM(td.subtotal),0) AS total_sales
      FROM transactions t
      JOIN transaction_details td ON t.id = td.transaction_id
      JOIN product_variants pv ON td.product_variant_id = pv.id
      JOIN products p ON pv.product_id = p.id
      WHERE {$monthCondition}
      GROUP BY p.id, p.name
      ORDER BY total_sold DESC
    ");

    /**
     * Branch Report
     */
    $branchQuery = $db->query("
      SELECT 
        b.branch_id, 
        b.branch_name, 
        COUNT(t.id) AS total_transactions, 
        COALESCE(SUM(t.total_price),0) AS total_sales
      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE {$monthCondition}
      GROUP BY b.branch_id, b.branch_name
      ORDER BY total_sales DESC
    ");

    return $this->respond([
      'transactions_report' => $transactionsFormatted,
      'product_sells_report' => $productSellsQuery->getResult(),
      'branch_report' => $branchQuery->getResult()
    ]);
  }

  private function generateSummary($user, $filterByBranch = false, $day = 0)
  {
    $db = \Config\Database::connect();

    $startDate = date('Y-m-d', strtotime("-$day days"));
    $endDate   = date('Y-m-d');

    $createBuilder = function () use ($db, $user, $filterByBranch, $startDate, $endDate) {
      $builder = $db->table('transactions t');

      // FIX: filter hanya untuk kasir
      if ($filterByBranch) {
        $builder->where('t.branch_id', $user['branch_id']);
      }

      // FIX: range tanggal konsisten
      $builder->where("DATE(t.date_time) >=", $startDate);
      $builder->where("DATE(t.date_time) <=", $endDate);

      return $builder;
    };

    // =========================
    // TOTAL SALES
    // =========================
    $totalSales = $createBuilder()
      ->selectSum('t.total_price')
      ->get()->getRow()->total_price ?? 0;

    // =========================
    // TOTAL TRANSAKSI
    // =========================
    $totalTransactions = $createBuilder()
      ->selectCount('t.id')
      ->get()->getRow()->id ?? 0;

    // =========================
    // 🔥 NEW: PAYMENT SUMMARY
    // =========================
    $paymentQuery = $createBuilder()
      ->select('t.payment_method, SUM(t.total_price) as total')
      ->groupBy('t.payment_method')
      ->get()
      ->getResultArray();

    // Format agar semua method selalu ada (meskipun 0)
    $paymentSummary = [
      'cash' => 0,
      'transfer_bank' => 0,
      'qris' => 0,
      'shopee' => 0,
    ];

    foreach ($paymentQuery as $row) {
      $paymentSummary[$row['payment_method']] = (int)$row['total'];
    }

    return [
      'total_sales' => (int)$totalSales,
      'total_transactions' => (int)$totalTransactions,

      // 🔥 tambahan baru
      'payment_summary' => $paymentSummary
    ];
  }

  public function summary()
  {
    try {
      $authUser = JwtHelper::getUserFromRequest($this->request);

      if (!$authUser) {
        return $this->failUnauthorized('Unauthorized');
      }

      // =========================
      // FIX: ambil param day
      // =========================
      $day = $this->request->getGet('day');
      if (!is_numeric($day) || $day < 0) {
        $day = 0; // default hari ini
      }

      // FIX: hanya kasir yang difilter
      $filterByBranch = ($authUser['role'] === 'kasir');

      // FIX: kirim $day ke generator
      $data = $this->generateSummary($authUser, $filterByBranch, $day);

      return $this->respond([
        'status' => 'success',
        'user' => [
          'id' => $authUser['id'],
          'username' => $authUser['username'],
          'role' => $authUser['role'],
        ],
        'data' => $data
      ]);
    } catch (\Exception $e) {
      return $this->failServerError($e->getMessage());
    }
  }

  public function topSelling($day = null)
  {
    $db = \Config\Database::connect();

    // =========================
    // FIX 1: default hari = hari ini
    // =========================
    if (!is_numeric($day) || $day < 0) {
      $day = 0;
    }

    $dateFrom = date('Y-m-d', strtotime("-$day days"));

    // =========================
    // FIX 2: param limit (optional)
    // contoh: ?limit=5
    // =========================
    $limit = $this->request->getGet('limit');
    if (!is_numeric($limit) || $limit <= 0) {
      $limit = null; // null = semua data
    }

    // =========================
    // FIX 3: query PER VARIANT (bukan product)
    // =========================
    $builder = $db->table('transaction_details td')
      ->select("
      pv.id AS variant_id,
      p.name AS product_name,
      pv.weight_grams,
      pv.price,
      SUM(td.quantity) as total_sold,
      COALESCE(SUM(td.subtotal),0) as total_sales
    ")
      ->join('product_variants pv', 'pv.id = td.product_variant_id')
      ->join('products p', 'p.id = pv.product_id')
      ->join('transactions t', 't.id = td.transaction_id')
      ->where("DATE(t.date_time) >=", $dateFrom)
      ->groupBy('pv.id, p.name, pv.weight_grams, pv.price')
      ->orderBy('total_sold', 'DESC');

    // =========================
    // FIX 4: apply limit jika ada
    // =========================
    if ($limit !== null) {
      $builder->limit($limit);
    }

    $query = $builder->get();

    return $this->respond([
      'status' => 'success',
      'meta' => [
        'day_range' => $day,
        'limit' => $limit ?? 'all'
      ],
      'data' => $query->getResult()
    ]);
  }

  public function sendDailyReport()
  {

    $db = \Config\Database::connect();

    // =========================
    // 1. AMBIL DATA (REUSE LOGIC)
    // =========================
    $day = 0;

    // Summary
    $summary = $this->generateSummary([
      'branch_id' => null
    ], false, $day);

    // Branch
    $branchQuery = $db->query("
      SELECT
        b.branch_id,
        b.branch_name,
        COUNT(t.id) AS total_transactions,
        COALESCE(SUM(t.total_price),0) AS total_income,

        COALESCE(SUM(CASE WHEN t.payment_method = 'cash' THEN t.total_price ELSE 0 END),0) AS total_income_cash,
        COALESCE(SUM(CASE WHEN t.payment_method = 'transfer_bank' THEN t.total_price ELSE 0 END),0) AS total_income_transfer_bank,
        COALESCE(SUM(CASE WHEN t.payment_method = 'qris' THEN t.total_price ELSE 0 END),0) AS total_income_qris,
        COALESCE(SUM(CASE WHEN t.payment_method = 'shopee' THEN t.total_price ELSE 0 END),0) AS total_income_shopee
        
      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, b.branch_name
      ORDER BY total_income DESC
    ");

    $branches = $branchQuery->getResult();

    // Top Selling (limit 5)
    $topSelling = $db->query("
      SELECT 
        b.branch_id,
        b.branch_name,
        p.name AS product_name,
        pv.weight_grams,
        SUM(td.quantity) as total_sold,
        SUM(td.subtotal) as total_sales
      FROM transaction_details td
      JOIN product_variants pv ON pv.id = td.product_variant_id
      JOIN products p ON p.id = pv.product_id
      JOIN transactions t ON t.id = td.transaction_id
      JOIN branch b ON b.branch_id = t.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, pv.id
      ORDER BY b.branch_id, total_sold DESC
    ")->getResult();

    $groupedProducts = [];

    foreach ($topSelling as $row) {
      $branchId = $row->branch_id;

      if (!isset($groupedProducts[$branchId])) {
        $groupedProducts[$branchId] = [
          'branch_name' => $row->branch_name,
          'products' => []
        ];
      }

      $groupedProducts[$branchId]['products'][] = [
        'product_name' => $this->formatProductName($row->product_name, $row->weight_grams),
        'total_sold' => $row->total_sold,
        'total_sales' => $row->total_sales,
      ];
    }

    $hourlySales = $db->query("
      SELECT 
        b.branch_name,
        HOUR(t.date_time) as hour,
        SUM(t.total_price) as total_sales
      FROM transactions t
      JOIN branch b ON b.branch_id = t.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, HOUR(t.date_time)
      ORDER BY hour ASC
    ")->getResult();

    $labels = range(9, 22); // jam kerja
    $datasets = [];

    // =========================
    // 1. BUILD DATASET
    // =========================
    foreach ($hourlySales as $row) {
      $branch = $row->branch_name;
      $hour = (int)$row->hour;
      $sales = (int)$row->total_sales;

      if (!isset($datasets[$branch])) {
        $datasets[$branch] = array_fill(0, 24, 0);
      }

      $datasets[$branch][$hour] = $sales;
    }

    // =========================
    // 2. POTONG JAM 9 - 22
    // =========================
    foreach ($datasets as $branch => $data) {
      $datasets[$branch] = array_slice($data, 9, 14); // 9–22
    }

    // =========================
    // 3. KONVERSI KE RIBUAN (BIAR CLEAN)
    // =========================
    foreach ($datasets as $branch => $data) {
      $datasets[$branch] = array_map(function ($v) {
        return round($v / 1000); // jadi "k"
      }, $data);
    }

    // =========================
    // 4. BUILD CHART CONFIG
    // =========================
    // $chartConfig = [
    //   "type" => "line",
    //   "data" => [
    //     "labels" => array_map(fn($h) => sprintf("%02d.00", $h), $labels),
    //     "datasets" => []
    //   ],
    //   "options" => [
    //     "plugins" => [
    //       "title" => [
    //         "display" => true,
    //         "text" => "Grafik Penjualan Harian per Jam (dalam ribuan Rupiah)"
    //       ],
    //       "legend" => [
    //         "position" => "bottom"
    //       ]
    //     ],
    //     "elements" => [
    //       "line" => [
    //         "tension" => 0.3 // smooth line
    //       ]
    //     ]
    //   ]
    // ];

    // =========================
    // 5. WARNA + DATASET
    // =========================
    // $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#8e44ad'];

    // $i = 0;
    // foreach ($datasets as $branch => $data) {
    //   $chartConfig["data"]["datasets"][] = [
    //     "label" => $branch,
    //     "data" => array_values($data),
    //     "fill" => false,
    //     "borderColor" => $colors[$i % count($colors)],
    //     "pointRadius" => 3
    //   ];
    //   $i++;
    // }

    // =========================
    // 6. GENERATE URL
    // =========================
    // $chartUrl = "https://quickchart.io/chart?width=500&height=300&c=" . urlencode(json_encode($chartConfig));

    // =========================
    // 2. GENERATE HTML (TEMPLATE)
    // =========================
    $html = view('pdf/daily_report', [
      'summary' => $summary,
      'branches' => $branches,
      'products' => $groupedProducts,
      'date' => date('Y-m-d'),
      // 'chartUrl' => $chartUrl
    ]);


    # code...
    // =========================
    // 3. GENERATE PDF
    // =========================
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    // =========================
    // 4. SIMPAN FILE
    // =========================
    $fileName = 'laporan-' . date('Y-m-d') . '.pdf';
    $filepath = FCPATH . "reports/$fileName";

    // =========================
    // HAPUS FILE LAMA JIKA ADA
    // =========================
    if (file_exists($filepath) && !unlink($filepath)) {
      throw new \Exception("Gagal menghapus file lama");
    }
    if (!is_dir(FCPATH . "reports")) {
      mkdir(FCPATH . "reports", 0777, true);
    }

    // =========================
    // SIMPAN FILE BARU
    // =========================
    file_put_contents($filepath, $dompdf->output());


    // ================= URL =================
    $url = base_url("reports/$fileName");


    // =========================
    // 5. KIRIM WA (FONNTE)
    // =========================
    $this->sendWhatsApp($url);

    return $this->respond([
      'status' => 'success',
      'message' => 'Laporan berhasil dikirim',
      'urlPDF' => $url
    ]);
  }

  private function sendWhatsApp($fileUrl)
  {
    $token = "eY94qtJeSPbYDPSiADTw"; // pastikan benar
    $target = "6281524047314";

    $message = "📊 Laporan Harian\n\nDownload:\n$fileUrl";

    $curl = curl_init();

    curl_setopt_array($curl, array(
      CURLOPT_URL => 'https://api.fonnte.com/send',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POSTFIELDS => array(
        'target' => $target,
        'message' => $message,
      ),
      CURLOPT_HTTPHEADER => array(
        "Authorization: $token"
      ),
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    log_message('info', 'WA Response: ' . $response);
  }

  public function viewDailyReport()
  {
    $db = \Config\Database::connect();

    // =========================
    // 1. AMBIL DATA (REUSE LOGIC)
    // =========================
    $day = 0;

    // Summary
    $summary = $this->generateSummary([
      'branch_id' => null
    ], false, $day);

    // Branch
    $branchQuery = $db->query("
      SELECT
        b.branch_id,
        b.branch_name,
        COUNT(t.id) AS total_transactions,
        COALESCE(SUM(t.total_price),0) AS total_income,

        COALESCE(SUM(CASE WHEN t.payment_method = 'cash' THEN t.total_price ELSE 0 END),0) AS total_income_cash,
        COALESCE(SUM(CASE WHEN t.payment_method = 'transfer_bank' THEN t.total_price ELSE 0 END),0) AS total_income_transfer_bank,
        COALESCE(SUM(CASE WHEN t.payment_method = 'qris' THEN t.total_price ELSE 0 END),0) AS total_income_qris,
        COALESCE(SUM(CASE WHEN t.payment_method = 'shopee' THEN t.total_price ELSE 0 END),0) AS total_income_shopee
        
      FROM transactions t
      JOIN branch b ON t.branch_id = b.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, b.branch_name
      ORDER BY total_income DESC
    ");

    $branches = $branchQuery->getResult();

    // Top Selling (limit 5)
    $topSelling = $db->query("
      SELECT 
        b.branch_id,
        b.branch_name,
        p.name AS product_name,
        pv.weight_grams,
        SUM(td.quantity) as total_sold,
        SUM(td.subtotal) as total_sales
      FROM transaction_details td
      JOIN product_variants pv ON pv.id = td.product_variant_id
      JOIN products p ON p.id = pv.product_id
      JOIN transactions t ON t.id = td.transaction_id
      JOIN branch b ON b.branch_id = t.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, pv.id
      ORDER BY b.branch_id, total_sold DESC
    ")->getResult();

    $groupedProducts = [];

    foreach ($topSelling as $row) {
      $branchId = $row->branch_id;

      if (!isset($groupedProducts[$branchId])) {
        $groupedProducts[$branchId] = [
          'branch_name' => $row->branch_name,
          'products' => []
        ];
      }

      $groupedProducts[$branchId]['products'][] = [
        'product_name' => $this->formatProductName($row->product_name, $row->weight_grams),
        'total_sold' => $row->total_sold,
        'total_sales' => $row->total_sales,
      ];
    }

    $hourlySales = $db->query("
      SELECT 
        b.branch_name,
        HOUR(t.date_time) as hour,
        SUM(t.total_price) as total_sales
      FROM transactions t
      JOIN branch b ON b.branch_id = t.branch_id
      WHERE DATE(t.date_time) = CURDATE()
      GROUP BY b.branch_id, HOUR(t.date_time)
      ORDER BY hour ASC
    ")->getResult();

    $labels = range(9, 22); // jam kerja
    $datasets = [];

    // =========================
    // 1. BUILD DATASET
    // =========================
    foreach ($hourlySales as $row) {
      $branch = $row->branch_name;
      $hour = (int)$row->hour;
      $sales = (int)$row->total_sales;

      if (!isset($datasets[$branch])) {
        $datasets[$branch] = array_fill(0, 24, 0);
      }

      $datasets[$branch][$hour] = $sales;
    }

    // =========================
    // 2. POTONG JAM 9 - 22
    // =========================
    foreach ($datasets as $branch => $data) {
      $datasets[$branch] = array_slice($data, 9, 14); // 9–22
    }

    // =========================
    // 3. KONVERSI KE RIBUAN (BIAR CLEAN)
    // =========================
    foreach ($datasets as $branch => $data) {
      $datasets[$branch] = array_map(function ($v) {
        return round($v / 1000); // jadi "k"
      }, $data);
    }

    // =========================
    // 4. BUILD CHART CONFIG
    // =========================
    $chartConfig = [
      "type" => "line",
      "data" => [
        "labels" => array_map(fn($h) => sprintf("%02d.00", $h), $labels),
        "datasets" => []
      ],
      "options" => [
        "plugins" => [
          "title" => [
            "display" => true,
            "text" => "Grafik Penjualan Harian per Jam (dalam ribuan Rupiah)"
          ],
          "legend" => [
            "position" => "bottom"
          ]
        ],
        "elements" => [
          "line" => [
            "tension" => 0.3 // smooth line
          ]
        ]
      ]
    ];

    // =========================
    // 5. WARNA + DATASET
    // =========================
    $colors = ['#e74c3c', '#3498db', '#2ecc71', '#f39c12', '#8e44ad'];

    $i = 0;
    foreach ($datasets as $branch => $data) {
      $chartConfig["data"]["datasets"][] = [
        "label" => $branch,
        "data" => array_values($data),
        "fill" => false,
        "borderColor" => $colors[$i % count($colors)],
        "pointRadius" => 3
      ];
      $i++;
    }

    // =========================
    // 6. GENERATE URL
    // =========================
    $chartUrl = "https://quickchart.io/chart?width=500&height=300&c=" . urlencode(json_encode($chartConfig));

    $chartImagePath = FCPATH . "reports/chart.png";

    function downloadImage($url, $path)
    {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $data = curl_exec($ch);
      curl_close($ch);

      if ($data) {
        file_put_contents($path, $data);
      }
    }

    // ambil gambar dari quickchart
    $imageContent = downloadImage($chartUrl, $chartImagePath);

    if ($imageContent !== false) {
      file_put_contents($chartImagePath, $imageContent);
    }

    // =========================
    // 2. GENERATE HTML (TEMPLATE)
    // =========================
    return view('pdf/daily_report', [
      'summary' => $summary,
      'branches' => $branches,
      'products' => $groupedProducts,
      'date' => date('Y-m-d'),
      'chartUrl' => $chartUrl,
      'chartImagePath' => $chartImagePath
    ]);
  }


  public function formatProductName($name, $weight)
  {
    if (!$weight || $weight == 0) return $name;

    if ($weight >= 1000) {
      $kg = $weight / 1000;
      return $name . ' (' . (intval($kg) == $kg ? $kg : number_format($kg, 2)) . 'kg)';
    }

    return $name . ' (' . $weight . 'gr)';
  }
}
