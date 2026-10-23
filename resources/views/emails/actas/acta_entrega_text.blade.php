Estimado(a) {{ $nombreFuncionario }},

Reciba un cordial saludo de parte de IPS Clinical House.

Por medio del presente mensaje, remitimos adjunto los documentos oficiales correspondientes a la asignación de su equipo de cómputo:

Resumen del Equipo Asignado:
Nombre / Hostname: {{ $equipo->nombre_equipo ?? 'No registrado' }}
Tipo de Equipo: {{ $equipo->tipo ?? 'No especificado' }}
Marca y Modelo: {{ $equipo->marca ?? 'N/A' }} {{ $equipo->modelo ?? '' }}
Número de Serial: {{ $equipo->serial ?? 'No registrado' }}
N° de Inventario: {{ $equipo->numero_inventario ?? 'N/A' }}
Sede / Ubicación: {{ optional($equipo->sede)->nombre ?? 'No asignada' }}
Área / Servicio: {{ optional($equipo->area)->nombre ?? 'No asignada' }}

En el presente correo se adjunta la hoja de vida del equipo de computo y el acta de entrega del mismo.

Para cualquier solicitud, reporte o inquietud de soporte, puede comunicarse a través de nuestros canales de atención:

• Numero de contacto: 310 301 0337

Estamos comprometidos con su bienestar y con brindarle las mejores herramientas tecnológicas para el óptimo desempeño de sus labores.

Agradecemos su compromiso institucional.

Cordialmente,

Gestión de Tecnología y sistemas de información
NexaCore - IPS Clinical House
