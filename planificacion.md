# planificacion.md — App Deportistas Polideportivos (mobile-first)

Plan de implementación. Actualizado: 12/10/2026.
Contexto y reglas duras: ver `Agents.md`.

---

## 1. Objetivo

App web mobile-first a la que los deportistas acceden escaneando un QR impreso (carnet de
acreditación). Permite:

1. **Registro** con verificación de celular por **SMS** y elección de clave (usuario = DNI).
2. **Login** con DNI + clave.
3. Consulta de **actividades y categorías inscriptas** con el **nombre del polideportivo**.
4. Consulta de **asistencias** por actividad.
5. Edición de datos (menos el DNI), con **re-verificación SMS** si cambia el celular.
6. **Foto de perfil** tomada con la cámara (selfie).
7. Generación de **carnet QR**: QR con los datos del deportista y sus actividades, **embebido
   dentro de la imagen `Imagenes/carnet.jpeg`**.

---

## 2. Decisiones tomadas (con el cliente)

| Tema | Decisión |
|---|---|
| Stack | **PHP 8.4 + MariaDB 11.4** (backend sin framework) + **Angular 22.2.2** (frontend, última estable) |
| Datos | **Seed desde los CSV** de la carpeta raíz (`../poli_5_*.csv`) + `MALVINAS ARGENTINAS CALLES.xlsx` |
| SMS | **Modo demo/simulado** con adaptador listo para proveedor real (Twilio/etc. por `.env`) |
| Asistencias | **Seed sintético realista** (no hay archivos de asistencia) |
| QR | **Texto estructurado legible** (escaneable con el lector de cámara común) |
| DNI ya existente | **Rechazar el registro** → "Este DNI ya figura en el polideportivo. Comunicate con el polideportivo." |
| Polideportivo | id 5 → nombre **"Palmeras"** (configurable en BD/`.env`) |
| Recuperar clave | **Sí**, con código por SMS |
| Despliegue | **Local primero**; publicación a definir (Apache estático+API o hosting con PHP) |
| Frontend | Angular con proxy `/api` → `127.0.0.1:8080` en dev |

### Información clave levantada en la investigación
- Sitio de referencia: Angular Material, **Montserrat**, azules `#005cbb` / `#00458f`,
  acento `#abc7ff`, fondo `#faf9fd`; login del admin titulado "Municipio Malvinas Argentinas /
  Polideportivos". Localidades oficiales del municipio:
  Grand Bourg, Ingeniero Adolfo Sourdeaux, Ingeniero Pablo Nogues, Los Polvorines,
  Malvinas Argentinas, Tortuguitas, Villa de Mayo, Tierras Altas.
- `Imagenes/carnet.jpeg`: **650×945 px**; recuadro blanco para QR en **x 147–502, y 415–754**
  (centro ≈ 324,5 / 584,5). El recuadro es blanco → QR negro funciona directo.
- CSVs disponibles: `poli_5_inscriptos` (2.635 filas, `;`, UTF-8), `poli_5_actividades` (10),
  `poli_5_categorias` (190), `poli_5_act_inscriptos` (inscripciones), `turnos_resumen.csv`.
  **No hay CSV de asistencias.** Diagnóstico de calidad de datos en
  `../diagnostico_datos_inscriptos.md` (fechas de nacimiento 98,8% inválidas, teléfonos 96,9%
  válidos, etc.) → el seed debe saneear (no bloquear por esos campos).
- Existe API municipal en `https://api-polideportivos.triskal.ar/rutas/` (PHP 8.3, CORS `*`,
  solo POST, requiere `Authorization`) orientada al admin. **No se consume** en esta fase
  (sin credenciales) pero la forma de respuesta `{status,data,error}` se replica.
