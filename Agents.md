# Agents.md — Guía para agentes de código del proyecto "App Deportistas Polideportivos"

Este archivo es el punto de entrada obligatorio para cualquier agente (humano o IA) que trabaje
sobre esta aplicación. Léelo completo antes de tocar un archivo.

---

## 1. Qué es este proyecto

Aplicación web **mobile-first** para deportistas del Municipio Malvinas Argentinas. El deportista
llega escaneando un **QR impreso** (carnet de acreditación) que apunta a la URL de la app.

Flujo principal:

1. **Registro** (solo DNIs nuevos, ver §6 Regla dura 1): datos personales → verificación de
   celular por **código SMS** → elección de clave. Al terminar se lo lleva a **Login**.
2. **Login**: usuario = **DNI**, clave = la que eligió.
3. **Dentro de la app**: actividades/categorías en las que está inscripto (con el nombre del
   polideportivo), consulta de asistencias, foto de perfil (selfie) y **carnet QR**.
4. El deportista **edita sus datos en cualquier momento excepto el DNI**. Si cambia el celular,
   debe **re-verificarlo por SMS** antes de que el cambio quede activo.

Estética tomada de <https://polideportivos.malvinasargentinas.gob.ar>
(Montserrat, azules `#005cbb` / `#00458f`, acentos `#abc7ff`, fondo `#faf9fd`).

---

## 2. Stack (decidido, no cambiar sin acuerdo)

| Capa | Tecnología | Versión |
|---|---|---|
| Backend | PHP sin framework (arquitectura en capas propia) | **8.4.23** (local: `/usr/local/bin/php84`) |
| Base de datos | MySQL/MariaDB | **MariaDB 11.4** (brew, corriendo; cliente `mariadb`) |
| QR | `endroid/qr-code` vía Composer | **6.1.3** (requiere PHP ^8.4, usa `bacon/bacon-qr-code`) |
| Imágenes | extensión `gd` | ya instalada |
| Frontend | **Angular** (standalone + signals, SCSS) | **22.2.2** última estable |
| HTTP local | `php -S` para API + `ng serve` con proxy | — |
| Paquete manager PHP | Composer | 2.10.3 |
| Paquete manager front | npm | 10.9.8 / Node 22.23.2 |

- El CLI global de Angular es 22.1.5: **usar siempre `npx @angular/cli@22.2.2`** para
  `ng new`/`ng generate` y actualizar dependencias a `22.2.2` (`npm i @angular/core@22.2.2 ...`)
  para no quedar en 22.1.x.
- No usar SSR en Angular: el backend es PHP.
- No se usan frameworks PHP (Laravel/Symfony). Si aparece la tentación de agregar uno, **preguntar**.

---

## 3. Estructura del repositorio

```
Aplicacion/                         ← raíz de trabajo (este directorio)
├── Agents.md                       ← este archivo
├── planificacion.md                ← plan de implementación
├── Imagenes/
│   └── carnet.jpeg                 ← plantilla del carnet (650×945). NO MODIFICAR.
│                                     Recuadro blanco para QR: x 147–502, y 415–754
│                                     (centro ≈ 324,5 / 584,5; QR de ~300×300 px)
├── MALVINAS ARGENTINAS CALLES.xlsx ← padrón de calles (para autocompletado de domicilio)
├── backend/
│   ├── composer.json
│   ├── .env                        ← creado por cada dev desde .env.example (NO commitear)
│   ├── public/
│   │   └── index.php               ← front controller; en prod sirve también el build Angular
│   ├── src/
│   │   ├── Config.php  Database.php  Router.php  Auth.php
│   │   ├── Controllers/            ← un controlador por recurso (Auth, Deportista, ...)
│   │   ├── Services/               ← lógica de negocio (Registro, Sms, Qr, Foto, Seed)
│   │   └── Support/                ← Validators, RateLimiter, JsonResponse, CsvReader
│   ├── sql/schema.sql              ← esquema completo (DROP/CREATE idempotente)
│   ├── scripts/
│   │   ├── seed.php                ← importa CSV + XLSX, crea asistencias y cuentas demo
│   │   └── convertir_calles.py     ← XLSX → data/ calles (python3 + openpyxl, ya disponible)
│   ├── data/                       ← CSV intermedios generados (calles.csv, etc.)
│   └── storage/
│       ├── fotos/                  ← avatares {dni}.jpg
│       ├── carnets/                ← carnets QR generados {dni}.jpg
│       └── tmp/
└── frontend/                       ← workspace Angular 22
    └── src/app/
        ├── core/                   ← guards, interceptores, servicios (auth, api)
        ├── shared/                 ← componentes reutilizables (botón, input, spinner, snackbar)
        └── features/
            ├── registro/  login/  recuperar/
            └── app/ (actividades, asistencias, perfil, carnet)
```

