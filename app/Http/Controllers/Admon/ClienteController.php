<?php

namespace App\Http\Controllers\Admon;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use GuzzleHttp\Client;
use Illuminate\View\View;

class ClienteController extends Controller
{
    /**
     * Muestra una lista de los clientes.
     *
     * @desc Este método obtiene todos los clientes de la base de datos y los pasa a la vista.
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $clientes = Cliente::all();
        return view('clientes.index', [
            'clientes' => $clientes,
        ]);
    }

    /**
     * Consume un endpoint y almacena los datos en la tabla Cliente.
     *
     * @desc Este método consume un endpoint externo para obtener datos de clientes y los guarda en la base de datos.
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function endpoint(Request $request)
    {
        // Crear una nueva instancia del cliente HTTP Guzzle
        $client = new Client();

        try {
            // Realiza una solicitud GET al endpoint deseado
            $response = $client->request('GET', 'https://imecplusdev.ienm.com.co:8443/api/cliente/list');

            // Obtiene el cuerpo de la respuesta
            $data = $response->getBody()->getContents();

            // Decodifica la respuesta JSON a un array asociativo
            $clientesData = json_decode($data, true);

            // Itera sobre los datos de los clientes
            foreach ($clientesData as $clienteData) {
                // Sanitiza los datos
                $nombre = htmlspecialchars($clienteData['nombre'], ENT_QUOTES, 'UTF-8');
                $clienteId = htmlspecialchars($clienteData['cliente_id'], ENT_QUOTES, 'UTF-8');

                // Busca si ya existe un cliente con el cliente_endpoint_id
                $cliente = Cliente::firstOrNew(['cliente_endpoint_id' => $clienteId]);

                // Solo actualiza y guarda si es un nuevo registro
                if (!$cliente->exists) {
                    $cliente->nombre = $nombre;
                    $cliente->save();
                }
            }

            // Redirige de vuelta a la página de clientes con un mensaje de éxito
            return redirect()->route('clientes.index')->with('success', 'Datos de clientes guardados correctamente');
        } catch (\Exception $e) {
            // Maneja cualquier error
            return redirect()->route('clientes.index')->with('error', $e->getMessage());
        }
    }
   /**
     * Actualiza el estado de un cliente (activar/desactivar).
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            '_token' => 'required|string',
            '_method' => 'required|string|in:PUT'
        ]);

        try {
            $cliente = Cliente::findOrFail($id);

            // Alternar el estado del cliente
            $cliente->activo = $cliente->activo === 's' ? 'n' : 's';
            $cliente->save();

            return redirect()->back()->with('success', 'El estado del cliente ha sido actualizado.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Ocurrió un error al actualizar el estado del cliente.');
        }
    }



    /**
     * Elimina el cliente especificado del almacenamiento.
     *
     * @desc Este método elimina un cliente específico de la base de datos usando softdeletes.
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(int $id)
    {
        $cliente = Cliente::findOrFail($id);
        $cliente->delete();
        return redirect()->back()->with('success', 'Cliente borrado correctamente.');
    }

    /**
     * Muestra una lista de los clientes deshabilitados.
     *
     * @desc Este método obtiene una lista de todos los clientes deshabilitados (eliminados softdelete).
     * @return \Illuminate\Http\JsonResponse
     */
    public function deshabilitados()
    {
        $clientes = Cliente::onlyTrashed()->get();
        return response()->json($clientes);
    }

    /**
     * Restaura el cliente deshabilitado especificado.
     *
     * @desc Este método restaura un cliente previamente deshabilitado (eliminado softdeletes).
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function restaurar(int $id)
    {
        $cliente = Cliente::withTrashed()->findOrFail($id);
        $cliente->restore();
        return redirect()->back()->with('success', 'Cliente restaurado con éxito.');
    }

    /**
     * Elimina permanentemente el cliente deshabilitado especificado.
     *
     * @desc Este método elimina permanentemente un cliente previamente deshabilitado.
     * @param int $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function eliminacion(int $id)
    {
        $cliente = Cliente::withTrashed()->findOrFail($id);
        $cliente->forceDelete();
        return redirect()->back()->with('success', 'Cliente eliminado permanentemente con éxito.');
    }
}
