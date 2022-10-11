<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class AccountingController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(){
        try{
            $access = env('ACCESS_FILE_2');
            $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
            $this->con = $db;
        }catch(PDOException $e){
            return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
        }
    }

    public function getAll(){
        $query = "SELECT * FROM F_APU";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->filter(function($row){
            return $row["D-HAPU"] == "D";
        })->map(function($row){
            return [
                "originated_at" => $row["FECAPU"],
                "created_at" => $row["FCRAPU"],
                "asiento" => intval($row["ASIAPU"]),
                "orden" => intval($row["ORDAPU"]),
                "description" => mb_convert_encoding((string)$row["CONAPU"], "UTF-8", "Windows-1252"),
                "total" => floatval($row["IMPAPU"]),
                "_concept" => intval($row["CUEAPU"])
            ];
        })->values();
        return response()->json($rows);
    }

    public function updated(Request $request){
        $query = "SELECT * FROM F_APU WHERE FCRAPU >= #".$request->date."#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->filter(function($row){
            return $row["D-HAPU"] == "D";
        })->map(function($row){
            return [
                "originated_at" => $row["FECAPU"],
                "created_at" => $row["FCRAPU"],
                "asiento" => intval($row["ASIAPU"]),
                "orden" => intval($row["ORDAPU"]),
                "description" => mb_convert_encoding((string)$row["CONAPU"], "UTF-8", "Windows-1252"),
                "total" => floatval($row["IMPAPU"]),
                "_concept" => intval($row["CUEAPU"])
            ];
        })->values();
        return response()->json($rows);
    }

    public function concepts(){
        $query = "SELECT CODMAE, NOMMAE, NEXMAE FROM F_MAE";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
            return [
                "id" => $row["CODMAE"],
                "alias" => mb_convert_encoding((string)$row["NOMMAE"], "UTF-8", "Windows-1252"),
                "name" => mb_convert_encoding((string)$row["NEXMAE"], "UTF-8", "Windows-1252")
            ];
        });
        return response()->json($rows);
    }
}
