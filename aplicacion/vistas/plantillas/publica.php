<?php

/**
 * MARCO PROVISIONAL "publica": envuelve las páginas sin sesión.
 * La tarea 3 lo reemplaza por la tarjeta centrada del login.
 * Recibe: $contenido (el HTML de la página, ya seguro).
 */
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BenefitRack</title>
</head>
<body>
<?= $contenido ?>
</body>
</html>
