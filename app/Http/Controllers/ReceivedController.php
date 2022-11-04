<?php

namespace App\Http\Controllers;



use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class ReceivedController extends Controller
{
    private $conn = null;

    public function __construct(){
      $access = env("ACCESS_FILE");
      if(file_exists($access)){
      try{  $this->conn  = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
          }catch(\PDOException $e){ die($e->getMessage()); }
      }else{ die("$access no es un origen de datos valido."); }
    } 

    public function invoice(Request $request){ //metodo para crear la salida a la sucursal
        try{
            $id = $request->id;//se recibe por metodo post el id de la requisicion           
            $date = date("Y/m/d H:i");//se gerera la fecha de el dia de hoy con  formato de fecha y hora
            $date_format = Carbon::now()->format('d/m/Y');
            // $date_format = date("d/m/Y");//se formatea la fecha de el dia con el formato solo de fecha
            $hour = "01/01/1900 ".explode(" ", $date)[1];//se formatea la fecha de el dia de hoy poniendo solo la hora en la que se genera
            $status = DB::table('requisition')->where('id',$id)->value('_status');//se obtiene el status de el la requisicion
            $id = DB::table('requisition')->where('id',$id)->value('id');//se verifica que exista
            if($id){//SE VALIDA QUE LA REQUISICION EXISTA
                if($status == 6){//SE VALIDA QUE LA REQUISICION ESTE EN ESTATUS 5 validando
                    $count =DB::table('product_required')->where('_requisition',$id)->wherenotnull('toDelivered')->where('toDelivered','>',0)->count('_product');//se cuentan cuantos articulos se validaron
                    $sumcase = DB::table('product_required AS PR')->select(DB::raw('SUM(CASE WHEN PR._supply_by = 1 THEN PR.toDelivered  WHEN PR._supply_by = 2  THEN PR.toDelivered * 12  WHEN PR._supply_by = 3  THEN PR.toDelivered * PR.ipack   WHEN PR._supply_by = 4    THEN (PR.toDelivered * (PR.ipack / 2))  ELSE 0  END) AS CASESUM'))->where('PR._requisition', $id)->first(); //se cuenta cuantas piezas se validaron
                     $sum = $sumcase->CASESUM;
                    if($count > 0){//SE VALIDA QUE LA REQUISICION CONTENGA AL MENOS 1 ARTICULO CONTADO
                        $requisitions = DB::table('requisition AS R')->join('workpoints AS W','W.id','=','R._workpoint_from')->where('R.id', $id)->select('R.*','W._client AS cliente')->first();//se realiza el query para pasar los datos de la requisicion con la condicion de el id recibido
                        $clien = $requisitions->cliente;//se obtiene el cliente de el query que es el numero de cliente de la sucursal que pide la mercancia
                        $not = $requisitions->notes;//se obtiene las notas de la requisision


                        $client = "SELECT * FROM F_CLI WHERE CODCLI = $clien";//query para obtener los datos de el cliente directamente de factusol
                        $exec = $this->conn->prepare($client);
                        $exec -> execute();
                        $roles=$exec->fetch(\PDO::FETCH_ASSOC);
                            $rol = $roles["DOCCLI"];//tipo de documento que se debe de crear en factusol
                            $nofcli = $roles["NOFCLI"];//nombre de el cliente
                            $dom = $roles["DOMCLI"];//domicilio
                            $pob = $roles["POBCLI"];//poblacion
                            $cpo = $roles["CPOCLI"];//codigo postal
                            $pro = $roles["PROCLI"];//providencia
                            $tel = $roles["TELCLI"];//telefono
                        $max = "SELECT max(CODFAC) as CODIGO FROM F_FAC WHERE TIPFAC = '".$rol."'";//query para sacar el numero de factura maximo de el tipo(serie)
                        $exec = $this->conn->prepare($max);
                        $exec -> execute();
                        $maxcode=$exec->fetch(\PDO::FETCH_ASSOC);//averS
                            $codfac = intval($maxcode["CODIGO"])+ 1;//se obtiene el nuevo numero de factura que se inserara

                        $prouduct = $this->productrequired($id,$rol,$codfac);//se envian datos id de la requisision, tipo de factura(serie) y codigo de factura a insertar hacia el metodo 
            
                            $fac = [//se crea el arrego para insertar en factusol
                                $rol,//tipo(serie) de factura
                                $codfac,//codigo de factura
                                "P-".$requisitions->id,//codigo de requisision de la aplicacion
                                $date_format,//fecha actual en formato
                                "GEN",//almacen de donde sale la mercancia siempre sera GEN
                                500,//agente que atiende la factura siempre sera 500 cuando es de cedis
                                $clien,//numero de cliente
                                $nofcli,//nombre de cliente
                                $dom,//domicilio
                                $pob,//poblacion
                                $cpo,//codigo postal
                                $pro,//providencia
                                $tel,//telefono
                                $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                                $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                                $prouduct,//el metodo productrequired me devuelve el total o sea que este es el total de la factura compas xd
                                "C30",//la forma de pago siempre esta en credito 30 dias
                                $not,//observaciones 
                                $date_format,//fecha actual en formato
                                $hour,//hora      
                                900,//quien hizo la factura en este caso vizapp
                                900,//quien modifico simpre sera el mismo cuando se insertan
                                1,//iva2
                                2,//iva3
                                "02-01-00",//fehca operacion contable simpre esa cambia hasta que se traspasa a contasol
                                2022,//ano de ejercicio
                                $date_format,//fecha actual en formato
                                1//no se xd pero se requiere para mostrar la factura
                            ];//termino de arreglo de insercion

                        $sql = "INSERT INTO F_FAC (TIPFAC,CODFAC,REFFAC,FECFAC,ALMFAC,AGEFAC,CLIFAC,CNOFAC,CDOFAC,CPOFAC,CCPFAC,CPRFAC,TELFAC,NET1FAC,BAS1FAC,TOTFAC,FOPFAC,OB1FAC,VENFAC,HORFAC,USUFAC,USMFAC,TIVA2FAC,TIVA3FAC,FROFAC,EDRFAC,FUMFAC,BCOFAC) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";//se crea el query para insertar en la tabla
                        $exec = $this->conn->prepare($sql);
                        $exec -> execute($fac);
                        $folio = $rol."-".str_pad($codfac, 6, "0", STR_PAD_LEFT);//se obtiene el folio de la factura
                        DB::table('requisition')->where('id',$id)->update(['invoice'=>$folio]);//se actualiza la columna invoice con el numero de la factura
                        // $curl = curl_init();//inicia el curl para el envio de el mensaje via whats app
                        // curl_setopt_array($curl, array(
                        //   CURLOPT_URL => "https://api.ultramsg.com/instance9800/messages/chat",
                        //   CURLOPT_RETURNTRANSFER => true,
                        //   CURLOPT_ENCODING => "",
                        //   CURLOPT_MAXREDIRS => 10,
                        //   CURLOPT_TIMEOUT => 30,
                        //   CURLOPT_SSL_VERIFYHOST => 0,
                        //   CURLOPT_SSL_VERIFYPEER => 0,
                        //   CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        //   CURLOPT_CUSTOMREQUEST => "POST",
                        //   CURLOPT_POSTFIELDS => "token=6r5vqntlz18k61iu&to=+52$tel&body=el pedido numero P-$id ya esta validado con $count  Modelos y $sum piezas.  El numero de salida es $folio proximo a llegar🤙🛺🚛&priority=1&referenceId=",//se redacta el mensaje que se va a enviar con los modelos y las piezas y el numero de salida
                        //   CURLOPT_HTTPHEADER => array(
                        //     "content-type: application/x-www-form-urlencoded"),));
                        // $response = curl_exec($curl);
                        // $err = curl_error($curl);         
                        // curl_close($curl);
                        return response()->json([
                            "folio"=>$folio,
                            "art_contados"=>$count,
                            "can_contada"=>$sum],201);//se retorna el folio de la factura
                    }else{return response("NO SE PUEDE PROCESAR YA QUE NO HAY ARTICULOS VALIDADOS",400);}
                }else{return response("NO SE CREA LA FACTURA LA REQUISICION AUN NO ES VALIDADA",400);}
            }else{return response("EL CODIGO DE REQUISICION NO EXITE",404);}
        }catch (\PDOException $e){ die($e->getMessage());}
    }
    public function productrequired($id,$rol,$codfac){//metoro de insercion de productos en factusol
        
        $product_require = DB::table('product_required AS PR')//se crea el query para obteener los productos de la requisision
            ->join('products AS P','P.id','=','PR._product')
            ->leftjoin('prices_product AS PP','PP._product','=','P.id')
            ->where('PR._requisition',$id)
            ->wherenotnull('PR.toDelivered')
            ->where('PR.toDelivered','>',0)
            ->select('P.code AS codigo','P.description AS descripcion','PR.toDelivered AS cantidad','PP.AAA AS precio' ,'P.cost as costo','PR._supply_by AS medida','PR.ipack AS PXC')
            ->get();

        $pos= 1;//inicio contador de posision
        $ttotal=0;//inicio contador de total
       
        foreach($product_require as $pro){//inicio de cliclo para obtener productos
            $precio = $pro->precio;//se optiene el precio de cada producto  
            $bull = null;
            if($pro->medida == 1){$canti = $pro->cantidad ;}elseif($pro->medida == 2){$canti = $pro->cantidad * 12; }elseif($pro->medida == 3){$canti = $pro->cantidad * $pro->PXC ; $bull = $pro->cantidad; }elseif($pro->medida == 4){$canti = ($pro->cantidad * ($pro->PXC / 2)) ;}//se valida la unidad de medida de el surtio
            // $bul = $bull > 0 ? $bull : null;
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
                $total,//total de la linea
                $pro->costo,//costo actual de el articulo
                $bull//cajas ponidas
            ];
            $insert = "INSERT INTO F_LFA (TIPLFA,CODLFA,POSLFA,ARTLFA,DESLFA,CANLFA,PRELFA,TOTLFA,COSLFA,BULLFA) VALUES (?,?,?,?,?,?,?,?,?,?)";//query para insertar las lineas de la factura creada en factusol
            $exec = $this->conn->prepare($insert);
            $exec -> execute($values);//envia el arreglo

            $updatestock = "UPDATE F_STO SET ACTSTO = ACTSTO - ? , DISSTO = DISSTO - ?  WHERE  ARTSTO = ? AND ALMSTO = ?";//query para actualizar los stock de el almacen recordemos que solo es general
            $exec = $this->conn->prepare($updatestock);
            $exec -> execute([$pro->cantidad,$pro->cantidad,$pro->codigo, "GEN"]);
            $pos++;//contador
        }  

        return $ttotal;//se retorna el total para el uso en el encabezado de la factura

    }
}