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
        COB.TERMINAL,
        COB.TICKET,
        F_FAC.CNOFAC AS CLIENTE,
        Format(F_FAC.FECFAC, 'Short Date') as FECHA,
        COB.EFECTIVO,
        COB.TARJETAS,
        COB.TRANSFERENCIAS,
        COB.CREDITOS,
        COB.VALES
        FROM F_FAC
        INNER JOIN (
        SELECT
        T_TER.DESTER AS TERMINAL,
        F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
        F_LCO.FECLCO AS FECHA,
        MAX(IIF(F_LCO.FPALCO = 'EFE','OK' ,'')) AS EFECTIVO,
        MAX(IIF((F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA'),F_LCO.IMPLCO ,'')) AS TARJETAS,
        MAX(IIF((F_LCO.FPALCO = 'TDB' OR  F_LCO.FPALCO = 'TDA' OR F_LCO.FPALCO = 'TDS'), F_LCO.IMPLCO,'')) AS TRANSFERENCIAS,
        MAX(IIF(F_LCO.FPALCO = 'C30', F_LCO.IMPLCO,'')) AS CREDITOS,
        MAX(IIF(F_LCO.FPALCO = '[V]', F_LCO.IMPLCO,'')) AS VALES
        FROM F_LCO
        INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO
        WHERE FECLCO =  DATE()
        GROUP BY
        T_TER.DESTER,
        TFALCO&'-'&CFALCO,
        FECLCO ) AS COB  ON COB.TICKET = F_FAC.TIPFAC&'-'&F_FAC.CODFAC";
        $exec = $this->con->prepare($selpagtar);
        $exec->execute();
        $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);

        $res = [
            "terminales"=>$terminales,
            "formaspagos"=>$fpas
        ];
        return response()->json( mb_convert_encoding($res,'UTF-8'),200);
    }

}
