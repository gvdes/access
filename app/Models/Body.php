<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Body extends Model{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'tkt_order_body';
    protected $primaryKey = 'tob_id';
    public $timestamps = false;
    protected $fillable = ['tob_id', 'tob_item', 'tob_units', 'tob_toh', 'tob_calcby'];

    public function ticket(){
        return $this->belongsTo('App\Models\Order', 'toh_backname', 'tob_toh');
    }
}