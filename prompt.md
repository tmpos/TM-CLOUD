Quiero crear un sistema llamado **TMPBase**, inspirado en Supabase, pero más ligero, privado y construido en **PHP + SQLite + FlightPHP + TailwindCSS**.

El objetivo es crear una plataforma donde yo pueda registrar proyectos, y cada proyecto tenga su propia base de datos SQLite. Desde un panel web profesional debo poder crear bases de datos, crear tablas, modificar tablas, agregar campos, eliminar campos, vaciar tablas, importar registros, exportar registros y consumir automáticamente endpoints API para cada tabla.

El sistema debe tener backend, API REST y frontend administrativo.

---

# 1. Tecnologías obligatorias

Usar:

* PHP 8+
* SQLite
* PDO
* FlightPHP para la API
* TailwindCSS para el frontend
* JavaScript moderno sin frameworks pesados
* Arquitectura limpia y ordenada
* Diseño responsive profesional
* Código seguro, modular y fácil de mantener

No usar Laravel.

---

# 2. Nombre del sistema

El sistema se llamará:

**TMPBase**

Debe tener una identidad visual limpia, moderna, profesional, estilo Supabase, pero con personalidad propia.

Colores recomendados:

* Fondo oscuro elegante
* Verde / azul tecnológico como color principal
* Tarjetas modernas
* Bordes suaves
* Tablas limpias
* Formularios profesionales
* Sidebar fijo
* Dashboard estilo SaaS

---

# 3. Estructura general del proyecto

Crear una estructura parecida a esta:

/public
index.php
assets/
css/
js/

/app
Core/
Database.php
Router.php
Response.php
Validator.php
Auth.php

Controllers/
AuthController.php
DashboardController.php
ProjectController.php
TableController.php
RecordController.php
ImportExportController.php
BackupController.php
ApiController.php
SyncController.php
StorageController.php

Services/
ProjectService.php
SchemaService.php
RecordService.php
ImportExportService.php
BackupService.php
ApiKeyService.php
LogService.php
SyncService.php
StorageService.php

Views/
layout.php
login.php
dashboard.php
projects/
tables/
records/
api-docs/
settings/

Middleware/
AuthMiddleware.php
ApiKeyMiddleware.php

/storage
projects/
backups/
uploads/

/config
app.php

/composer.json

---

# 4. Base principal del sistema

Debe existir una base SQLite principal para TMPBase:

/storage/tmpbase.sqlite

Esta base guardará la información del sistema:

## Tabla users

Campos:

* id
* uid
* name
* email
* password_hash
* role
* created_at
* updated_at

## Tabla projects

Campos:

* id
* uid
* name
* slug
* description
* database_path
* public_key
* secret_key
* status
* created_at
* updated_at

## Tabla project_logs

Campos:

* id
* uid
* project_uid
* action
* table_name
* record_uid
* old_data
* new_data
* ip_address
* user_agent
* created_at

## Tabla backups

Campos:

* id
* uid
* project_uid
* file_path
* size
* created_at

## Tabla webhooks

Campos:

* id
* uid
* project_uid
* event
* url
* is_active
* created_at
* updated_at

---

# 5. Creación de proyectos

Desde el panel debo poder crear un proyecto.

Cuando cree un proyecto:

1. Crear registro en tabla projects.
2. Generar uid único.
3. Generar slug.
4. Crear una carpeta:

/storage/projects/{project_uid}/

5. Crear una base SQLite:

/storage/projects/{project_uid}/database.sqlite

6. Generar API keys:

* public_key
* secret_key

7. Crear tablas internas del proyecto:

_system_logs
_system_files
_system_settings

---

# 6. Creación automática de tablas

Desde el panel debo poder crear tablas manualmente.

Cada tabla creada debe tener automáticamente estos campos obligatorios:

* id INTEGER PRIMARY KEY AUTOINCREMENT
* uid TEXT UNIQUE NOT NULL
* created_at TEXT NOT NULL
* updated_at TEXT NOT NULL

