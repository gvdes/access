<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class UserController extends Controller{
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

    public function getUsers(){
        $query = "SELECT CODAGE, FALAGE, NOMAGE FROM F_AGE";
        $exec = $this->con->prepare($query);
        $exec->execute();

        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $result = $rows->map(function($user){ //Seller or agent
            return [
                "id" => $user["CODAGE"],
                "name" => mb_convert_encoding((string)$user["NOMAGE"], "UTF-8", "Windows-1252"),
                "created_at" => $user["FALAGE"]
            ];
        });
        return response()->json($result);
    }

    public function getRawUsers(){
        $query = "SELECT * FROM F_AGE";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
        foreach($rows as $key_row => $row){
            foreach($row as $key => $col){
                $row[$key] = mb_convert_encoding($col, "UTF-8", "Windows-1252");
            }
            $rows[$key_row] = $row;
        }
        return $rows;
    }

    public function syncUsers(Request $request){
        if($request->users){
            $keys = array_keys($request->users[0]);
            $toUpdate = "";
            $values = "";
            $cols = "";
            foreach($keys as $i => $key){
                if($i == 0){
                    $toUpdate = $key." = ?";
                    $cols = " ".$key;
                    $values = " ?";
                }else{
                    $toUpdate = $toUpdate.", ".$key." = ?";
                    $values = $values.", ?";
                    $cols = $cols.", ".$key;
                }
            }
            $query_select = "SELECT count(*) FROM F_AGE WHERE CODAGE = ?";
            $exec_select = $this->con->prepare($query_select);
        
            $query_update = "UPDATE F_AGE SET ".$toUpdate." WHERE CODAGE = ?";
            $exec_update = $this->con->prepare($query_update);
        
            $query_insert = "INSERT INTO F_AGE (".$cols.") VALUES(".$values.")";
            $exec_insert = $this->con->prepare($query_insert);
        
            $response = [];
            foreach($request->users as $key => $user){
                $exec_select->execute([$user["CODAGE"]]);
                $count = intval($exec_select->fetch(\PDO::FETCH_ASSOC)['Expr1000']);
                if($count == 1){
                    $toUpdate = array_values($user);
                    $toUpdate[] = $user["CODAGE"];
                    $result = $exec_update->execute($toUpdate);
                    if($result){
                        $accion = "Actualización";
                    }else{
                        $accion = "No se a podido actualizar";
                    }
                }else if($count == 0){
                    $result = $exec_insert->execute($array_values($user));
                    if($result){
                        $accion = "Creado";
                    }else{
                        $accion = "No se ha podido crear";
                    }
                }else{
                    $accion = "Duplicado";
                }
                $response[] = ["# Agente" => $user["CODAGE"], "Agente" => $user["NOMAGE"], "Acción" => $accion];
            }
            return $response;
        }else{
            return response()->json(["msg" => "Sin agentes por actualizar"]);
        }
    }
}