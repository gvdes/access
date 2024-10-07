<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\Printer;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller{
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
        return $imp;
    }

    private function getPrinterIPAddress($portName){
        $wmi = new \COM('winmgmts:{impersonationLevel=impersonate}//./root/cimv2');
        $portQuery = $wmi->ExecQuery("SELECT * FROM Win32_TCPIPPrinterPort WHERE HostAddress = '$portName'");

        foreach ($portQuery as $port) {
            return $port->HostAddress;
        }

        return 'No disponible';
    }

    public function getCash(){
        $select = "SELECT * FROM T_TER";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $selpagtar = "SELECT
        COB.TERMINAL,
        COB.TICKET,
        F_FAC.CNOFAC AS CLIENTE,
        Format(F_FAC.FECFAC, 'Short Date') as FECHA,
        Format(F_FAC.HORFAC, 'HH:MM:SS') AS HORA,
        COB.EFECTIVO,
        COB.TARJETAS,
        COB.TRANSFERENCIAS,
        COB.CREDITOS,
        COB.VALES
        FROM F_FAC
        INNER JOIN (
        SELECT
        T_TER.DESTER AS TERMINAL,
        F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
        F_LCO.FECLCO AS FECHA,
        MAX(IIF(F_LCO.FPALCO = 'EFE','OK' ,'')) AS EFECTIVO,
        MAX(IIF((F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA'),F_LCO.IMPLCO ,'')) AS TARJETAS,
        MAX(IIF((F_LCO.FPALCO = 'TDB' OR  F_LCO.FPALCO = 'TDA' OR F_LCO.FPALCO = 'TDS'), F_LCO.IMPLCO,'')) AS TRANSFERENCIAS,
        MAX(IIF(F_LCO.FPALCO = 'C30', F_LCO.IMPLCO,'')) AS CREDITOS,
        MAX(IIF(F_LCO.FPALCO = '[V]', F_LCO.IMPLCO,'')) AS VALES
        FROM F_LCO
        INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO
        WHERE FECLCO =  DATE()
        GROUP BY
        T_TER.DESTER,
        TFALCO&'-'&CFALCO,
        FECLCO ) AS COB  ON COB.TICKET = F_FAC.TIPFAC&'-'&F_FAC.CODFAC";
        $exec = $this->con->prepare($selpagtar);
        $exec->execute();
        $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
        // $impresoras = $this->getPrinter();

        $res = [
            "terminales"=>mb_convert_encoding($terminales,'UTF-8'),
            "formaspagos"=>mb_convert_encoding($fpas,'UTF-8'),
            // "impresoras"=>$impresoras
        ];
        return response()->json($res,200);
    }

    public function getSales(){
        $report = [
            "salesant"=>0,
            "salesact"=>0,
            "saleshoy"=>0,
            "tiketsant"=>0,
            "tiketsact"=>0,
            "hoytck"=>0,
            "ventasdepmonth"=>[],
            "ventasdepday"=>[],
        ];
        $year =  date("Y");
        $month = date("m");
        $day = date("d");

        $name = env('namedb');
        $ruta = env('rout');
        $slash = '\\';
        $concat = $ruta.$slash.$name;

        $ant = $concat.($year-1) .".accdb";

        $select = "SELECT SUM(TOTFAC) as TOTAL FROM F_FAC WHERE month(FECFAC) = $month ";
        $tickets = "SELECT COUNT(*) as TICKETS FROM F_FAC WHERE month(FECFAC) = $month ";

        if(file_exists($ant)){
            try{
                $dbd = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$ant."; Uid=; Pwd=;");
                $this->cone = $dbd;
            }catch(PDOException $e){
                return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
            }

            $antes = $this->cone->prepare($select);
            $antes->execute();
            $antessale = $antes->fetch(\PDO::FETCH_ASSOC);
            $report['salesant'] = $antessale['TOTAL'];

            $tckant = $this->cone->prepare($tickets);
            $tckant->execute();
            $tckantsale = $tckant->fetch(\PDO::FETCH_ASSOC);
            $report['tiketsant'] = $tckantsale['TICKETS'];
        }

            $act = $this->con->prepare($select);
            $act->execute();
            $actsale = $act->fetch(\PDO::FETCH_ASSOC);
            if($actsale){
                $report['salesact'] = $actsale['TOTAL'];
            }

            $tckact = $this->con->prepare($tickets);
            $tckact->execute();
            $tckactsale = $tckact->fetch(\PDO::FETCH_ASSOC);
            if($tckactsale){
                $report['tiketsact'] = $tckactsale['TICKETS'];
            }


            $venthoy = "SELECT SUM(TOTFAC) AS TOTAL FROM F_FAC WHERE FECFAC = date()";
            $hoy = $this->con->prepare($venthoy);
            $hoy->execute();
            $hoysale = $hoy->fetch(\PDO::FETCH_ASSOC);
            if($hoysale){
                $report['saleshoy'] = $hoysale['TOTAL'];
            }


            $tckhoy = "SELECT COUNT(*) AS TOTAL FROM F_FAC WHERE FECFAC = date()";
            $hoytck = $this->con->prepare($tckhoy);
            $hoytck->execute();
            $hoytcksale = $hoytck->fetch(\PDO::FETCH_ASSOC);
            if($hoytcksale){
                $report['hoytck'] = $hoytcksale['TOTAL'];
            }

            $ventdepmonth = "SELECT
            T_DEP.NOMDEP,
            SUM(F_FAC.TOTFAC) AS VENTA
            FROM T_DEP
            INNER JOIN F_FAC ON F_FAC.DEPFAC = T_DEP.CODDEP
            WHERE MONTH(F_FAC.FECFAC) = $month AND DAY(F_FAC.FECFAC) <= $day
            GROUP BY T_DEP.NOMDEP
            ORDER BY SUM(F_FAC.TOTFAC) DESC
            ";
            $vendep = $this->con->prepare($ventdepmonth);
            $vendep->execute();
            $vendepsale = $vendep->fetchall(\PDO::FETCH_ASSOC);
            if($vendepsale){
                $report['ventasdepmonth'] = $vendepsale;
            }

            $ventdepday = "SELECT
            T_DEP.NOMDEP,
            SUM(F_FAC.TOTFAC) AS VENTA
            FROM T_DEP
            INNER JOIN F_FAC ON F_FAC.DEPFAC = T_DEP.CODDEP
            WHERE F_FAC.FECFAC = DATE()
            GROUP BY T_DEP.NOMDEP
            ORDER BY SUM(F_FAC.TOTFAC) DESC
            ";
            $vendepd = $this->con->prepare($ventdepday);
            $vendepd->execute();
            $vendepdsale = $vendepd->fetchall(\PDO::FETCH_ASSOC);
            if($vendepdsale){
                $report['ventasdepday'] = $vendepdsale;
            }
            return  mb_convert_encoding($report,'UTF-8');

    }

    public function filter(Request $request){
            $fechas = $request->all();
            // return $fechas;
            if(isset($fechas['filt']['from'])){
                $desde = $fechas['filt']['from'];
                $hasta = $fechas['filt']['to'];
                $condicion = "#".$desde."#"." AND "."#".$hasta."#";
            }else{
                $date = $fechas['filt'];
                $condicion = "#".$date."#"." AND "."#".$date."#";
            }
            $selpagtar = "SELECT
            COB.TERMINAL,
            COB.TICKET,
            F_FAC.CNOFAC AS CLIENTE,
            Format(F_FAC.FECFAC, 'Short Date') as FECHA,
            Format(F_FAC.HORFAC, 'HH:MM:SS') AS HORA,
            COB.EFECTIVO,
            COB.TARJETAS,
            COB.TRANSFERENCIAS,
            COB.CREDITOS,
            COB.VALES
            FROM F_FAC
            INNER JOIN (
            SELECT
            T_TER.DESTER AS TERMINAL,
            F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
            F_LCO.FECLCO AS FECHA,
            MAX(IIF(F_LCO.FPALCO = 'EFE','OK' ,'')) AS EFECTIVO,
            MAX(IIF((F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA'),F_LCO.IMPLCO ,'')) AS TARJETAS,
            MAX(IIF((F_LCO.FPALCO = 'TDB' OR  F_LCO.FPALCO = 'TDA' OR F_LCO.FPALCO = 'TDS'), F_LCO.IMPLCO,'')) AS TRANSFERENCIAS,
            MAX(IIF(F_LCO.FPALCO = 'C30', F_LCO.IMPLCO,'')) AS CREDITOS,
            MAX(IIF(F_LCO.FPALCO = '[V]', F_LCO.IMPLCO,'')) AS VALES
            FROM F_LCO
            INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO
            WHERE FECLCO BETWEEN ".$condicion."
            GROUP BY
            T_TER.DESTER,
            TFALCO&'-'&CFALCO,
            FECLCO ) AS COB  ON COB.TICKET = F_FAC.TIPFAC&'-'&F_FAC.CODFAC";
            $exec = $this->con->prepare($selpagtar);
            $exec->execute();
            $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
            $res = [
                "formaspagos"=>mb_convert_encoding($fpas,'UTF-8'),
            ];
            return response()->json($res,200);
    }

    public function getSalesPerMonth($month){

        $report = [
            "salesant"=>0,
            "salesact"=>0,
            "saleshoy"=>0,
            "tiketsant"=>0,
            "tiketsact"=>0,
            "hoytck"=>0,
            "ventasdepmonth"=>[],
            "ventasdepday"=>[],
        ];
        $year =  date("Y");
        $month = $month;
        $day = date("d");

        $name = env('namedb');
        $ruta = env('rout');
        $slash = '\\';
        $concat = $ruta.$slash.$name;

        $ant = $concat.($year-1) .".accdb";

        $select = "SELECT SUM(TOTFAC) as TOTAL FROM F_FAC WHERE month(FECFAC) = $month ";
        $tickets = "SELECT COUNT(*) as TICKETS FROM F_FAC WHERE month(FECFAC) = $month ";

        if(file_exists($ant)){
            try{
                $dbd = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$ant."; Uid=; Pwd=;");
                $this->cone = $dbd;
            }catch(PDOException $e){
                return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
            }

            $antes = $this->cone->prepare($select);
            $antes->execute();
            $antessale = $antes->fetch(\PDO::FETCH_ASSOC);
            $report['salesant'] = $antessale['TOTAL'];

            $tckant = $this->cone->prepare($tickets);
            $tckant->execute();
            $tckantsale = $tckant->fetch(\PDO::FETCH_ASSOC);
            $report['tiketsant'] = $tckantsale['TICKETS'];
        }

            $act = $this->con->prepare($select);
            $act->execute();
            $actsale = $act->fetch(\PDO::FETCH_ASSOC);
            if($actsale){
                $report['salesact'] = $actsale['TOTAL'];
            }

            $tckact = $this->con->prepare($tickets);
            $tckact->execute();
            $tckactsale = $tckact->fetch(\PDO::FETCH_ASSOC);
            if($tckactsale){
                $report['tiketsact'] = $tckactsale['TICKETS'];
            }


            $venthoy = "SELECT SUM(TOTFAC) AS TOTAL FROM F_FAC WHERE FECFAC = date()";
            $hoy = $this->con->prepare($venthoy);
            $hoy->execute();
            $hoysale = $hoy->fetch(\PDO::FETCH_ASSOC);
            if($hoysale){
                $report['saleshoy'] = $hoysale['TOTAL'];
            }


            $tckhoy = "SELECT COUNT(*) AS TOTAL FROM F_FAC WHERE FECFAC = date()";
            $hoytck = $this->con->prepare($tckhoy);
            $hoytck->execute();
            $hoytcksale = $hoytck->fetch(\PDO::FETCH_ASSOC);
            if($hoytcksale){
                $report['hoytck'] = $hoytcksale['TOTAL'];
            }

            $ventdepmonth = "SELECT
            T_TER.DESTER,
            SUM(F_FAC.TOTFAC) AS VENTA,
            COUNT(F_FAC.CODFAC) AS TCK
            FROM T_TER
            INNER JOIN F_FAC ON F_FAC.TERFAC = T_TER.CODTER
            WHERE MONTH(F_FAC.FECFAC) = $month AND DAY(F_FAC.FECFAC) <= $day
            GROUP BY T_TER.DESTER
            ORDER BY SUM(F_FAC.TOTFAC) ASC
            ";
            $vendep = $this->con->prepare($ventdepmonth);
            $vendep->execute();
            $vendepsale = $vendep->fetchall(\PDO::FETCH_ASSOC);
            if($vendepsale){
                $report['ventasdepmonth'] = $vendepsale;
            }

            $ventdepday = "SELECT
            T_TER.DESTER,
            SUM(F_FAC.TOTFAC) AS VENTA,
            COUNT(F_FAC.CODFAC) AS TCK
            FROM T_TER
            INNER JOIN F_FAC ON F_FAC.TERFAC = T_TER.CODTER
            WHERE F_FAC.FECFAC = DATE()
            GROUP BY T_TER.DESTER
            ORDER BY SUM(F_FAC.TOTFAC) ASC
            ";
            $vendepd = $this->con->prepare($ventdepday);
            $vendepd->execute();
            $vendepdsale = $vendepd->fetchall(\PDO::FETCH_ASSOC);
            if($vendepdsale){
                $report['ventasdepday'] = $vendepdsale;
            }
            return  mb_convert_encoding($report,'UTF-8');

    }

    public function getCashCard(){
        $select = "SELECT * FROM T_TER";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $selpagtar = "SELECT
        COB.TERMINAL,
        COB.TICKET,
        F_FAC.CNOFAC AS CLIENTE,
        Format(F_FAC.FECFAC, 'Short Date') as FECHA,
        Format(F_FAC.HORFAC, 'HH:MM:SS') AS HORA,
        COB.TARJETAS
        FROM F_FAC
        INNER JOIN (
        SELECT
        T_TER.DESTER AS TERMINAL,
        F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
        F_LCO.FECLCO AS FECHA,
        MAX(IIF((F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA'),F_LCO.IMPLCO ,'')) AS TARJETAS
        FROM F_LCO
        INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO
        WHERE FECLCO =  DATE() AND (F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA')
        GROUP BY
        T_TER.DESTER,
        TFALCO&'-'&CFALCO,
        FECLCO ) AS COB  ON COB.TICKET = F_FAC.TIPFAC&'-'&F_FAC.CODFAC";
        $exec = $this->con->prepare($selpagtar);
        $exec->execute();
        $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
        // $impresoras = $this->getPrinter();

        $res = [
            "terminales"=>mb_convert_encoding($terminales,'UTF-8'),
            "formaspagos"=>mb_convert_encoding($fpas,'UTF-8'),
            // "impresoras"=>$impresoras
        ];
        return response()->json($res,200);
    }

    public function getCashOrDateCard($date){
        // return $date;
        $select = "SELECT * FROM T_TER";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $terminales = $exec->fetchall(\PDO::FETCH_ASSOC);

        $selpagtar = "SELECT
        COB.TERMINAL,
        COB.TICKET,
        F_FAC.CNOFAC AS CLIENTE,
        Format(F_FAC.FECFAC, 'Short Date') as FECHA,
        Format(F_FAC.HORFAC, 'HH:MM:SS') AS HORA,
        COB.TARJETAS
        FROM F_FAC
        INNER JOIN (
        SELECT
        T_TER.DESTER AS TERMINAL,
        F_LCO.TFALCO&'-'&F_LCO.CFALCO AS TICKET,
        F_LCO.FECLCO AS FECHA,
        MAX(IIF((F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA'),F_LCO.IMPLCO ,'')) AS TARJETAS
        FROM F_LCO
        INNER JOIN T_TER ON T_TER.CODTER = F_LCO.TERLCO
        WHERE FECLCO =  "."#".$date."#"." AND (F_LCO.FPALCO = 'TBA' OR  F_LCO.FPALCO = 'TSC' OR F_LCO.FPALCO = 'TSA')
        GROUP BY
        T_TER.DESTER,
        TFALCO&'-'&CFALCO,
        FECLCO ) AS COB  ON COB.TICKET = F_FAC.TIPFAC&'-'&F_FAC.CODFAC";
        $exec = $this->con->prepare($selpagtar);
        $exec->execute();
        $fpas = $exec->fetchall(\PDO::FETCH_ASSOC);
        // $impresoras = $this->getPrinter();

        $res = [
            "terminales"=>mb_convert_encoding($terminales,'UTF-8'),
            "formaspagos"=>mb_convert_encoding($fpas,'UTF-8'),
            // "impresoras"=>$impresoras
        ];
        return response()->json($res,200);
    }

    public function OpenBoxes(Request $request){
        $fechas = $request->filt;
        if(isset($fechas['from'])){
            $desde = $fechas['from'];
            $hasta = $fechas['to'];
            $condicion = "#".$desde."#"." AND "."#".$hasta."#";
        }else{
            $date = $fechas;
            $condicion = "#".$date."#"." AND "."#".$date."#";
        }
        $cashes = "SELECT
        T_TER.DESTER,
        COUNT(F_FAC.CODFAC) AS TICKETS
        FROM F_FAC
        INNER JOIN T_TER ON T_TER.CODTER = F_FAC.TERFAC
        WHERE F_FAC.FECFAC BETWEEN ".$condicion.
        "GROUP BY T_TER.DESTER";
        $exec = $this->con->prepare($cashes);
        $exec->execute();
        $cashiers = $exec->fetchall(\PDO::FETCH_ASSOC);
        return response()->json($cashiers);
    }
}
