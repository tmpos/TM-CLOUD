# TMPBase

TMPBase es una plataforma backend privada construida con PHP, SQLite y FlightPHP. Cada proyecto tiene su propia base de datos SQLite, claves API y endpoints REST automáticos.

## Requisitos

- PHP 8.1 o superior.
- Extensiones `pdo_sqlite` y `fileinfo`.
- Extensión `pdo_mysql` para sincronización o consultas MySQL.
- Apache con `mod_rewrite`, Nginx o el servidor de desarrollo de PHP.
- Permiso de escritura sobre `storage/`.
- Composer 2 es recomendado, pero no obligatorio en hosting compartido.

## Instalación local

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php -S localhost:8000 router.php
```

Abra `http://localhost:8000`. El instalador web valida el servidor, configura el entorno, crea la base central y registra el primer administrador. No existe una contraseña predeterminada.

Variables recomendadas para producción:

```dotenv
APP_NAME=TMPBase
APP_URL=https://base.example.com
APP_ENV=production
APP_DEBUG=false
SESSION_SECURE=true
MAX_UPLOAD_MB=10
RATE_LIMIT_PER_MINUTE=120
```

En Apache o Nginx, el directorio público debe ser `public/`. Las solicitudes que no correspondan a archivos reales deben dirigirse a `public/index.php`. Para Hostinger consulte [HOSTINGER.md](HOSTINGER.md).

## Uso desde el panel

### 1. Crear un proyecto

1. Inicie sesión y abra `Dashboard`.
2. Complete el formulario de proyecto con su nombre y descripción.
3. Abra el proyecto creado.
4. En `API keys` encontrará la URL del proyecto, la clave pública y la clave secreta.

Cada proyecto crea una base SQLite independiente dentro de `storage/projects/`.

### 2. Crear una tabla

1. Abra el proyecto.
2. Pulse `New table`.
3. Escriba un nombre como `productos` y confirme.
4. Abra la tabla y seleccione la pestaña `Structure`.
5. Agregue los campos necesarios uno por uno.

Los nombres deben comenzar con una letra y solo pueden contener letras, números y guion bajo. Ejemplos válidos: `productos`, `clientes_2026`, `orden_detalle`.

Todas las tablas incluyen automáticamente estos campos protegidos:

| Campo | Descripción |
| --- | --- |
| `id` | Número autoincremental interno. |
| `uid` | Identificador público del registro, por ejemplo `rec_xxx`. |
| `created_at` | Fecha de creación. |
| `updated_at` | Fecha de última modificación. |

### 3. Crear campos

En `Structure`, indique nombre, tipo y opciones del campo:

| Tipo | Uso recomendado |
| --- | --- |
| `TEXT` | Texto general. |
| `INTEGER` | Números enteros. |
| `REAL` | Números decimales. |
| `BOOLEAN` | Valores verdadero/falso guardados como `1` o `0`. |
| `DATE` | Fecha. |
| `DATETIME` | Fecha y hora. |
| `JSON` | Contenido JSON serializado. |
| `EMAIL` | Correo electrónico. |
| `PHONE` | Número telefónico. |
| `URL` | Enlace web. |
| `FILE` | URL o UID de un archivo subido. |
| `IMAGE` | URL o UID de una imagen subida. |

Opciones disponibles:

- `Required`: no permite valores nulos.
- `Unique`: impide valores duplicados y crea un índice único.
- `Indexed`: crea un índice para acelerar consultas.
- `Default`: valor utilizado cuando no se envía el campo.

Ejemplo para una tabla `productos`:

| Campo | Tipo | Opciones |
| --- | --- | --- |
| `nombre` | `TEXT` | Required, Indexed |
| `precio` | `REAL` | Required |
| `activo` | `BOOLEAN` | Default: `1` |
| `imagen` | `IMAGE` | Ninguna |

### 4. Administrar registros

Desde la pestaña `Data` puede:

