<?php

namespace App\Controllers;

use App\Models\SalesReportModel;
use App\Models\ActivityLogModel;
use App\Helpers\JwtHelper;
use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;
use Exception;

class ReportsController extends ResourceController
{
  protected $format = 'json';

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

  // GET /reports/sales
  public function sales()
  {
    try {
      $salesReportModel = new SalesReportModel();
      $data = $salesReportModel->findAll();
      if (empty($data)) {
        $this->createLog('SALES_REPORT', 'Tidak ada data laporan penjualan.');
        return $this->failNotFound('Tidak ada data laporan penjualan.');
      }
      $this->createLog('SALES_REPORT', ['SUCCESS']);
      return $this->respond([
        'status' => 'success',
        'data'   => $data
      ]);
    } catch (Exception $e) {
      $this->createLog('SALES_REPORT', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage()
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // GET /reports/charts
  public function charts()
  {
    try {
      // Logika untuk mengambil data grafik penjualan dapat disesuaikan.
      // Contoh: data dummy grafik
      $chartData = [
        'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'Mei'],
        'data'   => [1000, 1500, 1200, 1800, 2000]
      ];
      $this->createLog('SALES_CHARTS', ['SUCCESS']);
      return $this->respond([
        'status' => 'success',
        'data'   => $chartData
      ]);
    } catch (Exception $e) {
      $this->createLog('SALES_CHARTS', ['ERROR']);
      return Services::response()
        ->setJSON([
          'status'  => 'error',
          'message' => 'Terjadi kesalahan pada server.',
          'error'   => $e->getMessage()
        ])
        ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
    }
  }

  // GET /reports/export
  // public function export()
  // {
  //   try {
  //     // Implementasi export PDF bisa menggunakan library seperti TCPDF atau Dompdf.
  //     // Berikut contoh respon dummy.
  //     $this->createLog('EXPORT_PDF', ['SUCCESS']);
  //     return $this->respond([
  //       'status'  => 'success',
  //       'message' => 'Fungsi export PDF belum diimplementasikan.'
  //     ]);
  //   } catch (Exception $e) {
  //     $this->createLog('EXPORT_PDF', ['ERROR']);
  //     return Services::response()
  //       ->setJSON([
  //         'status'  => 'error',
  //         'message' => 'Terjadi kesalahan pada server.',
  //         'error'   => $e->getMessage()
  //       ])
  //       ->setStatusCode(ResponseInterface::HTTP_INTERNAL_SERVER_ERROR);
  //   }
  // }


  private function generateSummary($user, $filterByBranch = false, $startDate, $endDate)
  {
    $db = \Config\Database::connect();

    $createBuilder = function () use ($db, $user, $filterByBranch, $startDate, $endDate) {
      $builder = $db->table('transactions t');

      // filter cabang (opsional)
      if ($filterByBranch && isset($user['branch_id'])) {
        $builder->where('t.branch_id', $user['branch_id']);
      }

      // 🔥 FIX: pakai BETWEEN (lebih clean & aman)
      $builder->where('t.date_time >=', $startDate . ' 00:00:00');
      $builder->where('t.date_time <=', $endDate . ' 23:59:59');

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
    // PAYMENT SUMMARY
    // =========================
    $paymentQuery = $createBuilder()
      ->select('t.payment_method, SUM(t.total_price) as total')
      ->groupBy('t.payment_method')
      ->get()
      ->getResultArray();

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
      'payment_summary' => $paymentSummary
    ];
  }

  private function generateReport($type, $params)
  {
    $db = \Config\Database::connect();

    // $dateFilter = $this->getDateFilter($type, $params);

    // =========================
    // SUMMARY
    // =========================
    [$startDate, $endDate] = $this->getDateRange($type, $params);
    $summary = $this->generateSummary(
      [],
      false,
      $startDate,
      $endDate
    );

    // =========================
    // BRANCH
    // =========================
    $branches = $db->query("
        SELECT
            b.branch_id,
            b.branch_name,
            COUNT(t.id) AS total_transactions,
            COALESCE(SUM(t.total_price),0) AS total_income
        FROM transactions t
        JOIN branch b ON t.branch_id = b.branch_id
        WHERE t.date_time >= '{$startDate} 00:00:00'
        AND t.date_time <= '{$endDate} 23:59:59'
        GROUP BY b.branch_id
    ")->getResult();

    // =========================
    // CHART (DINAMIS)
    // =========================

    if ($type === 'daily') {
      $chart = $this->buildHourlyChart($db, $startDate, $endDate);
    } else {
      $chart = $this->buildDailyChart($db, $startDate, $endDate);
    }

    return $this->respond([
      'status' => 'success',
      'type' => $type,
      'summary' => $summary,
      'branches' => $branches,
      'chart' => $chart
    ]);
  }
  private function buildDailyChart($db, $startDate, $endDate)
  {
    $result = $db->query("
        SELECT 
            b.branch_name,
            DATE(t.date_time) as date,
            SUM(t.total_price) as total_sales
        FROM transactions t
        JOIN branch b ON b.branch_id = t.branch_id
        WHERE t.date_time >= '{$startDate} 00:00:00'
        AND t.date_time <= '{$endDate} 23:59:59'
        GROUP BY b.branch_id, DATE(t.date_time)
        ORDER BY date ASC
    ")->getResult();

    $datasets = [];
    $labels = [];

    foreach ($result as $row) {
      $date = $row->date;
      $branch = $row->branch_name;

      if (!in_array($date, $labels)) {
        $labels[] = $date;
      }

      if (!isset($datasets[$branch])) {
        $datasets[$branch] = [];
      }

      $datasets[$branch][$date] = $row->total_sales / 1000;
    }

    return [
      'labels' => $labels,
      'datasets' => $datasets
    ];
  }
  private function buildHourlyChart($db, $startDate, $endDate)
  {
    $result = $db->query("
        SELECT 
            b.branch_name,
            HOUR(t.date_time) as hour,
            SUM(t.total_price) as total_sales
        FROM transactions t
        JOIN branch b ON b.branch_id = t.branch_id
        WHERE t.date_time >= '{$startDate} 00:00:00'
        AND t.date_time <= '{$endDate} 23:59:59'
        GROUP BY b.branch_id, HOUR(t.date_time)
    ")->getResult();

    $labels = range(9, 22);
    $datasets = [];

    foreach ($result as $row) {
      $branch = $row->branch_name;

      if (!isset($datasets[$branch])) {
        $datasets[$branch] = array_fill(0, 24, 0);
      }

      $datasets[$branch][$row->hour] = $row->total_sales;
    }

    foreach ($datasets as $branch => $data) {
      $datasets[$branch] = array_slice($data, 9, 14);
    }

    return [
      'labels' => array_map(fn($h) => "$h:00", $labels),
      'datasets' => $datasets
    ];
  }

  private function getDateRange($type, $params)
  {
    switch ($type) {
      case 'daily':
        $start = date('Y-m-d');
        $end   = date('Y-m-d');
        break;

      case 'range':
        $days = (int)$params['days'];
        $start = date('Y-m-d', strtotime("-{$days} days"));
        $end   = date('Y-m-d');
        break;

      case 'monthly':
        $month = $params['month']; // format: 2026-04

        $start = date('Y-m-01', strtotime($month));
        $end   = date('Y-m-t', strtotime($month));
        break;

      default:
        throw new \Exception("Invalid type");
    }

    return [$start, $end];
  }

  public function daily()
  {
    return $this->generateReport('daily', []);
  }

  public function range()
  {
    $days = $this->request->getGet('days') ?? 7;
    return $this->generateReport('range', ['days' => $days]);
  }

  public function monthly()
  {
    $month = $this->request->getGet('month'); // 2026-04
    return $this->generateReport('monthly', ['month' => $month]);
  }
}
