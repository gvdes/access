<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $query = "SELECT CODART, CCOART, DESART, DEEART, REFART, UPPART ,CP5ART, CP2ART, FAMART, PCOART, NPUART, PHAART, DIMART, FALART, EANART, CP1ART, CP4ART FROM F_ART";
        $exec = $this->con->prepare($query);
        $exec->execute();
        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $products = $rows->map(function($product){
            $dimensions = explode('*', $product['DIMART']);
            $created_at = $product['FALART'];
            $_status = $product['NPUART'] == 0 ? 1 : 5;
            if(is_null($product['CP4ART'])){
                $refillable = null;
            }elseif($product['CP4ART'] == "SI"){
                $refillable = 1;
            }elseif($product['CP4ART'] == "NO"){
                $refillable = 0;
            }
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
              "created_at" => $created_at,
              "refillable" => $refillable
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
        try {
            //code...
            $date = is_null($request->date) ? date('Y-m-d', time()) : $request->date;
            $query = "SELECT F_ART.CODART, F_ART.CCOART, F_ART.EANART, F_ART.DESART, F_ART.DEEART, F_ART.REFART, F_ART.UPPART, F_ART.FAMART, F_ART.CP1ART, F_ART.PCOART, F_ART.NPUART, F_ART.PHAART, F_ART.DIMART, F_LTA.TARLTA, F_LTA.PRELTA, F_ART.CP2ART,F_ART.CP5ART,F_ART.CP4ART FROM F_ART INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART WHERE F_ART.FUMART >= #".$date."#";
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

                $refillable = $group[0]['CP4ART'] == "SI" ? 1 : 0;

                return [
                    "code" => mb_convert_encoding($group[0]['CODART'], "UTF-8", "Windows-1252"),
                    "name" => $group[0]['CCOART'],
                    "barcode" => $group[0]['EANART'],
                    "large" => mb_convert_encoding($group[0]['CP2ART']." ".$group[0]['CP5ART'], "UTF-8", "Windows-1252"),
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
                    "refillable" => $refillable,
                    "prices" => $prices
                ];
            })->values()->all();

            return $products;
        } catch (\Exception $e) { return response()->json($e->getMessage(),500); }
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
            case 20: //ECO
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => "FDT"];
            case 22: //BR3
                return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => "FDT"];
                case 23: //CRG
                    return ["GEN" => "GEN", "EXH" => "EXH", "DES" => "DES", "FDT" => "FDT"];
        }
    }
    public function ReplaceProducts(Request $request){
        $updates = $request->all();

        foreach($updates as $update){

          $original = "'".$update['original']."'";
          $upd = "'".$update['edit']."'";

          try{
            $upda = "UPDATE F_LFA SET ARTLFA = $upd WHERE ARTLFA = $original";
            $exec = $this->con->prepare($upda);
            $exec -> execute();
            $updsto = "UPDATE F_LFR SET ARTLFR = $upd WHERE ARTLFR = $original";
            $exec = $this->con->prepare($updsto);
            $exec -> execute();
            $updlta = "UPDATE F_LEN SET ARTLEN = $upd WHERE ARTLEN = $original";
            $exec = $this->con->prepare($updlta);
            $exec -> execute();
            $updltr = "UPDATE F_LTR SET ARTLTR = $upd WHERE ARTLTR = $original";
            $exec = $this->con->prepare($updltr);
            $exec -> execute();
            $updcin = "UPDATE F_LFB SET ARTLFB = $upd WHERE ARTLFB = $original";
            $exec = $this->con->prepare($updcin);
            $exec -> execute();
            $upddev = "UPDATE F_LFD SET ARTLFD = $upd WHERE ARTLFD = $original";
            $exec = $this->con->prepare($upddev);
            $exec -> execute();
            $deleteart = "DELETE FROM F_ART WHERE CODART = $original";
            $exec = $this->con->prepare($deleteart);
            $exec -> execute();
            $deletetar = "DELETE FROM F_LTA WHERE ARTLTA = $original";
            $exec = $this->con->prepare($deletetar);
            $exec -> execute();
            $deletesto = "DELETE FROM F_STO WHERE ARTSTO = $original";
            $exec = $this->con->prepare($deletesto);
            $exec -> execute();
            $deleteean = "DELETE FROM F_EAN WHERE ARTEAN = $original";
            $exec = $this->con->prepare($deleteean);
            $exec -> execute();

          }catch (\PDOException $e){ die($e->getMessage());}


        }


      return response()->json("CAMBIOS REALIZADOS EN EL SISTEMA :)");
    }

    public function insart(Request $request){
        $goals=[
            "INSERTADOS"=>[],
            "ACTUALIZADOS"=>[]
        ];
        $fail=[];

        $articulos = $request->all();

            $almacenes  = [
                "GEN"=>"GEN",
                "PAN"=>"PAN",
                "BOL"=>"BOL",
                "DES"=>"DES",
                "CAS"=>"CAS"
            ];

            $tarifas = [
                "1"=>"1",
                "2"=>"2",
                "3"=>"3",
                "4"=>"4",
                "5"=>"5",
                "6"=>"6",
                "7"=>"7"
            ];


        foreach($articulos as $art){
            $codigo = trim($art["CODIGO"]);
            $deslarga = trim($art["DESCRIPCION"]);
            $desgen = trim(substr($art["DESCRIPCION"],0,50));
            $deset = trim(substr($art["DESCRIPCION"],0,30));
            $destic = trim(substr($art["DESCRIPCION"],0,20));
            $famart = trim($art["FAMILIA"]);
            $cat = trim($art["CATEGORIA"]);
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = date("d/m/Y");
            $barcode = $art["CB"] == "\"\""? null : $art["CB"];
            $cost =$art["COSTO"] == null ? 0 : $art["COSTO"];
            $medidas = $art["MEDIDAS NAV"] == "\"\"" ? null : $art["MEDIDAS NAV"];
            $luces = $art["#LUCES"] == "\"\"" ? null : $art["#LUCES"];
            $PXC = $art["PXC"];
            $refart = $art["REFERENCIA"];
            $cp3art = trim($art["UNIDA MED COMPRA"]);

            $articulo  = [
                    $codigo,
                    $barcode,
                    $famart,
                    $desgen,
                    $deset,
                    $destic,
                    $deslarga,
                    $art["PXC"],
                    $art["CODIGO CORTO"],
                    $art["PROVEEDOR"],
                    $art["REFERENCIA"],
                    $art["FABRICANTE"],
                    $cost,
                    $date_format,
                    $date_format,
                    $art["PXC"],
                    1,
                    1,
                    1,
                    $cat,
                    $luces,
                    $art["UNIDA MED COMPRA"],
                    $art["PRO RES"],
                    $medidas,
                    0,
                    "Peso"
            ];

            $articul = "SELECT CODART, EANART, CCOART FROM F_ART WHERE CODART = ? ";
            $exec = $this->con->prepare($articul);
            $exec -> execute([$codigo]);
            $arti=$exec->fetchall(\PDO::FETCH_ASSOC);
            if($arti){
                foreach($arti as $arc){//actualizar en factusol
                    $update = "UPDATE F_ART SET FAMART = "."'".$famart."'"." , CP1ART = "."'".$cat."'"."  , FUMART = "."'".$date_format."'".", EANART = $barcode, PCOART = $cost, UPPART = $PXC , EQUART = $PXC, REFART = "."'".$refart."'"."  , CP3ART = "."'".$cp3art."'"."  WHERE CODART = ? ";
                    $exec = $this->con->prepare($update);
                    $exec -> execute([$codigo]);
                    $goals["ACTUALIZADOS"][]="Se actualizo el modelo  ".$codigo." con codigo de barras ".$arc["EANART"];}
            }else{
                $corto = "SELECT CODART, EANART, CCOART FROM F_ART WHERE CCOART = ? ";
                $exec = $this->con->prepare($corto);
                $exec -> execute([$art["CODIGO CORTO"]]);
                $short=$exec->fetchall(\PDO::FETCH_ASSOC);
                if($short){
                    foreach($short as $cort)
                        $fail[]="codigo corto ".$cort["CCOART"]." esta asignado ya a ".$cort["CODART"];
                }else{
                    $ean = "SELECT CODART, EANART, CCOART FROM F_ART WHERE EANART = ? ";
                    $exec = $this->con->prepare($corto);
                    $exec -> execute([$art["CB"]]);
                    $eana=$exec->fetchall(\PDO::FETCH_ASSOC);
                    if($eana){
                        foreach($eana as $eanart)
                            $fail[]="codigo de barras ".$eanart["EANART"]." esta asignado ya a ".$eanart["CODART"];
                    }else{
                        $insert = "INSERT INTO F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,FALART,FUMART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,MPTART,UEQART
                        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                        $exec = $this->con->prepare($insert);
                        $exec -> execute($articulo);
                        foreach($almacenes as $alm){
                            $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                            $exec = $this->con->prepare($insertsto);
                            $exec -> execute([$art["CODIGO"],$alm,0,0,0,0]);
                        }
                        foreach($tarifas as $price){
                            $insertlta = "INSERT INTO F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?) ";
                            $exec = $this->con->prepare($insertlta);
                            $exec -> execute([$price,$art["CODIGO"],0,0]);
                        }
                        $goals["INSERTADOS"][]="SE INSERTARON EL ARTICULO ".$art["CODIGO"]." CON EXITO";
                    }
                }
            }
        }

        return response()->json([
            "GOALS" =>$goals,
            "FAILS" =>$fail]);

    }
    public function replypub(request $request){
        $date = $request->date;

        $products = "SELECT F_ART.* FROM ((F_ART  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_ART.CODART) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 20  AND  F_FAC.FECFAC >= #".$date."#";
        $exec = $this->con->prepare($products);
        $exec -> execute();
        $articulos=$exec->fetchall(\PDO::FETCH_ASSOC);
        if($articulos){
        $dat =$this->replyprices($date);

        $colsTabProds = array_keys($articulos[0]);

        foreach($articulos as $art){
            foreach($colsTabProds as $col){ $art[$col] = utf8_encode($art[$col]); }
            $artic[]=$art;
        }



        $url ="192.168.90.253:1619/access/public/product/insertpub";
        $ch = curl_init($url);
        $data = json_encode(["products" => $art]);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $ex = curl_exec($ch);
        curl_close($ch);

            return response()->json(["products" => $ex,
                                    "prices" => $dat
        ]);
        }
        else{return response()->json("no hay articulos que exportar");}
    }

    public function replyprices($date){
        $prices = "SELECT F_LTA.* FROM ((F_LTA  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_LTA.ARTLTA) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 20 AND F_LTA.TARLTA NOT IN (7) AND  F_FAC.FECFAC >= #".$date."#";
        $exec = $this->con->prepare($prices);
        $exec -> execute();
        $precios=$exec->fetchall(\PDO::FETCH_ASSOC);
        // foreach($precios as $pre){



        // }
        $url ="192.168.90.253:1619/access/public/product/insertpricespub";
        $ch = curl_init($url);
        $data = json_encode(["prices" => $precios]);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $ex = curl_exec($ch);
        curl_close($ch);
        return response()->json($ex);
    }
    public function insertpub(request $request){
        $products =  $request->products;
        foreach($product as $products){
            $cost = ($product["PCOART"]*1.05);
            $eanart =$product["EANART"];
            $famart =$product["FAMART"];
            $uppart =$product["UPPART"];
            $cp1art =$product["CP1ART"];
            $art = "SELECT COUNT(CODART) AS CANTIDAD FROM F_ART WHERE = ?";
            $exec = $this->con->prepare($art);
            $exec -> execute([$product["CODART"]]);
            $articulos=$exec->fetchall(\PDO::FETCH_ASSOC);

                $artupd = "UPDATE  F_ART SET FUMART = DATE(), PCOART = $cost, EANART = $eanart, FAMART = $famart, UPPART = $uppart, CP1ART = $cp1art WHERE CODART = (?)";
                $exec = $this->con->prepare($artupd);
                $exec -> execute([$product["CODART"]]);

                $artid = "INSERT INTO  F_ART (CODART,EANART,FAMART,DESART,DEEART,DETART,DLAART,EQUART,CCOART,PHAART,REFART,FTEART,PCOART,UPPART,CANART,CAEART,UMEART,CP1ART,CP2ART,CP3ART,CP4ART,CP5ART,FALART,FUMART,MPTART,UEQART
                ) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,DATE(),DATE(),?,?)";
                $exec = $this->con->prepare($artid);
                $exec -> execute([
                    $product["CODART"],
                    $product["EANART"],
                    $product["FAMART"],
                    $product["DESART"],
                    $product["DEEART"],
                    $product["DETART"],
                    $product["DLAART"],
                    $product["EQUART"],
                    $product["CCOART"],
                    $product["PHAART"],
                    $product["REFART"],
                    $product["FTEART"],
                    $product["PCOART"],
                    $product["UPPART"],
                    $product["CANART"],
                    $product["CAEART"],
                    $product["UMEART"],
                    $product["CP1ART"],
                    $product["CP2ART"],
                    $product["CP3ART"],
                    $product["CP4ART"],
                    $product["CP5ART"],
                    $product["MPTART"],
                    $product["UEQART"],
                                    ]);
                    $almacenes  = [
                        "GEN"=>"GEN",
                        "DES"=>"PAN",
                        "EMP"=>"BOL",
                        "EXH"=>"DES",
                        "FDT"=>"CAS"

                    ];
                    foreach($almacenes as $alm){


                        $insertsto = "INSERT INTO F_STO (ARTSTO,ALMSTO,MINSTO,MAXSTO,ACTSTO,DISSTO) VALUES (?,?,?,?,?,?) ";
                        $exec = $this->con->prepare($insertsto);
                        $exec -> execute([$product["CODART"],$alm,0,0,0,0]);
                    }
        }
    }

    public function insertpricespub(request $request){
        $prices = $request->prices;

        foreach($prices as $price){
            $updprices =   round($price["PRELTA"]);
            $upd = "UPDATE F_LTA SET PRELTA = $updprices WHERE ARTLTA = ? AND TARLTA = ?";
            $exec = $this->con->prepare($upd);
            $exec -> execute([$price["ARTLTA"], $price["TARLTA"]]);

        $insert = "INSERT INTO  F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?)";
        $exec = $this->con->prepare($insert);
        $exec -> execute([$price["TARLTA"],$price["ARTLTA"],0,round($price["PRELTA"])]);
        }
        // if($request->prices["TARLTA"] > 1){

        // if($request->prices["TARLTA"] == 2){
        //     $pricesnew =   round($request->prices["PRELTA"]);

        //     if(($pricesnew >= 0) && ($pricesnew <= 50)){
        //         $prai = $pricesnew + 5;
        //     }elseif(($pricesnew >= 51) && ($pricesnew <= 100)){
        //         $prai = $pricesnew + 10;
        //     }elseif(($pricesnew >= 101) && ($pricesnew <= 500)){
        //         $prai = $pricesnew + 20;
        //     }elseif(($pricesnew >= 501) && ($pricesnew <= 1000)){
        //         $prai = $pricesnew + 50;
        //     }elseif($pricesnew >= 1001){
        //         $prai =  $pricesnew + 100;
        //     }

        //     $upda = "UPDATE F_LTA SET PRELTA = $prai WHERE ARTLTA = ? AND TARLTA = 1";
        //     $exec = $this->con->prepare($upda);
        //     $exec -> execute([$request->prices["ARTLTA"]]);

        //     $inserta = "INSERT INTO  F_LTA (TARLTA,ARTLTA,MARLTA,PRELTA) VALUES (?,?,?,?)";
        //     $exec = $this->con->prepare($inserta);
        //     $exec -> execute([1,$request->prices["ARTLTA"],0,$prai]);
        // }
    }
    public function pricesart(Request $request){
        $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
        $date_format = date("d/m/Y");
        $prices = $request->all();
        $goals = [];
        $fail = [];

        foreach($prices as $price){
            $modelo = $price["MODELO"];
            $costo = round($price["COSTO"],2);
            $aaa = round($price["AAA"],2);
            $centro = round($price["CENTRO"],0);
            $especial = round($price["ESPECIAL"],0);
            $caja = round($price["CAJA"],0);
            $docena = round($price["DOCENA"],0);
            $mayoreo = round($price["MAYOREO"],0);
            if(($mayoreo >= 0) && ($mayoreo <= 50)){
                $menudeo = $mayoreo + 5;
            }elseif(($mayoreo >= 51) && ($mayoreo <= 100)){
                $menudeo = $mayoreo + 10;
            }elseif(($mayoreo >= 101) && ($mayoreo <= 500)){
                $menudeo = $mayoreo + 20;
            }elseif(($mayoreo >= 501) && ($mayoreo <= 1000)){
                $menudeo = $mayoreo + 50;
            }elseif($mayoreo >= 1001){
                $menudeo =  $mayoreo + 100;
            }
            if($costo <= $aaa){
                if($aaa <= $centro){
                    if($centro <= $especial){
                        if($especial <= $caja){
                            if($caja <= $docena){
                                if($docena <= $mayoreo){
                                    $sql = "SELECT * FROM F_ART WHERE CODART = ?";
                                    $exec = $this->con->prepare($sql);
                                    $exec -> execute([$price["MODELO"]]);
                                    $price=$exec->fetchall(\PDO::FETCH_ASSOC);
                                    if($price){
                                        foreach($price as $pri){
                                            $upd7 = "UPDATE F_LTA SET PRELTA = $aaa WHERE ARTLTA = ? AND TARLTA = 7 ";
                                            $exec = $this->con->prepare($upd7);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd6 = "UPDATE F_LTA SET PRELTA = $centro WHERE ARTLTA = ? AND TARLTA = 6 ";
                                            $exec = $this->con->prepare($upd6);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd5 = "UPDATE F_LTA SET PRELTA = $especial WHERE ARTLTA = ? AND TARLTA = 5 ";
                                            $exec = $this->con->prepare($upd5);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd4 = "UPDATE F_LTA SET PRELTA = $caja WHERE ARTLTA = ? AND TARLTA = 4 ";
                                            $exec = $this->con->prepare($upd4);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd3 = "UPDATE F_LTA SET PRELTA = $docena WHERE ARTLTA = ? AND TARLTA = 3 ";
                                            $exec = $this->con->prepare($upd3);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd2 = "UPDATE F_LTA SET PRELTA = $mayoreo WHERE ARTLTA = ? AND TARLTA = 2 ";
                                            $exec = $this->con->prepare($upd2);
                                            $exec -> execute([$pri["CODART"]]);
                                            $upd1 = "UPDATE F_LTA SET PRELTA = $menudeo WHERE ARTLTA = ? AND TARLTA = 1 ";
                                            $exec = $this->con->prepare($upd1);
                                            $exec -> execute([$pri["CODART"]]);
                                            $arti = "UPDATE F_ART SET PCOART = $costo, FUMART = "."'".$date_format."'"." WHERE CODART = ? ";
                                            $exec = $this->con->prepare($arti);
                                            $exec -> execute([$pri["CODART"]]);
                                        }
                                        $goals[]= "producto $modelo actualizado ";
                                        }else{$fail[]="No se encuentra el modelo $modelo";}
                                }else{$fail[]="$modelo precio DOCENA mas alto que MAYOREO ";}
                            }else{$fail[]="$modelo precio CAJA mas alto que DOCENA ";}
                        }else{$fail[]="$modelo precio ESPECIAL mas alto que CAJA ";}
                    }else{$fail[]="$modelo precio CENTRO mas alto que ESPECIAL ";}
                }else{$fail[]="$modelo precio AAA mas alto que CENTRO ";}
            }else{$fail[]="$modelo precio COSTO mas alto que AAA";}
        }
        return response()->json([
            "actualizados"=>$goals,
            "revisar"=>$fail
        ]);

    }
    public function familiarizacion(){//metodo para replicar las familiarizaciones en mysql

        DB::statement("SET SQL_SAFE_UPDATES = 0;");//se desactiva safe update
        DB::statement("SET FOREIGN_KEY_CHECKS = 0;");//se desactivan las llaves foraneas
        DB::statement("truncate table product_variants;");//se vacia la tabla de familiarizaciones
        DB::statement("SET SQL_SAFE_UPDATES = 1;");//se activan las llaves foraneas
        DB::statement("SET FOREIGN_KEY_CHECKS = 1;");//se activa safe update
        try{
            $select = "SELECT * FROM F_EAN";//query para ver las familiarizaciones
            $exec = $this->con->prepare($select);
            $exec -> execute();
            $art=$exec->fetchall(\PDO::FETCH_ASSOC);//se executa
        }catch (\PDOException $e){ die($e->getMessage());}
                foreach($art as $artic){
                    $pro = DB::table('products')->where('code',$artic["ARTEAN"])->value('id');// se busca el id de el producto que se va a familiarizar
                    $product = [//se crea el arreglo de insercion
                        "barcode"=>$artic["EANEAN"],
                        "stock"=>0,
                        "_product"=>$pro
                    ];
                    $ins = DB::table('product_variants')->insert($product);//se inserta el arreglo
                }
                return response()->json("Familiarizaciones creadas correctamente");
    }

    public function productDepure(Request $request){
        $stor = [];
        $failstores = [];
        $products = $request->all();
        foreach($products as $product){
            $all [] = "'".$product['products']."'";
        }
        $implode = implode(",",$all);

        $deleteart = "DELETE FROM F_ART WHERE CODART IN ($implode)";
        $exec = $this->con->prepare($deleteart);
        $exec->execute();

        $deletesto = "DELETE FROM F_STO WHERE ARTSTO IN ($implode)";
        $exec = $this->con->prepare($deletesto);
        $exec->execute();

        $deletelta = "DELETE FROM F_LTA WHERE ARTLTA IN ($implode)";
        $exec = $this->con->prepare($deletelta);
        $exec->execute();


        $stores = DB::table('workpoints')->where('_type',2)->where('active',1)->get();
        foreach($stores as $store){
          $url = $store->dominio."/access/public/product/depurestor";//se optiene el inicio del dominio de la sucursal
          $ch = curl_init($url);//inicio de curl
          $data = json_encode(["products" => $all]);//se codifica el arreglo de los proveedores
          //inicio de opciones de curl
          curl_setopt($ch, CURLOPT_POSTFIELDS,$data);//se envia por metodo post
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_HEADER, 0);
          curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
          //fin de opciones e curl
          $exec = curl_exec($ch);//se executa el curl
          $exc = json_decode($exec);//se decodifican los datos decodificados
          if(is_null($exc)){//si me regresa un null
            //   $failstores[] =$store->alias." sin conexion";//la sucursal se almacena en sucursales fallidas
              $failstores[] =["sucursal"=>$store->alias, "mssg"=>$exec];//la sucursal se almacena en sucursales fallidas

          }else{
              $stor[] =["sucursal"=>$store->alias, "mssg"=>$exc];
          }
          curl_close($ch);//cirre de curl
        }
    }

    public function depureStore(Request $request){
        $products = $request->products;
        $implode = implode(",",$products);

        $deleteart = "DELETE FROM F_ART WHERE CODART IN ($implode)";
        $exec = $this->con->prepare($deleteart);
        $exec->execute();

        $deletesto = "DELETE FROM F_STO WHERE ARTSTO IN ($implode)";
        $exec = $this->con->prepare($deletesto);
        $exec->execute();

        $deletelta = "DELETE FROM F_LTA WHERE ARTLTA IN ($implode)";
        $exec = $this->con->prepare($deletelta);
        $exec->execute();

        return response()->json(count($products)." articulos eliminados");
    }

    public function insertart(Request $request){
        $fails = [
            "sucursal"=>[],
            "categoria"=>[],
            "codigo_barras"=>[],
            "codigo_corto"=>[]
        ];

        $goals = [
            "sucursal"=>[],
            "insertados"=>[],
            "actualizados"=>[]
        ];
        $articulos = $request->all();
        $sucu = $request->sucursal;
        $sucursal = $sucu == null ? "all" : $sucu;
        if($sucursal == "all"){

            $stores= DB::table('workpoints')->where('_type',2)->where('active',1)->get();
            foreach($stores as $stor){
                $url = $stor->dominio."/access/public/product/insartstore";


            }
            return $url;
        }else{return $sucu;

        }
    }
    public function insartstore(Request $request){

    }

    public function replydio(request $request){
        $date = $request->date;

        $products = "SELECT F_ART.* FROM ((F_ART  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_ART.CODART) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 37  AND  F_FAC.FECFAC >= #".$date."#";
        $exec = $this->con->prepare($products);
        $exec -> execute();
        $articulos=$exec->fetchall(\PDO::FETCH_ASSOC);
        if($articulos){
        $dat =$this->replypricesdio($date);

        $colsTabProds = array_keys($articulos[0]);

        foreach($articulos as $art){
            foreach($colsTabProds as $col){ $art[$col] = utf8_encode($art[$col]); }
            $artic[]=$art;
        }
        $url ="192.168.140.254:1619/access/public/product/insertpub";
        $ch = curl_init($url);
        $data = json_encode(["products" => $artic]);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $ex = curl_exec($ch);
        $res = json_decode($ex);
        curl_close($ch);
            return response()->json(["products" => $res,
                                    "prices" => $dat
        ]);
        }
        else{return response()->json("no hay articulos que exportar");}
    }

    public function replypricesdio($date){
        $prices = "SELECT F_LTA.* FROM ((F_LTA  INNER JOIN F_LFA ON F_LFA.ARTLFA = F_LTA.ARTLTA) INNER JOIN F_FAC ON F_FAC.TIPFAC = F_LFA.TIPLFA AND F_FAC.CODFAC = F_LFA.CODLFA) WHERE F_FAC.CLIFAC = 37 AND F_LTA.TARLTA NOT IN (7) AND  F_FAC.FECFAC >= #".$date."#";
        $exec = $this->con->prepare($prices);
        $exec -> execute();
        $precios=$exec->fetchall(\PDO::FETCH_ASSOC);
        // foreach($precios as $pre){


        // }
        $url ="192.168.140.254:1619/access/public/product/insertpricespub";
        $ch = curl_init($url);
        $data = json_encode(["prices" => $precios]);
        curl_setopt($ch,CURLOPT_POSTFIELDS,$data);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
        $ex = curl_exec($ch);
        curl_close($ch);
        return $ex;
    }
}
