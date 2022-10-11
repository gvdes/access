<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class ProviderOrderController extends Controller{
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

    public function createHeader(Request $request){
        /* HEADER F_PRO */
        $query = "INSERT INTO F_PRO(TIPPRO, CODPRO, FECPCL, PROPPR, ESTPPR, ALMPPR, PNOPPR, NET1PCL, TOTPCL, HORPCL) VALUES(?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->con->prepare($query);
        $date = date("Y/m/d h:m");
        $code = $this->nextOrder($request->num_cash);
        $result = $exec->execute([$request->num_cash, $code, $date, 900, $request->_client, $request->name, $request->total, 0, 0 , $request->total, $date]);
        if($result){
            $res = $this->createBody($request->num_cash, $code, $request->products);
            return $res;
            if($res){
                return response()->json(["msg" => "creado"]);
            }else{
                return response()->json(["msg" => "no se pudo crear el cuerpo"]);
            }
        }else{
            return response()->json(["msg" => "No se pudo crear"]);
        }
    }

    public function createBody($cash, $code, $body){
        /* BODY F_LCL */
        /* TIVPCL */
        $values = "";
        $toInsert = [];
        foreach($body as $key => $row){
            $toInsert = array_merge($toInsert, [$cash, $code, $key+1, $row["code"], $row["description"], $row["ordered"]["units"], $row["ordered"]["price"], $row["cost"], $row["ordered"]["total"], $row["ordered"]["total"]]);
            $values = $key == 0 ? "(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)" : $values.", (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        }
        $query = "INSERT INTO F_LPC(TIPLPC, CODLPC, POSLPC, ARTLPC, DESLPC, CANLPC, PRELPC, COSLPC, TOTLPC, PENLPC) VALUES".$values;
        $exec = $this->con->prepare($query);
        $result = $exec->execute($toInsert);
        return response()->json(["query" =>$exec, "toInsert" => $toInsert, "rest" => $result]);
        if($result){
            return true;
        }else{
            return false;
        }
    }

    public function nextOrder($serie){
        $query = "SELECT TOP 1 CODPRO FROM F_PRO WHERE TIPPRO = ? ORDER BY CODPRO DESC";
        $exec = $this->con->prepare($query);
        $exec->execute([$serie]); 
        $row = $exec->fetch(\PDO::FETCH_ASSOC);
        if($row){
            return intval($row["CODPRO"])+1;
        }else{
            return 1;
        }
    }

    public function getAll(){
        $query = "SELECT TIPPPR, CODPPR, REFPPR, FECPPR, PROPPR, ESTPPR, PNOPPR, TOTPPR, PENPPR FROM F_PPR";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $query_body = "SELECT ARTLPP, CANLPP, PRELPP, TOTLPP FROM F_LPP WHERE TIPLPP = ? AND CODLPP = ?";
        $exec_body = $this->con->prepare($query_body);
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPPPR'], $row['CODPPR']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLPP'], "UTF-8", "Windows-1252")),
                    "amount" => floatval($row['CANLPP']),
                    "price" => floatval($row['PRELPP']),
                    "total" => floatval($row['TOTLPP']),
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

            return [
                "serie" => intval($row["TIPPPR"]),
                "code" => intval($row["CODPPR"]),
                "ref" => mb_convert_encoding((string)$row['REFPPR'], "UTF-8", "Windows-1252"),
                "_provider" => intval($row['PROPPR']),
                "_status" => intval($row["ESTPPR"]),
                "description" => mb_convert_encoding((string)$row['PNOPPR'], "UTF-8", "Windows-1252"),
                "total" => floatval($row["TOTPPR"]),
                "created_at" => $row["FECPPR"],
                "received_at" => $row["PENPPR"],
                "body" => $body
            ];
        });
        return response()->json($rows);
    }
}
