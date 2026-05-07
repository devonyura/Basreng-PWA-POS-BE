<?php
function formatTanggalIndo($date)
{
  $hari = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
  $bulan = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

  $timestamp = strtotime($date);

  return $hari[date('w', $timestamp)] . ', ' .
    date('d', $timestamp) . ' ' .
    $bulan[date('n', $timestamp) - 1] . ' ' .
    date('Y', $timestamp);
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
  <meta charset="UTF-8">
  <title>Laporan Harian - <?= formatTanggalIndo($date) ?></title>

  <style>
    body {
      font-family: Arial, sans-serif;
      font-size: 13px;
      color: #333;
      margin: 15px 20px;
    }

    /* HEADER */
    .header {
      text-align: center;
      margin-bottom: 15px;
    }

    .header h2 {
      margin: 0;
      font-size: 16px;
      color: #2c3e50;
    }

    .header p {
      margin: 2px 0;
      font-size: 12px;
    }

    /* SECTION */
    .section-title {
      margin-top: 20px;
      font-weight: bold;
      font-size: 13px;
      border-bottom: 1px solid #ccc;
      padding-bottom: 3px;
      color: #2c3e50;
      border-bottom: 2px solid #2c3e50;
    }

    /* SUMMARY */
    .summary {
      margin-top: 10px;
      border: 1px solid #ccc;
      border-radius: 6px;
      padding: 10px;
    }

    .summary-row {
      display: flex;
      justify-content: space-between;
      margin-bottom: 6px;
    }

    /* CARD STYLE (GANTI TABLE) */
    .card {
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 10px;
      margin-top: 10px;
    }

    .card-title {
      font-weight: bold;
      margin-bottom: 5px;
    }

    .row-table {
      width: 100%;
      margin-bottom: 4px;

    }

    .row-table td {
      padding: 2px 0;
    }

    .text-left {
      text-align: left;
    }

    .text-right {
      text-align: right;
      font-weight: bold;
      color: #1b5e20;
    }

    .label {
      color: #666;
    }

    .value {
      font-weight: bold;
    }

    /* FOOTER */
    .footer {
      margin-top: 25px;
      text-align: center;
      font-size: 10px;
      color: #777;
    }
  </style>
</head>

<body>

  <!-- HEADER -->
  <div class="header">
    <h2 style="margin-bottom:4px;">BASRENG GHOSTING</h2>
    <p style="font-size:13px;">Laporan Harian</p>
    <p style="font-size:12px;color:#666;">
      <?= formatTanggalIndo($date) ?>
    </p>
    <hr>
  </div>

  <!-- SUMMARY -->
  <div class="section-title">Ringkasan</div>

  <table class="row-table">
    <tr>
      <td>Total Omset</td>
      <td class="text-right">
        Rp <?= number_format($summary['total_sales'] ?? 0, 0, ',', '.') ?>
      </td>
    </tr>
    <tr>
      <td>Total Transaksi</td>
      <td class="text-right">
        <?= $summary['total_transactions'] ?? 0 ?>
      </td>
    </tr>
  </table>

  <div class="section-title">Grafik Penjualan per Jam</div>

  <!-- <div style="text-align:center; margin-top:10px;">
    <img src=" $chartUrl ?>" style="width:100%; max-width:500px;margin-top:10px;">
  </div> -->

  <!-- CABANG -->
  <div class="section-title">Pendapatan per Cabang</div>

  <?php
  $bestBranchId = null;
  $maxIncome = 0;

  foreach ($branches as $b) {
    if ($b->total_income > $maxIncome) {
      $maxIncome = $b->total_income;
      $bestBranchId = $b->branch_id;
    }
  }
  ?>

  <?php foreach ($branches as $b): ?>
    <div style="
    border:1px solid #ddd;
    border-radius:6px;
    padding:10px;
    margin-top:10px;
    <?= ($b->branch_id == $bestBranchId) ? 'background:#fff8e1;border:2px solid #fbc02d;' : '' ?>
  ">
      <table class="row-table">
        <tr>
          <td>Nama Cabang</td>
          <td class="text-right"><?= $b->branch_name ?></td>
        </tr>
        <tr>
          <td>Transaksi</td>
          <td class="text-right"><?= $b->total_transactions ?></td>
        </tr>
        <tr>
          <td>Cash</td>
          <td class="text-right">Rp <?= number_format($b->total_income_cash, 0, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Transfer</td>
          <td class="text-right">Rp <?= number_format($b->total_income_transfer_bank, 0, ',', '.') ?></td>
        </tr>
        <tr>
          <td>QRIS</td>
          <td class="text-right">Rp <?= number_format($b->total_income_qris, 0, ',', '.') ?></td>
        </tr>
        <tr>
          <td>Shopee</td>
          <td class="text-right">Rp <?= number_format($b->total_income_shopee, 0, ',', '.') ?></td>
        </tr>
      </table>

      <hr>

      <table class="row-table">
        <tr>
          <td><strong>Total</strong></td>
          <td class="text-right">
            <strong>Rp <?= number_format($b->total_income, 0, ',', '.') ?></strong>
          </td>
        </tr>
      </table>
    </div>
  <?php endforeach; ?>

  <!-- PRODUK -->
  <div class="section-title">Rekap Penjualan per Cabang</div>

  <?php foreach ($products as $branch): ?>

    <div class="card">
      <div class="card-title"> Nama Cabang: <b><?= $branch['branch_name'] ?></b> </div>
      <?php $rank = 1; ?>
      <?php foreach ($branch['products'] as $p): ?>

        <table class="row-table">
          <tr>
            <td>
              #<?= $rank ?> <?= $p['product_name'] ?>
            </td>
            <td class="text-right"><?= $p['total_sold'] ?>pcs</td>
          </tr>
          <tr>
            <td>Omset</td>
            <td class="text-right">
              Rp <?= number_format($p['total_sales'], 0, ',', '.') ?>
            </td>
          </tr>
        </table>

        <hr>
        <?php $rank++; ?>
      <?php endforeach; ?>

    </div>

  <?php endforeach; ?>

  <!-- FOOTER -->
  <div class="footer">
    <p>BASRENG POS v1.0</p>
    <p>Generated automatically at <?= date('d-m-Y H:i') ?></p>
  </div>

</body>

</html>