- Entorno local: PHP 8.4.23 (`gd`, `pdo_mysql`, `mbstring`, `curl`, `openssl`),
  MariaDB 11.4 corriendo (usuario `slm` con `ALL PRIVILEGES WITH GRANT OPTION`, `root`
  requiere sudo → usar `-u slm`), Composer 2.10.3, Node 22.23.2, npm 10.9.8,
  Angular CLI global 22.1.5 (usar `npx @angular/cli@22.2.2`), `openpyxl` para el XLSX.

---

## 3. Estructura del proyecto

```
Aplicacion/
├── Agents.md  planificacion.md
├── Imagenes/carnet.jpeg
├── MALVINAS ARGENTINAS CALLES.xlsx
├── backend/
│   ├── composer.json  .env.example  phpunit.xml
│   ├── public/index.php
│   ├── src/
│   │   ├── Config.php  Database.php  Router.php  Auth.php
│   │   ├── Controllers/{Auth,Deportista,Actividades,Asistencias,Carnet,Dev}Controller.php
│   │   ├── Services/{RegistroService,SmsService,QrService,FotoService,AsistenciaService}.php
│   │   └── Support/{JsonResponse,Validators,RateLimiter,CsvReader,Sms/DemoSmsSender}.php
│   ├── sql/schema.sql
│   ├── scripts/{seed.php, convertir_calles.py}
│   ├── data/            (calles.csv generado)
│   └── storage/{fotos,carnets,tmp}/
└── frontend/            (Angular 22, npm)
```

---

## 4. Modelo de datos (MySQL, esquema en `backend/sql/schema.sql`)

| Tabla | Campos principales | Notas |
|---|---|---|
| `deportistas` | `dni` PK (7–8 dígitos), `nombre`, `apellido`, `fecha_nacimiento DATE`, `email`, `calle`, `numero`, `departamento NULL`, `localidad`, `celular`, `celular_verificado TINYINT`, `celular_verificado_at`, `password_hash`, `foto VARCHAR NULL`, `origen ENUM('csv','app')`, `estado`, `creado_en`, `actualizado_en` | `dni` **nunca** se modifica. `origen='csv'` = importado, sin cuenta, bloquea registro. |
| `registros_pendientes` | `dni` PK, datos personales (JSON o columnas), `celular`, `codigo_hash`, `codigo_expira`, `intento`, `creado_en` | Wizard de registro; TTL 30 min. Se borra al crear la cuenta. |
| `codigos_verificacion` | `id`, `dni`/`celular`, `proposito ENUM('registro','cambio_celular','recuperar_clave')`, `codigo_hash`, `expira_en`, `intentos`, `usado_en`, `creado_en` | Código de 6 dígitos, vigencia 10 min, máx. 5 intentos, reenvío cada 60 s. |
| `sesiones` | `token_hash` PK, `dni`, `creada_en`, `expira_en`, `ip`, `user_agent`, `revocada_en` | Token aleatorio 32 bytes; se guarda solo el hash SHA-256. TTL 30 días. |
| `polideportivos` | `id` (seed: 5), `nombre` (`Palmeras`) | Nombre configurable. |
| `actividades` | `id_actividad` PK, `nombre`, `cupos`, `estado` | Desde `poli_5_actividades.csv`. |
| `categorias` | `id` PK, `id_categoria`, `categoria`, `dia`, `hora_i`, `hora_f`, `profesor`, `actividad_id`, `polideportivo_id`, `estado` | Desde `poli_5_categorias.csv` (una fila por día). |
| `inscripciones` | `id_act_ins` PK, `dni`, `actividad_id`, `categoria_id`, `estado`, `fecha_alta` | Desde `poli_5_act_inscriptos.csv`. |
| `asistencias` | `id`, `dni`, `categoria_id`, `fecha`, `presente TINYINT`, `tomada TINYINT`, `observacion` | **Seed sintético**: últimas ~10 ocurrencias del día de la categoría, ~80% presente. |
| `calles` | `localidad`, `calle`, `codigo` | Desde el XLSX, para autocompletado/validación. |
| `sms_log` | `id`, `destino`, `proposito`, `mensaje`, `codigo_ref`, `creado_en` | Demo: historial visible en dev. |