Archivos de datos de la carpeta padre (`../poli_5_*.csv`, `../turnos_resumen.csv`) son **solo
fuente de seed**: nunca se modifican ni se leen en tiempo de ejecución desde la app.

---

## 4. Comandos de referencia (usar estos, no inventar)

```bash
# --- Backend ---
cd backend
composer install                        # instala endroid/qr-code + phpdotenv
cp .env.example .env                    # editar credenciales si hace falta
php scripts/seed.php                    # crea BD, schema, importa CSV/XLSX, cuentas demo
php -S 127.0.0.1:8080 -t public         # API en :8080
find src scripts public -name '*.php' -print0 | xargs -0 -n1 php -l   # lint
composer test                           # tests (phpunit)

# --- Base de datos (usuario slm, privilegios completos por socket) ---
mariadb -u slm -e "SHOW DATABASES;"     # inspección
mariadb -u slm polideportivos_app       # consola (la BD se llama polideportivos_app)

# --- Frontend ---
cd frontend
npm install
npx ng serve                            # dev en :4200 (proxy /api → 127.0.0.1:8080)
npx ng build --configuration production # build de producción
npx ng test                             # unit tests
npx ng lint                             # lint (angular-eslint)
```

Nota sobre MariaDB local: el usuario `slm` tiene `ALL PRIVILEGES ... WITH GRANT OPTION` y
autentica por socket (`unix_socket`) o password `invalid`. `root` solo por socket y requiere
sudo (evitar). Usar siempre `-u slm`.

---

## 5. Convenciones

**PHP**
- PSR-12 (indentación 4 espacios, `declare(strict_types=1);` en cada archivo).
- Sin clases anónimas gigantes ni lógica en `index.php`: **controlador → servicio → DB**.
- Respuestas JSON SIEMPRE por `Support\JsonResponse` con forma
  `{ "status": <int>, "data": <any>, "error": <string|null> }` (misma forma que la API
  municipal existente para no reinventar).
- Fechas en ISO `Y-m-d`/`Y-m-d H:i:s` en BD y API; se muestran en `d/m/Y` en la UI.
- Validar y sanitizar **en el servidor**, siempre (el frontend es solo UX).
- Nombres en español (tablas, campos, mensajes de error al usuario).

**Angular**
- Standalone components + `signals()` + `input()`/`output()`. Nada de NgModules.
- Inyección con `providedIn: 'root'` para servicios; guards como funciones (`canActivate`).
- SCSS por componente + `styles.scss` global con variables del tema (paleta del sitio oficial).
- Tipado estricto (`strictTemplates` activo); modelos en `core/models/*.ts`.
- Textos de UI en **español rioplatense** ("Inscripciones", "Asistencias", "Celular", "DNI").
- Mobile-first: breakpoints `min-width: 480px / 768px`. Targets táctiles ≥ 44 px.

**Git**
- No commitear `.env`, `node_modules/`, `vendor/`, `storage/fotos/*`, `storage/carnets/*`
  (hay `.gitignore` que lo cubre; verificar antes de `git add`).
