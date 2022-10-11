<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class DevolucionesController extends Controller{
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
            $this->_workpoint = env('_WORKPOINT');
        }catch(PDOException $e){
            return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
        }
    }

    public function getAmount(Request $request){
        $products = [];
        $data = array_column($request->products, "code");
        foreach($data as $product){
            $products[] = [
                "Modelo" => $product,
                "entradas" => $this->getEntradas($product),
                "facturas recibidas" => $this->getFacturasRecibidas($product),
                "ventas" => $this->getVentas($product),
                "devoluciones" => $this->getDevoluciones($product)
            ];
        }
        return response()->json($products);
    }

    public function getEntradas($code){
        $query = "SELECT SUM(CANLEN) FROM F_LEN WHERE ARTLEN = ?";
        $exec = $this->con->prepare($query);
        $exec->execute([$code]);
        $result = $exec->fetch(\PDO::FETCH_ASSOC);
        return intval($result["Expr1000"]);
    }

    public function getFacturasRecibidas($code){
        $query = "SELECT SUM(CANLFR) FROM F_LFR WHERE ARTLFR = ?";
        $exec = $this->con->prepare($query);
        $exec->execute([$code]);
        $result = $exec->fetch(\PDO::FETCH_ASSOC);
        return intval($result["Expr1000"]);
    }

    public function getSalidas($code){
        $query = "SELECT SUM(CANLSA) FROM F_LSA WHERE ARTLSA = ?";
        $exec = $this->con->prepare($query);
        $exec->execute([$code]);
        $result = $exec->fetch(\PDO::FETCH_ASSOC);
        return intval($result["Expr1000"]);
    }

    public function getDevoluciones($code){
        $query = "SELECT SUM(F_LFD.CANLFD) FROM F_FRD INNER JOIN F_LFD ON F_FRD.CODFRD = F_LFD.CODLFD WHERE F_FRD.TIPFRD = F_LFD.TIPLFD AND F_LFD.ARTLFD = ? AND F_FRD.FECFRD > #2019-12-15#";
        $exec = $this->con->prepare($query);
        $exec->execute([$code]);
        $result = $exec->fetch(\PDO::FETCH_ASSOC);
        return intval($result["Expr1000"]);
    }

    public function getVentas($code){
        $query = "SELECT SUM(CANLAL) FROM F_LAL WHERE ARTLAL = ?";
        $exec = $this->con->prepare($query);
        $exec->execute([$code]);
        $result = $exec->fetch(\PDO::FETCH_ASSOC);
        return intval($result["Expr1000"]);
    }
}
