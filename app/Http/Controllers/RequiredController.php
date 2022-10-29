<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class RequiredController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS_FILE");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    } 

    public function received(Request $request){ //metodo para crear la salida a la sucursal
        try{
            $id = $request->id;//se recibe por metodo post el id de la requisicion
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = Carbon::now()->format('d/m/Y');//se obtiene el dia que ocurre

            // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
            $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
            $status = DB::table('requisition')->where('id',$id)->value('_status');
            $id = DB::table('requisition')->where('id',$id)->value('id');
            if($id){//SE VALIDA QUE LA REQUISICION EXISTA
                if($status == 9){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 6 POR ENVIAR
                    $count =DB::table('product_required')->where('_requisition',$id)->wherenotnull('toReceived')->where('toReceived','>',0)->count('_product');
                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        $requisitions = DB::table('requisition AS R')->where('R.id', $id)->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        $not = $requisitions->notes;//se obtiene las notas de la requisision
                        $rol = "1";
                        $max = "SELECT max(CODFRE) as CODIGO FROM F_FRE WHERE TIPFRE = '".$rol."'";//query para sacar el numero de factura maximo de el tipo(serie)
                        $exec = $this->conn->prepare($max);
                        $exec -> execute();
                        $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                        $codfac = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo numero de factura que se inserara
                        $product = $this->productreceived($id,$rol,$codfac);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo 
                            $fac = [//se crea el arrego para insertar en factusol
                                $rol,//tipo(serie) de factura
                                $codfac,//codigo de factura
                                "FAC ".$requisitions->invoice,//codigo de factura de salida
                                "P-".$requisitions->id,//codigo de requisision de la aplicacion
                                $date_format,//fecha actual en formato
                                $date_format,//fecha actual en formato
                                5,
                                "BODEGA SAN PABLO 10",
                                "AV SAN PABLO 10 LOCAL G",
                                "Centro",
                                "06090",
                                "DEL. CUAUHTEMOC CD",
                                $product,
                                $product,
                                $product,
                                "02-01-00",                           
                                "02-01-00",
                                900,
                                900,
                                "GEN",//almacen de donde sale la mercancia siempre sera GEN
                                "MEXICO",
                                100,
                                1,
                                2,
                                "02-01-00",
                            ];//termino de arreglo de insercion

                        $sql = "INSERT INTO F_FRE (TIPFRE,CODFRE,FACFRE,REFFRE,FECFRE,FUMFRE,PROFRE,PNOFRE,PDOFRE,PPOFRE,PCPFRE,PPRFRE,NET1FRE,BAS1FRE,TOTFRE,FENFRE,FROFRE,USUFRE,USMFRE,ALMFRE,PPAFRE,PDEFRE,TIVA2FRE,TIVA3FRE,FRCFRE) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se crea el query para insertar en la tabla
                        $exec = $this->conn->prepare($sql);
                        $exec -> execute($fac);
                        $folio = $rol."-".str_pad($codfac, 6, "0", STR_PAD_LEFT);//se obtiene el folio de la factura
                        DB::table('requisition')->where('id',$id)->update(['invoice_received'=>$folio]);//se actualiza la columna invoice con el numero de la factura
                        $sumcase = DB::table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toReceived  WHEN PR._supply_by = 2  THEN PR.toReceived * 12  WHEN PR._supply_by = 3  THEN PR.toReceived * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toReceived * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where('PR._requisition', $id)->first(); //se cuenta cuantas piezas se validaron
                        $sum = $sumcase->CASESUM;
                        $countde =DB::table('product_required')->where('_requisition',$id)->wherenotnull('toDelivered')->count('_product');//suma de conteo de productos enviadas
                        $sumcasede = DB::table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toDelivered  WHEN PR._supply_by = 2  THEN PR.toDelivered * 12  WHEN PR._supply_by = 3  THEN PR.toDelivered * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toDelivered * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where('PR._requisition', $id)->first(); //se cuenta cuantas piezas se validaron
                        $sumde = $sumcasede->CASESUM;
                        $difmod =  $count - $countde;//diferencia de conteos
                        $difcan = $sum - $sumde;//diferencias en cantidad
                        if(($difcan == 0) && ($difmod == 0)){//se valida que no haya diferencia
                            $message = "El pedido numero P-$id se recibio con $count  Modelos y $sum piezas. No hay diferencias!!!😎🫡🤙. El numero de Factura Recibida es $folio";//se redacta el mensaje a enviar por whatsapp
                        }else{$message = "El pedido numero P-$id se recibio con $count  Modelos y $sum piezas obteniendo una dIferencia de $difmod modelos y $difcan piezas. El numero de Factura Recibida es $folio ❗❗*favor de revisar las diferencias*❗❗"; }//si hay diferencia se envia un mensaje con la cantidad de difeerencia                                    
                        $curl = curl_init();//inicia el curl para enviar mensaje via whatsapp
                        curl_setopt_array($curl, array(
                          CURLOPT_URL => "https://api.ultramsg.com/instance9800/messages/chat",
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => "",
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 30,
                          CURLOPT_SSL_VERIFYHOST => 0,
                          CURLOPT_SSL_VERIFYPEER => 0,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_CUSTOMREQUEST => "POST",
                          CURLOPT_POSTFIELDS => "token=6r5vqntlz18k61iu&to=+525573461022&body=$message&priority=1&referenceId=",//se coloca la variable a cambiar
                          CURLOPT_HTTPHEADER => array(
                            "content-type: application/x-www-form-urlencoded"),));
                        $response = curl_exec($curl);//respuesta
                        $err = curl_error($curl);         //errror
                        curl_close($curl);//se cierra curl
                        return response()->json([                            
                        "folio"=>$folio,
                        "art_contados"=>$count,
                        "can_contada"=>$sum],201);//se retorna el folio de la factura
            
                    }else{return response("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS",400);}
                }else{return response("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
            }else{return response("EL CODIGO DE REQUISICION NO EXITE",404);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }
    public function productreceived($id,$rol,$codfac){//metoro de insercion de productos en factusol
        
        $product_require = DB::table('product_required AS PR')//se crea el query para obteener los productos de la requisision
            ->join('products AS P','P.id','=','PR._product')
            ->leftjoin('prices_product AS PP','PP._product','=','P.id')
            ->where('PR._requisition',$id)
            ->wherenotnull('PR.toReceived')
            ->where('PR.toReceived','>',0)
            ->select('P.code AS codigo','P.description AS descripcion','PR.toReceived AS cantidad','PP.AAA AS precio' ,'P.cost as costo','PR._supply_by AS medida','PR.ipack AS PXC')
            ->get();

        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total
        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $precio = $pro->precio;//se optiene el precio de cada producto
            if($pro->medida == 1){$canti = $pro->cantidad ;}elseif($pro->medida == 2){$canti = $pro->cantidad * 12; }elseif($pro->medida == 3){$canti = $pro->cantidad * $pro->PXC ;}elseif($pro->medida == 4){$canti = ($pro->cantidad * ($pro->PXC / 2)) ;}//SE VALIDA LA UNIDAD DE MECIDA DE SURTIDO
            $cantidad = $pro->cantidad;//se obtine la cantidad de cada producto
            $total = $precio * $canti ;//se obtiene el total de la linea
            $ttotal = $ttotal + $total ;//se obtiene el total de la requisision
            $values = [//se genera el arreglo para la insercion a factusol
                $rol,//tipo de documento
                $codfac,//codigo de documento
                $pos,//posision de la linea
                $pro->codigo,//codigo de el articulo
                $pro->descripcion,//descripcion de el articulo
                $canti,//cantidad contada
                $pro->precio,//precio de el articulo
                $total//total de la linea            
            ];
            $insert = "INSERT INTO F_LFR (TIPLFR,CODLFR,POSLFR,ARTLFR,DESLFR,CANLFR,PRELFR,TOTLFR) VALUES (?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO + ? , DISSTO = DISSTO + ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
            $exec = $this->conn->prepare($updatestock);
            $exec -> execute([$pro->cantidad,$pro->cantidad,$pro->codigo, "GEN"]);

            $pos++;//contador
        }  
        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }
}
