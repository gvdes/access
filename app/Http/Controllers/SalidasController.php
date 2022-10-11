<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class SalidasController extends Controller{
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

    public function getSalidas(){
    //     $clientes_tiendas = [1,2,3,4,5,6,7,73,122,248,389,551,60,967,968, 980];
    //     $i = 0;
    //     $clientes = count($clientes_tiendas);
    //     /* $clause = " WHERE"; */
    //     $clause = " ";
    //     foreach($clientes_tiendas as $cliente){
    //         $i++;
    //         $clause = $clause. " CLIFAC = ".$cliente;
    //         if($i<$clientes){
    //             $clause = $clause. " OR";
    //         }
    //     }

        $query = "SELECT TIPFAC, CODFAC, REFFAC, FECFAC, CLIFAC, CNOFAC, FOPFAC, HORFAC, TOTFAC, AGEFAC FROM F_FAC WHERE CLIFAC IN (7,8,9,10,11,12,13,14,15,16,17,18,19,20,21) AND FECFAC > #2021/01/01#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFA, CANLFA, PRELFA, TOTLFA, COSLFA FROM F_LFA WHERE TIPLFA = ? AND CODLFA = ?";
        $exec_body = $this->con->prepare($query_body);
        $salidas = $rows->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFAC'], $row['CODFAC']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLFA'], "UTF-8", "Windows-1252")),
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
                    $amount = $body["amount"] + $el["amount"];
                    $total = $body["total"] + $el["total"];
                    $price = $total / $amount;
                    $body['amount'] = $amount;
                    $body['price'] = $price;
                    $body['total'] = $total;
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
            $ref = mb_convert_encoding((string)$row['REFFAC'], "UTF-8", "Windows-1252");
            $split = explode(" ", $ref);
            $_requisition = $split[0];
            $_requisition= "";
            foreach($split as $str){
                $split_ = explode("P-", $str);
                if(count($split_)>1){
                    $str = $split_[1];
                }
                if(ctype_digit($str)){
                    $_requisition = $str;
                    break;
                }
            }
            return [
                "serie" => intval($row['TIPFAC']),
                "ref" => $ref,
                "num_ticket" => intval($row['CODFAC']),
                "total" => floatval($row['TOTFAC']),
                "created_at" => $date,
                "_workpoint_from" => 1,
                "_workpoint_to" => $this->getStore(intval($row['CLIFAC'])),
                "name" => mb_convert_encoding((string)$row['CNOFAC'], "UTF-8", "Windows-1252"),
                "_requisition" => $_requisition,
                "body" => $body
            ];

        })->filter(function($sale){
            return count($sale['body'])>0;
        })->values();
        return $salidas;
    }

    public function getNewSalidasFolio(Request $request){
        $clause = " where";
        $i = 0;
        $cajas = count($request->cash);
        foreach($request->cash as $cash){
            $i++;
            $clause = $clause." (TIPFAC = '".$cash['_cash']."' AND CODFAC > ".$cash['num_ticket'].")";
            if($i<$cajas){
                $clause = $clause." OR";
            }
        }

        $clientes_tiendas = [7,8,9,10,11,12,13,14,15,16,17,18,19,20,21];
        $i = 0;
        $clientes = count($clientes_tiendas);
        $clause = $clause." AND (";
        foreach($clientes_tiendas as $cliente){
            $i++;
            $clause = $clause. "CLIFAC = ".$cliente."";
            if($i<$clientes){
                $clause = $clause. " OR ";
            }
        }
        $clause = $clause.")";

        $query = "SELECT TIPFAC, CODFAC, REFFAC, FECFAC, CLIFAC, CNOFAC, FOPFAC, HORFAC, TOTFAC, AGEFAC FROM F_FAC".$clause;
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFA, CANLFA, PRELFA, TOTLFA, COSLFA FROM F_LFA WHERE TIPLFA = ? AND CODLFA = ?";
        $exec_body = $this->con->prepare($query_body);
        $salidas = $rows->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFAC'], $row['CODFAC']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLFA'], "UTF-8", "Windows-1252")),
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
                    $amount = $body["amount"] + $el["amount"];
                    $total = $body["total"] + $el["total"];
                    $price = $total / $amount;

                    $body['amount'] = $amount;
                    $body['price'] = $price;
                    $body['total'] = $total;
                }
                return $body;
            })->values()->all();
            $date = explode(" ",$row['FECFAC'])[0]." ".explode(" ",$row['HORFAC'])[1];
            $_paid_by = 1;
            switch($row['FOPFAC']){
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
            $ref = mb_convert_encoding((string)$row['REFFAC'], "UTF-8", "Windows-1252");
            $split = explode(" ", $ref);
            $_requisition = $split[0];
            $_requisition= "";
            foreach($split as $str){
                $split_ = explode("P-", $str);
                if(count($split_)>1){
                    $str = $split_[1];
                }
                if(ctype_digit($str)){
                    $_requisition = $str;
                    break;
                }
            }
            return [
                "serie" => intval($row['TIPFAC']),
                "ref" => $ref,
                "num_ticket" => intval($row['CODFAC']),
                "total" => floatval($row['TOTFAC']),
                "created_at" => $date,
                "_workpoint_from" => 1,
                "_workpoint_to" => $this->getStore(intval($row['CLIFAC'])),
                "name" => mb_convert_encoding((string)$row['CNOFAC'], "UTF-8", "Windows-1252"),
                "_requisition" => $_requisition,
                "body" => $body
            ];
        });
        return response()->json($salidas);
    }

    public function getNewSalidas(Request $request){
        $clause = " WHERE";
        $i = 0;
        $cajas = $request->cash;
        $_cash_array = array_column($cajas, "_cash");
        foreach($request->cash as $cash){
            $i++;
            $last_date = $request->created_at;
            $clause = $clause." (TIPFAC = '".$cash['_cash']."' AND FECFAC >= #".$cash['date']."#)";
            if($i<count($cajas)){
                $clause = $clause." OR";
            }
        }

        $clientes_tiendas = [7,8,9,10,11,12,13,14,15,16,17,18,19,20,21];
        $i = 0;
        $clientes = count($clientes_tiendas);
        $clause = $clause." AND (";
        foreach($clientes_tiendas as $cliente){
            $i++;
            $clause = $clause. "CLIFAC = ".$cliente."";
            if($i<$clientes){
                $clause = $clause. " OR ";
            }
        }
        $clause = $clause.")";

        $query = "SELECT TIPFAC, CODFAC, REFFAC, FECFAC, CLIFAC, CNOFAC, FOPFAC, HORFAC, TOTFAC, AGEFAC FROM F_FAC".$clause;
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFA, CANLFA, PRELFA, TOTLFA, COSLFA FROM F_LFA WHERE TIPLFA = ? AND CODLFA = ?";
        $exec_body = $this->con->prepare($query_body);
        $salidas = $rows->filter(function($row) use($cajas, $_cash_array){
            $date = explode(" ",$row['FECFAC'])[0]." ".explode(" ",$row['HORFAC'])[1];
            $index = array_search($row["TIPFAC"], $_cash_array);
            return $cajas[$index]["created_at"]<$date;

          })->map(function($row) use($exec_body){
            $exec_body->execute([$row['TIPFAC'], $row['CODFAC']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
                return [
                    "_product" => strtoupper(mb_convert_encoding((string)$row['ARTLFA'], "UTF-8", "Windows-1252")),
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
                    $amount = $body["amount"] + $el["amount"];
                    $total = $body["total"] + $el["total"];
                    $price = $total / $amount;

                    $body['amount'] = $amount;
                    $body['price'] = $price;
                    $body['total'] = $total;
                }
                return $body;
            })->values()->all();
            $date = explode(" ",$row['FECFAC'])[0]." ".explode(" ",$row['HORFAC'])[1];
            $_paid_by = 1;
            switch($row['FOPFAC']){
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
            $ref = mb_convert_encoding((string)$row['REFFAC'], "UTF-8", "Windows-1252");
            $split = explode(" ", $ref);
            $_requisition = $split[0];
            $_requisition= "";
            foreach($split as $str){
                $split_ = explode("P-", $str);
                if(count($split_)>1){
                    $str = $split_[1];
                }
                if(ctype_digit($str)){
                    $_requisition = $str;
                    break;
                }
            }
            return [
                "serie" => intval($row['TIPFAC']),
                "ref" => $ref,
                "num_ticket" => intval($row['CODFAC']),
                "total" => floatval($row['TOTFAC']),
                "created_at" => $date,
                "_workpoint_from" => 1,
                "_workpoint_to" => $this->getStore(intval($row['CLIFAC'])),
                "name" => mb_convert_encoding((string)$row['CNOFAC'], "UTF-8", "Windows-1252"),
                "_requisition" => $_requisition,
                "body" => $body
            ];
        })->filter(function($row){
            return !is_null($row["_workpoint_to"]);
        })->values()->all();
        return response()->json($salidas);
    }

    public function getStore($client){
        switch($client){
            case 7:
                return 3;
            case 8:
                return 4;
            case 11:
                return 5;
            case 12:
                return 6;
            case 16:
                return 11;
            case 18:
                return 7;
            case 13:
                return 9;
            case 19:
                return 8;
            case 14:
                return 10;
            case 17:
                return 12;
            case 15:
                return 13;
            case 21:
                return 14;
            case 60:
                return 2;
            case 9:
                return 17;
            case 20:
                return 18;
            case 10:
                return 19;
        }
    }

    public function getEntradas(Request $request){
        $query = "SELECT TIPFRE, CODFRE, FACFRE, REFFRE, FECFRE, PROFRE, PNOFRE, HORFRE, TOTFRE FROM F_FRE WHERE PROFRE = 5 AND FECFRE > #2021/08/31#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFR, CANLFR, PRELFR, TOTLFR FROM F_LFR WHERE TIPLFR = ? AND CODLFR = ?";
        $exec_body = $this->con->prepare($query_body);
        $_workpoint = $request->_workpoint;
        $salidas = $rows->map(function($row) use($exec_body, $_workpoint){
            $exec_body->execute([$row['TIPFRE'], $row['CODFRE']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
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
                    "total" => 0
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
            $ref = mb_convert_encoding((string)$row['REFFRE'], "UTF-8", "Windows-1252");
            $split = explode(" ", $ref);
            $folio= 0;
            $serie= 0;
            foreach($split as $str){
                $split_ = explode("-", $str);
                if(count($split_)>1){
                    $str = $split_[1];
                    $i = $split_[0];
                    if(ctype_digit($str) || ctype_digit($i)){
                        $folio = intval($str);
                        $serie = intval($i);
                        break;
                    }
                }
            }
            return [
                "serie" => intval($row['TIPFRE']),
                "reference" => $ref,
                "num_ticket" => intval($row['CODFRE']),
                "total" => floatval($row['TOTFRE']),
                "created_at" => $row['FECFRE'],
                "_workpoint" => $_workpoint,
                "_workpoint_from" => 1,
                "name" => mb_convert_encoding((string)$row['PNOFRE'], "UTF-8", "Windows-1252"),
                "folio_fac" => $folio,
                "serie_fac" => $serie,
                "body" => $body
            ];

        })->filter(function($sale){
            return count($sale['body'])>0;
        })->values();
        return $salidas;
    }

    public function getNewEntradas(Request $request){
        $clause = "";
        foreach($request->series as $key => $serie){
            if($key == 0){
                $clause = "(TIPFRE = '".$serie["serie"]."' AND CODFRE >= ".$serie["folio"].")";
            }else{
                $clause = $clause." OR (TIPFRE = ".$serie["serie"]." AND CODFRE >= ".$serie["folio"].")";
            }
        }

        $query = "SELECT TIPFRE, CODFRE, FACFRE, REFFRE, FECFRE, PROFRE, PNOFRE, HORFRE, TOTFRE FROM F_FRE WHERE (".$clause. ") AND PROFRE = 5 AND FECFRE > #2021/08/31#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $query_body = "SELECT ARTLFR, CANLFR, PRELFR, TOTLFR FROM F_LFR WHERE TIPLFR = ? AND CODLFR = ?";
        $exec_body = $this->con->prepare($query_body);
        $_workpoint = $request->_workpoint;
        $salidas = $rows->map(function($row) use($exec_body, $_workpoint){
            $exec_body->execute([$row['TIPFRE'], $row['CODFRE']]);
            $body = collect($exec_body->fetchAll(\PDO::FETCH_ASSOC))->map(function($row){
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
                    "total" => 0
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
            $ref = mb_convert_encoding((string)$row['REFFRE'], "UTF-8", "Windows-1252");
            $split = explode(" ", $ref);
            $folio= 0;
            $serie= 0;
            foreach($split as $str){
                $split_ = explode("-", $str);
                if(count($split_)>1){
                    $str = $split_[1];
                    $i = $split_[0];
                    if(ctype_digit($str) || ctype_digit($i)){
                        $folio = intval($str);
                        $serie = intval($i);
                        break;
                    }
                }
            }
            return [
                "serie" => intval($row['TIPFRE']),
                "num_ticket" => intval($row['CODFRE']),
                "name" => mb_convert_encoding((string)$row['PNOFRE'], "UTF-8", "Windows-1252"),
                "reference" => $ref,
                "total" => floatval($row['TOTFRE']),
                "created_at" => $row['FECFRE'],
                "_workpoint" => $_workpoint,
                "_workpoint_from" => 1,
                "folio_fac" => $folio,
                "serie_fac" => $serie,
                "body" => $body
            ];

        })->filter(function($sale){
            return count($sale['body'])>0;
        })->values();
        return $salidas;
    }
}
