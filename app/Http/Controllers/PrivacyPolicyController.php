<?php

namespace App\Http\Controllers;

use App\Models\PrivacyPolicyAcceptance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;

class PrivacyPolicyController extends Controller
{
    /**
     * Devuelve el contenido de la política (markdown o html) y su versión.
     */
    public function show(Request $request): JsonResponse
    {
        $version = Config::get('privacy_policy.version', 'v1');
        $path    = Config::get('privacy_policy.path');
        $format  = 'markdown';
        $content = '';

        if ($path && File::exists($path)) {
            $content = File::get($path);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (in_array($ext, ['html', 'htm'])) {
                $format = 'html';
            }
        }

        return response()->json([
            'version' => $version,
            'format'  => $format,
            'content' => $content,
        ]);
    }

    /**
     * Indica si el usuario autenticado ya aceptó la política vigente.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $version  = Config::get('privacy_policy.version', 'v1');
        $accepted = PrivacyPolicyAcceptance::where('user_id', $user->id)
            ->where('policy_version', $version)
            ->exists();

        return response()->json([
            'version'  => $version,
            'accepted' => $accepted,
        ]);
    }

    /**
     * Registra (o actualiza) la aceptación de la política para el usuario autenticado.
     */
    public function accept(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $version = Config::get('privacy_policy.version', 'v1');

        PrivacyPolicyAcceptance::updateOrCreate(
            ['user_id' => $user->id, 'policy_version' => $version],
            [
                'accepted_at' => now(),
                'ip_address'  => $request->ip(),
                'user_agent'  => (string) $request->userAgent(),
            ]
        );

        return response()->json(['ok' => true, 'version' => $version], 201);
    }
}
