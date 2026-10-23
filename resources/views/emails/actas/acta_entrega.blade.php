<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Asignación de Equipo de Cómputo - IPS Clinical House</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; font-size: 14px; color: #1e293b; line-height: 1.6; margin: 0; padding: 20px; background-color: #ffffff;">
    <p>Estimado(a) <strong>{{ $nombreFuncionario }}</strong>,</p>

    <p>Reciba un cordial saludo de parte de <strong>IPS Clinical House</strong>.</p>

    <p>Por medio del presente mensaje, remitimos adjunto los documentos oficiales correspondientes a la asignación de su equipo de cómputo:</p>

    <p style="margin-top: 16px; margin-bottom: 4px;"><strong>Resumen del Equipo Asignado:</strong></p>
    <p style="margin: 0; line-height: 1.6;">
        <strong>Nombre / Hostname:</strong> {{ $equipo->nombre_equipo ?? 'No registrado' }}<br>
        <strong>Tipo de Equipo:</strong> {{ $equipo->tipo ?? 'No especificado' }}<br>
        <strong>Marca y Modelo:</strong> {{ $equipo->marca ?? 'N/A' }} {{ $equipo->modelo ?? '' }}<br>
        <strong>Número de Serial:</strong> {{ $equipo->serial ?? 'No registrado' }}<br>
        <strong>N° de Inventario:</strong> {{ $equipo->numero_inventario ?? 'N/A' }}<br>
        <strong>Sede / Ubicación:</strong> {{ optional($equipo->sede)->nombre ?? 'No asignada' }}<br>
        <strong>Área / Servicio:</strong> {{ optional($equipo->area)->nombre ?? 'No asignada' }}
    </p>

    <p style="margin-top: 16px;">En el presente correo se adjunta la hoja de vida del equipo de computo y el acta de entrega del mismo.</p>

    <p>Para cualquier solicitud, reporte o inquietud de soporte, puede comunicarse a través de nuestros canales de atención:</p>

    <p style="margin-top: 4px; margin-bottom: 16px;">• Numero de contacto: <strong>310 301 0337</strong></p>

    <p>Estamos comprometidos con su bienestar y con brindarle las mejores herramientas tecnológicas para el óptimo desempeño de sus labores.</p>

    <p>Agradecemos su compromiso institucional.</p>

    <p style="margin-top: 24px;">Cordialmente,</p>

    <p style="margin-top: 4px; line-height: 1.4;">
        <strong>Gestión de Tecnología y sistemas de información</strong><br>
        NexaCore - IPS Clinical House
    </p>
</body>
</html>