Índices: `deportistas(celular)`, `inscripciones(dni)`, `asistencias(dni,categoria_id,fecha)`,
`categorias(actividad_id)`, `codigos_verificacion(dni,proposito)`.

---

## 5. API REST (prefijo `/api`, JSON `{status,data,error}`)

### Auth
| Método | Ruta | Descripción |
|---|---|---|
| POST | `/registro/validar` | `{dni}` → `{disponible:boolean, motivo:'dni_en_base'\|'dni_con_cuenta'}` |
| POST | `/registro/paso1` | Datos personales + domicilio + celular + email. Valida; si OK → crea `registros_pendientes` y **envía SMS**. Responde `{etapa:'verificar'}`. |
| POST | `/registro/verificar` | `{dni, codigo}` → valida y marca celular verificado. |
| POST | `/registro/clave` | `{dni, clave, clave_confirmacion}` → **crea la cuenta** (borra pendiente). No deja logueado: el flujo pasa a Login. |
| POST | `/login` | `{dni, clave}` → `{token, deportista}`. Rate limit 10 intentos/15 min por DNI+IP. |
| POST | `/logout` | Revoca el token actual. |
| POST | `/recuperar/solicitar` | `{dni}` → SMS (solo si existe cuenta). Respuesta genérica (no revela si existe). |
| POST | `/recuperar/verificar` | `{dni, codigo, clave}` → cambia la clave y revoca sesiones. |
| GET | `/dev/sms/ultimo-codigo` | **Solo `APP_ENV=local`**: devuelve el último código (modo demo). |