- Crear y editar registros.
- Eliminar un registro.
- Seleccionar y eliminar varios registros.
- Buscar en todas las columnas.
- Ordenar y paginar resultados.
- Importar JSON o CSV, hasta 1,000 registros por solicitud.
- Exportar la tabla a CSV.
- Vaciar la tabla con `Clear table`, eliminando todos los registros y reiniciando el autoincremento.

`Delete table` elimina la estructura completa. No debe confundirse con `Clear table`, que conserva campos, índices y configuración.

### 5. Subir y mostrar imágenes

1. Abra el proyecto y entre en `Storage`.
2. Suba la imagen.
3. Copie la URL devuelta o su UID `fil_xxx`.
4. Guarde ese valor en un campo del registro, preferiblemente de tipo `IMAGE`.
5. Al abrir `Data`, TMPBase muestra una miniatura. Al pulsarla se abre la imagen completa.

El archivo y el valor del registro son entidades independientes. Eliminar un registro no elimina automáticamente el archivo de `Storage`.

### 6. Acceso de la tabla

En `Settings` de cada tabla puede elegir:

| Modo | Lectura | Escritura |
| --- | --- | --- |
| `Public read` | Sin clave o con clave pública | Clave secreta |
| `Private` | Clave pública o secreta | Clave secreta |
| `Secret only` | Clave secreta | Clave secreta |
| `Blocked` | Bloqueada | Bloqueada |

La clave secreta permite operaciones destructivas. No debe incluirse en aplicaciones cliente ni repositorios públicos.

## API REST

Use la clave correspondiente en el encabezado:

```http
Authorization: Bearer tmp_secret_xxx
Content-Type: application/json
```

Los ejemplos siguientes usan:

```text
BASE_URL=https://base.example.com
PROJECT=prj_xxx
TABLE=productos
```

### Crear tablas por API

La creación por API se realiza en lote y acepta hasta 100 tablas. Los campos protegidos se agregan automáticamente.

```bash
curl -X POST "https://base.example.com/api/prj_xxx/schema/tables/batch" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '{
    "tables": [
      {
        "name": "productos",
        "columns": [
          {"name": "nombre", "type": "TEXT", "required": true, "indexed": true},
          {"name": "precio", "type": "REAL", "required": true},
          {"name": "activo", "type": "BOOLEAN", "default": 1},
          {"name": "imagen", "type": "IMAGE"}
        ]
      }
    ]
  }'
```

Si una tabla ya existe, se marca como omitida y no se modifica.

### Consultar el esquema

```bash
# Todas las tablas y columnas
curl "https://base.example.com/api/prj_xxx/schema" \
  -H "Authorization: Bearer tmp_public_xxx"

# Lista de tablas
curl "https://base.example.com/api/prj_xxx/schema/tables" \
  -H "Authorization: Bearer tmp_public_xxx"

# Columnas de una tabla
curl "https://base.example.com/api/prj_xxx/schema/tables/productos" \
  -H "Authorization: Bearer tmp_public_xxx"
```

### CRUD de registros

```bash
# Listar, buscar, filtrar y ordenar
curl "https://base.example.com/api/prj_xxx/productos?page=1&limit=20&search=cafe&filter_activo=1&order_by=nombre&order_dir=ASC" \
  -H "Authorization: Bearer tmp_public_xxx"

# Crear
curl -X POST "https://base.example.com/api/prj_xxx/productos" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Cafe","precio":250.50,"activo":1}'

# Consultar un registro
curl "https://base.example.com/api/prj_xxx/productos/rec_xxx" \
  -H "Authorization: Bearer tmp_public_xxx"

# Actualizar
curl -X PUT "https://base.example.com/api/prj_xxx/productos/rec_xxx" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '{"precio":275}'

# Eliminar un registro
curl -X DELETE "https://base.example.com/api/prj_xxx/productos/rec_xxx" \
  -H "Authorization: Bearer tmp_secret_xxx"

# Vaciar la tabla y reiniciar IDs
curl -X DELETE "https://base.example.com/api/prj_xxx/productos" \
  -H "Authorization: Bearer tmp_secret_xxx"
```

