<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class ProductController extends Controller{
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

    public function getProducts(){
        $query = "SELECT CODART, CCOART, DESART, DEEART, REFART, UPPART ,CP5ART, FAMART, PCOART, NPUART, PHAART, DIMART, FALART, EANART, CP1ART FROM F_ART";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $products = $rows->map(function($product){
            $dimensions = explode('*', $product['DIMART']);
            $created_at = $product['FALART'];
            $_status = $product['NPUART'] == 0 ? 1 : 5;
            return [
              "code" => mb_convert_encoding((string)$product['CODART'], "UTF-8", "Windows-1252"),
              "name" => mb_convert_encoding((string)$product['CCOART'], "UTF-8", "Windows-1252"),
              "barcode" => $product['EANART'],
              "large" => $product['CP2ART']." ".$product['CP5ART'],
              "description" => mb_convert_encoding((string)$product['DESART'], "UTF-8", "Windows-1252"),
              "label" => mb_convert_encoding((string)$product['DEEART'], "UTF-8", "Windows-1252"),
              "reference" => mb_convert_encoding((string)$product['REFART'], "UTF-8", "Windows-1252"),
              "cost" => $product['PCOART'],
              "dimensions" => json_encode([
                "length" => count($dimensions)>0 ? $dimensions[0] : '',
                "height" => count($dimensions)>1 ? $dimensions[1] : '',
                "width" => count($dimensions)>2 ? $dimensions[2] : ''
              ]),
              "pieces" => explode(" ", $product['UPPART'])[0] ? intval(explode(" ", $product['UPPART'])[0]) : 0,
              "_category" => mb_convert_encoding($product['CP1ART'], "UTF-8", "Windows-1252"),
              "_family" => mb_convert_encoding($product['FAMART'], "UTF-8", "Windows-1252"),
              "_status" => $_status,
              "_provider" => intval($product['PHAART']),
              "_unit" => 1,
              "created_at" => $created_at
            ];
          });
          return $products;
    }

    public function getPrices(){
        $query = "SELECT TARLTA, ARTLTA, PRELTA FROM F_LTA INNER JOIN F_ART ON F_LTA.ARTLTA = F_ART.CODART";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $prices = $rows->map(function($price){
            return [
                'price' => $price['PRELTA'],
                '_type' => intval($price['TARLTA']),
                'code' => mb_convert_encoding((string)$price['ARTLTA'], "UTF-8", "Windows-1252")
            ];
        });
        return $prices;
    }

    public function getRelatedCodes(){
        $query = "SELECT ARTEAN, EANEAN FROM F_EAN";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        return response()->json($rows);
    }

    public function updatedProducts(Request $request){
        $date = is_null($request->date) ? date('Y-m-d', time()) : $request->date;
        $query = "SELECT F_ART.CODART, F_ART.CCOART, F_ART.EANART, F_ART.DESART, F_ART.DEEART, F_ART.REFART, F_ART.UPPART, F_ART.FAMART, F_ART.CP1ART, F_ART.PCOART, F_ART.NPUART, F_ART.PHAART, F_ART.DIMART, F_LTA.TARLTA, F_LTA.PRELTA, F_ART.CP5ART FROM F_ART INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART WHERE F_ART.FUMART >= #".$date."#";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $products = $rows->groupBy('CODART')->map(function($group){
            $prices = $group->map(function($el){
                return [
                    "_type" => $el['TARLTA'],
                    "price" => $el['PRELTA']
                ];
            });
            $dimensions = explode('*', $group[0]['DIMART']);
            $_status = $group[0]['NPUART'] == 0 ? 1 : 5;
            return [
                "code" => mb_convert_encoding($group[0]['CODART'], "UTF-8", "Windows-1252"),
                "name" => $group[0]['CCOART'],
                "barcode" => $group[0]['EANART'],
                "large" => mb_convert_encoding($group[0]['CP5ART'], "UTF-8", "Windows-1252"),
                "description" => mb_convert_encoding($group[0]['DESART'], "UTF-8", "Windows-1252"),
                "label" => mb_convert_encoding($group[0]['DEEART'], "UTF-8", "Windows-1252"),
                "reference" => mb_convert_encoding((string)$group[0]['REFART'], "UTF-8", "Windows-1252"),
                "cost" => $group[0]['PCOART'],
                "dimensions" =>json_encode([
                    "length" => count($dimensions)>0 ? $dimensions[0] : '',
                    "height" => count($dimensions)>1 ? $dimensions[1] : '',
                    "width" => count($dimensions)>2 ? $dimensions[2] : ''
                ]),
                "pieces" => explode(" ", $group[0]['UPPART'])[0] ? intval(explode(" ", $group[0]['UPPART'])[0]) : 0,
                "_category" => mb_convert_encoding($group[0]['CP1ART'], "UTF-8", "Windows-1252"),
                "_family" => mb_convert_encoding($group[0]['FAMART'], "UTF-8", "Windows-1252"),
                "_status" => $_status,
                "_provider" => intval($group[0]['PHAART']),
                "_unit" => 1,
                "prices" => $prices
            ];
        })->values()->all();
        return $products;
    }

    public function UpdatedProductAccess(Request $request){
        $cols_required = $request->required;
        $date = $request->date;
        $products = $request->products ? $this->getRawProducts($cols_required, $date) : null;
        $prices = $request->prices ? $this->getRawPrices($date) : null;
        return ["products" => $products, "prices" => $prices, "related_codes" => null];
    }

    public function getRawProducts($cols_required, $date = null){
        if(isset($cols_required) && !is_null($cols_required)){
            $cols = "";
            foreach($cols_required as $key => $col){
                if($key == 0){
                    $cols = $col;
                }else{
                    $cols = $cols.", ".$col;
                }
            }
        }else{
            $cols = " * ";
        }
        $where_date = "";
        if(isset($date) && !is_null($date)){
            $where_date = " WHERE FUMART >= #".$date."#";
        }
        $query = "SELECT ".$cols." FROM F_ART".$where_date;
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
        $price = "SELECT PRELTA FROM F_LTA WHERE TARLTA = 7 AND ARTLTA = ?";
        $exec_price = $this->con->prepare($price);
        foreach($rows as $key_row => $row){
            foreach($row as $key => $col){
                if($key =="PCOART"){
                    $exec_price->execute([$row["CODART"]]);
                    $cost = $exec_price->fetch(\PDO::FETCH_ASSOC);
                    if($cost){
                        $row["PCOART"] = $cost["PRELTA"];
                    }
                }else{
                    $row[$key] = mb_convert_encoding($col, "UTF-8", "Windows-1252");
                }
            }
            $rows[$key_row] = $row;
        }
        return $rows;
    }

    public function getRawPrices($date = null){
        $query = "SELECT F_LTA.TARLTA, F_LTA.ARTLTA, F_LTA.MARLTA, F_LTA.PRELTA FROM F_LTA INNER JOIN F_ART ON F_ART.CODART = F_LTA.ARTLTA";
        $query = $date ? $query." WHERE F_ART.FUMART >= #".$date."# AND F_LTA.TARLTA < 7" : $query." WHERE F_LTA.TARLTA < 7";
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

    public function checkDistinctProducts(Request $request){
        $clouster_data = $request->clouster ? : [];
        $local_data = $this->getRawProducts(["CODART"]);
        $clouster_codes = array_column($clouster_data, 'CODART');
        $local_codes = array_column($local_data, 'CODART');
        $hasClouster = array_diff($clouster_codes, $local_codes);
        $hasStore = array_diff($local_codes, $clouster_codes);
        return response()->json(["hasClouster" => $hasClouster, "hasStore" => $hasStore, "clouster" => count($clouster_codes), "local" => count($local_codes)]);
    }

    public function syncProducts($products){
        $keys = array_keys($products[0]);
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
        $query = "UPDATE F_ART SET ".$update." WHERE CODART = ?";
        $exec = $this->con->prepare($query);
        $query_select = "SELECT count(*) FROM F_ART WHERE CODART = ?";
        $exec_select = $this->con->prepare($query_select);
        $query_insert = "INSERT INTO F_ART (".$cols.") VALUES(".$values.")";
        $exec_insert = $this->con->prepare($query_insert);
        $response = [];
        foreach($products as $key => $row){
            $exec_select->execute([$row["CODART"]]);
            $count = intval($exec_select->fetch(\PDO::FETCH_ASSOC)['Expr1000']);
            if($count == 1){
                $toUpdate = array_values($row);
                $toUpdate[] = $row["CODART"];
                $result = $exec->execute($toUpdate);
                if($result){
                    $accion = "Actualización";
                }else{
                    $accion = "No se ha podido actualizar";
                }
            }else if($count == 0){
                $result = $exec_insert->execute(array_values($row));
                $this->createStocks($row["CODART"]);
                if($result){
                    $accion = "Creado";
                }else{
                    $accion = "No se ha podido crear";
                }
            }else{
                $accion = "Duplicado";
            }
            $response[] = ["Modelo" => $row["CODART"], "Código" => $row["CCOART"], "Descripción" => $row["DESART"], "Acción" => $accion];
        }
        return $response;
    }

    public function sync(Request $request){
        $prices = $request->prices ? $this->syncPrices($request->prices): [];
        $products = $request->products ? $this->syncProducts($request->products): [];

        return ["products" => $products, "prices" => $prices];
    }

    public function syncPrices($prices){
        $products = collect($prices)->groupBy('ARTLTA');
        $query_delete = "DELETE FROM F_LTA WHERE ARTLTA = ?;";
        $exec_delete = $this->con->prepare($query_delete);
        
        $keys = array_keys($prices[0]);
        $cols = "";
        foreach($keys as $i => $key){
            if($i == 0){
                $cols = "".$key;
            }else{
                $cols = $cols.", ".$key;
            }
        }
        $response = [];
        foreach($products as $key => $product){
            $query_insert = "INSERT INTO F_LTA(".$cols.") VALUES (?, ?, ?, ?)";
            $res = $exec_delete->execute([$key]);
            $values = "";
            if($res){
                $prices_inserted = [];
                foreach($product as $price){
                    $exec_insert = $this->con->prepare($query_insert);
                    $res_insert = $exec_insert->execute(array_values($price));
                    $prices_inserted[$price["TARLTA"]] = $price["PRELTA"];
                }
                $response[] = array_merge(["Modelo" => $price["ARTLTA"]], $prices_inserted);
            }
        }
        return $response;
    }

    public function compareProductVsStock(){
        $query = "SELECT DISTINCT F_ART.CODART, F_STO.ARTSTO FROM F_ART LEFT JOIN F_STO ON F_STO.ARTSTO = F_ART.CODART WHERE F_STO.ARTSTO IS NULL";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        return $rows;
    }

    public function compareProductVsPrices(){
        $query = "SELECT DISTINCT F_ART.CODART, F_LTA.ARTLTA FROM F_ART LEFT JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART WHERE F_LTA.ARTLTA IS NULL";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        return $rows;
    }

    public function createStocks($product){
        $almacenes = $this->getAlmacenes(env('_WORKPOINT'));
        $query = "INSERT INTO F_STO(ARTSTO, ALMSTO, MINSTO, MAXSTO, ACTSTO, DISSTO) VALUES(?,?,?,?,?,?)";
        $exec = $this->con->prepare($query);
        $response = [];
        foreach($almacenes as $almacen){
            if($almacen){
                $exec->execute([$product, $almacen, 0, 0, 0, 0]);
            }
        }
        return response()->json($response);
    }
    public function getAlmacenes($_workpoint){
        switch($_workpoint){
            case 1: //CEDIS
                return ["GEN" => "GEN", "EXH" => "", "DES" => "DES", "FDT" => ""];
            case 2: //PANTACO
                return ["GEN" => "PAN", "EXH" => "", "DES" => "", "FDT" => ""];
            case 3: //SP1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => "FDT"];
            case 4: //SP2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE2", "FDT" => "FDT"];
            case 5: //CR1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE3", "FDT" => "FDT"];
            case 6: //CR2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE4", "FDT" => ""];
            case 7: //AP1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE7", "FDT" => "FDT"];
            case 8: //AP2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE6", "FDT" => "FDT"];
            case 9: //RC1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE1", "FDT" => "FDT"];
            case 10: //RC2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE8", "FDT" => "FDT"];
            case 11: //BRA1
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "D10", "FDT" => "FDT"];
            case 12: //BRA2
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "D11", "FDT" => "FDT"];
            case 13: //CEDISBOL
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE5", "FDT" => "FDT"];
                //return ["GEN" => "BOL", "EXH" => "", "DES" => "", "FDT" => ""];
            case 14: //SP3
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "", "FDT" => ""];
            case 15: //SP4
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "", "FDT" => ""];
            case 17: //SPC
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => ""];
            case 18: //PUE
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DE3", "FDT" => "FDT"];
            case 19: //SOT
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "CUA", "FDT" => "FDT"];
        }
    }
}