- Mensajes de commit en español, estilo `feat(auth): valida código SMS`.

---

## 6. Reglas DURAS (no violar bajo ninguna circunstancia)

1. **Rechazo de DNI existente**: si el DNI ya está en la base importada de los CSV
   (`deportistas.origen = 'csv'`), el registro se **rechaza** con el mensaje
   "Este DNI ya figura en el polideportivo. Comunicate con el polideportivo." (decisión del
   cliente). No crear cuenta, no heredar inscripciones, no "arreglarlo" con un flag.
2. **El DNI es inmutable**: no existe endpoint ni UI que permita modificarlo.
3. **Celular re-verificado**: si el celular cambia, el registro queda con
   `celular_verificado = 0` y la API debe exigir verificación SMS antes de considerar activo
   ese número (se conserva el anterior hasta verificar).
4. **SMS en modo demo**: `SmsSenderInterface` con implementación `DemoSmsSender`
   (registra en log + tabla + endpoint local `dev/sms/ultimo-codigo`). **No** hardcodear
   credenciales de Twilio ni ningún proveedor real; eso va por `.env` cuando se decida.
5. **Nunca subir secretos** (`.env`, tokens, claves) al repo.
6. **No modificar** `Imagenes/carnet.jpeg` ni los CSV originales de la carpeta padre.
7. Todo cambio no trivial se verifica antes de dar por terminada la tarea:
   `php -l`, `composer test`, `npx ng build`, y una pasada manual del flujo afectado.
8. Si una petición del usuario chocaría con las reglas 1–6, **preguntar antes de implementar**.

---

## 7. Roles sugeridos de agentes (para delegar con el tool `task`)

| Rol | Responsable de | Debe verificar con |
|---|---|---|
| `agente-backend-php` | `backend/src/**`, endpoints, schema, seed | `php -l`, `composer test` |
| `agente-datos` | CSV/XLSX → seed, saneamiento de datos, asistencias sintéticas | `php scripts/seed.php` + queries de control |
| `agente-frontend-angular` | `frontend/src/**`, pantallas, guards, diseño | `npx ng build`, `npx ng lint` |
| `agente-qr-imagen` | `QrService`, composición del QR sobre `carnet.jpeg` | test visual + decodificar el QR generado |
| `agente-qa` | pruebas E2E manuales guiadas, checklist de aceptación | checklist de `planificacion.md` §9 |

Al delegar: siempre incluir en el prompt la ruta de este `Agents.md` y el alcance exacto
(archivos que puede tocar y los que no).

---

## 8. Estado actual del proyecto

- [x] Investigación de entorno y decisiones de stack.
- [x] `Agents.md` y `planificacion.md` escritos.
- [x] Fase 1 a la 7 de `planificacion.md` (implementación): backend completo
      (schema, seed, auth + SMS demo, actividades, asistencias, perfil, foto, carnet QR)
      y frontend Angular 22 completo (login, registro, recuperar, shell con
      actividades/asistencias/perfil/carnet).
- [x] Verificación: `php -l`, `composer test` (15 tests / 53 assertions), `npx ng build
      --configuration production`, `npx ng lint` (sin errores) y un smoke test end-to-end
      vía el proxy de `ng serve` (:4200 → :8080).
- [x] Checklist manual de `planificacion.md` §13 en navegador (pantallas chicas, cámara,
      compartir carnet) y ajustes de diseño que surjan.
- [x] Repo Git inicializado (rama `main`), commit inicial hecho. `.env` y artefactos
      ignorados vía `.gitignore` (verificado antes del commit).
- [x] Fix iOS: el shell ahora es una columna flex scrolleable (header sticky arriba,
      `.pagina` con `flex: 1 0 auto`, nav inferior fija) — el contenido ya no se corta.

Servidores habituales en desarrollo: API `php -S 127.0.0.1:8080 -t backend/public`
(log en `/tmp/opencode/api.log`); front `npx ng serve` en :4200 con proxy `/api` → :8080.