Parámetros de listado: `page`, `limit` (máximo 100), `search`, `order_by`, `order_dir` y filtros exactos con el formato `filter_campo=valor`.

### Inserción masiva y upsert

El límite es de 1,000 registros por solicitud.

```bash
# Inserción masiva
curl -X POST "https://base.example.com/api/prj_xxx/productos/bulk" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '[{"nombre":"Cafe","precio":250},{"nombre":"Leche","precio":80}]'

# Upsert: cada elemento debe incluir uid
curl -X POST "https://base.example.com/api/prj_xxx/productos/upsert" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '{"rows":[{"uid":"rec_xxx","nombre":"Cafe premium","precio":300}]}'
```

### Subir una imagen y asociarla a un registro

```bash
# 1. Subir el archivo
curl -X POST "https://base.example.com/storage/prj_xxx/upload?directory=productos" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -F "file=@producto.jpg"

# 2. Guardar data.url o data.uid de la respuesta en el registro
curl -X PUT "https://base.example.com/api/prj_xxx/productos/rec_xxx" \
  -H "Authorization: Bearer tmp_secret_xxx" \
  -H "Content-Type: application/json" \
  -d '{"imagen":"https://base.example.com/storage/prj_xxx/fil_xxx"}'
```

Endpoints de archivos:

| Método | Endpoint | Función |
| --- | --- | --- |
| `POST` | `/storage/{project}/upload` | Subir archivo. |
| `GET` | `/storage/{project}/files` | Listar archivos. |
| `GET` | `/storage/{project}/{uid}` | Descargar o visualizar archivo. |
| `DELETE` | `/storage/{project}/{uid}` | Eliminar archivo. |

### Sincronización y exportación

```bash
# Cambios de una tabla en un rango
curl "https://base.example.com/api/prj_xxx/productos/sync?from=2026-06-01%2000:00:00&to=2026-06-30%2023:59:59" \
  -H "Authorization: Bearer tmp_public_xxx"

# Cambios de todas las tablas desde una fecha, incluyendo eliminaciones registradas
curl "https://base.example.com/api/prj_xxx/sync?since=2026-06-01%2000:00:00" \
  -H "Authorization: Bearer tmp_public_xxx"

# Exportar JSON o CSV
curl "https://base.example.com/api/prj_xxx/productos/export?format=csv" \
  -H "Authorization: Bearer tmp_public_xxx" -o productos.csv
```

## Copias de seguridad

Los backups incluyen la base SQLite del proyecto. Desde `Backups` puede crear, descargar, restaurar y eliminar copias. Antes de restaurar, TMPBase crea una copia de seguridad preventiva.

Debe respaldar también:

- `storage/tmpbase.sqlite`.
- `storage/projects/`.
- `storage/uploads/`.
- `storage/backups/`.
- `storage/.credential-key`, necesaria para descifrar contraseñas MySQL guardadas.

## Seguridad

- Los identificadores SQL son validados y entrecomillados.
- Los valores se envían mediante consultas preparadas.
- El panel usa sesiones y protección CSRF.
- Los archivos reciben nombres físicos aleatorios.
- Las URLs de archivos son públicas para quien conozca su UID.
- Los webhooks rechazan destinos locales y redes privadas.
- Las contraseñas MySQL se cifran con `storage/.credential-key`.
- El editor SQL exige confirmación explícita para operaciones de escritura.

Mantenga `.env`, `storage/` y las bases SQLite fuera del acceso web directo.

## Estructura del proyecto

```text
app/Core/          Inicialización, base de datos, autenticación, CSRF y vistas
app/Controllers/   Rutas del panel y API REST
app/Services/      Proyectos, esquemas, registros, archivos, backups y webhooks
app/Views/         Interfaz administrativa
config/            Configuración basada en variables de entorno
database/          Esquemas SQL de referencia
public/            Punto de entrada web y recursos estáticos
storage/           Base central, bases de proyectos, backups y archivos
```

TMPBase crea y migra automáticamente la base de datos central durante el arranque.
