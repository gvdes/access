<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller{
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
  
  public function getClients(Request $request){
    $query = "SELECT CODCLI, NOFCLI, DOMCLI, POBCLI, CPOCLI, PROCLI, TELCLI, TARCLI, FALCLI, EMACLI FROM F_CLI";
    $query = ($request->date && !is_null($request->date)) ? $query." WHERE FUMCLI >= #".$request->date."#" : $query;
    $exec = $this->con->prepare($query);
    $exec->execute();

    $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
    $result = $rows->map(function($client){
      return [
        'id' => intval($client['CODCLI']),
        'name' => mb_convert_encoding((string)$client['NOFCLI'], "UTF-8", "Windows-1252"),
        'phone' => mb_convert_encoding((string)$client['TELCLI'], "UTF-8", "Windows-1252"),
        'email' => mb_convert_encoding((string)$client['EMACLI'], "UTF-8", "Windows-1252"),
        'rfc' => '',
        'address' => json_encode([
          "calle" => mb_convert_encoding((string)$client['DOMCLI'], "UTF-8", "Windows-1252"),
          "colonia" => mb_convert_encoding((string)$client['POBCLI'], "UTF-8", "Windows-1252"),
          "cp" => intval(mb_convert_encoding((string)$client['CPOCLI'], "UTF-8", "Windows-1252")),
          "municipio" => mb_convert_encoding((string)$client['PROCLI'], "UTF-8", "Windows-1252")
        ]),
        '_price_list' => intval($client['TARCLI']),
        "created_at" => $client['FALCLI']
      ];
    });
    return response()->json($result);
  }

  public function getRawClients(Request $request){
    $query = "SELECT * FROM F_CLI";
    $query = ($request->date && !is_null($request->date)) ? $query." WHERE CODCLI > 6 AND FUMCLI >= #".$request->date."#" : $query;
    $exec = $this->con->prepare($query);
    $exec->execute();
    $rows = $exec->fetchAll(\PDO::FETCH_ASSOC);
    foreach($rows as $key_row => $row){
      foreach($row as $key => $col){
        $row[$key] = mb_convert_encoding($col, "UTF-8", "Windows-1252");
      }
      $rows[$key_row] = $row;
    }
    return array_map(function($row){
      return ["id" => $row["CODCLI"],"init" => array_slice($row, 0,100), "end" => array_slice($row, 100)];
    }, $rows);
    return $rows;
  }

  public function syncClients(Request $request){
    if($request->clients){
      $init = array_keys($request->clients[0]["init"]);
      $end = array_keys($request->clients[0]["end"]);
      $toUpdate_init = "";
      $values_init = "";
      $cols_init = "";
      foreach($init as $i => $key){
        if($i == 0){
          $toUpdate_init = $key." = ?";
          $cols_init = " ".$key;
          $values_init = " ?";
        }else{
          $toUpdate_init = $toUpdate_init.", ".$key." = ?";
          $values_init = $values_init.", ?";
          $cols_init = $cols_init.", ".$key;
        }
      }
      $toUpdate_end = "";
      foreach($end as $i => $key){
        if($i == 0){
          $toUpdate_end = $key." = ?";
        }else{
          $toUpdate_end = $toUpdate_end.", ".$key." = ?";
        }
      }

      $query_select = "SELECT count(*) FROM F_CLI WHERE CODCLI = ?";
      $exec_select = $this->con->prepare($query_select);

      $query_update_init = "UPDATE F_CLI SET ".$toUpdate_init." WHERE CODCLI = ?";
      $exec_update_init = $this->con->prepare($query_update_init);

      $query_update_end = "UPDATE F_CLI SET ".$toUpdate_end." WHERE CODCLI = ?";
      $exec_update_end = $this->con->prepare($query_update_end);

      $query_insert = "INSERT INTO F_CLI (".$cols_init.") VALUES(".$values_init.")";
      $exec_insert = $this->con->prepare($query_insert);

      $response = [];
      foreach($request->clients as $key => $client){
        $exec_select->execute([$client["id"]]);
        $count = intval($exec_select->fetch(\PDO::FETCH_ASSOC)['Expr1000']);
        $success = true;
        if($count == 1){
          $values_update_init = array_values($client["init"]);
          $values_update_end = array_values($client["end"]);
          $values_update_init[] = $client["id"];
          $values_update_end[] = $client["id"];
          /* $toUpdate[] = $client["id"]; */
          $result_init = $exec_update_init->execute($values_update_init);
          $result_end = $exec_update_end->execute($values_update_end);
          if($result_init && $result_end){
            $accion = "Actualización";
          }else{
            $accion = "No se a podido actualizar";
            $success = false;
          }
        }else if($count == 0){
          $result = $exec_insert->execute(array_values($client["init"]));
          if($result){
            $values_update_end = array_values($client["end"]);
            $values_update_end[] = $client["id"];
            $result_end = $exec_update_end->execute($values_update_end);
            if($result_end){
              $accion = "Creado";
            }else{
              $accion = "Creación incompleta";
              $success = false;
            }
          }else{
            $accion = "No se ha podido crear";
            $success = false;
          }
        }else{
          $accion = "Duplicado";
          $success = false;
        }
        $response[] = ["# Cliente" => $client["id"], "Cliente" => $client["init"]["NOFCLI"], "Acción" => $accion, "success" => $success];
      }
      $successful = array_reduce($response, function($success, $row){
        return $row["success"] == $success;
      }, true);
      return ["success" => $successful, "log" => $response];
    }else{
      return response()->json(["msg" => "Sin clientes por actualizar"]);
    }
  }

  public function replyespecial(Request $request){
    $failstores = [];
    $stor = [];
    $idclient = $request->id;
    $select = "SELECT * FROM F_PRC WHERE CLIPRC = $idclient";
    $exec = $this->con->prepare($select);
    $exec->execute();
    $fil = $exec->fetchall(\PDO::FETCH_ASSOC);
    if($fil){
      foreach($fil as $row){
        $sel[] = $row;
      }

      $stores = DB::table('workpoints')->where('_type',2)->where('active',1)->get();
      foreach($stores as $store){
        $url = $store->dominio."/access/public/client/repes";//se optiene el inicio del dominio de la sucursal
        $ch = curl_init($url);//inicio de curl
        $data = json_encode(["precios" => $sel,"client"=>$idclient]);//se codifica el arreglo de los proveedores
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
            $failstores[] =$store->alias." sin conexion";//la sucursal se almacena en sucursales fallidas
            // $failstores[] =["sucursal"=>$store->alias, "mssg"=>$exec];//la sucursal se almacena en sucursales fallidas

        }else{
            $stor[] =["sucursal"=>$store->alias, "mssg"=>$exc];
        }
        curl_close($ch);//cirre de curl
      }

      $res = [
        "store"=>$stor,
        "fail"=>$failstores,
        "idcliente"=>$idclient,
        "preciosespeciales"=>$sel
      ];


      return response()->json($res);
    }else{return response()->json("No se encuentra el cliente con precios especiales",404);}
  }

  public function repes(Request $request){
    $idclient = $request->client;

    $delete = "DELETE FROM F_PRC WHERE CLIPRC = $idclient";
    $exec = $this->con->prepare($delete);
    $exec->execute();

    $especial = $request->precios;

    foreach($especial as $row){
      $date [] = $row;
      $column = array_keys($row);
      $values = array_values($row);
      $impcol = implode(",",$column);
      $signos = implode(",",array_fill(0, count($column),'?'));
      $insert = "INSERT INTO F_PRC ($impcol) VALUES ($signos)";
      $exec = $this->con->prepare($insert);
      $exec -> execute($values);
    }
    $res = [
      "msg"=>"correcto en efeito",
      "idcliente"=>$idclient,
      "precios"=>count($date)
    ];
    return $res;
  }
  
}
