<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\KeywordRule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Idempotent: safe to re-run via /setup after every deploy.
     */
    public function run(): void
    {
        $categories = [
            ['slug' => 'food',      'name' => 'Food',      'color' => '#F5B453', 'icon' => 'bowl',     'sound_preset' => 'pop',    'sort_order' => 1],
            ['slug' => 'going-out', 'name' => 'Going Out', 'color' => '#F4778A', 'icon' => 'glass',    'sound_preset' => 'chime',  'sort_order' => 2],
            ['slug' => 'protein',   'name' => 'Protein',   'color' => '#5BD6A2', 'icon' => 'egg',      'sound_preset' => 'thock',  'sort_order' => 3],
            ['slug' => 'transport', 'name' => 'Transport', 'color' => '#6FB5F7', 'icon' => 'fuel',     'sound_preset' => 'whoosh', 'sort_order' => 4],
            ['slug' => 'bills',     'name' => 'Bills',     'color' => '#A78BFA', 'icon' => 'bolt',     'sound_preset' => 'ding',   'sort_order' => 5],
            ['slug' => 'fun',       'name' => 'Fun',       'color' => '#E879F9', 'icon' => 'gamepad',  'sound_preset' => 'arcade', 'sort_order' => 6],
            ['slug' => 'misc',      'name' => 'Misc',      'color' => '#9AA7B8', 'icon' => 'box',      'sound_preset' => 'click',  'sort_order' => 7],
        ];

        $bySlug = [];
        foreach ($categories as $cat) {
            $bySlug[$cat['slug']] = Category::updateOrCreate(['slug' => $cat['slug']], $cat);
        }

        $keywords = [
            'food' => [
                'nasi', 'ayam', 'geprek', 'penyetan', 'lalapan', 'bubur', 'bakso', 'mie',
                'soto', 'gudeg', 'snack', 'kopi', 'coffee', 'sprite', 'teh', 'jajan',
                'warung', 'makan', 'sarapan', 'gofood', 'grabfood', 'shopeefood', 'sauce',
                'saus', 'roti', 'martabak', 'sate', 'pecel', 'gorengan', 'tumpeng',
                'cokelat', 'chocolate', 'oat milk',
            ],
            'going-out' => [
                'date', 'cafe', 'nongkrong', 'hangout', 'restoran', 'karaoke', 'nonton',
                'bioskop', 'mall', 'keluar', 'traktir',
            ],
            'protein' => [
                'chicken breast', 'dada ayam', 'telur', 'egg', 'whey', 'protein', 'susu',
                'creatine', 'suplemen',
            ],
            'transport' => [
                'bensin', 'pertalite', 'pertamax', 'parkir', 'gojek', 'grab', 'tol',
                'ojek', 'servis', 'oli', 'ban',
            ],
            'bills' => [
                'wifi', 'listrik', 'pulsa', 'token', 'pdam', 'internet',
                'langganan', 'subscription', 'netflix', 'spotify', 'iuran', 'bpjs',
            ],
            'fun' => [
                'game', 'steam', 'poe', 'skin', 'top up', 'topup', 'valorant', 'buku',
                'hobi', 'gear', 'keyboard', 'mouse',
            ],
        ];

        foreach ($keywords as $slug => $words) {
            foreach ($words as $word) {
                KeywordRule::updateOrCreate(
                    ['keyword' => $word],
                    ['category_id' => $bySlug[$slug]->id, 'is_seed' => true]
                );
            }
        }
    }
}
