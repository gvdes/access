<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class ClientOrderController extends Controller{
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
        //FOPPCL EFE
        /* Crear encabezado del pedido de cliente*/
        $query = "INSERT INTO F_PCL(TIPPCL, CODPCL, REFPCL, FECPCL, AGEPCL, CLIPCL, ALMPCL, CNOPCL, NET1PCL, PIVA1PCL, IIVA1PCL, TOTPCL, HORPCL) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->con->prepare($query);
        $date = date("Y/m/d H:i");
        $date_format = date("d/m/Y");
        $hour = "01/01/1900 ".explode(" ", $date)[1];
        $cash = collect($request->log)->filter(function($step){
            return $step["id"] == 2;
        })->values()->all()[0]["responsable"];
        $num_cash = $cash["num_cash"];
        $code = $this->nextOrder($num_cash);
        $total = array_reduce($request->products, function($total, $product){
            return $total + $product["ordered"]["toDelivered"] * $product["ordered"]["price"];
        }, 0);
        $result = $exec->execute([$num_cash, $code, "P-".$request->id, $date_format, $request->created_by["id"], $request->client["id"], 'GEN', $request->name, $total, 0, 0, $total, $hour]);
        if($result){
            /* Crear detalles del pedido (productos) */
            $res = $this->createBody($num_cash, $code, $request->products);
            if($res){
                return response()->json(["msg" => "creado", "status" => 200, "serie" => $num_cash,"ticket" => $code]);
            }else{
                /* Se tiene que eliminar el pedido */
                return response()->json(["msg" => "no se pudo crear el cuerpo", "status" => 500]);
            }
        }else{
            return response()->json(["msg" => "No se pudo crear", "status" => 500]);
        }
    }

    public function createBody($cash, $code, $body){
        $query = "INSERT INTO F_LPC(TIPLPC, CODLPC, POSLPC, ARTLPC, DESLPC, CANLPC, PRELPC, COSLPC, TOTLPC, PENLPC) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $exec = $this->con->prepare($query);
        $success = true;
        $success_stock = true;
        $counter = 1;
        foreach($body as $key => $row){
            if($row["ordered"]["toDelivered"] && $row["ordered"]>0){
                $result = $exec->execute([$cash, $code, $counter, $row["code"], $row["description"], $row["ordered"]["toDelivered"], $row["ordered"]["price"], $row["cost"], $row["ordered"]["total"], $row["ordered"]["toDelivered"]]);
                $result_stock = $this->reserveStock($row["code"], $row["ordered"]["toDelivered"]);
                $success = $result == $success;
                $success_stock = $result_stock == $success_stock;
                $counter++;
            }
        }
        return $success == $success_stock;
    }

    public function createHeaderRequisition(Request $request){
        //FOPPCL EFE
        /* Crear encabezado del pedido de cliente*/
        $query = "INSERT INTO F_PCL(TIPPCL, CODPCL, REFPCL, FECPCL, AGEPCL, CLIPCL, ALMPCL, CNOPCL, NET1PCL, PIVA1PCL, IIVA1PCL, TOTPCL, HORPCL) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->con->prepare($query);
        $date = date("Y/m/d H:i");
        $date_format = date("d/m/Y");
        $hour = "01/01/1900 ".explode(" ", $date)[1];
        $num_cash = $this->getSerieToRequisition($request->from["id"]);
        $code = $this->nextOrder($num_cash);
        $total = array_reduce($request->products, function($total, $product){
            return $total + ($product["ordered"]["toDelivered"] * $product["ordered"]["cost"]);
        }, 0);
        $result = $exec->execute([$num_cash, $code, "R-".$request->id, $date_format, $request->created_by["id"], $request->from["_client"], 'GEN', $request->from['name'], $total, 0, 0, $total, $hour]);
        if($result){
            /* Crear detalles del pedido (productos) */
            $res = $this->createBodyRequisition($num_cash, $code, $request->products);
            if($res){
                return response()->json(["msg" => "creado", "status" => 200, "serie" => $num_cash, "ticket" => $code]);
            }else{
                /* Se tiene que eliminar el pedido */
                return response()->json(["msg" => "no se pudo crear el cuerpo", "status" => 500]);
            }
        }else{
            return response()->json(["msg" => "No se pudo crear", "status" => 500]);
        }
    }

    public function createBodyRequisition($cash, $code, $body){
        $query = "INSERT INTO F_LPC(TIPLPC, CODLPC, POSLPC, ARTLPC, DESLPC, CANLPC, PRELPC, COSLPC, TOTLPC, PENLPC) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $exec = $this->con->prepare($query);
        $success = true;
        $success_stock = true;
        $counter = 1;
        foreach($body as $key => $row){
            if($row["ordered"]["toDelivered"] && $row["ordered"]>0){
                $result = $exec->execute([$cash, $code, $counter, $row["code"], $row["description"], $row["ordered"]["toDelivered"], $row["ordered"]["cost"], $row["cost"], $row["ordered"]["total"], $row["ordered"]["toDelivered"]]);
                $result_stock = $this->reserveStock($row["code"], $row["ordered"]["toDelivered"]);
                $success = $result == $success;
                $success_stock = $result_stock == $success_stock;
                $counter++;
            }
        }
        return $success == $success_stock;
    }

    public function getSerieToRequisition($_workpoint){
        switch($_workpoint){
            case 1: //CEDISSP
            case 2: //CEDISPAN
                return 8;
            case 3: //SP1
            case 17: //SPC
            case 19: //SOT
                return 1;
            case 4: //SP2
                return 2;
            case 5: //CR1
                return 3;
            case 6: //CR2
                return 4;
            case 7: //APA1
                return 6;
            case 8: //APA2
            case 13: //BOL
            case 18: //PUE
                return 9;
            case 9: //RAC1
            case 10: //RAC2
                return 7;
            case 11: //BRA1
            case 12: //BRA2
                return 5;
        }
    }

    public function reserveStock($code, $amount, $reserve = true){
        /* Query para obtener el producto con su respectivo stock */
        $query = "SELECT ARTSTO, ACTSTO, DISSTO FROM F_STO WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
        $exec = $this->con->prepare($query);

        /* Query para actualizar stock */
        $query_update = "UPDATE F_STO SET DISSTO = ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
        $exec_update = $this->con->prepare($query_update);

        $exec->execute([$code]);
        $row = $exec->fetch(\PDO::FETCH_ASSOC);
        $new_stock =  $reserve ? $row["DISSTO"] - $amount : $row["DISSTO"] + $amount; //Se reserva o libera la mercancia

        return $exec_update->execute([$new_stock, $code]);
    }

    public function deleteOrder($cash, $code, $body){
        /* 1.- Liberar stocks  F_STO*/
        /* 2.- Eliminar detalles F_LPC*/
        /* 3.- Eliminar encabezado del pedido F_PCL*/
        foreach($body as $key => $row){
            $result_stock = $this->reserveStock($row["code"], $row["ordered"]["units"], false);
        }

        $query_delete_header = "DELETE FROM F_LPC WHERE TIPLPC = ? AND CODLPC = ?";
        $exec_delete_header = $this->con->prepare($query_delete_header);
        $result = $exec_delete_header->execute([$cash, $code]);

        $query_delete_header = "DELETE FROM F_PCL WHERE TIPPCL = ? AND CODPCL = ?";
        $exec_delete_header = $this->con->prepare($query_delete_header);
        $result = $exec_delete_header->execute([$cash, $code]);
    }

    public function nextOrder($serie){
        /* Se obtiene el consecutivo de los pedidos de cliente */
        $query = "SELECT TOP 1 CODPCL FROM F_PCL WHERE TIPPCL = ? ORDER BY CODPCL DESC";
        $exec = $this->con->prepare($query);
        $exec->execute([$serie]);
        $row = $exec->fetch(\PDO::FETCH_ASSOC);
        if($row){
            return intval($row["CODPCL"])+1;
        }else{
            return 1;
        }
    } 

    public function InvoiceRequired(Request $request){
        $required = $request->folio;
        $quitado = explode("-",$required);
        $tipo = $quitado[0];
        $folio = intval($quitado[1]);
        $sql = "SELECT CNOFAC, TOTFAC, FECFAC, HORFAC FROM F_FAC WHERE TIPFAC = ? AND CODFAC = ?";
        $exec = $this->con->prepare($sql);
        $exec->execute([$tipo,$folio]);
        $row = $exec->fetch(\PDO::FETCH_ASSOC);
        if($row){
            $fpa =  $this->FpaInvoice($tipo,$folio);
            $body = $this->bodyInvoice($tipo,$folio);
            $header = [
                "FECFAC"=>$row['FECFAC'],
                "HORFAC"=>$row['HORFAC'],
                "TIPFAC_DOCFAC"=>$required,
                "CNOFAC"=>$row['CNOFAC'],
                "TOTFAC"=>$row["TOTFAC"]
            ];
            return response()->json([  "header"=>$header,
                                        "body"=>$body,
                                        "payments"=>$fpa],200);
        }else{return response()->json("No existe ninguna factura",404);}
    }

    public function FpaInvoice($tipo,$folio){
        $sql = "SELECT LINLCO, FPALCO, CPTLCO,IMPLCO FROM F_LCO WHERE TFALCO = ? AND CFALCO = ?";
        $exec = $this->con->prepare($sql);
        $exec->execute([$tipo,$folio]);
        $row = $exec->fetchall(\PDO::FETCH_ASSOC);
        $colsTabProds = array_keys($row[0]);
        foreach($row as $rows){{foreach($colsTabProds as $col){ $rows[$col] = utf8_encode($rows[$col]);}}
            $fpa [] = [
                "LINLCO" =>$rows['LINLCO'],
                "FPALCO"=>$rows['FPALCO'],
                "CPTLCO"=>$rows['CPTLCO'],
                "IMPLCO"=>$rows['IMPLCO']
            ];
        }
        return $fpa;
    }

    public function BodyInvoice($tipo,$folio){
        $sql = "SELECT ARTLFA, DESLFA,CANLFA, PRELFA, TOTLFA FROM F_LFA WHERE TIPLFA = ? AND CODLFA = ?";
        $exec = $this->con->prepare($sql);
        $exec->execute([$tipo,$folio]);
        $row = $exec->fetchall(\PDO::FETCH_ASSOC);
        $colsTabProds = array_keys($row[0]);
        foreach($row as $rows){{foreach($colsTabProds as $col){ $rows[$col] = utf8_encode($rows[$col]);}}
            $body [] = [
                "ARTLFA"=>$rows['ARTLFA'],
                "DESLFA"=>$rows['DESLFA'],
                "CANLFA"=>$rows['CANLFA'],
                "PRELFA"=>$rows['PRELFA'],
                "TOTLFA"=>$rows['TOTLFA'],
            ];
        }
        return $body;
    }
}
