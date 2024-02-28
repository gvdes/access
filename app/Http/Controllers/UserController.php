<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class UserController extends Controller{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    // public function __construct(){
    //     try{
    //         $access = env('ACCESS_FILE');
    //         $db = new \PDO("odbc:DRIVER={Microsoft Access Driver (*.mdb, *.accdb)};charset=UTF-8; DBQ=".$access."; Uid=; Pwd=;");
    //         $this->con = $db;
    //     }catch(PDOException $e){
    //         return response()->json(["message" => "Algo salio mal con la conexión a la base de datos"]);
    //     }
    // }

    public function getUsers(){
        $query = "SELECT CODAGE, FALAGE, NOMAGE FROM F_AGE";
        $exec = $this->con->prepare($query);
        $exec->execute();

        $rows = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
        $result = $rows->map(function($user){ //Seller or agent
            return [
                "id" => $user["CODAGE"],
                "name" => mb_convert_encoding((string)$user["NOMAGE"], "UTF-8", "Windows-1252"),
                "created_at" => $user["FALAGE"]
            ];
        });
        return response()->json($result);
    }

    public function getRawUsers(){
        $query = "SELECT * FROM F_AGE";
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

    public function syncUsers(Request $request){
        if($request->users){
            $keys = array_keys($request->users[0]);
            $toUpdate = "";
            $values = "";
            $cols = "";
            foreach($keys as $i => $key){
                if($i == 0){
                    $toUpdate = $key." = ?";
                    $cols = " ".$key;
                    $values = " ?";
                }else{
                    $toUpdate = $toUpdate.", ".$key." = ?";
                    $values = $values.", ?";
                    $cols = $cols.", ".$key;
                }
            }
            $query_select = "SELECT count(*) FROM F_AGE WHERE CODAGE = ?";
            $exec_select = $this->con->prepare($query_select);

            $query_update = "UPDATE F_AGE SET ".$toUpdate." WHERE CODAGE = ?";
            $exec_update = $this->con->prepare($query_update);

            $query_insert = "INSERT INTO F_AGE (".$cols.") VALUES(".$values.")";
            $exec_insert = $this->con->prepare($query_insert);

            $response = [];
            foreach($request->users as $key => $user){
                $exec_select->execute([$user["CODAGE"]]);
                $count = intval($exec_select->fetch(\PDO::FETCH_ASSOC)['Expr1000']);
                if($count == 1){
                    $toUpdate = array_values($user);
                    $toUpdate[] = $user["CODAGE"];
                    $result = $exec_update->execute($toUpdate);
                    if($result){
                        $accion = "Actualización";
                    }else{
                        $accion = "No se a podido actualizar";
                    }
                }else if($count == 0){
                    $result = $exec_insert->execute($array_values($user));
                    if($result){
                        $accion = "Creado";
                    }else{
                        $accion = "No se ha podido crear";
                    }
                }else{
                    $accion = "Duplicado";
                }
                $response[] = ["# Agente" => $user["CODAGE"], "Agente" => $user["NOMAGE"], "Acción" => $accion];
            }
            return $response;
        }else{
            return response()->json(["msg" => "Sin agentes por actualizar"]);
        }
    }

    public function permission(Request $request){
        $failstores = [];
        $stor = [];
        $idpermission = $request->id;
        $select = "SELECT CODPER, DESPER, CADPER FROM T_PER WHERE CODPER = $idpermission";
        $exec = $this->con->prepare($select);
        $exec->execute();
        $fil = $exec->fetch(\PDO::FETCH_ASSOC);

        $stores = DB::table('workpoints')->where('_type',2)->where('active',1)->get();
        foreach($stores as $store){
          $url = $store->dominio."/access/public/user/replypermission";//se optiene el inicio del dominio de la sucursal
          $ch = curl_init($url);//inicio de curl
          $data = json_encode(["permisos" => $fil]);//se codifica el arreglo de los proveedores
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
            //   $failstores[] =["sucursal"=>$store->alias, "mssg"=>$exec];//la sucursal se almacena en sucursales fallidas

          }else{
              $stor[] =["sucursal"=>$store->alias, "mssg"=>$exc];
          }
          curl_close($ch);//cirre de curl
        }
        $res = [
            "store"=>$stor,
            "fail"=>$failstores,
            "replicados"=>$fil,
          ];


          return response()->json($res);
    }

    public function replypermission(Request $request){
        $updat = $request->permisos;

        $updated = "UPDATE T_PER SET DESPER = "."'".$updat['DESPER']."'"." , CADPER = "."'".$updat['CADPER']."'"." WHERE CODPER = ".$updat['CODPER'];
        $exec = $this->con->prepare($updated);
        $exec->execute();
        $res = [
            "msg"=>"LISTO BROU"
        ];
        return $res;

    }

    public function highusers(Request $request){
        $false = [
            "nick"=>[],
            "_wp_workpoint"=>[],
            "_rol"=>[]
        ];
        $goals = [];
        $req = $request->all();
        if($req){
            foreach($req as $requi){
                $nick = $requi['nick'];
                    $name = trim($requi['complete_name']);

                    if($space === 2){
                        $nom = explode(" ",$name);
                        $names = $nom[0];
                        $surname_pat = $nom[1];
                        $surname_mat = $nom[2];
                    }elseif($space === 3){
                        $nom = explode(" ",$name);
                        $names = $nom[0]." ".$nom[1];
                        $surname_pat = $nom[2];
                        $surname_mat = $nom[3];
                    }
                    $wp = $requi['id_wp'];
                    $existwp = DB::table('workpoints')->where('id',$wp)->first();
                    if($existwp){
                        $rol = $requi['_rol'];
                        $existrol = DB::table('roles')->where('id',$rol)->first();
                        if($existrol){
                            $insert = [
                                "nick"=>$nick,
                                "password"=>'$2y$10$l5xgt3LlOM0WQFlbJcvTkOOpaBW/S.1RwWffuTmaeMUtB6ahSzhBW',
                                "picture"=>'',
                                "names"=>$names,
                                "surname_pat"=>$surname_pat,
                                "surname_mat"=>$surname_mat,
                                "change_password"=>1,
                                "_wp_principal"=>$wp,
                                "_rol"=>$rol,
                                "created_at"=>Carbon::now()->subHour(),
                                "updated_at"=>Carbon::now()->subHour()
                            ];
                            $dbins = DB::table('accounts')->insert($insert);
                            if($dbins){
                            $goals[] = $insert;
                            }else{
                                $false['insert'][] = "no se pudo insertar el usuario ".$nick;
                            }
                        }else{
                            $false['_rol'][] = "El id del rol ".$rol." no existe en el usuario ".$nick;
                        }
                    }else{
                        $false['_wp_workpoint'][] = "El id de la sucursal ".$wp." no existe en el usuario ".$nick;
                    }

            }
            $res = [
                'goals'=>$goals,
                'falsse'=>$false
            ];
            return response()->json($res);
        }else {
            return response()->json("No se recibio ningun parametro",404);
        }

    }
}
