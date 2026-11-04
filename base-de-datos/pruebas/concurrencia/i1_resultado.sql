USE benefitrack_v2;
SELECT a.numero_pagos,
       (SELECT COUNT(*) FROM pagos p WHERE p.id_asignacion = a.id_asignacion AND p.estado = 'Registrado') AS pagos_vigentes,
       a.estado,
       IF(a.estado = 'Finalizado', 'CORRECTO: quedó Finalizado', 'ERROR: todos los pagos están registrados pero no quedó Finalizado') AS veredicto
  FROM asignaciones_beneficio a WHERE a.id_postulacion = 3;