### Deportista (requiere `Authorization: Bearer <token>`)
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/me` | Perfil completo (menos hash/clave). |
| PUT | `/me` | Editable: nombre, apellido, fecha_nacimiento, email, calle, numero, departamento, localidad. **DNI no se acepta en el body** (se ignora/422). |
| POST | `/me/celular/solicitar` | `{celular}` → si es distinto al verificado, guarda como "pendiente" y envía SMS (no cambia `celular` activo hasta verificar). |
| POST | `/me/celular/verificar` | `{codigo}` → activa el nuevo celular y pone `celular_verificado=1`. |
| POST | `/me/foto` | multipart `foto` (jpeg/png/webp ≤ 5 MB) → redimensiona/ recorta a 400×400 JPEG → `storage/fotos/{dni}.jpg`. |
| GET | `/me/foto` | Devuelve la imagen (o 404 si no tiene). |

### Dominio
| Método | Ruta | Descripción |
|---|---|---|
| GET | `/actividades` | Lista agrupada: `{actividad, categoria, dias[], hora_i, hora_f, profesor, polideportivo:'Palmeras', id_categoria}` + `resumen_asistencia`. |
| GET | `/actividades/{id_categoria}/asistencias` | `{historial:[{fecha, presente, tomada, observacion}], resumen:{presentes, total, pct}}`. |
| GET | `/carnet` | Imagen JPEG del carnet con QR embebido (`Content-Type: image/jpeg`). Se cachea en `storage/carnets/{dni}.jpg` y se regenera si cambian datos/actividades. |

Health: `GET /api/health` → `{status:200,data:'ok'}` (para verificar el server).

---

## 6. Flujo de registro (pantalla por pantalla)

1. **Paso 1 — Datos**: nombre*, apellido*, fecha nacimiento*, DNI*, domicilio
   (calle* con autocompletado desde `calles` + localidad*, número*, departamento),
   celular primario*, email*. Validaciones: DNI 7–8 dígitos y no repetido en el formulario;
   fecha de nacimiento con fecha real y 5–100 años; celular argentino (acepta `+54 9 ...`
   o 10–13 dígitos, se normaliza a `549...`); email RFC básico; número entero > 0;
   localidad de la lista oficial.
   - Si el DNI está en la base importada → pantalla de **rechazo** con el mensaje del cliente.
   - Si el DNI ya tiene cuenta → aviso "ya estás registrado" + enlace a Login.
2. **Paso 2 — Verificación SMS**: input de código de 6 dígitos (autofocus, pega,
   solo números), botón **Reenviar** con cooldown de 60 s, contador de intentos, expiración
   10 min con mensaje claro. En modo demo el código se muestra en pantalla (banner "modo
   demo") y hay endpoint `/api/dev/sms/ultimo-codigo`.
3. **Paso 3 — Clave**: clave + confirmación (mín. 8, al menos una letra y un número),
   medidor de fortaleza, luego → **redirige a `/login`** con mensaje "Registro completado,
   ingresá con tu DNI y tu clave" (requisito explícito del cliente).

Protección del flujo: el estado del wizard vive en `sessionStorage` (sobrevive refresh);
`registroGuard` impide entrar a `/registro/verificar` sin datos pendientes.

---

## 7. Pantallas Angular (mobile-first, Montserrat)

| Ruta | Componente | Contenido |
|---|---|---|
| `/` | redirect | → `/app/actividades` si hay sesión, si no `/login` |
| `/login` | `LoginPage` | Logo/título, DNI, clave, mostrar/ocultar clave, botón "Crear cuenta", "Olvidé mi clave" |
| `/registro` | `RegistroPage` (wizard) | 3 pasos con indicador; solo el paso activo |
| `/recuperar` | `RecuperarPage` | DNI → SMS → clave nueva |
| `/app/actividades` | `ActividadesPage` | Cards: nombre actividad, categoría, días, horario, profesor, **Polideportivo Palmeras**, % asistencia; actions: ver asistencias, carnet, perfil |
| `/app/actividades/:id/asistencias` | `AsistenciasPage` | Resumen (presentes/total/%) + lista fecha–estado |
| `/app/perfil` | `PerfilPage` | Avatar con selfie (captura + fallback archivo), edición de datos, DNI bloqueado (solo lectura), aviso si cambió el celular → botón "Verificar celular" |
| `/app/carnet` | `CarnetPage` | Vista previa del carnet, botón **Descargar** y **Compartir** (`navigator.share` con archivo cuando esté soportado) |

Base visual: header fijo con marca, bottom-nav de 3 ítems (Actividades · Carnet · Perfil),
botones ≥ 44 px, formularios con labels arriba y errores inline en español.
Variables SCSS: `--azul:#005cbb; --azul-oscuro:#00458f; --acento:#abc7ff; --fondo:#faf9fd;`.

Guards: `authGuard` para `/app/*`; `noAuthGuard` para `/login`, `/registro`, `/recuperar`.
Interceptor HTTP: adjunta `Authorization` y maneja 401 → logout + redirect a `/login`.

---

## 8. Carnet QR (requisito clave)

**Contenido** (texto plano, ordenado y legible):

```
POLIDEPORTIVOS - Palmeras
APELLIDO, Nombre
DNI 12345678
Actividades:
- Funcional | Martes 15:00-16:00
- Musculacion | Lun, Mie, Vie 07:00-17:30
```

Reglas: sin tildes obligatorias (normalizar a ASCII para máxima compatibilidad de escáner),
máx. ~450 caracteres; si el deportista tiene muchas actividades, se resumen
(`... +2 actividades`) para no bajar la resolución del módulo.

**Composición** (servicio `QrService`):
1. `endroid/qr-code` 6.x → PNG, nivel de corrección **M**, tamaño 300 px, margen 1–2 módulos.
2. `gd` sobre `Imagenes/carnet.jpeg` (solo lectura) → copia en `storage/carnets/{dni}.jpg`,
   pegar el QR centrado en el recuadro blanco: **x = 174…474, y = 434…734**
   (centro 324 / 584; margen ≥ 25 px de las líneas del recuadro).
3. Devolver JPEG calidad 90. Verificación: decodificar el QR generado (p. ej. con
   `zxing`/librería de lectura o una app del celular) y comparar el texto.

---

