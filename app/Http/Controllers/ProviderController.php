<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class ProviderController extends Controller{
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
    
    public function getProviders(Request $request){
        $query = "SELECT CODPRO, NIFPRO, NOFPRO, NOCPRO, DOMPRO, PROPRO, TELPRO FROM F_PRO";
        $query = ($request->date && !is_null($request->date)) ? $query." WHERE FUMPRO >= #".$request->date."#" : $query;
        try{
            $exec = $this->con->prepare($query);
            $exec->execute();
            $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
            $providers = $rows->map(function($provider){
                return [
                    "id" => intval($provider['CODPRO']),
                    "rfc" => (string)$provider['NIFPRO'],
                    "name" => mb_convert_encoding((string)$provider['NOFPRO'], "UTF-8", "Windows-1252"),
                    "alias" => mb_convert_encoding((string)$provider['NOCPRO'], "UTF-8", "Windows-1252"),
                    "description" => '',
                    "adress" => json_encode([
                        'calle' => mb_convert_encoding((string)$provider['DOMPRO'], "UTF-8", "Windows-1252"),
                        'municipio' => mb_convert_encoding((string)$provider['PROPRO'], "UTF-8", "Windows-1252")
                    ]),
                    "phone" => (string)$provider['TELPRO']
                ];
            })->toArray();
            return response()->json($providers);
        }catch(\PDOException $e){
            return response()->json(["message" => "Algo salio mal no se han podido obtener los datos"]);
        }
    }

    public function getRawProviders(Request $request){
        $query = "SELECT * FROM F_PRO";
        $query = ($request->date && !is_null($request->date)) ? $query." WHERE FUMPRO >= #".$request->date."#" : $query;
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

    public function syncProviders(Request $request){
        if($request->providers){
            $keys = array_keys($request->providers[0]);
            $update = "";
            $values = "";
            $cols = "";
            foreach($keys as $i => $key){
                if($i == 0){
                    $update = $key." = ?";
                    $cols = " ".$key;
                    $values = " ?";
                }else{
                    $update = $update.", ".$key." = ?";
                    $values = $values.", ?";
                    $cols = $cols.", ".$key;
                }
            }
            $query_select = "SELECT count(*) FROM F_PRO WHERE CODPRO = ?";
            $exec_select = $this->con->prepare($query_select);
        
            $query_update = "UPDATE F_PRO SET ".$update." WHERE CODPRO = ?";
            $exec_update = $this->con->prepare($query_update);
        
            $query_insert = "INSERT INTO F_PRO (".$cols.") VALUES(".$values.")";
            $exec_insert = $this->con->prepare($query_insert);
        
            $response = [];
            foreach($request->providers as $key => $provider){
                $exec_select->execute([$provider["CODPRO"]]);
                $count = intval($exec_select->fetch(\PDO::FETCH_ASSOC)['Expr1000']);
                if($count == 1){
                    $toUpdate = array_values($provider);
                    $toUpdate[] = $provider["CODPRO"];
                    $result = $exec_update->execute($toUpdate);
                    if($result){
                        $accion = "Actualización";
                    }else{
                        $accion = "No se a podido actualizar";
                    }
                }else if($count == 0){
                    $result = $exec_insert->execute(array_values($provider));
                    if($result){
                        $accion = "Creado";
                    }else{
                        $accion = "No se ha podido crear";
                    }
                }else{
                    $accion = "Duplicado";
                }
                $response[] = ["# Proveedor" => $provider["CODPRO"], "Proveedor" => $provider["NOFPRO"], "Acción" => $accion];
            }
            return $response;
        }else{
          return response()->json(["msg" => "Sin proveedores por actualizar"]);
        }
      }
}
