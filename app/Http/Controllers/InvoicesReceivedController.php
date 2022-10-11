<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class InvoicesReceivedController extends Controller{
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
        $query = "SELECT TIPFRE, CODFRE, REFFRE, FECFRE, PROFRE, PNOFRE, TOTFRE FROM F_FRE WHERE FECFRE >= #2021-03-02#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $query_body = "SELECT ARTLFR, CANLFR, PRELFR, TOTLFR, DTPLFR, DCOLFR FROM F_LFR WHERE TIPLFR = ? AND CODLFR = ?";
        $exec_body = $this->con->prepare($query_body);
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFRE'], $row['CODFRE']]);
            $data = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC));
            $body = $data->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLFR'], "UTF-8", "Windows-1252")),
                    "amount" => floatval($row['CANLFR']),
                    "price" => floatval($row['PRELFR']),
                    "total" => floatval($row['TOTLFR'])
                ];
            })->filter(function($body){
                return $body['amount']!=0;
            })->groupBy('_product')->map(function($group){
                $body = [
                    "_product" => strtoupper($group[0]['_product']),
                    "amount" => 0,
                    "price" => 0,
                    "total" => 0,
                ];
                foreach($group as $el){
                    $amount = $body["amount"] + $el["amount"];
                    $total = $body["total"] + $el["total"];
                    $price = $total / $amount;
                    $body['amount'] = $amount;
                    $body['price'] = $price;
                    $body['total'] = $total;
                }
                return $body;
            })->values()->all();
            $_serie_order = count($data) > 0 ? $data[0]["DTPLFR"] : "";
            $_code_order = count($data) > 0 ? $data[0]["DCOLFR"] : "";

            return [
                "serie" => $row["TIPFRE"],
                "code" => $row["CODFRE"],
                "ref" => $row["REFFRE"],
                "_provider" =>  $row["PROFRE"],
                "description" => $row["PNOFRE"],
                "total" => $row["TOTFRE"],
                "created_at" => $row["FECFRE"],
                "body" => $body,
                "_serie_order" => $_serie_order,
                "_code_order" => $_code_order,
            ];
        });
        return response()->json($rows);
    }

    public function getNew(Request $request){
        $query = "SELECT TIPFRE, CODFRE, REFFRE, FECFRE, PROFRE, PNOFRE, TOTFRE FROM F_FRE WHERE";
        $last_data = $request->last_data;
        foreach($last_data as  $key => $row){
            if($key > 0){
                $query = $query."OR";
            }
            $query = $query." (TIPFRE = '".$row['serie']."' AND CODFRE > ".$row["code"].") ";
        }
        $exec = $this->con->prepare($query);
        $exec->execute();
        $query_body = "SELECT ARTLFR, CANLFR, PRELFR, TOTLFR, DTPLFR, DCOLFR FROM F_LFR WHERE TIPLFR = ? AND CODLFR = ?";
        $exec_body = $this->con->prepare($query_body);
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFRE'], $row['CODFRE']]);
            $data = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC));
            $body = $data->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLFR'], "UTF-8", "Windows-1252")),
                    "amount" => floatval($row['CANLFR']),
                    "price" => floatval($row['PRELFR']),
                    "total" => floatval($row['TOTLFR'])
                ];
            })->filter(function($body){
                return $body['amount']!=0;
            })->groupBy('_product')->map(function($group){
                $body = [
                    "_product" => strtoupper($group[0]['_product']),
                    "amount" => 0,
                    "price" => 0,
                    "total" => 0,
                ];
                foreach($group as $el){
                    $amount = $body["amount"] + $el["amount"];
                    $total = $body["total"] + $el["total"];
                    $price = $total / $amount;
                    $body['amount'] = $amount;
                    $body['price'] = $price;
                    $body['total'] = $total;
                }
                return $body;
            })->values()->all();
            $_serie_order = count($data) > 0 ? $data[0]["DTPLFR"] : "";
            $_code_order = count($data) > 0 ? $data[0]["DCOLFR"] : "";

            return [
                "serie" => $row["TIPFRE"],
                "code" => $row["CODFRE"],
                "ref" => $row["REFFRE"],
                "_provider" =>  $row["PROFRE"],
                "description" => $row["PNOFRE"],
                "total" => $row["TOTFRE"],
                "created_at" => $row["FECFRE"],
                "body" => $body,
                "_serie_order" => $_serie_order,
                "_code_order" => $_code_order,
            ];
        });
        return response()->json($rows);
    }
}
