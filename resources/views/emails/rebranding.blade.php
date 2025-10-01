@php
  $bloque = match ($operation) {
    'logistica'   => "Ahora tu relación con nosotros se vive bajo **IM Logística**: misma esencia, una imagen más clara y moderna.",
    'manufactura' => "Seguimos contigo como **IM Manufactura**: evolucionamos la marca, conservamos el compromiso.",
    'maquila'     => "Formas parte de **IM Maquila**: nueva imagen, el mismo equipo y calidad.",
    'aeropuertos' => "Te acompañamos como **IM Aeropuertos**: identidad renovada, servicio intacto.",
    'zona_franca' => "Caminamos como **IM Zona Franca**: renovación visual con la misma calidad.",
    'soluciones'  => "Eres parte de **IM Soluciones**: un nombre que refleja lo que construimos juntos.",
    default       => "",
  };
@endphp

@component('mail::message')
# Hola {{ $name }},

En **IM** evolucionamos nuestra marca: ahora somos **IM Ingeniería**.  
Cambiamos el logo y la forma de presentarnos, **no** cambia tu equipo, la calidad ni el compromiso.

@if($bloque){!! $bloque !!}@endif

@component('mail::button', ['url' => $surveyUrl])
Responder encuesta de satisfacción
@endcomponent

Gracias por seguir caminando con nosotros.  
**IM Ingeniería**
@endcomponent