Ejemplo:

CREATE TABLE clientes (
id INTEGER PRIMARY KEY AUTOINCREMENT,
uid TEXT UNIQUE NOT NULL,
nombre TEXT,
telefono TEXT,
created_at TEXT NOT NULL,
updated_at TEXT NOT NULL
);

El sistema debe impedir que el usuario elimine los campos:

* id
* uid
* created_at
* updated_at

---

# 7. Tipos de campos soportados

Al crear o modificar una tabla, el usuario podrá agregar campos con estos tipos:

* TEXT
* INTEGER
* REAL
* BOOLEAN
* DATE
* DATETIME
* JSON
* EMAIL
* PHONE
* URL
* FILE
* IMAGE

Cada campo debe poder tener opciones:

* Nombre del campo
* Tipo
* Requerido sí/no
* Valor por defecto
* Único sí/no
* Indexado sí/no
* Visible en tabla sí/no
* Editable sí/no

---

# 8. Modificar tablas

El sistema debe permitir:

* Agregar campos
* Renombrar campos
* Eliminar campos
* Cambiar tipo de campo cuando sea posible
* Agregar índices
* Eliminar índices
* Vaciar tabla
* Eliminar tabla completa

Importante:

SQLite tiene limitaciones para modificar columnas, por eso cuando haga falta, el sistema debe recrear la tabla internamente de forma segura:

1. Crear tabla temporal.
2. Copiar datos compatibles.
3. Eliminar tabla original.
4. Renombrar tabla temporal.
5. Mantener los campos obligatorios.
6. Registrar la acción en logs.

---

# 9. Vaciar tabla y resetear ID

Debe existir una opción llamada:

**Vaciar tabla**

Cuando se use:

1. Borrar todos los registros.
2. Resetear el autoincrement.
3. Dejar la tabla lista para que el próximo registro tenga id = 1.

SQL:

DELETE FROM nombre_tabla;
DELETE FROM sqlite_sequence WHERE name = 'nombre_tabla';

Esta acción debe pedir confirmación visual en el frontend porque es peligrosa.

---

# 10. CRUD automático para cada tabla

Cada tabla creada debe tener endpoints automáticos.

Ejemplo para tabla clientes:

## Crear registro

POST /api/{project_uid}/clientes

Body:

{
"nombre": "Juan Pérez",
"telefono": "8090000000"
}

El sistema debe agregar automáticamente:

* uid
* created_at
* updated_at

## Listar registros

GET /api/{project_uid}/clientes

Debe soportar:

* page
* limit
* search
* order_by
* order_dir
* filters

Ejemplo:

GET /api/{project_uid}/clientes?page=1&limit=20&search=juan

## Ver registro

GET /api/{project_uid}/clientes/{uid}

## Actualizar registro

PUT /api/{project_uid}/clientes/{uid}

Debe actualizar automáticamente updated_at.

## Borrar registro

DELETE /api/{project_uid}/clientes/{uid}

## Borrar todos los registros

DELETE /api/{project_uid}/clientes

Debe borrar todo y resetear ID.

---

# 11. Endpoint de sincronización por updated_at

Crear un endpoint para obtener registros modificados entre fechas:

GET /api/{project_uid}/{table}/sync?from=2026-06-01 00:00:00&to=2026-06-06 23:59:59

Debe usar el campo updated_at.

Consulta base:

SELECT * FROM table
WHERE updated_at BETWEEN :from AND :to
ORDER BY updated_at ASC;

También debe permitir:

GET /api/{project_uid}/{table}/modified-since?since=2026-06-01 00:00:00

Esto devuelve todo lo modificado desde una fecha.

---

# 12. Importar registros masivos

Debe permitir subir muchos registros de golpe.

Endpoint:

POST /api/{project_uid}/{table}/bulk

Body:

[
{
"nombre": "Juan",
"telefono": "8090000000"
},
{
"nombre": "Maria",
"telefono": "8290000000"
}
]

