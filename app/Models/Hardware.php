<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Hardware extends Model
{
	use HasFactory;

	protected $table = 'hardware';  // Replace 'actual_table_name' with your table name
        protected $guarded = [];

	 public function deviceRegisters()
    {
        return $this->hasMany(DeviceRegister::class, 'hardware_id');
    }
	
	public function product(){
		return $this->hasOne(Product::class, 'hardware_id');
	}

}
