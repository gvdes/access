<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class SalesController extends Controller{
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

    public function getVentasX(){
        $query = "SELECT TIPALB, CODALB, FECALB, CLIALB, CNOALB, FOPALB, HORALB, TOTALB, AGEALB FROM F_ALB";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLAL, CANLAL, PRELAL, TOTLAL, COSLAL FROM F_LAL WHERE TIPLAL = ? AND CODLAL = ?";
        $exec_body = $this->con->prepare($query_body);
        $sales = $rows->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPALB'], $row['CODALB']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => mb_convert_encoding((string)$row['ARTLAL'], "UTF-8", "Windows-1252"),
                    "amount" => floatval($row['CANLAL']),
                    "price" => floatval($row['PRELAL']),
                    "total" => floatval($row['TOTLAL']),
                    "costo" => floatval(explode(" ",$row['COSLAL'])[0])
                ];
            })->filter(function($body){
                return $body['amount']!=0;
            })->groupBy('_product')->map(function($group){
                $body = [
                    "_product" => strtoupper($group[0]['_product']),
                    "amount" => 0,
                    "price" => 0,
                    "total" => 0,
                    "costo" => $group[0]['costo']
                ];
                foreach($group as $el){
                    $body['amount'] = $body["amount"] + $el["amount"];
                    $body['price'] = $body["price"] + $el["price"];
                    $body['total'] = $body["total"] + $el["total"];
                }
                return $body;
            })->values()->all();
            $date = explode(" ",$row['FECALB'])[0]." ".explode(" ",$row['HORALB'])[1];
            $_paid_by = 1;
            switch($row['FOPALB']){
                case "EFE":
                    $_paid_by = 1;
                break;
                case "TCD":
                    $_paid_by = 2;
                break;
                case "DEP":
                    $_paid_by = 3;
                break;
                case "TRA":
                    $_paid_by = 4;
                break;
                case "C30":
                    $_paid_by = 5;
                break;
                case "CHE":
                    $_paid_by = 6;
                break;
            }
            return [
                "_cash" => intval($row['TIPALB']),
                "num_ticket" => intval($row['CODALB']),
                "total" => floatval($row['TOTALB']),
                "created_at" => $date,
                "TEST" => $row['FECALB'],
                "_client" => intval($row['CLIALB']),
                "name" => mb_convert_encoding((string)$row['CNOALB'], "UTF-8", "Windows-1252"),
                "_seller" => intval($row["AGEALB"]),
                "_paid_by" => $_paid_by,
                "body" => $body
            ];
        });

        return response()->json($sales);
    }

    public function getVentas(){
        $query = "SELECT TIPFAC, CODFAC, REFFAC, FECFAC, CLIFAC, CNOFAC, FOPFAC, HORFAC, TOTFAC, AGEFAC FROM F_FAC" /*  WHERE TIPFAC = '8' */;
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFA, CANLFA, PRELFA, TOTLFA, COSLFA FROM F_LFA WHERE TIPLFA = ? AND CODLFA = ?";
        $exec_body = $this->con->prepare($query_body);
        $sales = $rows->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFAC'], $row['CODFAC']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => mb_convert_encoding((string)$row['ARTLFA'], "UTF-8", "Windows-1252"),
                    "amount" => floatval($row['CANLFA']),
                    "price" => floatval($row['PRELFA']),
                    "total" => floatval($row['TOTLFA']),
                    "costo" => floatval(explode(" ",$row['COSLFA'])[0])
                ];
            })->filter(function($body){
                return $body['amount']!=0;
            })->groupBy('_product')->map(function($group){
                $body = [
                    "_product" => strtoupper($group[0]['_product']),
                    "amount" => 0,
                    "price" => 0,
                    "total" => 0,
                    "costo" => $group[0]['costo']
                ];
                foreach($group as $el){
                    $body['amount'] = $body["amount"] + $el["amount"];
                    $body['price'] = $body["price"] + $el["price"];
                    $body['total'] = $body["total"] + $el["total"];
                }
                return $body;
            })->values()->all();
            $date = explode(" ",$row['FECFAC'])[0]." ".explode(" ",$row['HORFAC'])[1];
            $_paid_by = 1;
            switch($row["FOPFAC"]){
                case "EFE":
                  $_paid_by = 1;
                break;
                case "TCD":
                  $_paid_by = 2;
                break;
                case "DEP":
                  $_paid_by = 3;
                break;
                case "TRA":
                  $_paid_by = 4;
                break;
                case "C30":
                  $_paid_by = 5;
                break;
                case "CHE":
                  $_paid_by = 6;
                break;
                case "TBA":
                  $_paid_by = 7;
                break;
                case "TDA":
                  $_paid_by = 8;
                break;
                case "TDB":
                  $_paid_by = 9;
                break;
                case "TDS":
                  $_paid_by = 10;
                break;
                case "TSA":
                  $_paid_by = 11;
                break;
                case "TSC":
                  $_paid_by = 12;
                break;
              }
            return [
                "_cash" => intval($row['TIPFAC']),
                "ref" => mb_convert_encoding((string)$row['REFFAC'], "UTF-8", "Windows-1252"),
                "num_ticket" => intval($row['CODFAC']),
                "total" => floatval($row['TOTFAC']),
                "created_at" => $date,
                "_client" => intval($row['CLIFAC']),
                "name" => mb_convert_encoding((string)$row['CNOFAC'], "UTF-8", "Windows-1252"),
                "_seller" => intval($row["AGEFAC"]),
                "_paid_by" => $_paid_by,
                "body" => $body
            ];
        })/* ->filter(function($sale){
            $key = array_search($sale["_client"], [0, 1,2,3,4,5,6,7,73,122,248,389,551, 60, 874]);
            if($key === 0 || $key >0 ){
                return false;
            }else{
                if(str_contains(strtoupper($sale['ref']), 'CREDITO')){
                    return false;
                }else{
                    return true;
                }
                return true;
            }
        })->values()->all() */;
        return response()->json($sales);
    }

    public function getNewVentas(Request $request){
        $clause = " where";
        $i = 0;
        $cajas = count($request->cash);
        foreach($request->cash as $cash){
            $i++;
            $clause = $clause." (TIPALB = '".$cash['_cash']."' AND CODALB > ".$cash['num_ticket'].")";
            if($i<$cajas){
                $clause = $clause." OR";
            }
        }
        if($this->_workpoint == 1){
            $clause = $clause. "AND TIPALB = 8";
        }
        $query = "SELECT TIPALB, CODALB, REFALB, FECALB, CLIALB, CNOALB, FOPALB, HORALB, TOTALB, AGEALB FROM F_ALB".$clause;
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLAL, CANLAL, PRELAL, TOTLAL, COSLAL FROM F_LAL WHERE TIPLAL = ? AND CODLAL = ?";
        $exec_body = $this->con->prepare($query_body);
        $sales = $rows->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPALB'], $row['CODALB']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLAL'], "UTF-8", "Windows-1252")),
                    "amount" => floatval($row['CANLAL']),
                    "price" => floatval($row['PRELAL']),
                    "total" => floatval($row['TOTLAL']),
                    "costo" => floatval(explode(" ",$row['COSLAL'])[0])
                ];
            })->filter(function($body){
                return $body['amount']!=0;
            })->groupBy('_product')->map(function($group){
                $body = [
                    "_product" => strtoupper($group[0]['_product']),
                    "amount" => 0,
                    "price" => 0,
                    "total" => 0,
                    "costo" => $group[0]['costo']
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
            $date = explode(" ",$row['FECALB'])[0]." ".explode(" ",$row['HORALB'])[1];
            $_paid_by = 1;
            switch($row["FOPALB"]){
                case "EFE":
                  $_paid_by = 1;
                break;
                case "TCD":
                  $_paid_by = 2;
                break;
                case "DEP":
                  $_paid_by = 3;
                break;
                case "TRA":
                  $_paid_by = 4;
                break;
                case "C30":
                  $_paid_by = 5;
                break;
                case "CHE":
                  $_paid_by = 6;
                break;
                case "TBA":
                  $_paid_by = 7;
                break;
                case "TDA":
                  $_paid_by = 8;
                break;
                case "TDB":
                  $_paid_by = 9;
                break;
                case "TDS":
                  $_paid_by = 10;
                break;
                case "TSA":
                  $_paid_by = 11;
                break;
                case "TSC":
                  $_paid_by = 12;
                break;
              }
            return [
                "_cash" => intval($row['TIPALB']),
                "ref" => mb_convert_encoding((string)$row['REFALB'], "UTF-8", "Windows-1252"),
                "num_ticket" => intval($row['CODALB']),
                "total" => floatval($row['TOTALB']),
                "created_at" => $date,
                "_client" => intval($row['CLIALB']),
                "name" => mb_convert_encoding((string)$row['CNOALB'], "UTF-8", "Windows-1252"),
                "_seller" => intval($row["AGEALB"]),
                "_paid_by" => $_paid_by,
                "body" => $body
            ];
        })->filter(function($sale){
          if($this->_workpoint == 1){
            $key = array_search($sale["_client"], range(7,35));
            if($key === 0 || $key > 0 ){
              return false;
            }else{
              if(str_contains(strtoupper($sale['ref']), 'CREDITO')){
                return false;
              }else{
                return true;
              }
              return true;
            }
          }else{
            return true;
          }
        })->values()->all();
        return response()->json($sales);
    }

    public function getNewVentasTime(Request $request){
      $clause = " where";
      $i = 0;
      $cajas = $request->cash;
      $_cash_array = array_column($cajas, "_cash");
      foreach($request->cash as $cash){
        $last_date = $request->last_date;
        $i++;
        $clause = $clause." (TIPALB = '".$cash['_cash']."' AND FECALB >= #".$cash['date']."#)";
        if($i<count($cajas)){
          $clause = $clause." OR";
        }
      }
      $query = "SELECT TIPALB, CODALB, REFALB, FECALB, CLIALB, CNOALB, FOPALB, HORALB, TOTALB, AGEALB FROM F_ALB".$clause;
      $exec = $this->con->prepare($query);
      $exec->execute();
      $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
      $query_body = "SELECT ARTLAL, CANLAL, PRELAL, TOTLAL, COSLAL FROM F_LAL WHERE TIPLAL = ? AND CODLAL = ?";
      $exec_body = $this->con->prepare($query_body);
      $query_paid_methods = "SELECT IMPLCO, FPALCO FROM F_LCO WHERE TFALCO = ? AND CFALCO = ?";
      $exec_paid_methods = $this->con->prepare($query_paid_methods);
      $sales = $rows->filter(function($row) use($cajas, $_cash_array){
        $date = explode(" ",$row['FECALB'])[0]." ".explode(" ",$row['HORALB'])[1];
        $index = array_search($row["TIPALB"], $_cash_array);
        return $cajas[$index]["last_date"]<$date;
      })->map(function($row) use($exec_body, $exec_paid_methods){
        $exec_body->execute([$row['TIPALB'], $row['CODALB']]);
        $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
          return [
            "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLAL'], "UTF-8", "Windows-1252")),
            "amount" => floatval($row['CANLAL']),
            "price" => floatval($row['PRELAL']),
            "total" => floatval($row['TOTLAL']),
            "costo" => floatval(explode(" ",$row['COSLAL'])[0])
          ];
        })->filter(function($body){
          return $body['amount']!=0;
        })->groupBy('_product')->map(function($group){
          $body = [
            "_product" => strtoupper($group[0]['_product']),
            "amount" => 0,
            "price" => 0,
            "total" => 0,
            "costo" => $group[0]['costo']
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
        $date = explode(" ",$row['FECALB'])[0]." ".explode(" ",$row['HORALB'])[1];
        $_paid_by = $this->getPaidMethodType($row['FOPALB']);
        $exec_paid_methods->execute([$row['TIPALB'], $row['CODALB']]);
        $paid_methods_body = collect($exec_paid_methods->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
          $_paid_by = $this->getPaidMethodType($row['FPALCO']);
          return [
            "_paid_by" => $_paid_by,
            "total" => floatval($row["IMPLCO"])
          ];
        });
        return [
          "_cash" => intval($row['TIPALB']),
          "ref" => mb_convert_encoding((string)$row['REFALB'], "UTF-8", "Windows-1252"),
          "num_ticket" => intval($row['CODALB']),
          "total" => floatval($row['TOTALB']),
          "created_at" => $date,
          "_client" => intval($row['CLIALB']),
          "name" => mb_convert_encoding((string)$row['CNOALB'], "UTF-8", "Windows-1252"),
          "_seller" => intval($row["AGEALB"]),
          "_paid_by" => $_paid_by,
          "body" => $body,
          "paidMethod" => $paid_methods_body
        ];
      })->filter(function($sale){
        if($this->_workpoint == 1){
          $key = array_search(intval($sale["_client"]), range(7,35));
          if(($key === 0 || $key > 0) || intval($sale["_cash"]) == 9){
            return false;
          }else{
            if(str_contains(strtoupper($sale['ref']), 'CREDITO')){
              return false;
            }else{
              return true;
            }
            return true;
          }
        }else{
          return true;
        }
      })->values()->all();
      return response()->json($sales);
  }

    public function getTicket(Request $request){
      $folio = $request->folio;
        $caja = $request->caja;
        $query = "SELECT TIPLFA as caja, CODLFA as folio, ARTLFA as code, CANLFA as req FROM F_LFA WHERE CODLFA = ? AND TIPLFA = ?";
        $exec = $this->con->prepare($query);
        $exec->execute([$folio, $caja]);
        $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
        if(count($rows)==0){
            return response()->json(["msg" => "El folio no existe o no tiene productos"]);
        }
        return response()->json(["products" => $rows]);
    }

    public function getPaidMethodType($paidBy){
      switch($paidBy){
        case "EFE":
          return 1;
        break;
        case "TCD":
          return 2;
        break;
        case "DEP":
          return 3;
        break;
        case "TRA":
          return 4;
        break;
        case "C30":
          return 5;
        break;
        case "CHE":
          return 6;
        break;
        case "TBA":
          return 7;
        break;
        case "TDA":
          return 8;
        break;
        case "TDB":
          return 9;
        break;
        case "TDS":
          return 10;
        break;
        case "TSA":
          return 11;
        break;
        case "TSC":
          return 12;
        break;
        case "[V]":
          return 13;
        break;
        default:
          return 14;
      }
    }

    public function getSellers(){
      $query = "SELECT CODAGE, FALAGE, NOMAGE FROM F_AGE";
      $exec = $this->con->prepare($query);
      $exec->execute();
      $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
        return [
          "id" => $row["CODAGE"],
          "name" => mb_convert_encoding((string)$row["NOMAGE"], "UTF-8", "Windows-1252"),
          "created_at" => $row["FALAGE"]
        ];
      });
      return response()->json($rows);
    }
}
