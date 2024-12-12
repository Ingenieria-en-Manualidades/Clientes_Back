<?php

namespace App\Http\Controllers;
use DateTime;
use App\Models\File;
use App\Models\Cliente;
use App\Models\Tablero_Sae;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function store(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'cliente_endpoint' => 'required|integer',
            ]);

            $verificacionCliente = Cliente::select('clientes.*')
            ->where('clientes.cliente_endpoint_id','=', $validatedData['cliente_endpoint'])
            ->get();

            $archivos = [];
            $archivosInexistentes = [];
            if ($verificacionCliente->isEmpty()) {
                return response()->json([
                    'message' => 'Cliente inexistente en el sistema.',
                    'errors' => $request
                ], 404);
            } else {
                $consTableroSae = Tablero_Sae::select('tablero_sae.tablero_sae_id', 'tablero_sae.fecha')
                ->join('clientes', 'clientes.id', '=','tablero_sae.cliente_id')
                ->where('clientes.cliente_endpoint_id','=', $validatedData['cliente_endpoint'])
                ->orderBy('tablero_sae.fecha', 'DESC')->get();
                
                if ($consTableroSae->isEmpty()) {
                    return response()->json([
                        'message' => 'Ninguna meta registrada.',
                        'errors' => $request
                    ], 405);
                } else {
                    foreach ($consTableroSae as $tablero) {
                        $rutas = File::select('files.ruta')
                        ->where('files.tablero_sae_id', '=', $tablero->tablero_sae_id)
                        ->get();
    
                        if (!$rutas->isEmpty()) {
                            foreach ($rutas as $ruta) {
                                $rutaActual = $ruta->ruta;
                                $nombre = basename('evidencias/' . $rutaActual);

                                $date = new DateTime($tablero->fecha);
                                $fechaMeta = $date->format('Y') .'-'. $date->format('m');

                                $buscarCalidad = strpos($rutaActual, 'checklist');
                                $tipoCalidad = "";
                                if ($buscarCalidad !== false) {
                                    $tipoCalidad = "Checklist";
                                } else {
                                    $tipoCalidad = "Inspección sol";
                                }

                                if (Storage::disk('evidencias')->exists($rutaActual)) {
                                    $archivos[] = [
                                        "nombre" => $nombre,
                                        "tipo_calidad" => $tipoCalidad,
                                        "meta" => $fechaMeta,
                                        "url" => $rutaActual,
                                    ];
                                } else {
                                    $archivosInexistentes[] = [
                                        "nombre" => $nombre,
                                        "tipo_calidad" => $tipoCalidad,
                                        "meta" => $fechaMeta,
                                        "url" => null,
                                    ];
                                }
                            }
                        }
                    }
                    return response()->json([
                        'success' => true,
                        'message' => 'Carga exitosa de archivos.',
                        'archivos' => $archivos,
                        'archivosInexistentes' => $archivosInexistentes,
                    ], 200);
                }
            }
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de file',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al cargar.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function saveFileCalidad(Request $request){
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'archivo' => 'required|file|mimes:pdf',
                'cliente_endpoint_id' => 'required|integer',
                'tipo_formulario' => 'required|string',
                'tablero_sae_id' => 'required|integer',
            ]);

            $nombreCliente = Cliente::select('clientes.nombre')->where('clientes.cliente_endpoint_id', '=', $validatedData['cliente_endpoint_id'])
            ->get();

            if ($nombreCliente->isEmpty()) {
                return response()->json(['message' => 'No existe un cliente con ese ID.','errors' => $request], 404);
            } else {
                $fecha = new DateTime();
                $directorioCliente = str_replace(' ','-', $nombreCliente[0]->nombre);

                if (!Storage::disk('evidencias')->exists('Calidad/' . $directorioCliente)) {
                    Storage::disk('evidencias')->makeDirectory('Calidad/' . $directorioCliente);
                }

                $directorio = 'Calidad/' . $directorioCliente . '/' . $fecha->format('Y');

                if (!Storage::disk('evidencias')->exists($directorio)) {
                    Storage::disk('evidencias')->makeDirectory($directorio);
                }
                
                $directorio = $directorio . '/' . $validatedData['tipo_formulario'];

                if (!Storage::disk('evidencias')->exists($directorio)) {
                    Storage::disk('evidencias')->makeDirectory($directorio);
                }

                $nombreArchivo = $nombreArchivo = $fecha->format('Y-m-d') . "_" . $request->file('archivo')->getClientOriginalName();

                $path = $request->file('archivo')->storeAs($directorio, $nombreArchivo, 'evidencias');
                
                $file = new File();
                $file->ruta = $path;
                $file->tipo = $request->file('archivo')->getClientOriginalExtension();
                $file->tablero_sae_id = $validatedData['tablero_sae_id'];
                $file->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Calidad creada con éxito',
                    'data' => $request,
                ], 200);
            }
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de file',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al guardar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadFile(Request $request) {
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'url' => 'required|string',
            ]);

            if (Storage::disk('evidencias')->exists($validatedData['url'])) {
                return Storage::disk('evidencias')->download($validatedData['url']);
            } else {
                return response()->json([
                    'message' => 'Archivo no encontrado.',
                    'errors' => $request
                ], 422);
            }
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al descargar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
