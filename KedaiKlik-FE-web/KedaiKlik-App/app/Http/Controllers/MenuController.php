<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function hidangan() {
        $menus = [
            ['name' => 'Nasi Goreng', 'desc' => 'Nasi Goreng dengan sayur, telur mata sapi dan kerupuk', 'price_raw' => 25000, 'img' => 'nasgor.jpg'],
            ['name' => 'Ayam Bakar', 'desc' => 'Ayam Bakar dengan nasi dan lalapan sedap', 'price_raw' => 29000, 'img' => 'ayam bakar.jpg'],
            ['name' => 'Soto Ayam', 'desc' => 'Soto Ayam + Nasi putih dan kuah yang gurih', 'price_raw' => 23000, 'img' => 'soto.jpg'],
            ['name' => 'Gudeg', 'desc' => 'Nasi Gudeg khas Yogyakarta', 'price_raw' => 23000, 'img' => 'Gudeg.jpg'],
            ['name' => 'Lontong Sayur', 'desc' => 'Lontong Sayur dengan kari ayam kuah gurih', 'price_raw' => 28000, 'img' => 'Lontong sayur.jpg'],
            ['name' => 'Ayam Geprek', 'desc' => 'Paket Ayam Geprek + Nasi', 'price_raw' => 22000, 'img' => 'Ayam Geprek.jpg'],
            ['name' => 'Sate Ayam', 'desc' => 'Paket Sate Ayam + Lontong', 'price_raw' => 22000, 'img' => 'sate ayam.jpg'],
        ];
        return view('menu', compact('menus'));
    }

    public function cemilan() {
        $menus = [
            ['name' => 'Martabak Telur', 'desc' => 'Martabak telur dengan isian daging dan telur, gurih dan renyah di setiap gigitan', 'price_raw' => 18000, 'img' => 'Martabak Telur Mini.jpg'],
            ['name' => 'Pempek', 'desc' => 'Pempek khas Palembang dengan kuah cuko asam manis pedas yang segar', 'price_raw' => 20000, 'img' => 'Pempek.jpg'],
            ['name' => 'Tahu Gejrot', 'desc' => 'Tahu goreng dengan bumbu khas asam manis pedas yang menggugah selera', 'price_raw' => 15000, 'img' => 'Tahu Gejrot.jpg'],
            ['name' => 'Arem-Arem', 'desc' => 'Lontong isi sayur dan ayam berbumbu gurih, cocok untuk camilan ringan', 'price_raw' => 12000, 'img' => 'arem-arem.jpg'],
        ];
        return view('menu', compact('menus'));
    }

    public function minuman() {
        $menus = [
            ['name' => 'Es Teh Manis', 'desc' => 'Teh manis dingin yang segar dan cocok untuk menemani semua hidangan', 'price_raw' => 8000, 'img' => 'esteh.jpg'],
            ['name' => 'Es Lemon Tea', 'desc' => 'Perpaduan teh dan lemon segar dengan rasa manis dan sedikit asam yang menyegarkan', 'price_raw' => 12000, 'img' => 'lemon tea.jpg'],
            ['name' => 'Matcha Latte', 'desc' => 'Minuman matcha creamy dengan rasa khas teh hijau yang lembut dan menenangkan', 'price_raw' => 18000, 'img' => 'matcha.jpg'],
            ['name' => 'Caramel Latte', 'desc' => 'Kopi latte dengan sentuhan karamel manis yang creamy dan nikmat', 'price_raw' => 20000, 'img' => 'caramel.jpg'],
        ];
        return view('menu', compact('menus'));
    }
}