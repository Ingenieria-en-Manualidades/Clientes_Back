<?php

return [
    // Cambia la versión cuando actualices la política para volver a pedir aceptación
    'version' => env('DATA_POLICY_VERSION', 'v2025.01'),
    // Ruta del archivo (markdown o HTML) con el contenido
    'path' => resource_path('policies/data_policy_es.md'),
];