Debe:

* Validar campos existentes.
* Insertar en transacción.
* Generar uid automático por cada registro.
* Generar created_at y updated_at.
* Devolver cuántos registros fueron insertados.
* Devolver errores por fila si existen.

También desde el frontend debo poder importar:

* JSON
* CSV

---

# 13. Exportar registros

Endpoint:

GET /api/{project_uid}/{table}/export?format=json

GET /api/{project_uid}/{table}/export?format=csv

Desde el frontend debe haber botón para:

* Exportar JSON
* Exportar CSV
* Copiar endpoint
* Descargar archivo

---

# 14. API keys y seguridad

Cada proyecto debe tener:

* public_key
* secret_key

Las APIs deben poder protegerse usando:

Authorization: Bearer API_KEY

Debe existir configuración por tabla:

* Lectura pública
* Escritura privada
* Lectura y escritura privada
* Solo secret key
* Bloqueada

Crear middleware ApiKeyMiddleware para validar permisos.

---

# 15. Panel frontend profesional

Crear un panel administrativo con TailwindCSS.

Debe incluir:

## Login

* Pantalla elegante
* Email
* Password
* Recordar sesión
* Validaciones
* Mensajes de error

## Dashboard principal

Mostrar:

* Total de proyectos
* Total de tablas
* Total de registros
* Últimos cambios
* Últimos backups
* Actividad reciente

## Sidebar

Opciones:

* Dashboard
* Proyectos
* Tablas
* Editor de datos
* API Docs
* Importar / Exportar
* Backups
* Logs
* Storage
* Webhooks
* Configuración

## Página de proyectos

Debe permitir:

* Crear proyecto
* Editar proyecto
* Eliminar proyecto
* Ver API keys
* Regenerar API keys
* Entrar al proyecto

## Página de tablas

Debe permitir:

* Ver tablas existentes
* Crear tabla
* Editar estructura
* Agregar campo
* Eliminar campo
* Ver cantidad de registros
* Vaciar tabla
* Eliminar tabla

## Editor de registros

Debe ser como una tabla tipo Supabase:

* Filas y columnas
* Botón agregar registro
* Editar registro en modal
* Eliminar registro
* Buscar
* Filtrar
* Ordenar
* Paginación
* Selección múltiple
* Borrado múltiple
* Exportar
* Importar

## Editor visual de estructura

Debe permitir crear campos con formulario:

* Nombre
* Tipo
* Requerido
* Único
* Indexado
* Default
* Visible
* Editable

## API Docs automática

Por cada tabla debe mostrar:

* Endpoint de crear
* Endpoint de listar
* Endpoint de leer
* Endpoint de actualizar
* Endpoint de borrar
* Endpoint de sync
* Endpoint de bulk insert
* Endpoint de exportar

También debe mostrar ejemplos en:

* cURL
* JavaScript fetch
* PHP cURL

## Logs

Mostrar:

* Acción realizada
* Tabla afectada
* Usuario
* Fecha
* IP
* Antes
* Después

## Backups

Debe permitir:

* Crear backup manual
* Descargar backup
* Restaurar backup
* Eliminar backup

## Storage

Debe permitir:

* Subir archivos
* Ver archivos
* Descargar archivos
* Eliminar archivos
* Copiar URL

---

# 16. Diseño frontend

Usar TailwindCSS con diseño moderno:

* Dark mode
* Sidebar oscuro
* Cards con sombras suaves
* Botones modernos
* Inputs elegantes
* Tablas limpias
* Modales profesionales
* Confirmaciones visuales
* Toast notifications
* Estados loading
* Estados empty
* Badges de estado
* Dropdowns
* Menús contextuales

El diseño debe sentirse como un SaaS profesional, no como un sistema básico.

Inspiración visual:

* Supabase
* Linear
* Vercel
* PlanetScale

---

# 17. Validaciones importantes

Validar:

