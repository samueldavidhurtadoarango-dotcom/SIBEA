USE benefitrack_v2;
SELECT c.cupos,
       (SELECT COUNT(*) FROM postulaciones p WHERE p.id_convocatoria = 1 AND p.estado = 'Aprobada') AS aprobadas,
       IF((SELECT COUNT(*) FROM postulaciones p WHERE p.id_convocatoria = 1 AND p.estado = 'Aprobada') <= c.cupos,
          'CORRECTO: no se superan los cupos', 'ERROR: se superaron los cupos') AS veredicto
  FROM convocatorias c WHERE c.id_convocatoria = 1;
