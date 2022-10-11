<?php

namespace App\Http\Controllers;

class ConnectionController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        //
    }

    public function getConnection(){
        return response()->json(true);
    }
}
