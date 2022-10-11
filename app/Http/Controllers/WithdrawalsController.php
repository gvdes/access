<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class WithdrawalsController extends Controller{
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

    public function getAll(){
        $query = "SELECT CODRET, CAJRET, FECRET, HORRET, CONRET, IMPRET, PRORET FROM F_RET WHERE PRORET > 0 ORDER BY CODRET";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
            $hour = $row["HORRET"] ? : "00:00";
            $date = explode(" ",$row["FECRET"])[0]." ".$hour;
            return [
                "code" => intval($row["CODRET"]),
                "_cash" => intval($row["CAJRET"]),
                "description" => mb_convert_encoding((string)$row["CONRET"], "UTF-8", "Windows-1252"),
                "total" => floatval($row["IMPRET"]),
                "_provider" => intval($row["PRORET"]),
                "created_at" => $date,
            ];
        });
        return response()->json($rows);
    }

    public function getLatest(Request $request){
        $query = "SELECT CODRET, CAJRET, FECRET, HORRET, CONRET, IMPRET, PRORET FROM F_RET WHERE PRORET > 0 AND CODRET > ? ORDER BY CODRET";
        $exec = $this->con->prepare($query);
        $exec->execute([$request->code]);
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
            $hour = $row["HORRET"] ? : "00:00";
            $date = explode(" ",$row["FECRET"])[0]." ".$hour;
            return [
                "code" => intval($row["CODRET"]),
                "_cash" => intval($row["CAJRET"]),
                "description" => mb_convert_encoding((string)$row["CONRET"], "UTF-8", "Windows-1252"),
                "total" => floatval($row["IMPRET"]),
                "_provider" => intval($row["PRORET"]),
                "created_at" => $date,
            ];
        });
        return response()->json($rows);
    }
}
