<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class WarehouseController extends Controller{
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

    public function getStocks(Request $request){
        $almacenes = $this->getAlmacenes($request->_workpoint);
        if($almacenes){
            $gen = $almacenes["GEN"];
            $exh = $almacenes["EXH"];
            $des = $almacenes["DES"];
            $fdt = $almacenes["FDT"];
            $query = "SELECT F_STO.ACTSTO, F_STO.ARTSTO, F_STO.ALMSTO, F_STO.MINSTO, F_STO.MAXSTO FROM F_STO INNER JOIN F_ART ON F_STO.ARTSTO = F_ART.CODART WHERE (F_STO.ALMSTO = ? OR F_STO.ALMSTO = ? OR F_STO.ALMSTO = ? OR F_STO.ALMSTO = ?)"; # AND F_ART.NPUART = 0
            $exec = $this->con->prepare($query);
            $exec->execute([$gen, $exh, $des, $fdt]);
            $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
            if($rows){
                $res = $rows->groupBy('ARTSTO')->map(function($product) use($almacenes){
                    $min = 0;
                    $max = 0;
                    $gen = 0;
                    $exh = 0;
                    $des = 0;
                    $fdt = 0;
                    foreach($product as $stock){
                        if($stock["ALMSTO"] == $almacenes["GEN"]){
                            $gen = intval($stock["ACTSTO"]);
                            $min = intval($stock["MINSTO"]);
                            $max= intval($stock["MAXSTO"]);
                        }else if($stock["ALMSTO"] == $almacenes["EXH"]){
                            $exh = intval($stock["ACTSTO"]);
                        }else if($stock["ALMSTO"] == $almacenes["DES"]){
                            $des = intval($stock["ACTSTO"]);
                        }else{
                            $fdt = intval($stock["ACTSTO"]);
                        }
                    }
                    return [
                        "code" => trim(strtoupper(mb_convert_encoding((string)$product[0]['ARTSTO'], "UTF-8", "Windows-1252"))),
                        "gen" => $gen,
                        "exh" => $exh,
                        "des" => $des,
                        "fdt" => $fdt,
                        "stock" => $gen+$exh+$des+$fdt,
                        "min" => $min,
                        "max" => $max
                    ];
                })->values()->all();
                return $res;
            }else{
                return false;
            }
        }else{
            return false;
        }
    }

    public function getAlmacenes($_workpoint){
        switch($_workpoint){
            case 1: //CEDIS
                return ["GEN" => "GEN", "EXH" => "", "DES" => "DES", "FDT" => ""];
            case 2: //PANTACO
                return ["GEN" => "PAN", "EXH" => "", "DES" => "", "FDT" => ""];
            case 3: //SP1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => "FDT"];
            case 4: //SP2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE2", "FDT" => "FDT"];
            case 5: //CR1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE3", "FDT" => "FDT"];
            case 6: //CR2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE4", "FDT" => ""];
            case 7: //AP1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE7", "FDT" => "FDT"];
            case 8: //AP2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE6", "FDT" => "FDT"];
            case 9: //RC1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE1", "FDT" => "FDT"];
            case 10: //RC2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE8", "FDT" => "FDT"];
            case 11: //BRA1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "D10", "FDT" => "FDT"];
            case 12: //BRA2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "D11", "FDT" => "FDT"];
            case 13: //CEDISBOL
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE5", "FDT" => "FDT"];
                //return ["GEN" => "BOL", "EXH" => "", "DES" => "", "FDT" => ""];
            case 14: //SP3
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "", "FDT" => ""];
            case 15: //SP4
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "", "FDT" => ""];
            case 17: //SPC
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => ""];
            case 18: //PUE
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE3", "FDT" => "FDT"];
            case 19: //SOT
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "CUA", "FDT" => "FDT"];
        }
    }
}
