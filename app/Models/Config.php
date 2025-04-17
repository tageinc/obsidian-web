<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
Use Exception;

class Config extends Model
{
    use HasFactory;

    protected $guarded = [];

    public static function get($key)
    {
        $value = null;

        try
        {
            $value = Config::where('key', $key)->first()->value;
        } catch(Exception $e) {
            //dd($e);
        }

        return $value;
    }
}
