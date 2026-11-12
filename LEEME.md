# BenefitRack

Aplicación web para gestionar los beneficios (alimentación, plan de datos, cívica) de los aprendices
del SENA, Centro Textil y de Gestión Industrial. Proyecto formativo ADSO, ficha 3425429.

El proyecto está dividido en dos partes:

## `codigo/`: lo que hace funcionar la app (lo que se expone)

| Carpeta | Qué tiene | Por dónde empezar |
|---|---|---|
| `codigo/aplicacion/` | La app: el código PHP, las pantallas y las pruebas automáticas. | `codigo/aplicacion/LEEME.md`: cómo instalarla, cómo funciona por dentro y cómo correr las pruebas. |
| `codigo/base-de-datos/` | El script que crea la base de datos (`benefitrack_v2.sql`), los datos iniciales, el usuario de la app y las pruebas de la base. | `codigo/base-de-datos/README.md` |

## `documentacion/`: los documentos del proyecto

| Carpeta | Qué tiene |
|---|---|
| `documentacion/entregables/` | Ficha del proyecto, matriz de requisitos y catálogo de pantallas (PDF). |
| `documentacion/specs/` | Qué se acordó construir: `001` (base de datos) y `003` (la app). |
| `documentacion/decisiones/` | Por qué la app se hizo en PHP puro por capas y no con Laravel. |

## Para ver la app funcionando

1. Crea la base de datos (paso 1.2 de `codigo/aplicacion/LEEME.md`).
2. Configura `codigo/aplicacion/config/config.local.php` (paso 1.4).
3. Desde la carpeta `codigo/aplicacion`, ejecuta `php -S 127.0.0.1:8080 -t public` y abre <http://127.0.0.1:8080>.
