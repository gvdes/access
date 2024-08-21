<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\DB;
use Mike42\Escpos\EscposImage;

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
            foreach($print as $prn){
                $imp[] = $prn;
            }

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
            $ala = $this->devolucion($ticket,$request->serie,$request->mot,$request->create,$request->folio,$request->print);
            $status = $ala->original['err'] == false ? 200 : 401;
            return response()->json($ala,$status);
        }else if($tipo == "Reimpresion"){
            $existck = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket."'";
            $exec = $this->con->prepare($existck);
            $exec->execute();
            $tck = $exec->fetch(\PDO::FETCH_ASSOC);
            if($tck){
                $fpa = "SELECT LINLCO, IMPLCO AS IMPORTE, CPTLCO, FPALCO FROM F_LCO WHERE TFALCO&'-'&CFALCO = "."'".$ticket."'";
                // $fpa = "SELECT EFEFAC, EFSFAC, EFVFAC FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket."'";
                $exec = $this->con->prepare($fpa);
                $exec->execute();
                $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
                $inx = array_search('EFE', array_column($fpas,'FPALCO'));
                if(is_numeric($inx)){
                    $fpas[$inx]['IMPORTE'] = $tck['EFEFAC'] ;
                }
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
                "desfpa"=>isset($fpas)? ['CPTLCO'=>'CONTADO EFECTIVO', 'FPALCO'=>'EFE','IMPORTE'=>$tck['TOTFAC'] , 'ANTLCO'=>''] : $fpas[0],
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
                    return response()->json( mb_convert_encoding($res,'UTF-8'),200);
                }
            }else{
                return response()->json("El ticket no existe",404);
            }
        }

    }

    public function modificacion(Request $request){
        $tipo = $request->type;
        $ticket = $request->serie."-".$request->folio;
        $ala = $this->devolucion($ticket,$request->serie,$request->mot,$request->create,$request->folio,$request->print);
        // $this->devolucion($ticket,$request->serie,$request->mot,$request->create,$request->folio,$request->print);
        return $ala;
    }

    public function nwtck(Request $request){
        $primp =[];
        $date = date("Y/m/d H:i");
        $hour = "01/01/1900 ".explode(" ", $date)[1];
        $horad = explode(" ", $date)[1];
        $fecha =  date("d/m/Y");
        $all = $request->all();
        $create = $all['create'];
        $productos = $all['productos'];
        $print = $all['print'];
        $tckdev = $all['serdev'].'-'.$all['foldev'];
        $total = $all['total'];
        $serie = $all['serdev'];
        $cambio = $all['cambio'];
        $fdps = $all['fdp'];
        $formas = $fdps['efedig'];
        $newFormas = [];
        $efeIndex = null;
        $rescam = $cambio;
        foreach ($formas as $index => $forma) {
            if (is_array($forma) && isset($forma['id']) && isset($forma['id']['desc']) && isset($forma['id']['id']) && isset($forma['val'])) {
            if ($forma['id']['id'] === 'EFE') {
                $efeIndex = $index;
                break;
            }
        }
        }

        if ($efeIndex !== null) {
            $forma = $formas[$efeIndex];
            $desc = $forma['id']['desc'];
            $id = $forma['id']['id'];

            if ($forma['val'] >= $rescam) {
                $importe = $forma['val'] - $rescam;
                $rescam = 0;
            } else {
                $importe = 0;
                $rescam -= $forma['val'];
            }

            $newFormas[] = [
                'CPTLCO' => $desc,
                'FPALCO' => $id,
                'IMPORTE' => $importe,
                'ANTLCO' => 0
            ];
        }

        foreach ($formas as $index => $forma) {
            if (is_array($forma) && isset($forma['id']) && isset($forma['id']['desc']) && isset($forma['id']['id']) && isset($forma['val'])) {
                if ($index !== $efeIndex) {
                    $desc = $forma['id']['desc'];
                    $id = $forma['id']['id'];

                    if ($rescam > 0) {
                        if ($forma['val'] >= $rescam) {
                            $importe = $forma['val'] - $rescam;
                            $rescam = 0;
                        } else {
                            $importe = 0;
                            $rescam -= $forma['val'];
                        }
                    } else {
                        $importe = $forma['val'];
                    }

                    $newFormas[] = [
                        'CPTLCO' => $desc,
                        'FPALCO' => $id,
                        'IMPORTE' => $importe,
                        'ANTLCO' => 0
                    ];
                }
            }
        }
        $clifac = $all['cliente'];
        $val = $fdps['vale'];// cobro de vale
        if($val <> null){
            doubleval($valv=$fdps['vale']['IMPANT']);
            $newFormas[] = ['CPTLCO'=>'[V]', 'FPALCO'=>'VALE Nº: '.$val['CODANT'],'IMPORTE'=>$valv , 'ANTLCO'=>$val['CODANT']];
        }else{
            $valv=0;
        }
        $exisdev = "SELECT * FROM F_FAC WHERE TDRFAC&'-'&CDRFAC ="."'".$tckdev."'";
        $exec = $this->con->prepare($exisdev);
        $exec->execute();
        $devolucion = $exec->fetch(\PDO::FETCH_ASSOC);
        if($devolucion){
            $codmax = "SELECT MAX(CODFAC) as maxi FROM F_FAC WHERE TIPFAC ="."'".$serie."'";//maximo ticket
            $exec = $this->con->prepare($codmax);
            $exec->execute();
            $max = $exec->fetch(\PDO::FETCH_ASSOC);
            $codigo = $max['maxi'] + 1;

            $cobmax = "SELECT MAX(CODCOB) as maxi FROM F_COB";
            $exec = $this->con->prepare($cobmax);
            $exec->execute();
            $maxcob = $exec->fetch(\PDO::FETCH_ASSOC);
            $cobro = $maxcob['maxi'] + 1;

            $terminal = "SELECT T_TER.*  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER   WHERE T_DOC.TIPDOC = ".$serie;
            $exec = $this->con->prepare($terminal);
            $exec->execute();
            $codter = $exec->fetch(\PDO::FETCH_ASSOC);
            $nomter = $codter['DESTER'];
            $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".date('ymd');

            $client =  "SELECT CODCLI, NOFCLI, DOMCLI, POBCLI, CPOCLI, PROCLI, TELCLI FROM F_CLI WHERE CODCLI = $clifac";
            $exec = $this->con->prepare($client);
            $exec->execute();
            $ncli = $exec->fetch(\PDO::FETCH_ASSOC);

            if($val <> null){
                $ups = [
                    1,
                    1,
                    $serie,
                    $codigo,
                    $val['CODANT']
                ];
                $updval = "UPDATE F_ANT SET ESTANT = ?, DOCANT = ?, TDOANT = ?, CDOANT = ? WHERE CODANT = ?";
                $exec = $this->con->prepare($ups);
                $exec->execute($updval);
            }
            $column = ["TIPFAC","CODFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","TOTFAC","FOPFAC","PRIFAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC","TDRFAC","CDRFAC","EFVFAC"];
            $factura = [
                $serie,//
                $codigo,//
                $fecha,
                "GEN",
                $devolucion['AGEFAC'],
                $ncli['CODCLI'],
                $ncli['NOFCLI'],
                $ncli['DOMCLI'],
                $ncli['POBCLI'],
                $ncli['CPOCLI'],
                $ncli['PROCLI'],
                $ncli['TELCLI'],
                $total,
                $total,
                $total,
                $newFormas[0]['FPALCO'] ,
                "Ticket nuevo por : devolucion".$tckdev." de el ticket : ".$devolucion['TIPFAC'].'-'.$devolucion['CODFAC']." creado por : ".$create,
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
                intval($devolucion['DEPFAC']),
                $newFormas[0]['IMPORTE'] + $cambio,
                $cambio,
                isset($newFormas[1]['IMPORTE']) ? $newFormas[1]['IMPORTE'] : 0,
                $devolucion['TIPFAC'],
                intval($devolucion['CODFAC']),
                $valv
            ];
            $impcol = implode(",",$column);
            $signos = implode(",",array_fill(0, count($column),'?'));
            $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
            $exec = $this->con->prepare($sql);
            $res = $exec -> execute($factura);
            if($res){
                $contap = 1;
                foreach($productos as $product){
                    $primp [] = [
                        "ARTLFA"=>$product['ARTICULO'],
                        "DESLFA"=>$product['DESCRIPCION'],
                        "CANLFA"=>$product['CANTIDAD'],
                        "PRELFA"=>$product['PRECIO'],
                        "TOTLFA"=>$product['PRECIO'] * $product['CANTIDAD'],
                    ];
                    $costo = "SELECT PCOART FROM F_ART WHERE CODART = "."'".$product['ARTICULO']."'";
                    $exec = $this->con->prepare($costo);
                    $exec->execute();
                    $pcoart = $exec->fetch(\PDO::FETCH_ASSOC);

                    $upd = [
                        $product['CANTIDAD'],
                        $product['CANTIDAD'],
                        $product['ARTICULO'],
                    ];

                    $inspro = [
                        $serie,
                        $codigo,
                        $contap,
                        $product['ARTICULO'],
                        $product['DESCRIPCION'],
                        intval($product['CANTIDAD']),
                        doubleval($product['PRECIO']),
                        doubleval($product['PRECIO'] * $product['CANTIDAD']),
                        $pcoart['PCOART']
                    ];

                    $insertapro = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA) VALUES(?,?,?,?,?,?,?,?,?)";
                    $exec = $this->con->prepare($insertapro);
                    $exec->execute($inspro);

                    $updatesto = "UPDATE F_STO SET DISSTO = DISSTO - ? , ACTSTO = ACTSTO - ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
                    $exec = $this->con->prepare($updatesto);
                    $exec -> execute($upd);
                    $contap++;
                }
                $count = 1;
                foreach($newFormas as $fip){
                    if($fip['IMPORTE'] == 0){

                    }else{
                        $inspg = [
                            $serie,
                            $codigo,
                            $count,
                            $fecha,
                            $fip['IMPORTE'],
                            $fip['CPTLCO'],
                            $fip['FPALCO'],
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

                }
                $header = [
                    "terminal"=>$nomter,
                    "ticket"=>$serie."-".str_pad($codigo,6,0,STR_PAD_LEFT),
                    "fecha"=>$fecha,
                    "hora"=>$horad,
                    "nomcli"=>$ncli['NOFCLI'],
                    "direccion"=>$ncli['DOMCLI']." ".$ncli['POBCLI'],
                    "nose"=>$ncli['PROCLI'],
                    "dependiente"=>$create,
                    "total"=>$total,
                    "observacion"=>"Modificacion de el ticket ".$tckdev,
                    "cambio"=>$cambio,
                    "products"=>$primp,
                    "pagos"=>$newFormas,
                    "desfpa"=>$newFormas[0],
                    "impresora"=>$print
                ];
                $print = $this->printck($header);
                $print = $this->printck($header);
                if($print){
                 $res = [
                     "mssg"=>"Ticket :".$header['ticket'],
                 ];
             }else{
                 $res = [
                     "mssg"=>"No se logro imprimir el ticket ".$header['ticket'],
                 ];
             }
             return response()->json($res,200);
            }else{
                return response()->json("No se puedo realizar la factura :(",500);
            }
        }else{
            return response()->json("El ticket no tiene devolucion",404);
        }
    }

    public function devolucion($ticket,$serie,$motivo,$creacion,$folio,$print){
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $fecha =  date("d/m/Y");
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
                    ],
                    "err"=>true
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
                        $terminal = "SELECT T_TER.*  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER   WHERE T_DOC.TIPDOC = ".$serie;
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
                            "Devolcion por : ".$motivo." de el ticket : ".$ticket." creado por : ".$creacion,
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
                            $serie,
                            intval($folio)
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


                                $updatesto = "UPDATE F_STO SET DISSTO = DISSTO - ? , ACTSTO = ACTSTO - ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
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
                                "dependiente"=>$creacion,
                                "total"=>$total,
                                "observacion"=>"Devolucion del TICKET => ".$ticket,
                                "cambio"=>0,
                                "products"=>$products,
                                "pagos"=>$fpas,
                                "desfpa"=>$fpas[0],
                                "impresora"=>$print
                            ];
                           $print = $this->printck($header);
                           if($print){
                            $res = [
                                "mssg"=>"Devolucion :".$header['ticket'],
                                "err"=>false
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

    }

    public function getProduct(Request $request){
        $client = $request->price;
        $product = $request->product;
        $code = "SELECT F_ART.CODART, F_ART.DESART, F_LTA.PRELTA FROM ((F_ART  INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART) INNER JOIN F_CLI ON F_CLI.TARCLI = F_LTA.TARLTA)  WHERE F_CLI.CODCLI = ".$client." AND  F_ART.CODART = "."'".$product."'";
        $exec = $this->con->prepare($code);
        $exec->execute();
        $prcode = $exec->fetch(\PDO::FETCH_ASSOC);
        if(!$prcode){
            $cco = "SELECT F_ART.CODART, F_ART.DESART, F_LTA.PRELTA FROM ((F_ART  INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART) INNER JOIN F_CLI ON F_CLI.TARCLI = F_LTA.TARLTA)  WHERE F_CLI.CODCLI = ".$client." AND  F_ART.CCOART = ".$product;
            $exec = $this->con->prepare($cco);
            $exec->execute();
            $ccoart = $exec->fetch(\PDO::FETCH_ASSOC);
            if(!$ccoart){
                $ean = "SELECT F_ART.CODART, F_ART.DESART, F_LTA.PRELTA FROM ((F_ART  INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART) INNER JOIN F_CLI ON F_CLI.TARCLI = F_LTA.TARLTA)  WHERE F_CLI.CODCLI = ".$client." AND  F_ART.EANART = "."'".$product."'";
                $exec = $this->con->prepare($ean);
                $exec->execute();
                $eanart = $exec->fetch(\PDO::FETCH_ASSOC);
                if(!$eanart){
                    $fam = "SELECT F_ART.CODART, F_ART.DESART, F_LTA.PRELTA FROM (((F_ART  INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART) INNER JOIN F_EAN ON F_EAN.ARTEAN = F_ART.CODART) INNER JOIN F_CLI ON F_CLI.TARCLI = F_LTA.TARLTA)  WHERE F_CLI.CODCLI = ".$client." AND  F_EAN.EANEAN = "."'".$product."'";
                    $exec = $this->con->prepare($fam);
                    $exec->execute();
                    $famart = $exec->fetch(\PDO::FETCH_ASSOC);
                    if(!$famart){
                        return response()->json("El producto ".$product." no existe",404);
                    }else{
                        return $famart;
                    }
                }else{
                    return $eanart;
                }
            }else{
                return $ccoart;
            }
        }else{
            return $prcode;
        }

    }

    public function getClient(Request $request){
        $client = $request->client;
        $sql = "SELECT CODCLI, TARCLI, NOFCLI FROM F_CLI WHERE CODCLI = $client AND CODCLI NOT IN (5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35)";
        $exec = $this->con->prepare($sql);
        $exec->execute();
        $text = $exec->fetch(\PDO::FETCH_ASSOC);//OK
        if($text){
            return response()->json($text,200);
        }else{
            return response()->json("El cliente no existe",404);
        }
    }

    public function getPrices(Request $request){
        $client =  $request->cliente;
        $products = $request->productos;
        foreach($products as $product){
            $code = "SELECT F_ART.CODART, F_LTA.PRELTA FROM ((F_ART  INNER JOIN F_LTA ON F_LTA.ARTLTA = F_ART.CODART) INNER JOIN F_CLI ON F_CLI.TARCLI = F_LTA.TARLTA)  WHERE F_CLI.CODCLI = ".$client." AND  F_ART.CODART = "."'".$product['ARTICULO']."'";
            $exec = $this->con->prepare($code);
            $exec->execute();
            $prcode = $exec->fetch(\PDO::FETCH_ASSOC);
            if($prcode){
                $alm[] = $prcode;
            }else{
                $alm[] = [];
            }
        }
        if(count($alm) > 0){
            return response()->json($alm,200);
        }else{
            return response()->json("Hubo un problema con los precios",500);
        }

    }

    public function vales(Request $request){
        $cliente = $request->price;
        $select = "SELECT CODANT, IMPANT  FROM  F_ANT WHERE CLIANT = $cliente AND ESTANT = 0";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $vales = $exec->fetchall(\PDO::FETCH_ASSOC);
        if($vales){
            return response()->json($vales,200);
        }else{
            return response()->json("No hay vales para este cliente :(",404);
        }
    }

    public function printck($header){
        $imagen = env('IMAGENLOCAL');
        $documento = env('DOCUMENTO');
        $printers = $header['impresora'];
        // $printers = "192.168.10.100";
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
                        $printer->setJustification(Printer::JUSTIFY_CENTER);
                        $printer->bitImage($logo,0);
                        $printer->feed();
                    }
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
                    $printer->text(mb_convert_encoding($header["nomcli"],'UTF-8')." \n");
                    $printer->text(mb_convert_encoding($header["direccion"],'UTF-8')." \n");
                    $printer->text(mb_convert_encoding($header["nose"],'UTF-8')." \n");
                    // $printer->text($header["nose"]." \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->text("ARTICULO        UD.        PRECIO        TOTAL \n");
                    $printer->text("_______________________________________________ \n");
                    $printer -> setFont(Printer::FONT_B);
                    foreach($header['products'] as $product){
                        $printer->setJustification(printer::JUSTIFY_LEFT);
                        $printer->text(mb_convert_encoding($product['ARTLFA'], 'UTF-8')."   ".mb_convert_encoding($product['DESLFA'], 'UTF-8')." \n");
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
                    $printer->text(str_pad("TOTAL: ",13));
                    $printer->text("$".number_format($header["total"],2)." \n");
                    $printer->text(" \n");
                    $printer->setEmphasis(false);
                    foreach($header['pagos'] as $pago){
                        $despa = $pago['FPALCO'] == 'EFE' ? "Efectivo:" : $pago['CPTLCO'].":";
                        // $padding = 54 - strlen($despa);
                        $printer->text(mb_convert_encoding($despa,'UTF-8'));
                        $printer->text(str_pad('',7,' '));
                        $numbe = $pago['FPALCO'] == 'EFE' ? $pago['IMPORTE'] + $header['cambio']  : $pago['IMPORTE'];
                        $printer->text(str_pad("$".number_format($numbe,2),-13)." \n");
                    }
                    if($header['cambio'] <> 0){
                        $printer->text(str_pad("Cambio: ",14));
                        $printer->text("$".number_format($header['cambio'],2)." \n");
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

    public function retirada(Request $request){
        $date = date("Y/m/d H:i");//horario para la hora
        $horad = explode(" ", $date)[1];
        $fecha =  date("d/m/Y");
        $serie = $request->serdev;
        $valor  = $request->retiro;
        $nota = "Devolucion por el ".$request->nota;
        $proveedor = 833;


        $maxcocret = "SELECT MAX(CODRET) AS CODIGO FROM F_RET";
        $exec = $this->con->prepare($maxcocret);
        $exec->execute();
        $max = $exec->fetch(\PDO::FETCH_ASSOC);
        $codigo = $max['CODIGO'] + 1;

        $terminal = "SELECT T_TER.CODTER AS CODTER  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER WHERE T_DOC.TIPDOC = $serie";
        $exec = $this->con->prepare($terminal);
        $exec->execute();
        $codter = $exec->fetch(\PDO::FETCH_ASSOC);
        $term = $codter['CODTER'];
        $idterminal = str_pad($codter['CODTER'], 4, "0", STR_PAD_LEFT)."00".date('ymd');

        $ins = [
            $codigo,
            $proveedor,
            $idterminal,
            $term,
            $fecha,
            $horad,
            $nota,
            $valor,
            0,
            0
        ];

        $valin = "INSERT INTO F_RET (CODRET, PRORET, TPVIDRET, CAJRET, FECRET, HORRET, CONRET, IMPRET, CFARET, PFARET)  VALUES (?,?,?,?,?,?,?,?,?,?)";
        $exec = $this->con->prepare($valin);
        $exec->execute($ins);
        if($exec){
            $header = [
                "print"=>$request->print,
                "proveedor"=>$proveedor,
                "retirada"=>$codigo,
                "terminal"=>$term,
                "fecha"=>$fecha,
                "hora"=>$horad,
                "dependiente"=>$request->by,
                "valor"=>$valor,
                "notas"=>$nota
            ];
            $retirada = $this->printret($header);
            if($retirada){
                $res = ["mssg"=>"Retirada ".$codigo."realizada"];
                return $res;
            }else{
                $res = ["mssg"=>"No se imprimio la retirada"];
                return $res;
            }

        }else{
            return "No se pudo generar la retirada";
        }
    }

    public function printret($header){
        $documento = env('DOCUMENTO');
        $printers = $header['print'];

        $pro = "SELECT * FROM F_PRO WHERE CODPRO =". $header['proveedor'];
        $exec = $this->con->prepare($pro);
        $exec->execute();
        $proveedor = $exec->fetch(\PDO::FETCH_ASSOC);//OK

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
                $printer->setJustification(printer::JUSTIFY_LEFT);
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(" \n");
                $printer->text($text["CTT1TPV"]."\n");
                $printer->text($text["CTT3TPV"]." \n");
                $printer->text($text["CTT5TPV"]." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("------------------------------------------------\n");
                $printer->text("SALIDA DE TERMINAL".$header['terminal']." \n");
                $printer->text("N° ".$header['retirada']." Fecha: ".$header["fecha"]." ".$header["hora"] ." \n");
                $printer->text("Le atendio :".$header["dependiente"]." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text($proveedor['NOFPRO']." \n");
                $printer->text(" \n");
                $printer->text(" \n");
                $printer->text("00000"." \n");
                $printer->text(" \n");
                $printer->text("GVC"." \n");
                $printer->text("------------------------------------------------\n");
                $printer->text(str_pad("IMPORTE RETIRADO: ",14));
                $printer->text(number_format($header['valor'],2)." \n");
                $printer->text("Concepto:"." \n");
                $printer->text($header['notas']." \n");
                $printer -> cut();
                $printer -> close();
            }catch(Exception $e){}

        } finally {
            $printer -> close();
            return true;
        }
            return false;

    }

    public function getTicket($ticket){
        $documento = env('DOCUMENTO');
        $empresa = "SELECT DESTPV, CTT1TPV, CTT2TPV, CTT3TPV, CTT4TPV, CTT5TPV, PTT1TPV, PTT2TPV, PTT3TPV, PTT4TPV, PTT5TPV, PTT6TPV, PTT7TPV, PTT8TPV FROM T_TPV WHERE CODTPV = $documento";
        $exec = $this->con->prepare($empresa);
        $exec->execute();
        $text = $exec->fetch(\PDO::FETCH_ASSOC);// encabezado empresa
        $enctck = "
        SELECT
        T_TER.DESTER AS TERMINAL,
        F_FAC.TIPFAC&'-'&Format(F_FAC.CODFAC,'000000') AS TICKET,
        Format(F_FAC.FECFAC, 'dd-mm-YYYY') AS FECHA,
        Format(F_FAC.HORFAC, 'hh:nn:ss') AS HORA,
        F_FPA.DESFPA AS PAGOPRINCIPAL,
        F_FAC.CLIFAC AS CLIENTE,
        F_FAC.CNOFAC AS NOMBRECLIENTE,
        F_FAC.CDOFAC AS DOMICILIO,
        F_FAC.CPOFAC AS POBALCION,
        F_FAC.CPOFAC AS CODIGOPOSTAL,
        F_FAC.CPRFAC AS PROVINCIA,
        F_FAC.CAMFAC AS CAMBIO,
        T_DEP.NOMDEP AS DEPENDIENTE,
        F_FAC.TOTFAC AS TOTAL
        FROM (((F_FAC
        INNER JOIN T_TER ON T_TER.CODTER = F_FAC.TERFAC)
        INNER JOIN F_FPA ON F_FPA.CODFPA =  F_FAC.FOPFAC)
        INNER JOIN T_DEP ON T_DEP.CODDEP = F_FAC.DEPFAC)
        WHERE  F_FAC.TIPFAC&'-'&F_FAC.CODFAC = "."'".$ticket."'";
        $exec = $this->con->prepare($enctck);
        $exec->execute();
        $encabezado = $exec->fetch(\PDO::FETCH_ASSOC);// encabezado ticket
        if($encabezado){
            $prdtck = "
            SELECT
            ARTLFA AS ARTICULO,
            DESLFA AS DESCRIPCION,
            CANLFA AS CANTIDAD,
            PRELFA AS PRECIO,
            TOTLFA AS TOTAL
            FROM F_LFA
            WHERE TIPLFA&'-'&CODLFA = "."'".$ticket."'";
            $exec = $this->con->prepare($prdtck);
            $exec->execute();
            $products = $exec->fetchall(\PDO::FETCH_ASSOC);// products ticket
            $pgstck = "
            SELECT
            CPTLCO AS CONCEPTOPAGO,
            IMPLCO AS IMPORTE
            FROM F_LCO
            WHERE  TFALCO&'-'&CFALCO = "."'".$ticket."'";
            $exec = $this->con->prepare($pgstck);
            $exec->execute();
            $pagos = $exec->fetchall(\PDO::FETCH_ASSOC);// pagos ticket

            $res = [
                "empresa"=>$text,
                "header"=>$encabezado,
                "products"=>$products,
                "payments"=>$pagos
            ];
            return response()->json( mb_convert_encoding($res,'UTF-8') ,200);
        }else{
            return response()->json('No se encontro el ticket :/',401);
        }

    }

    public function CreateVale(Request $request){
        $impresora = $request->print;
        $ticket= $request->ticket;
        $products = $request->products;
        $creacion = $request->created;
        $devolucion = $this->devVal($ticket,$products,$creacion,$impresora['ip_address']);
        if($devolucion){
            $vale = $this->creatVal($devolucion,$impresora['ip_address']);
            if($vale['mmsg'] == true){
                return response()->json($vale,200);
            }else{
                return response()->json('No se pudo realizar el vale',500);
            }
        }else{
            return response()->json('No se pudo realizar el la devolucion',500);
        }
    }

    public function devVal($ticket,$products,$creacion,$printer){
        $date = date("Y/m/d H:i");//horario para la hora
        $hour = "01/01/1900 ".explode(" ", $date)[1];//hora para el ticket
        $horad = explode(" ", $date)[1];
        $fecha =  date("d/m/Y");
        $existck = "SELECT * FROM F_FAC WHERE TIPFAC&'-'&CODFAC = "."'".$ticket['ticket']."'";
        $exec = $this->con->prepare($existck);
        $exec->execute();
        $tck = $exec->fetch(\PDO::FETCH_ASSOC);

        if($tck){
            if($tck['TOTFAC'] > 0){

            $fpas = [['CPTLCO'=>"CONTADO EFECTIVO", 'FPALCO'=>"EFT",'IMPORTE'=>0, 'LINLCO' => 1]];

            if(count($fpas) != 1){
                $fpa2 = $fpas[0]['IMPORTE'];
                $fpa1 = $fpas[1]['IMPORTE'];
            }else{
                $fpa1 = $fpas[0]['IMPORTE'];
                $fpa2 = 0;
            }


                if($products){
                    $total = 0;
                    foreach($products as $key => $product){
                        $products[$key]['change'] *= - 1;
                        $products[$key]['_chantot'] *= - 1;
                        $total += $products[$key]['_chantot'];
                    }
                    // return intval(explode("-",$ticket['ticket'])[0]);
                    $terminal = "SELECT T_TER.*  FROM T_TER INNER JOIN T_DOC ON T_DOC.CODDOC = T_TER.DOCTER   WHERE T_DOC.TIPDOC = ".intval(explode("-",$ticket['ticket'])[0]);
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


                    $column = ["TIPFAC","CODFAC","FECFAC", "ALMFAC","AGEFAC","CLIFAC","CNOFAC","CDOFAC","CPOFAC","CCPFAC","CPRFAC","TELFAC","NET1FAC","BAS1FAC","TOTFAC","FOPFAC","PRIFAC","VENFAC","HORFAC","USUFAC","USMFAC","TIVA2FAC","TIVA3FAC","EDRFAC","FUMFAC","BCOFAC","TPVIDFAC","ESTFAC","TERFAC","DEPFAC","EFEFAC","CAMFAC","EFSFAC"];
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
                        'EFE',
                        "Devolucion por vale",
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
                        0,
                        $total * 1,
                        0
                    ];
                    $impcol = implode(",",$column);
                    $signos = implode(",",array_fill(0, count($column),'?'));
                    $sql = "INSERT INTO F_FAC ($impcol) VALUES ($signos)";//se crea el query para insertar en la tabla
                    $exec = $this->con->prepare($sql);
                    $res = $exec -> execute($factura);
                    if($res){
                        $count = 1;
                        $nwproducts = [];
                        foreach($products as $product){


                            $nwproducts[] = [
                                'ARTLFA'=>$product['ARTICULO'],
                                'DESLFA'=> $product['DESCRIPCION'],
                                'CANLFA'=>$product['change'],
                                'PRELFA'=>$product['PRECIO'],
                                'TOTLFA'=>$product['_chantot'],
                            ];


                            $upd = [
                                $product['change'],
                                $product['change'],
                                $product['ARTICULO'],
                            ];
                            $ins = [
                                "9",
                                $codigo,
                                $count,
                                $product['ARTICULO'],
                                $product['DESCRIPCION'],
                                $product['change'],
                                $product['PRECIO'],
                                $product['_chantot'],

                            ];

                            $product = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA) VALUES (?,?,?,?,?,?,?,?)";
                            $exec = $this->con->prepare($product);
                            $exec -> execute($ins);


                            $updatesto = "UPDATE F_STO SET DISSTO = DISSTO - ? , ACTSTO = ACTSTO - ? WHERE ALMSTO = 'GEN' AND ARTSTO = ?";
                            $exec = $this->con->prepare($updatesto);
                            $exec -> execute($upd);
                            $count++;


                        }
                        $header = [
                            "terminal"=>"CAMBIOS Y DEVOLUCIONES",
                            "ticket"=>"9-".str_pad($codigo,6,0,STR_PAD_LEFT),
                            "fecha"=>$fecha,
                            "hora"=>$horad,
                            "nomcli"=>$tck['CNOFAC'],
                            "direccion"=>$tck['CDOFAC']." ".$tck['CPOFAC'],
                            "nose"=>$tck['CPRFAC'],
                            "dependiente"=>$creacion,
                            "total"=>$total,
                            "observacion"=>"Vale creado del TICKET => ".$ticket['ticket'],
                            "cambio"=>$total * -1,
                            "products"=>$nwproducts,
                            "pagos"=>$fpas,
                            "desfpa"=>$fpas[0],
                            "impresora"=>$printer
                        ];
                       $print = $this->printck($header);
                       if($print){
                        $res = [
                            "dev"=>$header['ticket'],
                            "fecha"=>$header['fecha'],
                            "cliente"=>$tck['CLIFAC'],
                            "nomcli"=>$tck['CNOFAC'],
                            "terminal"=>$codter['CODTER'],
                            "total"=>$total,
                            "tpvid"=>$idterminal,
                            "creacion"=>$creacion,
                            "original"=>explode("-",$ticket['ticket'])[0]."-".str_pad(explode("-",$ticket['ticket'])[1],6,0,STR_PAD_LEFT),
                            "print"=>true
                        ];
                    }else{
                        $res = [
                            "mssg"=>$header['ticket'],
                            'print'=>false
                        ];
                    }
                    return $res;
                    }else{return response()->json("No se creo la devolucion");}
                }else{return response()->json("No hay productos ?",404);}
            }else{return response()->json("El ticket es menor o igual a 0",401);}
        }else{return response()->json('El ticket no existe',404);}

    }

    public function creatVal($devolucion,$print){
        $observacion = "Vale por la devolución grabada en la factura nº: ".$devolucion['dev'];
        $max = "SELECT MAX(CODANT) + 1 AS MAX FROM F_ANT";
        $exec = $this->con->prepare($max);
        $exec->execute();
        $codigo = $exec->fetch(\PDO::FETCH_ASSOC);
        $ins = [
            intval($codigo['MAX']),
            $devolucion['fecha'],
            intval($devolucion['cliente']),
            $devolucion['total'] * -1,
            0,
            $observacion,
            intval($devolucion['terminal']),
            $devolucion['tpvid'],
            'DF'.$devolucion['original'],
        ];
        $insertarvale = "INSERT INTO F_ANT (CODANT,FECANT,CLIANT,IMPANT,ESTANT,OBSANT,CAJANT,TPVIDANT,DORANT) VALUES(?,?,?,?,?,?,?,?,?)";
        $exec = $this->con->prepare($insertarvale);
        $res = $exec->execute($ins);
        if($res){
            $devolucion['vale'] = $codigo['MAX'];
            $imp = $this->ImpresionVale($devolucion, $print);
            if($imp){
                return [
                    "mmsg"=>true,
                    "devolucion"=>$devolucion['dev'],
                    "vale"=>$devolucion['vale']
                ];
            }else{
                return [
                    "mmsg"=>false,
                    "devolucion"=>$devolucion['dev'],
                    "vale"=>''
                ];
            }
        }else{
            return ["mmsg"=>false];
        }

    }

    public function ImpresionVale($vale,$printers){
        $documento = env('DOCUMENTO');
        // $printers = "192.168.10.100";
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
                    $printer->setJustification(printer::JUSTIFY_LEFT);
                    $printer->text("------------------------------------------------\n");
                    $printer->text($text["CTT1TPV"]."\n");
                    $printer->text($text["CTT3TPV"]." \n");
                    $printer->text($text["CTT5TPV"]." \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text(mb_convert_encoding($vale["nomcli"],'UTF-8')." \n");
                    $printer->text(" \n");
                    $printer->text(" \n");
                    $printer->text("N.I.F.:"." \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->text("N° ".$vale['vale']." Fecha: ".$vale["fecha"]." \n");
                    $printer->text("Realizado por: ".$vale['creacion']." \n");
                    $printer->text("_______________________________________________ \n");
                    $printer->setEmphasis(true);
                    $printer->text("IMPORTE VALE:"." \n");
                    $printer->setJustification(printer::JUSTIFY_RIGHT);
                    $printer->text(doubleval($vale['total'] * -1)." \n");
                    $printer->text("_______________________________________________ \n");
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
