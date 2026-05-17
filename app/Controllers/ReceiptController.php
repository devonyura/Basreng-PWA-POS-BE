<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class ReceiptController extends ResourceController
{
    private $fontPath;

    public function __construct()
    {
        // Path ke font TTF Anda
        $this->fontPath = FCPATH . 'fonts/CourierPrime-Regular.ttf';
    }

    public function generateReceipt()
    {
        $json = $this->request->getJSON();
        if (!$json || !isset($json->transactions) || !isset($json->transaction_details)) {
            return $this->fail('transactions & transaction_details wajib ada', 400);
        }

        $trx = $json->transactions;
        $details = $json->transaction_details;
        $reseller = $json->reseller ?? null;

        try {
            // --- KONFIGURASI UKURAN TEXT (Satuan Point/pt) ---
            $fSize = [
                'header' => 17, // Alamat Toko
                'body'   => 15, // Teks Umum
                'title'  => 16, // Judul Section
                'total'  => 22, // Angka Total (Besar)
                'shopee' => 19, // Shopee Code
                'footer' => 14, // Ucapan
                'small'  => 12   // Versi app
            ];

            $width = 380;
            $baseHeight = 2500; // Draft canvas
            $img = imagecreatetruecolor($width, $baseHeight);
            
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);

            $y = 20; 
            $padding = 20;
            $maxWidth = $width - ($padding * 2);

            // --- LOGO ---
            $logoPath = FCPATH . 'uploads/logo-struk.png';
            if (file_exists($logoPath)) {
                $logo = imagecreatefrompng($logoPath);
                if ($logo) {
                    $origW = imagesx($logo); $origH = imagesy($logo);
                    $targetW = 230; $targetH = ($origH / $origW) * $targetW;
                    $posX = ($width - $targetW) / 2;
                    imagealphablending($img, true);
                    imagecopyresampled($img, $logo, (int)$posX, (int)$y, 0, 0, $targetW, (int)$targetH, $origW, $origH);
                    imagedestroy($logo);
                    $y += $targetH + 20;
                }
            }

            // --- HEADER INFO ---
            $this->drawWrappedText($img, $trx->branch_address ?? "Alamat Toko", $width/2, $y, $black, $fSize['header'], $maxWidth, true);
            $y += 15;
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 25;

            // --- META DATA ---
            $this->drawRow($img, "No", $trx->transaction_code, $y, $black, $width, $fSize['body']); $y += 25;
            $this->drawRow($img, "Kasir", $trx->username, $y, $black, $width, $fSize['body']); $y += 25;
            $this->drawRow($img, "Tgl", date('d/m/Y H:i', strtotime($trx->date_time)), $y, $black, $width, $fSize['body']); $y += 25;
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 25;

            // --- ITEMS ---
            foreach ($details as $item) {
                $productName = $this->formatProductName($item->product_name, $item->weight_grams ?? null);
                $this->drawWrappedText($img, $productName, $padding, $y, $black, $fSize['body'], $maxWidth);
                $qtyText = $item->quantity . "x " . $this->formatRupiah($item->price);
                $totalText = $this->formatRupiah($item->subtotal);
                $this->drawRow($img, $qtyText, $totalText, $y, $black, $width, $fSize['body'], 40);
                $y += 30;
            }
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 20;

            // --- TOTALS ---
            $this->drawRow($img, "Pembayaran", strtoupper($trx->payment_method), $y, $black, $width, $fSize['body']); $y += 25;
            if (!empty($trx->shopee_code)) {
                $this->drawText($img, "Shopee Code:", $padding, $y, $black, $fSize['shopee']);
                $y += $fSize['shopee'] + 5;
                $this->drawWrappedText($img, $trx->shopee_code, $padding, $y, $black, $fSize['shopee'], $maxWidth);
                $y += 10;
            }
            $this->drawDashedLine($img, 0, $y, $width, $black); $y += 20;
            $this->drawRow($img, "TOTAL", $this->formatRupiah($trx->total_price), $y, $black, $width, $fSize['total']); $y += 40;
            $this->drawDashedLine($img, 0, $y, $width, $black); $y += 20;
            $this->drawRow($img, "Tunai", $this->formatRupiah($trx->cash_amount), $y, $black, $width, $fSize['body']); $y += 25;
            $this->drawRow($img, "Kembalian", $this->formatRupiah($trx->change_amount), $y, $black, $width, $fSize['body']); $y += 30;

            // --- KONDISIONAL: RESELLER ---
            if ($reseller && !empty($reseller->name)) {
                $y += 10; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 25;
                $this->drawText($img, "RESELLER", $padding, $y, $black, $fSize['title']); $y += 25;
                $this->drawRow($img, "Nama", $reseller->name, $y, $black, $width, $fSize['body']); $y += 25;
                $this->drawRow($img, "HP", $reseller->phone ?? "-", $y, $black, $width, $fSize['body']); $y += 25;
                $this->drawText($img, "Alamat:", $padding, $y, $black, $fSize['body']); $y += 22;
                $this->drawWrappedText($img, $reseller->address ?? "-", $padding, $y, $black, $fSize['body'], $maxWidth);
            }

            // --- KONDISIONAL: PEMESAN ---
            if (isset($trx->is_online_order) && $trx->is_online_order == "1") {
                $y += 10; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 25;
                $this->drawText($img, "PEMESAN", $padding, $y, $black, $fSize['title']); $y += 25;
                $this->drawRow($img, "Nama", $trx->customer_name ?? "-", $y, $black, $width, $fSize['body']); $y += 25;
                $this->drawRow($img, "HP", $trx->customer_phone ?? "-", $y, $black, $width, $fSize['body']); $y += 25;
                $this->drawText($img, "Alamat:", $padding, $y, $black, $fSize['body']); $y += 22;
                $this->drawWrappedText($img, $trx->customer_address ?? "-", $padding, $y, $black, $fSize['body'], $maxWidth);
                if (!empty($trx->notes)) {
                    $y += 10;
                    $this->drawText($img, "Catatan:", $padding, $y, $black, $fSize['body']); $y += 22;
                    $this->drawWrappedText($img, $trx->notes, $padding, $y, $black, $fSize['body'], $maxWidth);
                }
            }

            // --- FOOTER ---
            $y += 20; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 30;
            $this->drawText($img, "Selamat Menikmati :)", $width / 2, $y, $black, $fSize['footer'], true); $y += 25;
            $this->drawText($img, "BASRENG POS v1.1", $width / 2, $y, $black, $fSize['small'], true);

            // --- FINAL CROP ---
            $finalHeight = $y + 40;
            $finalImg = imagecreatetruecolor($width, $finalHeight);
            $finalWhite = imagecolorallocate($finalImg, 255, 255, 255);
            imagefill($finalImg, 0, 0, $finalWhite);
            imagecopy($finalImg, $img, 0, 0, 0, 0, $width, $finalHeight);

            ob_start(); imagepng($finalImg); $imageData = ob_get_clean();
            imagedestroy($img); imagedestroy($finalImg);

            return $this->respond(['success' => true, 'data' => ['base64' => 'data:image/png;base64,' . base64_encode($imageData)]]);

        } catch (\Exception $e) {
            return $this->fail($e->getMessage(), 500);
        }
    }

    // --- HELPERS (TTF VERSION) ---

    private function drawText($img, $text, $x, $y, $color, $fontSize = 11, $center = false) {
        if ($center) {
            $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $text);
            $textWidth = $bbox[2] - $bbox[0];
            $x = ($width ?? imagesx($img) - $textWidth) / 2;
        }
        // imagettftext menggunakan koordinat baseline (bawah teks), jadi kita tambah $fontSize agar koordinat $y mirip imagestring (top-left)
        imagettftext($img, $fontSize, 0, (int)$x, (int)($y + $fontSize), $color, $this->fontPath, $text);
    }

    private function drawWrappedText($img, $text, $x, &$y, $color, $fontSize, $maxWidth, $center = false) {
        $words = explode(' ', $text);
        $line = '';
        $lineHeight = $fontSize + 8;

        foreach ($words as $word) {
            $testLine = $line . ($line ? ' ' : '') . $word;
            $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $testLine);
            $testWidth = $bbox[2] - $bbox[0];

            if ($testWidth > $maxWidth && $line !== '') {
                $this->drawText($img, $line, $x, $y, $color, $fontSize, $center);
                $y += $lineHeight;
                $line = $word;
            } else {
                $line = $testLine;
            }

            // Handle word longer than maxWidth
            $bboxWord = imagettfbbox($fontSize, 0, $this->fontPath, $line);
            if ($bboxWord[2] - $bboxWord[0] > $maxWidth) {
                $chars = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
                $subLine = "";
                foreach ($chars as $char) {
                    $testSub = $subLine . $char;
                    $bboxSub = imagettfbbox($fontSize, 0, $this->fontPath, $testSub);
                    if ($bboxSub[2] - $bboxSub[0] <= $maxWidth) {
                        $subLine = $testSub;
                    } else {
                        $this->drawText($img, $subLine, $x, $y, $color, $fontSize, $center);
                        $y += $lineHeight;
                        $subLine = $char;
                    }
                }
                $line = $subLine;
            }
        }
        $this->drawText($img, $line, $x, $y, $color, $fontSize, $center);
        $y += $lineHeight;
    }

    private function drawRow($img, $left, $right, $y, $color, $width, $fontSize = 11, $indent = 20) {
        // Kiri
        imagettftext($img, $fontSize, 0, $indent, (int)($y + $fontSize), $color, $this->fontPath, $left);
        // Kanan (Right Aligned)
        $bbox = imagettfbbox($fontSize, 0, $this->fontPath, $right);
        $textWidth = $bbox[2] - $bbox[0];
        imagettftext($img, $fontSize, 0, (int)($width - $textWidth - $indent), (int)($y + $fontSize), $color, $this->fontPath, $right);
    }

    private function drawDashedLine($img, $x1, $y, $x2, $color) {
        $style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
        imagesetstyle($img, $style);
        imageline($img, $x1, (int)$y, $x2, (int)$y, IMG_COLOR_STYLED);
    }

    private function formatRupiah($val) {
        return "Rp " . number_format($val, 0, ',', '.');
    }

    private function formatProductName($name, $weight) {
        if (!$weight || $weight <= 0) return $name;
        $formattedWeight = ($weight >= 1000) ? ($weight / 1000) . "kg" : $weight . "gr";
        return "$name ($formattedWeight)";
    }
}
