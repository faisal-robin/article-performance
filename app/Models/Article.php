<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
    use HasFactory;

    protected $primaryKey = 'kArtikel';

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'kArtikel', 'kArtikel');
    }

    public function children()
    {
        return $this->hasMany(Article::class, 'kVaterArtikel', 'kArtikel');
    }
}
