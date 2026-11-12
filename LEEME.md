# BenefitRack

Aplicación web para gestionar los beneficios (alimentación, plan de datos, cívica) de los aprendices
del SENA, Centro Textil y de Gestión Industrial. Proyecto formativo ADSO, ficha 3425429.

## Qué hay en cada carpeta

| Carpeta | Qué tiene | Por dónde empezar |
|---|---|---|
| `aplicacion/` | La app: el código PHP, las pantallas y las pruebas automáticas. | `aplicacion/LEEME.md`: cómo instalarla, cómo funciona por dentro y cómo correr las pruebas. |
| `base-de-datos/` | El script que crea la base de datos (`benefitrack_v2.sql`), los datos iniciales, el usuario de la app y las pruebas de la base. | `base-de-datos/README.md` |
| `entregables/` | Los documentos para el instructor y el cliente: ficha del proyecto, matriz de requisitos y catálogo de pantallas (PDF). | Ábrelos directamente. |
| `specs/` | Qué se acordó construir: `001` (base de datos) y `003` (la app). | Útil para la sustentación. |
| `docs/adr/` | Por qué la app se hizo en PHP puro por capas y no con Laravel. | Útil para la sustentación. |

## Para ver la app funcionando

1. Crea la base de datos (paso 1.2 de `aplicacion/LEEME.md`).
2. Configura `aplicacion/config/config.local.php` (paso 1.4).
3. Desde la carpeta `aplicacion`, ejecuta `php -S 127.0.0.1:8080 -t public` y abre <http://127.0.0.1:8080>.