* Nombre de proyecto
* Slug único
* Nombre de tabla válido
* Nombre de columna válido
* Tipos permitidos
* No permitir SQL injection
* No permitir nombres peligrosos
* No permitir modificar campos protegidos
* No permitir borrar tablas internas del sistema
* Validar API key
* Validar permisos por tabla

Los nombres de tablas y columnas deben aceptar solo:

* letras
* números
* guion bajo

No permitir espacios ni caracteres especiales.

---

# 18. Logs automáticos

Registrar logs para:

* Proyecto creado
* Proyecto eliminado
* Tabla creada
* Tabla modificada
* Tabla eliminada
* Campo agregado
* Campo eliminado
* Registro creado
* Registro actualizado
* Registro eliminado
* Tabla vaciada
* Backup creado
* Backup restaurado
* API key regenerada

---

# 19. Backups

Crear sistema de backup por proyecto.

Debe copiar:

/storage/projects/{project_uid}/database.sqlite

A:

/storage/backups/{project_uid}/{fecha}_database.sqlite

Debe registrar el backup en la tabla backups.

Debe permitir restaurar backup con confirmación.

---

# 20. Storage tipo Supabase

Crear módulo de archivos.

Endpoints:

POST /storage/{project_uid}/upload

GET /storage/{project_uid}/{file_uid}

DELETE /storage/{project_uid}/{file_uid}

Guardar archivos en:

/storage/uploads/{project_uid}/

Registrar archivos en tabla interna:

_system_files

Campos:

* id
* uid
* original_name
* stored_name
* mime_type
* size
* path
* url
* created_at

---

# 21. Webhooks

Crear webhooks por proyecto.

Eventos:

* record.created
* record.updated
* record.deleted
* table.created
* table.updated
* table.truncated
* table.deleted

Cuando ocurra un evento, enviar POST al webhook configurado.

Payload:

{
"event": "record.created",
"project_uid": "...",
"table": "clientes",
"record": {},
"created_at": "..."
}

---

# 22. Funciones extra recomendadas

Agregar:

* Soft delete opcional con deleted_at
* Restaurar registros eliminados
* Campos ocultos
* Vista JSON del registro
* Copiar endpoint rápido
* Modo lectura pública por tabla
* Modo solo admin
* Historial de cambios por registro
* Duplicar tabla
* Duplicar proyecto
* Plantillas de tablas
* Buscador global
* Comandos rápidos estilo Command Palette
* Tema claro / oscuro
* Configuración SMTP para enviar alertas
* Rate limit básico por API key
* CORS configurable por proyecto

---

# 23. Primera versión mínima requerida

La primera versión funcional debe incluir:

1. Login admin.
2. Dashboard.
3. Crear proyectos.
4. Crear base SQLite por proyecto.
5. Crear tablas.
6. Agregar campos.
7. Eliminar campos.
8. Vaciar tabla y resetear ID.
9. CRUD automático por API.
10. Editor visual de registros.
11. Importar JSON.
12. Exportar JSON/CSV.
13. API docs automática.
14. API keys.
15. Logs.
16. Diseño Tailwind profesional.

---

# 24. Resultado esperado

Quiero que me entregues:

1. Estructura completa de carpetas.
2. Código PHP funcional.
3. Configuración Composer.
4. Rutas FlightPHP.
5. Clases de servicios.
6. Vistas con TailwindCSS.
7. SQL inicial para TMPBase.
8. Sistema de login.
9. Dashboard.
10. CRUD visual.
11. CRUD por API.
12. Importación/exportación.
13. Backups.
14. Seguridad básica.
15. Documentación de instalación.
16. Ejemplos de uso de endpoints.

El código debe estar limpio, comentado y listo para ejecutar en hosting tradicional con PHP y SQLite.

---

# 25. Importante

No quiero una demo incompleta. Quiero una base real, ordenada, escalable y profesional para seguir construyendo sobre ella.

Prioriza seguridad, orden, buena experiencia visual y facilidad de uso.
