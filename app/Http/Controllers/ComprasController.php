<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class ComprasController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        try{
            $access = env('ACCESS_FILE');
            $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            $this->con = $db;
        }catch(PDOException $e){
            return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
        }
    }

    public function getTotal(Request $request){
        $products = $request->products;
        $codes = array_column($products, 'Modelo');
        $query = "SELECT ARTLFR, CANLFR, PRELFR, TOTLFR FROM F_LFR WHERE ARTLFR = ?";
        $exec = $this->con->prepare($query);
        $body = [];
        foreach($codes as $code){
            $exec->execute([$code]);
            $body = array_merge($body, $exec->fetchAll(\PDO::FETCH_ASSOC));
        }
        $modelos = collect($body)->groupBy('ARTLFR')->toArray();
        $res = collect($products)->map(function($product) use($modelos){
            if(array_key_exists($product['Modelo'],$modelos)){
                $product['Compras'] = collect($modelos[$product['Modelo']])->sum('CANLFR');
            }else{
                $product['Compras'] = 0;
            }
            return $product;

        });
        return response()->json(["Compras" => $res]);
    }
}
