<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Movie extends Model
{
    protected $fillable = ['file_id', 'name', 'code', 'message_id', 'status'];

    // Timestamp avtomatik qo'shiladi
    public $timestamps = true;

    /**
     * Kod bo'yicha qidirish
     */
    public static function findByCode($code)
    {
        return self::where('code', trim($code))->first();
    }

    /**
     * Oxirgi qo'shilgan kinolar
     */
    public static function getLatest($limit = 10)
    {
        return self::orderBy('created_at', 'desc')->limit($limit)->get();
    }

    public function findByName($name, $limit = 10)
    {
        return self::where('name', 'LIKE', "%$name%")->limit($limit)->get();
    }
}