## 9. SMS en modo demo

- Interfaz `SmsSenderInterface::enviar(string $destino, string $mensaje, string $proposito): void`.
- `DemoSmsSender`: genera el código, lo guarda hasheado en `codigos_verificacion`,
  escribe en `sms_log` y en el log de PHP; **no** llama a ningún proveedor.
- En `APP_ENV=local`, el endpoint `/api/dev/sms/ultimo-codigo` y un banner en la UI muestran
  el código para poder completar el flujo.
- `RealSmsSender` (futuro): lee `SMS_PROVIDER/SMS_ACCOUNT/SMS_TOKEN/SMS_FROM` de `.env`.
  **No implementar hasta que el cliente defina proveedor.**

---

## 10. Seed (`php scripts/seed.php`)

Idempotente (DROP/CREATE + import):

1. `sql/schema.sql`.
2. CSV de calles generado por `scripts/convertir_calles.py` (openpyxl) → tabla `calles`.
3. `poli_5_actividades.csv` → `actividades` (10).
4. `poli_5_categorias.csv` → `categorias` (190, una fila por día; conserva `dia`,
   `hora_i`, `hora_f`, `profesor`, `id_categoria`).
5. `poli_5_inscriptos.csv` → `deportistas` con `origen='csv'`, **sin cuenta**
   (`password_hash NULL`, `celular_verificado=0`). Saneamiento: fechas imposibles
   (`1925-01-01`, etc.) → `NULL`; teléfonos no numéricos → `NULL`; se normalizan mayúsculas.
6. `poli_5_act_inscriptos.csv` → `inscripciones` (solo las que referencien deportista y
   categoría existentes; se registran descartes en el log).
7. `polideportivos` → id 5 "Palmeras" (editable por `.env` `POLIDEPORTIVO_NOMBRE`).
8. **Asistencias sintéticas**: por cada inscripción activa, para las últimas 10 ocurrencias
   de cada día de la categoría, `presente` con 80% de probabilidad (RNG sembrado para que
   sea reproducible), `tomada=1`.
9. **Cuentas demo** (DNIs ficticios **no** existentes en los CSV, p. ej. 99000001/2/3):
   clave `Demo2026!`, `origen='app'`, con 1–3 inscripciones y sus asistencias, para probar
   login/actividades/carnet sin tener que darse de alta.

Comandos de control tras correr el seed (los imprime el script):
`SELECT COUNT(*) FROM deportistas;` → ~2.635 + 3 demo · `categorias` → 190 ·
`inscripciones` → según CSV · `asistencias` → > 0.

---

## 11. Seguridad

- Claves: `password_hash(PASSWORD_DEFAULT)` / `password_verify`.
- Tokens de sesión: 32 bytes aleatorios, hash SHA-256 en DB, TTL 30 días, logout revoca.
- Rate limit por IP+DNI: login (10/15 min), SMS (1 por 60 s, 5 por hora), verificación
  (5 intentos antes de invalidar el código).
- Validación server-side de todos los campos; salida escapada (Angular ya sanitiza; en PHP
  usar `htmlspecialchars` sólo si hay HTML).
- Fotos: validación MIME + tamaño, nombre derivado del DNI (sin path del usuario),
  redimensión obligatoria con GD (elimina payloads ocultos).
- `.env` fuera de git (`.gitignore`), sin secretos en el front.
- Fuera de `localhost` la cámara y `getUserMedia` exigen **HTTPS** → documentar
  (`mkcert` o túnel) antes de publicar.

---

## 12. Fases de implementación

