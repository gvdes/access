<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller{
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

    public function getCash(){
        $select = "SELECT * FROM T_TER";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $selpagtar = "SELECT
        T_TER.DESTER AS CAJA,
        F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
        F_FAC.CNOFAC AS CLIENTE,
        F_LCO.FECLCO AS FECHA,
        MAX(IIF(F_LCO.LINLCO = 1, F_LCO.FPALCO&'-'&F_LCO.CPTLCO,'')) AS 1RAFPA,
        MAX(IIF(F_LCO.LINLCO = 2, F_LCO.FPALCO&'-'&F_LCO.CPTLCO,'')) AS 2DAFPA,
        MAX(IIF(F_LCO.LINLCO = 3, F_LCO.FPALCO&'-'&F_LCO.CPTLCO,'')) AS 3RAFPA
        FROM ((F_FAC
        INNER JOIN F_LCO ON  F_LCO.TFALCO&'-'&F_LCO.CFALCO = F_FAC.TIPFAC&'-'&F_FAC.CODFAC )
        INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO)
        WHERE F_FAC.FECFAC =  DATE()
        GROUP BY
        TFALCO&'-'&CFALCO,
        FECLCO,
        F_FAC.CNOFAC,
        T_TER.DESTER
        ;";
        $exec = $this->con->prepare($selpagtar);
        $exec->execute();
        $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);

        $res = [
            "terminales"=>$terminales,
            "formaspagos"=>$fpas
        ];
        return response()->json($res,200);
    }

}
