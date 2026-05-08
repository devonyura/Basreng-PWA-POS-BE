<?php

namespace App\Controllers;

use CodeIgniter\RESTful\ResourceController;

class ReceiptController extends ResourceController
{
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
            // --- CONFIGURASI UKURAN TEXT (Silakan Atur di Sini) ---
            $fSize = [
                'header' => 6, // Alamat Toko
                'body'   => 4, // Teks Umum (Kasir, Tgl, Nama Item)
                'title'  => 6, // Judul Section (RESELLER, PEMESAN)
                'total'  => 8, // Angka Total Akhir
                'footer' => 4, // Ucapan Terima Kasih
                'small'  => 2, // Versi aplikasi
            ];

            $scale = 1;
            $width = 380;
            $baseHeight = 2000; 
            $img = imagecreatetruecolor($width, $baseHeight);
            
            $white = imagecolorallocate($img, 255, 255, 255);
            $black = imagecolorallocate($img, 0, 0, 0);
            imagefill($img, 0, 0, $white);

            $y = 20; 
            $padding = 15;
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
                    imagecopyresampled($img, $logo, $posX, $y, 0, 0, $targetW, $targetH, $origW, $origH);
                    imagedestroy($logo);
                    $y += $targetH + 15;
                }
            } else {
                $this->drawText($img, "BASRENG POS", $width / 2, $y, $black, 5, true);
                $y += 30;
            }

            // --- HEADER INFO ---
            $this->drawWrappedText($img, $trx->branch_address ?? "Alamat Toko", $width/2, $y, $black, $fSize['header'], $maxWidth, true);
            $y += 15;
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 15;

            // --- META DATA ---
            $this->drawRow($img, "No", $trx->transaction_code, $y, $black, $width, $fSize['body']); $y += 20;
            $this->drawRow($img, "Kasir", $trx->username, $y, $black, $width, $fSize['body']); $y += 20;
            $this->drawRow($img, "Tgl", date('d/m/Y H:i', strtotime($trx->date_time)), $y, $black, $width, $fSize['body']); $y += 22;
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 15;

            // --- ITEMS ---
            foreach ($details as $item) {
                $productName = $this->formatProductName($item->product_name, $item->weight_grams ?? null);
                $this->drawWrappedText($img, $productName, $padding, $y, $black, $fSize['body'], $maxWidth);
                $qtyText = $item->quantity . "x " . $this->formatRupiah($item->price);
                $totalText = $this->formatRupiah($item->subtotal);
                $this->drawRow($img, $qtyText, $totalText, $y, $black, $width, $fSize['body'], 30);
                $y += 25;
            }
            $this->drawDashedLine($img, 0, $y, $width, $black);
            $y += 15;

            // --- TOTALS ---
            $this->drawRow($img, "Pembayaran", strtoupper($trx->payment_method), $y, $black, $width, $fSize['body']); $y += 20;
            $this->drawDashedLine($img, 0, $y, $width, $black); $y += 15;
            $this->drawRow($img, "TOTAL", $this->formatRupiah($trx->total_price), $y, $black, $width, $fSize['total']); $y += 30;
            $this->drawDashedLine($img, 0, $y, $width, $black); $y += 15;
            $this->drawRow($img, "Tunai", $this->formatRupiah($trx->cash_amount), $y, $black, $width, $fSize['body']); $y += 20;
            $this->drawRow($img, "Kembalian", $this->formatRupiah($trx->change_amount), $y, $black, $width, $fSize['body']); $y += 25;

            // --- KONDISIONAL: RESELLER ---
            if ($reseller && !empty($reseller->name)) {
                $y += 15; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 15;
                $this->drawText($img, "RESELLER", $padding, $y, $black, $fSize['title']); $y += 22;
                $this->drawRow($img, "Nama", $reseller->name, $y, $black, $width, $fSize['body']); $y += 20;
                $this->drawRow($img, "HP", $reseller->phone ?? "-", $y, $black, $width, $fSize['body']); $y += 20;
                $this->drawText($img, "Alamat:", $padding, $y, $black, $fSize['body']); $y += 20;
                $this->drawWrappedText($img, $reseller->address ?? "-", $padding, $y, $black, $fSize['body'], $maxWidth);
            }

            // --- KONDISIONAL: PEMESAN ---
            if (isset($trx->is_online_order) && $trx->is_online_order == "1") {
                $y += 15; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 15;
                $this->drawText($img, "PEMESAN", $padding, $y, $black, $fSize['title']); $y += 22;
                $this->drawRow($img, "Nama", $trx->customer_name ?? "-", $y, $black, $width, $fSize['body']); $y += 20;
                $this->drawRow($img, "HP", $trx->customer_phone ?? "-", $y, $black, $width, $fSize['body']); $y += 20;
                $this->drawText($img, "Alamat:", $padding, $y, $black, $fSize['body']); $y += 20;
                $this->drawWrappedText($img, $trx->customer_address ?? "-", $padding, $y, $black, $fSize['body'], $maxWidth);
                if (!empty($trx->notes)) {
                    $y += 5;
                    $this->drawText($img, "Catatan:", $padding, $y, $black, $fSize['body']); $y += 20;
                    $this->drawWrappedText($img, $trx->notes, $padding, $y, $black, $fSize['body'], $maxWidth);
                }
            }

            // --- FOOTER ---
            $y += 15; $this->drawDashedLine($img, 0, $y, $width, $black); $y += 20;
            $this->drawText($img, "- Menjual berbagai cemilan pedas -", $width / 2, $y, $black, $fSize['footer'], true); $y += 20;
            $this->drawText($img, "Selamat Menikmati :)", $width / 2, $y, $black, $fSize['footer'], true); $y += 25;
            $this->drawText($img, "BASRENG POS v1.1", $width / 2, $y, $black, $fSize['small'], true);

            // --- FINAL CROP ---
            $finalHeight = $y + 30;
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

    // --- HELPERS ---

    private function drawWrappedText($img, $text, $x, &$y, $color, $fontSize, $maxWidth, $center = false) {
        $fontWidth = imagefontwidth($fontSize);
        $fontHeight = imagefontheight($fontSize);
        $maxChars = floor($maxWidth / $fontWidth);
        
        $wrappedText = wordwrap($text, $maxChars, "\n", true);
        $lines = explode("\n", $wrappedText);
        
        foreach ($lines as $line) {
            $currentX = $x;
            if ($center) {
                $currentX = (imagesx($img) - ($fontWidth * strlen(trim($line)))) / 2;
            }
            imagestring($img, $fontSize, (int)$currentX, (int)$y, trim($line), $color);
            $y += $fontHeight + 5; // Jarak antar baris teks yang di-wrap
        }
    }

    private function drawText($img, $text, $x, $y, $color, $fontSize = 2, $center = false) {
        if ($center) {
            $fontWidth = imagefontwidth($fontSize) * strlen($text);
            $x = $x - ($fontWidth / 2);
        }
        imagestring($img, $fontSize, (int)$x, (int)$y, $text, $color);
    }

    private function drawRow($img, $left, $right, $y, $color, $width, $fontSize = 2, $indent = 15) {
        imagestring($img, $fontSize, $indent, $y, $left, $color);
        $fontWidth = imagefontwidth($fontSize) * strlen($right);
        imagestring($img, $fontSize, $width - $fontWidth - 15, $y, $right, $color);
    }

    private function drawDashedLine($img, $x1, $y, $x2, $color) {
        $style = array($color, $color, $color, $color, IMG_COLOR_TRANSPARENT, IMG_COLOR_TRANSPARENT);
        imagesetstyle($img, $style);
        imageline($img, $x1, $y, $x2, $y, IMG_COLOR_STYLED);
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
