<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;

class OriginController extends Controller{
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

    public function changeCodes(Request $request){
        $products = isset($request->products) ? $request->products : null;
        $report = [];
        if($products){
            //$report['F_BUS'] = $this->replace_col($products, "F_BUS", "CTEBUS");
            $report['F_CIN'] = $this->replace_col($products, "F_CIN", "ARTCIN");
            $report['F_LAL'] = $this->replace_col($products, "F_LAL", "ARTLAL");
            $report['F_LEN'] = $this->replace_col($products, "F_LEN", "ARTLEN");
            $report['F_LFA'] = $this->replace_col($products, "F_LFA", "ARTLFA");
            $report['F_LFB'] = $this->replace_col($products, "F_LFB", "ARTLFB");
            $report['F_LFD'] = $this->replace_col($products, "F_LFD", "ARTLFD");
            $report['F_LFR'] = $this->replace_col($products, "F_LFR", "ARTLFR");
            $report['F_LPA'] = $this->replace_col($products, "F_LPA", "ARTLPA");
            $report['F_LPC'] = $this->replace_col($products, "F_LPC", "ARTLPC");
            $report['F_LPP'] = $this->replace_col($products, "F_LPP", "ARTLPP");
            $report['F_LPS'] = $this->replace_col($products, "F_LPS", "ARTLPS");
            //$report['F_LTA'] = $this->replace_col($products, "F_LTA", "ARTLTA");
            $report['F_LTR'] = $this->replace_col($products, "F_LTR", "ARTLTR");
            $report['F_STC'] = $this->replace_col($products, "F_STC", "ARTSTC");
            //$report['F_STO'] = $this->replace_col($products, "F_STO", "ARTSTO");
        }
        return response()->json($report);
    }

    public function replace_col($products, $table, $col){
        $query = "SELECT * FROM $table WHERE $col = ?";
        $exec = $this->con->prepare($query);
        $query_update = "UPDATE $table SET $col = ? WHERE $col = ?";
        $exec_update = $this->con->prepare($query_update);
        $rows = [];
        foreach($products as $product){
            $exec->execute([$product["old"]]);
            $coincidences = collect($exec->fetchAll(\PDO::FETCH_ASSOC));
            if(count($coincidences)>0){
                foreach($coincidences as $key_row => $row){
                    foreach($row as $key => $col){
                        $row[$key] = mb_convert_encoding($col, "UTF-8", "Windows-1252");
                    }
                    $coincidences[$key_row] = $row;
                }
                $update_operation = $exec_update->execute([$product["new"], $product["old"]]);
                if($update_operation){
                    $accion = "Actualización";
                }else{
                    $accion = "No se ha podido actualizar";
                }

                $res = $coincidences->map(function($row) use($product, $table, $col, $accion){
                    $row["Tabla modificada"] = $table;
                    $row["Campo modificado"] = $col;
                    $row["Valor anterior"] = $product["old"];
                    $row["Valor nuevo"] = $product["new"];
                    $row["Accion"] = $accion;
                    return $row;
                })->toArray();
                $rows[] = $res;
            }
        }
        if(count($rows)>0){
            return array_merge_recursive(...$rows);
        }
        return $rows;
    }

    public function deleteCodes(Request $request){
        $products = isset($request->products) ? $request->products : null;
        $report = [];
        if($products){
            $report['F_ART'] = $this->delete_row($products, "F_ART", "CODART");
            $report['F_STO'] = $this->delete_row($products, "F_STO", "ARTSTO");
            $report['F_LTA'] = $this->delete_row($products, "F_LTA", "ARTLTA");
        }
        return response()->json($report);
    }

    public function delete_row($products, $table, $col){
        $query = "DELETE FROM $table WHERE $col = ?";
        $exec = $this->con->prepare($query);
        $res = [];
        foreach($products as $product){
            $success = $exec->execute([$product["code"]]);
            $res[] = ["code" => $product["code"], "success" => $success];
        }
        return $res;
    }

    public function updateCost(Request $request){
        $products = isset($request->products) ? $request->products : null;
        $report = [];
        if($products){
            $report['F_LFA'] = $this->replace_col($products, "F_LFA", "ARTLFA");
            $report['F_FAC'] = $this->recalculate_row("F_FAC", ["NET1FAC", "BAS1FAC", "TOTFAC"]);
            $report['F_LFR'] = $this->replace_col($products, "F_LFR", "ARTLFR");
            $report['F_FRE'] = $this->recalculate_row("F_FRE", ["NET1FRE", "BAS1FRE", "TOTFRE"]);
            $report['F_LFD'] = $this->replace_col($products, "F_LFD", "ARTLFD");
            $report['F_FRD'] = $this->recalculate_row("F_FRD", ["NET1FRD", "BAS1FRD", "TOTFRD"]);
        }
        return response()->json($report);
    }
}
