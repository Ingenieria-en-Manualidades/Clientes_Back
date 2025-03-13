<?php

namespace App\Http\Controllers;

use DateTime;
use App\Models\MetaUnidades;
use Illuminate\Http\Request;
use App\Models\UnidadesDiarias;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class UnidadesDiariasController extends Controller
{
    public function create(Request $request)
    {
        try {
            $validateData = $request->validate([
                'valor' => 'required|integer',
                'fecha_programacion' => 'required|date',
                'usuario' => 'required|string',
            ]);

            $dateExisting = UnidadesDiarias::where('fecha_programacion', $validateData['fecha_programacion'])->first();

            if ($dateExisting) {
                return response()->json(['message' => 'Ya hay unidades programadas para el día ingresado.'], 409);
            } else {
                $date = new DateTime($validateData['fecha_programacion']);
                $metaUnidadesID = MetaUnidades::where('fecha_meta','like',$date->format('Y') .'-'. $date->format('m') .'%')->first();
                
                if ($metaUnidadesID) {
                    $unidades = new UnidadesDiarias();
                    $unidades->valor = $validateData['valor'];
                    $unidades->fecha_programacion = $validateData['fecha_programacion'];
                    $unidades->meta_unidades_id = $metaUnidadesID->meta_unidades_id;
                    $unidades->usuario = $validateData['usuario'];
                    $unidades->save();
                    return response()->json(['message' => 'Unidades programadas guardadas con exito.'], 200);
                } else {
                    return response()->json(['message' => 'No existe una meta de unidades para el día ingresado.'], 404);
                }
            }
        } catch (validationException $e) {
            return response()->json(['message' => 'Error en la validación de la unidades programadas diarias.', 'error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Ha ocurrido un error al guardar las unidades diarias.', 'error' => $e->getMessage()], 500);
        }
    }
}
