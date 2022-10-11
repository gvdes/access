<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_order_head';
    protected $primaryKey = 'toh_id';
    public $timestamps = false;
    protected $fillable = ['toh_id', 'toh_backname', 'toh_frontname'];

    public function products(){
        return $this->hasMany('App\Models\Body', 'tob_toh', 'toh_backname');
    }
}