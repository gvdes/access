<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\Order;
class PreventaController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
    }

    public function getTicket(Request $request){
        $order = Order::find($request->folio);
        if($order){
            $products = $order->products->map(function($product){
                return [
                    "code" => $product->tob_item,
                    "req" => $product->tob_units,
                    "units" => $product->tob_calcby
                ];
            });
            return response()->json(["notes" => $order->toh_frontname, "products" => $products]);
        }
        return response()->json(["msg" => "El folio no existe"]);
    }
}