| # | Fase | Entregable | Verificación |
|---|---|---|---|
| 1 | Esqueleto: `backend/` (router, Config, Database, JsonResponse, `.env.example`, `schema.sql`) + `frontend/` (`ng new`, proxy, tema, layout base) | ambos corren: `php -S` + `ng serve` | `php -l`, `GET /api/health`, `ng build` |
| 2 | Seed: conversión XLSX + importación CSV + saneamiento + cuentas demo | `php scripts/seed.php` sin errores | queries de control §10 |
| 3 | Auth backend: registro (3 pasos), SMS demo, login/logout, sesión, recuperación, rate limits | endpoints completos | `composer test` (flujo registro→verificar→clave→login) |
| 4 | Dominio backend: actividades, asistencias, perfil/edición, cambio celular, foto | endpoints completos | `composer test` + `php -l` |
| 5 | Carnet QR: `QrService` (endroid + GD) + endpoint `/carnet` | JPEG generado | decodificar el QR y comparar texto; ver alineación en el recuadro |
| 6 | Frontend auth: login, registro wizard con SMS, recuperar, guards, interceptor | flujo navegable | `ng build`, prueba manual |
| 7 | Frontend dominio: actividades, asistencias, perfil+selfie, carnet | app completa | `ng build` + prueba manual en viewport móvil (Chrome DevTools) |
| 8 | Endurecimiento + docs: validaciones, errores, vacíos/estados de carga, `.gitignore`, README de arranque | checklist §13 completa | `php -l`, `composer test`, `ng build`, `ng lint` |

Estimación orientativa: fases 1–3 ≈ 1 sesión; 4–5 ≈ 1 sesión; 6–7 ≈ 1–2 sesiones; 8 ≈ media.

---

## 13. Checklist de aceptación

> Estado: la implementación de las fases 1–7 está completa y verificada a nivel de API
> (tests + smoke test end-to-end). Los ítems siguientes quedan pendientes de recorrer en
> navegador para confirmar UX (360×640, 390×844, 768×1024) y ajustar detalles visuales.

- [ ] Registro con DNI nuevo → SMS (demo) → clave → **llega a Login**.
- [ ] Registro con DNI de la base CSV → mensaje de rechazo, no crea cuenta.
- [ ] Login con DNI + clave; clave incorrecta → mensaje claro; 10 fallos → bloqueo temporal.
- [ ] Login OK → lista de actividades con **día, horario y "Polideportivo Palmeras"**.
- [ ] Cada actividad permite ver asistencias (fechas + % de asistencia).
- [ ] Perfil editable salvo DNI (el campo DNI ni siquiera se envía al editar).
- [ ] Cambiar celular → pide SMS nuevo → hasta verificar, el celular activo no cambia.
- [ ] Selfie: se puede sacar con la cámara (HTTPS/localhost) o subir archivo; queda visible.
- [ ] Carnet: el QR contiene nombre, apellido, DNI, actividades con días/horarios y
      polideportivo; está **dentro del recuadro blanco de `carnet.jpeg`** y se puede
      escanear con el lector de cámara del celular.
- [ ] Recuperar clave por SMS funciona de punta a punta.
- [ ] Diseño responsive: probado a 360×640, 390×844 y 768×1024.
- [ ] `php -l` sin errores, `composer test` en verde, `ng build` sin errores.

---

## 14. Riesgos y pendientes

1. **Alcance del rechazo de DNI**: con la regla actual, ningún inscripto histórico puede
   autoregistrarse; tendrían que crearles la cuenta desde el polideportivo (hoy no hay panel
   admin en esta app). **Confirmar con el cliente** si más adelante se agrega "alta por
   operador".
2. **HTTPS para la cámara**: obligatorio fuera de localhost.
3. **Longitud del QR**: si una actividad tiene muchas combinaciones de días, resumir.
4. **Asistencias sintéticas**: reemplazar por datos reales cuando exista fuente
   (posiblemente desde la API municipal, requiere credenciales).
5. **Proveedor SMS**: pendiente de definición y credenciales (costo por mensaje).
6. **Publicación**: local primero; evaluar Apache+PHP del municipio vs hosting con PHP 8.4.
7. **Calidad de datos CSV**: muchas fechas de nacimiento/teléfonos inválidos (ver
   `../diagnostico_datos_inscriptos.md`); el seed sanea y deja `NULL` sin bloquear.
