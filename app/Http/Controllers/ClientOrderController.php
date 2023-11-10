<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Mike42\Escpos\EscposImage;

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

    public function ticket(Request $request){
        $type = $request->serie;
        $cod = $request->folio;
        $ticket = "'".$type."-".$cod."'";

        $select = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha FROM F_FAC WHERE TIPFAC&'-'&CODFAC =".$ticket;
        $exec = $this->con->prepare($select);
        $exec->execute();
        $fil = $exec->fetch(\PDO::FETCH_ASSOC);
        if($fil){
            $tick = $type."-".$cod;
            $exist = "IVATCK-".$tick;
            $cobiv = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha  FROM F_FAC WHERE REFFAC = "."'".$exist."'";
            $exec = $this->con->prepare($cobiv);
            $exec->execute();
            $ivpag = $exec->fetch(\PDO::FETCH_ASSOC);
            if($ivpag){
                $res = [
                    "message"=>"IVA aplicado en ticket ".$ivpag['ticket'],
                    "ticketIva"=>[
                        "fecha"=>$ivpag['fecha'],
                        "ticket"=>$ivpag['ticket'],
                        "total"=>doubleval($ivpag['total']),
                    ]
                ];
                return response()->json($res,401);
            }else{
            $res = [
                "cliente"=>utf8_encode($fil['cliente']),
                "fecha"=>$fil['fecha'],
                "ticket"=>$fil['ticket'],
                "total"=>doubleval($fil['total'])
            ];
            return response()->json($res,200);}
        }else{
            $res = [
                "message"=>"No se encuentra el ticket ".$ticket
            ];
            return response()->json($res,404);
        }
    }

    public function iva(Request $request){
        $date_format = Carbon::now()->format('d/m/Y');//formato fecha factusol
        $year = Carbon::now()->format('Y');//ano de ejercicio
        $idano = Carbon::now()->format('ymd');//complemento para id de tpvsol
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $ticket = $request->ticket;//recibo ticket completo
        $fpa = $request->modes;//formas de pago
        $iva = $request->iva;//iva
        $create = $request->by;
        $efectivo = $fpa['EFE'];//valor de efectivo
        $fpaid = $fpa['DIG']['id'];//id de pago en caso de ser digital
        $fpaval = $fpa['DIG']['val'];//valor de pago en caso de ser digital
        $pago = [];//contenedor de pago
        if(($fpaid == null) && ($fpaval > 0)){
            $res = [
                "message"=>"No se envio una terminal"
            ];
            return response()->json($res,401);
        }

        $select = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC ="."'".$ticket."'";//se busca el ticket
        $exec = $this->con->prepare($select);
        $exec->execute();
        $fil = $exec->fetch(\PDO::FETCH_ASSOC);
        if($fil){

            $exist = "IVATCK-".$ticket;
            $cobiv = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha  FROM F_FAC WHERE REFFAC = "."'".$exist."'";
            $exec = $this->con->prepare($cobiv);
            $exec->execute();
            $ivpag = $exec->fetch(\PDO::FETCH_ASSOC);
            if($ivpag ){

                $res = [
                    "message"=>"IVA aplicado en ticket ".$ivpag['ticket'],
                    "ticketIva"=>[
                        "fecha"=>$ivpag['fecha'],
                        "ticket"=>$ivpag['ticket'],
                        "total"=>doubleval($ivpag['total']),
                        ]
                ];

                return response()->json($res,401);
            }else{

                $terminal = "SELECT *  FROM T_TER WHERE DESTER LIKE '%CAJAUNO%'";
                $exec = $this->con->prepare($terminal);
                $exec->execute();
                $codter = $exec->fetch(\PDO::FETCH_ASSOC);
                $nomter = $codter['DESTER'];
                $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".$idano;

                $cobmax = "SELECT MAX(CODCOB) as maxi FROM F_COB";
                $exec = $this->con->prepare($cobmax);
                $exec->execute();
                $maxcob = $exec->fetch(\PDO::FETCH_ASSOC);
                $cobro = $maxcob['maxi'] + 1;

                $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC = '1'";
                $exec = $this->con->prepare($codmax);
                $exec->execute();
                $max = $exec->fetch(\PDO::FETCH_ASSOC);
                $codigo = $max['maxi'] + 1;
                $total = $fil['TOTFAC'] * $iva;

                $contador = 1;

                if(($efectivo > 0) && ($fpaval == 0 )){
                    $pago = [["EFE"=>$efectivo]];
                }elseif(($fpaval > 0) && ($efectivo == 0)){
                    $pago = [[$fpaid=>$fpaval]];
                }elseif(($fpaval > 0) && ($efectivo > 0)){
                    $pago = [
                        [$fpaid=>$fpaval],
                        ["EFE"=>$efectivo]
                    ];
                }else{
                    $res = [
                        "message"=>"Imposible crear ticket (Pagos en 0)",
                    ];
                    return response()->json($res,401);
                }

                if(count($pago) == 2){
                    $twopay = $pago[0][$fpaid];
                    $payment = $pago[1]['EFE'];

                    $fpacod = implode(",",array_keys($pago[1]));
                    $cambio = round(($payment + $twopay) - $total,2);

                    if($payment > $total){
                        $pago[1]['EFE'] = $total - $twopay;
                    }
                    if($cambio < 0){
                        $res = [
                            "message"=>"faltante de cobro, favor de completar el monto "
                        ];
                        return response()->json($res,401);
                    }
                    $column = ["TIPFAC","CODFAC","REFFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","TOTFAC","FOPFAC","OB1FAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC"];

                    $factura = [
                        "1",
                        $codigo,
                        "IVATCK-".$ticket,
                        $date_format,
                        "GEN",
                        $fil['AGEFAC'],
                        $fil['CLIFAC'],
                        $fil['CNOFAC'],
                        $fil['CDOFAC'],
                        $fil['CPOFAC'],
                        $fil['CCPFAC'],
                        $fil['CPRFAC'],
                        $fil['TELFAC'],
                        $total,
                        $total,
                        $total,
                        $fpacod,
                        "Impuesto al valor agregado del ticket ".$ticket." creado por ".$create,
                        $date_format,
                        $hour,
                        27,
                        27,
                        1,
                        2,
                        $year,
                        $date_format,
                        1,
                        $idterminal,
                        2,
                        $codter['CODTER'],
                        $fil['DEPFAC'],
                        $payment,
                        $cambio,
                        $twopay
                    ];
                    $header = [
                        "terminal"=>$nomter,
                        "fecha"=>$date_format,
                        "hora"=>$horad,
                        "nomcli"=>$fil['CNOFAC'],
                        "direccion"=>$fil['CDOFAC']." ".$fil['CPOFAC'],
                        "nose"=>$fil['CPRFAC'],
                        "dependiente"=>$create,
                        "total"=>$total,
                        "observacion"=>$ticket,
                        "cambio"=>$cambio
                    ];
                }else{
                    $fpafin = array_keys($pago[0]);
                    $fpacod = implode(",",array_keys($pago[0]));
                    if($fpafin == ["EFE"]){
                        $payment = $pago[0]['EFE'];
                        $cambio = round($payment  - $total , 2);
                        if($payment > $total){
                            $pago[0]['EFE'] = $total;
                        }
                    }else{
                        $payment = $pago[0][$fpaid];
                        $cambio = round($payment - $total,2);
                    }
                    if($cambio < 0){
                        return response("faltante de cobro, favor de completar el monto",401);
                    }
                    $column = ["TIPFAC","CODFAC","REFFAC", "FECFAC", "ALMFAC", "AGEFAC", "CLIFAC", "CNOFAC", "CDOFAC", "CPOFAC", "CCPFAC", "CPRFAC", "TELFAC", "NET1FAC", "BAS1FAC", "TOTFAC", "FOPFAC", "OB1FAC", "VENFAC", "HORFAC", "USUFAC", "USMFAC", "TIVA2FAC", "TIVA3FAC", "EDRFAC", "FUMFAC", "BCOFAC",  "TPVIDFAC",  "ESTFAC",  "TERFAC",  "DEPFAC",  "EFEFAC",  "CAMFAC"];
                    $factura = [
                        "1",
                        $codigo,
                        "IVATCK-".$ticket,
                        $date_format,
                        "GEN",
                        $fil['AGEFAC'],
                        $fil['CLIFAC'],
                        $fil['CNOFAC'],
                        $fil['CDOFAC'],
                        $fil['CPOFAC'],
                        $fil['CCPFAC'],
                        $fil['CPRFAC'],
                        $fil['TELFAC'],
                        $total,
                        $total,
                        $total,
                        $fpacod,
                        "Impuesto al valor agregado del ticket ".$ticket." creado por ".$create,
                        $date_format,
                        $hour,
                        27,
                        27,
                        1,
                        2,
                        $year,
                        $date_format,
                        1,
                        $idterminal,
                        2,
                        $codter['CODTER'],
                        $fil['DEPFAC'],
                        $payment,
                        $cambio,
                    ];

                    $header = [
                        "terminal"=>$nomter,
                        "fecha"=>$date_format,
                        "hora"=>$horad,
                        "nomcli"=>$fil['CNOFAC'],
                        "direccion"=>$fil['CDOFAC']." ".$fil['CPOFAC'],
                        "nose"=>$fil['CPRFAC'],
                        "dependiente"=>$create,
                        "total"=>$total,
                        "observacion"=>$ticket,
                        "cambio"=>$cambio
                    ];
                }

                $impcol = implode(",",$column);
                $signos = implode(",",array_fill(0, count($column),'?'));

                $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
                $exec = $this->con->prepare($sql);
                $exec -> execute($factura);

                $folio = "1"."-".str_pad($codigo, 6, "0", STR_PAD_LEFT);//se obtiene el folio de la factura
                $header['ticket'] = $folio;
                $insva = [
                    1,
                    $codigo,
                    1,
                    "IVA",
                    "IMPUESTO AL VALOR AGREGADO",
                    1,
                    $total,
                    $total,
                    0
                ];

                $header['precioart']=$total;

                $product = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES (?,?,?,?,?,?,?,?,?)";
                $exec = $this->con->prepare($product);
                $exec -> execute($insva);//envia el arreglo

                foreach($pago as $row){

                    $codfpa = implode(array_keys($row));
                    $valfpa = implode(array_values($row));

                    $cobcod = "SELECT *  FROM F_FPA WHERE CODFPA ="."'".$codfpa."'";
                    $exec = $this->con->prepare($cobcod);
                    $exec->execute();
                    $codigocobro = $exec->fetch(\PDO::FETCH_ASSOC);

                    $faclco = "INSERT INTO F_LCO (TFALCO,CFALCO,LINLCO,FECLCO,IMPLCO,CPTLCO,FPALCO,MULLCO,TPVIDLCO,TERLCO) VALUES (?,?,?,?,?,?,?,?,?,?) ";
                    $exec = $this->con->prepare($faclco);
                    $exec->execute([1,$codigo,$contador,$date_format,$valfpa,$codigocobro['DESFPA'],$codfpa,$cobro,$idterminal,$codter['CODTER']]);

                    $inscob = "INSERT INTO F_COB (CODCOB,FECCOB,IMPCOB,CPTCOB) VALUES (?,?,?,?)";
                    $exec = $this->con->prepare($inscob);
                    $exec->execute([$cobro,$date_format,$valfpa,$codigocobro['DESFPA']]);
                    $cobro++;
                    $contador++;
                    if($codfpa == 'EFE'){
                        $valfpa  = $payment;
                    }
                    $pagoenv[] = [
                        $codigocobro['DESFPA']=>$valfpa
                    ];
                }
                $header['desfpa'] = $codigocobro['DESFPA'];
                $header['tipfpa']   = $pagoenv;

                $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - 1, DISSTO = DISSTO - 1 WHERE ARTSTO = 'IVA' AND ALMSTO = 'GEN'";
                $exec = $this->con->prepare($updatestock);
                $exec->execute();
                $impresion = $this->printticket($header);
                if($impresion == null){
                    $impresion = false;
                }else{
                    $impresion = true;
                }
                $res = [
                    "ticket"=>$folio,
                    "ivaPagado"=>$total,
                    "cambio"=>$cambio,
                    "printed"=>$impresion
                ];

                return response()->json($res,200);
            }
        }else{
            $res = [
                "message"=>"No se encuentra el ticket ".$ticket
            ];
            return response()->json($res,404);
        }
    }

    public function printticket($header){
        $documento = env('DOCUMENTO');
        $imagen = env('IMAGENLOCAL');
        $printers = env('IPCAJA');
        $sql = "SELECT CTT1TPV, CTT2TPV, CTT3TPV, CTT4TPV, CTT5TPV, PTT1TPV, PTT2TPV, PTT3TPV, PTT4TPV, PTT5TPV, PTT6TPV, PTT7TPV, PTT8TPV FROM T_TPV WHERE CODTPV = $documento";
        $exec = $this->con->prepare($sql);
        $exec->execute();
        $text = $exec->fetch(\PDO::FETCH_ASSOC);//OK
        try{
            $connector = new NetworkPrintConnector($printers, 9100, 3);
            $printer = new Printer($connector);
        }catch(\Exception $e){ return null;}

            try {
                try{
                    if(file_exists($imagen)){
                    $logo = EscposImage::load($imagen, false);
                    $printer->bitImage($logo);
                    }
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("------------------------------------\n");
                    $printer->text(" \n");
                    $printer->text($text["CTT1TPV"]."\n");
                    $printer->text($text["CTT2TPV"]." \n");
                    $printer->text($text["CTT3TPV"]." \n");
                    $printer->text($text["CTT4TPV"]." \n");
                    $printer->text($text["CTT5TPV"]." \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text($header['terminal']." \n");
                    $printer->text("N° ".$header['ticket']." Fecha: ".$header["fecha"]." ".$header["hora"] ." \n");
                    $printer->text("Forma de Pago: ".$header["desfpa"]." \n");
                    $printer->text($header["nomcli"]." \n");
                    $printer->text($header["direccion"]." \n");
                    $printer->text($header["nose"]." \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->text("  Concepto                             TOTAL \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text("IVA"."   "."IMPUESTO AL VALOR AGREGADO"."      $".str_pad(number_format($header["precioart"],2),10)." \n");
                    // $printer->text("                                    "."  $".str_pad(number_format($header["precioart"],2),10). " \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->setEmphasis(true);
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text("                             TOTAL: ");
                    $printer->text("$".number_format($header["total"],2)." \n");
                    $printer->text(" \n");
                    foreach($header['tipfpa'] as $pgo){
                        $codfpa = implode(array_keys($pgo));
                        $valfpa = implode(array_values($pgo));
                        $printer->setEmphasis(false);
                        $printer->text("            ".str_pad($codfpa,22).": $".number_format($valfpa,2)." \n");
                    }
                    if($header['cambio'] > 0){
                    $printer->text("            ".str_pad("Cambio",22).": $".number_format($header['cambio'],2)." \n");
                    }
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("Le atendio :".$header["dependiente"]." \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("TICKET : ".$header["observacion"]." \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text($text["PTT1TPV"]." \n");
                    $printer->text($text["PTT2TPV"]." \n");
                    $printer->text($text["PTT3TPV"]." \n");
                    $printer->text($text["PTT4TPV"]." \n");
                    $printer->text($text["PTT5TPV"]." \n");
                    $printer->text(mb_convert_encoding($text["PTT6TPV"],'UTF-8')." \n");
                    $printer->text($text["PTT7TPV"]." \n");
                    $printer->text($text["PTT8TPV"]." \n");
                    $printer -> cut();
                    $printer -> close();
                }catch(Exception $e){}

            } finally {
                $printer -> close();
            }
            return "TICKET IMPRIMIDO";



    }


    public function especialprice(Request $request){
        $goal =[];
        $fail = [];

        // $exec->fetch(\PDO::FETCH_ASSOC);
        $cliente = $request->cliente;
        $fecha = $request->fecha;

        $lfaupdat = "UPDATE ((F_LFA
        INNER JOIN F_FAC ON F_LFA.TIPLFA&'-'&F_LFA.CODLFA = F_FAC.TIPFAC&'-'&F_FAC.CODFAC)
        INNER JOIN F_PRC ON F_PRC.ARTPRC = F_LFA.ARTLFA)
        SET F_LFA.PRELFA = F_PRC.PREPRC, F_LFA.TOTLFA = F_LFA.CANLFA * F_PRC.PREPRC
        WHERE F_FAC.CLIFAC = ".$cliente." AND F_PRC.CLIPRC = ".$cliente." AND F_FAC.FECFAC >= #".$fecha."#";

        $exec = $this->con->prepare($lfaupdat);
        $result = $exec->execute();

        $selectlfa = "SELECT
        F_FAC.TIPFAC&'-'&F_FAC.CODFAC AS CODIGO,
        SUM(F_LFA.TOTLFA) AS TOTAL
        FROM F_FAC
        INNER JOIN F_LFA ON F_LFA.TIPLFA&'-'&F_LFA.CODLFA = F_FAC.TIPFAC&'-'&F_FAC.CODFAC
        WHERE F_FAC.CLIFAC = ".$cliente." AND F_FAC.FECFAC = #".$fecha."#
        GROUP BY F_FAC.TIPFAC&'-'&F_FAC.CODFAC";

        $exec = $this->con->prepare($selectlfa);
        $exec->execute();
        $rows = $exec->fetchall(\PDO::FETCH_ASSOC);

        foreach($rows as $row){
            $factura = "'".$row['CODIGO']."'";
            $total = $row['TOTAL'];
            $updatefac = "UPDATE F_FAC SET NET1FAC = ".$total.",BAS1FAC = ".$total.", TOTFAC = ".$total.", EFEFAC =".$total."  WHERE F_FAC.TIPFAC&'-'&F_FAC.CODFAC = ".$factura;
            $exec = $this->con->prepare($updatefac);
            $exec->execute();

            $updatelco = "UPDATE F_LCO SET IMPLCO = ".$total." WHERE TFALCO&'-'&CFALCO = ".$factura;
            $exec = $this->con->prepare($updatelco);
            $exec->execute();
        }
        return "llleimememes";





    }

    public function getTicket(Request $request){
        $type = $request->serie;
        $cod = $request->folio;
        $ticket = "'".$type."-".$cod."'";
        $select = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha FROM F_FAC WHERE TIPFAC&'-'&CODFAC =".$ticket;
        $exec = $this->con->prepare($select);
        $exec->execute();
        $fil = $exec->fetch(\PDO::FETCH_ASSOC);
        if($fil){
            $tick = $type."-".$cod;
            $exist = "MDV-".$tick."%";
            $cobiv = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha  FROM F_FAC WHERE TDRFAC&'-'&CDRFAC = ".$ticket;
            $exec = $this->con->prepare($cobiv);
            $exec->execute();
            $ivpag = $exec->fetch(\PDO::FETCH_ASSOC);
            if($ivpag){
                $res = [
                    "message"=>"Ticket Modificado en ticket ".$ivpag['ticket'],
                    "ticketIva"=>[
                        "fecha"=>$ivpag['fecha'],
                        "ticket"=>$ivpag['ticket'],
                        "total"=>doubleval($ivpag['total']),
                    ]
                ];
                return response()->json($res,401);
            }else{
            $res = [
                "cliente"=>utf8_encode($fil['cliente']),
                "fecha"=>$fil['fecha'],
                "ticket"=>$fil['ticket'],
                "total"=>doubleval($fil['total'])
            ];
            return response()->json($res,200);}
        }else{
            $res = [
                "message"=>"No se encuentra el ticket ".$ticket
            ];
            return response()->json($res,404);
        }
    }
}

