<?php

namespace App\Http\Controllers;
use DateTime;
use ZipArchive;
use App\Models\File;
use App\Models\Cliente;
use App\Models\Tablero_Sae;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\File as FileForStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                        $rutas = File::select('files.ruta', 'files.files_id')
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
                                        "tablero_sae_id" => $tablero->tablero_sae_id,
                                        "meta" => $fechaMeta,
                                        "url" => $rutaActual,
                                        "id" => $ruta->files_id,
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
                'year_file' => 'required|string',
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

                $directorio = 'Calidad/' . $directorioCliente . '/' . $validatedData['year_file'];

                if (!Storage::disk('evidencias')->exists($directorio)) {
                    Storage::disk('evidencias')->makeDirectory($directorio);
                }
                
                $directorio = $directorio . '/' . $validatedData['tipo_formulario'];

                if (!Storage::disk('evidencias')->exists($directorio)) {
                    Storage::disk('evidencias')->makeDirectory($directorio);
                }

                //----------------------
                // Construimos el nombre del archivo zip, con este formato "2025-06-03_nameFile.zip".
                $originalName = $request->file('archivo')->getClientOriginalName();
                $dateFile = $fecha->format('Y-m-d');
                $fileNameWithoutExtension = pathinfo($originalName, PATHINFO_FILENAME);
                $zipFileName = "{$dateFile}_{$fileNameWithoutExtension}.zip";

                // Crear un archivo ZIP en una ruta temporal.
                $temporaryZipPath = sys_get_temp_dir() . '/' . Str::random(12) . '_' . $zipFileName;
                $zip = new ZipArchive();
                if ($zip->open($temporaryZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                    return response()->json(['message' => 'No se pudo crear el ZIP en ruta temporal.'], 404);
                }

                // Añadir el PDF original dentro del ZIP.
                $actualPathPDF = $request->file('archivo')->getPathname();
                $zip->addFile($actualPathPDF, $originalName);
                $zip->close();

                // Guardar el ZIP en el disco ‘evidencias’ dentro de $directorio
                $storagePath = "{$directorio}/{$zipFileName}";
                Storage::disk('evidencias')->putFileAs(
                    $directorio,
                    new FileForStorage($temporaryZipPath),
                    $zipFileName
                );

                // Eliminar el archivo temporal
                @unlink($temporaryZipPath);

                // $path = $request->file('archivo')->storeAs($directorio, $nombreArchivo, 'evidencias');
                
                $file = new File();
                $file->ruta = $storagePath;
                $file->tipo = $request->file('archivo')->getClientOriginalExtension();
                $file->tablero_sae_id = $validatedData['tablero_sae_id'];
                $file->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Calidad creada con éxito',
                    'data' => [ 'ruta_zip' => $storagePath],
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
                // Obtiene la ruta del zip.
                $zipFullPath = storage::disk('evidencias')->path($validatedData['url']);
                $zip = new ZipArchive();

                // Intenta abrir el ZIP.
                if ($zip->open($zipFullPath) !== true) {
                    return response()->json(['message' => 'Fallo al abrir el ZIP'], 500);
                }

                // Verifica si el ZIP no esta vacío y en caso de no llamamos al unico pdf del ZIP.
                if ($zip->numFiles === 0) {
                    $zip->close();
                    return response()->json(['message' => 'El ZIP está vacío.'], 500);
                }
                $pdf = $zip->getNameIndex(0);

                // Abre un stream al archivo.
                $stream = $zip->getStream($pdf);
                if ($stream === false) {
                    $zip->close();
                    return response()->json(['message' => 'No se pudo abrir el archivo dentro de ZIP.'], 500);
                }

                // Prepara una respuesta en streaming con encabezado PDF.
                $response = new StreamedResponse(function() use($stream, $zip) {
                    fpassthru($stream);
                    fclose($stream);
                    $zip->close();
                }, 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => "attachment; filename=\"{$pdf}\"",
                ]);

                return $response;
            } else {
                return response()->json(['message' => 'Archivo no encontrado.','errors' => $request], 422);
            }
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al descargar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function delete(Request $request) {
        try {
            // Validar los datos entrantes
            $validatedData = $request->validate([
                'url' => 'required|string',
                'id' => 'required|integer'
            ]);

            $file = File::withTrashed()->find($validatedData['id']);

            if ($file) {
                $file->delete();
            } else {
                return response()->json(['success' => false, 'message' => 'No se encontro el archivo en la BD.'], 404);
            }

            if (Storage::disk('evidencias')->exists($validatedData['url'])) {
                Storage::disk('evidencias')->delete($validatedData['url']);
            }
            
            return response()->json(['success' => true, 'message' => 'Eliminación del archivo completa.'], 200);
        } catch (ValidationException $e) {
            // Si la validación falla, se capturan los errores y se devuelven
            return response()->json([
                'message' => 'Error en la validación de los datos de file',
                'errors' => $request
            ], 422);
        } catch (\Exception $e) {
            // Si ocurre cualquier otro error, devolver un error general
            return response()->json([
                'message' => 'Ha ocurrido un error al descargar el archivo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
