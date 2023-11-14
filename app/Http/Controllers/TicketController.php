<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\DB;

class TicketController extends Controller{
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

    public function getPrinter(){
        $pri = env('IPCAJA');
        $wmi = new \COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');
        $printerQuery = $wmi->ExecQuery('SELECT * FROM Win32_Printer');

        foreach ($printerQuery as $printer) {
            $impr []= [
                "name"=>$printer->Name,
                "ip"=>$this->getPrinterIPAddress($printer->PortName)
            ];
        }
        $print = array_filter($impr, function($e){
            return $e['ip'] <> "No disponible";
        });

        if($print){
            $imp = $print;
        }else{
            $imp =  [[
                "name"=>"IMP DEFAULT",
                "ip"=>$pri
            ]];
        }
        return response()->json($imp,200);
    }

    private function getPrinterIPAddress($portName){
        $wmi = new \COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');
        $portQuery = $wmi->ExecQuery("SELECT * FROM Win32_TCPIPPrinterPort WHERE HostAddress = '$portName'");

        foreach ($portQuery as $port) {
            return $port->HostAddress;
        }

        return 'No disponible';
    }

    public function newMod(Request $request){
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $fecha =  date("d/m/Y");
        $tipo = $request->type;
        $ticket = $request->serie."-".$request->folio;
        if($tipo == "Devolucion"){
            $existck = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket."'";
            $exec = $this->con->prepare($existck);
            $exec->execute();
            $tck = $exec->fetch(\PDO::FETCH_ASSOC);
            if($tck){
                $cobiv = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha  FROM F_FAC WHERE TDRFAC&'-'&CDRFAC = "."'".$ticket."'";
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
                    if($tck['TOTFAC'] > 0){
                        if(date("d/m/Y",strtotime($tck['FECFAC'])) == $fecha){
                            $fpa = "SELECT LINLCO, IMPLCO * - 1 AS IMPORTE, CPTLCO, FPALCO FROM F_LCO WHERE TFALCO&'-'&CFALCO = "."'".$ticket."'";
                            $exec = $this->con->prepare($fpa);
                            $exec->execute();
                            $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
                        }else{
                            $fpas = [['CPTLCO'=>"CONTADO EFECTIVO", 'FPALCO'=>"EFE",'IMPORTE'=>$tck['TOTFAC'] * - 1 , 'LINLCO' => 1]];
                        }
                        if(count($fpas) != 1){
                            $fpa2 = $fpas[0]['IMPORTE'];
                            $fpa1 = $fpas[1]['IMPORTE'];
                        }else{
                            $fpa1 = $fpas[0]['IMPORTE'];
                            $fpa2 = 0;
                        }
                        $prd = "SELECT * FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$ticket."'";
                        $exec = $this->con->prepare($prd);
                        $exec->execute();
                        $products = $exec->fetchall(\PDO::FETCH_ASSOC);
                        if($products){
                            foreach($products as $key => $product){
                                $products[$key]['CANLFA'] *= - 1;
                                $products[$key]['TOTLFA'] *= - 1;
                            }
                            $terminal = "SELECT T_TER.*  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER   WHERE T_DOC.TIPDOC = ".$request->serie;
                            $exec = $this->con->prepare($terminal);
                            $exec->execute();
                            $codter = $exec->fetch(\PDO::FETCH_ASSOC);
                            $nomter = $codter['DESTER'];
                            $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".date('ymd');

                            $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC = '9'";
                            $exec = $this->con->prepare($codmax);
                            $exec->execute();
                            $max = $exec->fetch(\PDO::FETCH_ASSOC);
                            $codigo = $max['maxi'] + 1;
                            $total = $tck['TOTFAC'] * -1;

                            $cobmax = "SELECT MAX(CODCOB) as maxi FROM F_COB";
                            $exec = $this->con->prepare($cobmax);
                            $exec->execute();
                            $maxcob = $exec->fetch(\PDO::FETCH_ASSOC);
                            $cobro = $maxcob['maxi'] + 1;
                            $column = ["TIPFAC","CODFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","TOTFAC","FOPFAC","PRIFAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC","TDRFAC","CDRFAC"];
                            $factura = [
                                "9",//
                                $codigo,//
                                $fecha,
                                "GEN",
                                $tck['AGEFAC'],
                                $tck['CLIFAC'],
                                $tck['CNOFAC'],
                                $tck['CDOFAC'],
                                $tck['CPOFAC'],
                                $tck['CCPFAC'],
                                $tck['CPRFAC'],
                                $tck['TELFAC'],
                                $total,
                                $total,
                                $total,
                                $tck['FOPFAC'],
                                "Devolcion por : ".$request->mot." de el ticket : ".$ticket." creado por : ".$request->create,
                                $fecha,
                                $hour,
                                27,
                                27,
                                1,
                                2,
                                date('Y'),
                                $fecha,
                                1,
                                $idterminal,
                                2,
                                intval($codter['CODTER']),
                                intval($tck['DEPFAC']),
                                $fpa1,
                                0,
                                $fpa2 ,
                                $request->serie,
                                intval($request->folio)
                            ];
                            $impcol = implode(",",$column);
                            $signos = implode(",",array_fill(0, count($column),'?'));
                            $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
                            $exec = $this->con->prepare($sql);
                            $res = $exec -> execute($factura);
                            if($res){
                                foreach($products as $product){
                                    $upd = [
                                        $product['CANLFA'],
                                        $product['CANLFA'],
                                        $product['ARTLFA'],
                                    ];
                                    $ins = [
                                        "9",
                                        $codigo,
                                        $product['POSLFA'],
                                        $product['ARTLFA'],
                                        $product['DESLFA'],
                                        $product['CANLFA'],
                                        $product['PRELFA'],
                                        $product['TOTLFA'],
                                        $product['COSLFA'],
                                    ];
                                    $product = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES (?,?,?,?,?,?,?,?,?)";
                                    $exec = $this->con->prepare($product);
                                    $exec -> execute($ins);


                                    $updatesto = "UPDATE F_STO SET DISSTO = DISSTO + ? , ACTSTO = ACTSTO + ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
                                    $exec = $this->con->prepare($updatesto);
                                    $exec -> execute($upd);

                                }
                                $count = 1;
                                foreach($fpas as $pag){
                                    $inspg = [
                                        "9",
                                        $codigo,
                                        $count,
                                        $fecha,
                                        $pag['IMPORTE'],
                                        $pag['CPTLCO'],
                                        $pag['FPALCO'],
                                        $cobro,
                                        $idterminal,
                                        $codter['CODTER']
                                    ];
                                    $faclco = "INSERT INTO F_LCO (TFALCO,CFALCO,LINLCO,FECLCO,IMPLCO,CPTLCO,FPALCO,MULLCO,TPVIDLCO,TERLCO) VALUES (?,?,?,?,?,?,?,?,?,?) ";
                                    $exec = $this->con->prepare($faclco);
                                    $exec->execute($inspg);
                                    $count++;
                                    $cobro++;

                                }
                                $header = [
                                    "terminal"=>"CAMBIOS Y DEVOLUCIONES",
                                    "ticket"=>"9-".str_pad($codigo,6,0,STR_PAD_LEFT),
                                    "fecha"=>$fecha,
                                    "hora"=>$horad,
                                    "nomcli"=>$tck['CNOFAC'],
                                    "direccion"=>$tck['CDOFAC']." ".$tck['CPOFAC'],
                                    "nose"=>$tck['CPRFAC'],
                                    "dependiente"=>$request->create,
                                    "total"=>$total,
                                    "observacion"=>"Devolucion del TICKET => ".$ticket,
                                    "cambio"=>0,
                                    "products"=>$products,
                                    "pagos"=>$fpas,
                                    "desfpa"=>$fpas[0],
                                    "impresora"=>$request->print
                                ];
                               $print = $this->printck($header);
                               if($print){
                                $res = [
                                    "mssg"=>"Devolucion :".$header['ticket'],
                                ];
                            }else{
                                $res = [
                                    "mssg"=>"No se logro imprimir la devolucion ".$header['ticket'],
                                ];
                            }
                            return response()->json($res);
                            }else{
                                return response()->json("Hubo un error no se pudo generar el documento :(",401);
                            }
                        }else{
                            return response()->json("El documento no contiene productos",401);
                        }
                    }else{
                        return response()->json("El ticket es menor o igual a 0",401);
                    }

                }
            }else{
                return response()->json("No se logro encontrar el ticket :(",404);
            }
            // return mb_convert_encoding($tck,'UTF-8');
        }else if($tipo == "Reimpresion"){
            $existck = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket."'";
            $exec = $this->con->prepare($existck);
            $exec->execute();
            $tck = $exec->fetch(\PDO::FETCH_ASSOC);
            if($tck){
                $fpa = "SELECT LINLCO, IMPLCO AS IMPORTE, CPTLCO, FPALCO FROM F_LCO WHERE TFALCO&'-'&CFALCO = "."'".$ticket."'";
                $exec = $this->con->prepare($fpa);
                $exec->execute();
                $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);

                $prd = "SELECT * FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$ticket."'";
                $exec = $this->con->prepare($prd);
                $exec->execute();
                $products = $exec->fetchall(\PDO::FETCH_ASSOC);
                if($request->serie == 9){
                    $nomter = "CAMBIOS Y DEVOLUCIONES";
                }else{
                    $terminal = "SELECT T_TER.*  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER   WHERE T_DOC.TIPDOC = ".$request->serie;
                    $exec = $this->con->prepare($terminal);
                    $exec->execute();
                    $codter = $exec->fetch(\PDO::FETCH_ASSOC);
                    $nomter = $codter['DESTER'];
                }



                $header = [
                "terminal"=>$nomter,
                "ticket"=>$request->serie."-".str_pad($request->folio,6,0,STR_PAD_LEFT),
                "fecha"=>date('d-m-Y',strtotime($tck['FECFAC'])),
                "hora"=>explode(" ", $tck['HORFAC'])[1],
                "nomcli"=>$tck['CNOFAC'],
                "direccion"=>$tck['CDOFAC']." ".$tck['CPOFAC'],
                "nose"=>$tck['CPRFAC'],
                "dependiente"=>$tck['DEPFAC'],//nombre
                "total"=>$tck['TOTFAC'],
                "observacion"=>$tck['OB1FAC'],
                "cambio"=>$tck['CAMFAC'],
                "products"=>$products,
                "pagos"=>$fpas,
                "desfpa"=>$fpas[0],
                "impresora"=>$request->print
                ];
                $print = $this->printck($header);
                if($print){
                    $res = [
                        "mssg"=>"Reimpresion Correcta"
                    ];
                }else{
                    $res = [
                        "mssg"=>"No se pudo realizar la Reimpresion"
                    ];
                }
                return response()->json($res);


            }else{
                return response()->json("No se logro encontrar el ticket :(",404);
            }


        }else if($tipo == "Modificacion"){

            $existck = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , CLIFAC AS codcli, Format(FECFAC, 'Short Date') as fecha, OB1FAC AS observacion FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket."'";
            $exec = $this->con->prepare($existck);
            $exec->execute();
            $tck = $exec->fetch(\PDO::FETCH_ASSOC);
            if($tck){
                $cobiv = "SELECT TIPFAC&'-'&CODFAC as ticket, TOTFAC as total, CNOFAC AS cliente , FECFAC as fecha  FROM F_FAC WHERE TDRFAC&'-'&CDRFAC = "."'".$ticket."'";
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
                    $fpa = "SELECT LINLCO, IMPLCO , CPTLCO, FPALCO FROM F_LCO WHERE TFALCO&'-'&CFALCO = "."'".$ticket."'";
                    $exec = $this->con->prepare($fpa);
                    $exec->execute();
                    $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);

                    $prd = "SELECT ARTLFA AS ARTICULO, DESLFA AS DESCRIPCION, CANLFA AS CANTIDAD, PRELFA AS PRECIO, TOTLFA AS TOTAL FROM F_LFA WHERE TIPLFA&'-'&CODLFA = "."'".$ticket."'";
                    $exec = $this->con->prepare($prd);
                    $exec->execute();
                    $products = $exec->fetchall(\PDO::FETCH_ASSOC);

                    $res = [
                        "ticket"=>$tck,
                        "product"=>$products,
                        "pagos"=>$fpas,
                    ];
                    return response()->json($res,200);
                }
            }else{
                return response()->json("El ticket no existe",404);
            }
        }

    }

    public function printck($header){
        $documento = env('DOCUMENTO');
        $printers = $header['impresora'];
        // $printers = "192.168.10.224";
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
                    // if(file_exists($imagen)){
                    // $logo = EscposImage::load($imagen, false);
                    // $printer->bitImage($logo);
                    // }
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("------------------------------------------------\n");
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
                    $printer->text("Forma de Pago: ".mb_convert_encoding($header["desfpa"]['CPTLCO'],'UTF-8')." \n");
                    $printer->text($header["nomcli"]." \n");
                    $printer->text($header["direccion"]." \n");
                    $printer->text($header["nose"]." \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->text("ARTICULO        UD.        PRECIO        TOTAL \n");
                    $printer->text("_______________________________________________ \n");
                    $printer -> setFont(Printer::FONT_B);
                    foreach($header['products'] as $product){
                        $printer->setJustification(printer::JUSTIFY_LEFT);
                               $printer->text($product['ARTLFA']."   ".$product['DESLFA']." \n");
                               $printer->setJustification(printer::JUSTIFY_RIGHT);
                               $quantity = str_pad(number_format($product['CANLFA'],2,'.',''),15);
                               $arti [] = $product['CANLFA'];
                               $price = str_pad(number_format($product['PRELFA'],2,'.',''),15);
                               $total = str_pad(number_format($product['TOTLFA'],2,'.',''),10);
                               $printer->text($quantity." ".$price."  ".$total." \n");

                    }
                    $printer -> setFont(Printer::FONT_A);
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->setJustification(printer::JUSTIFY_RIGHT);
                    $printer->setEmphasis(true);
                    $printer->text(str_pad("TOTAL: ",15));
                    $printer->text("$".number_format($header["total"],2)." \n");
                    $printer->text(" \n");
                    $printer->setEmphasis(false);
                    foreach($header['pagos'] as $pago){
                        $despa = $pago['FPALCO'] == 'EFE' ? "Efectivo:" : $pago['CPTLCO'].":";
                        // $padding = 54 - strlen($despa);
                        $printer->text(mb_convert_encoding($despa,'UTF-8'));
                        $printer->text(str_pad('',11,' '));
                        $printer->text(str_pad("$".number_format($pago['IMPORTE'],2),-13)." \n");
                    }
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text(" \n");
                    $printer->text("N Articulos: ".array_sum($arti)." \n");
                    $printer->text(" \n");
                    $printer->text("Le atendio :".$header["dependiente"]." \n");
                    $printer->text(" \n");
                    $printer->text($header["observacion"]." \n");
                    $printer->text("-------------------Grupo-Vizcarra---------------"." \n");
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
                return true;
            }
                return false;
    }


}
