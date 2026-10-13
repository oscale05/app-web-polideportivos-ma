import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/api.service';
import { RegistroStateService } from '../../core/registro-state.service';
import { Calle } from '../../core/models';

const LOCALIDADES = [
  'Grand Bourg', 'Ingeniero Adolfo Sourdeaux', 'Ingeniero Pablo Nogues',
  'Los Polvorines', 'Malvinas Argentinas', 'Tortuguitas', 'Villa de Mayo',
  'Tierras Altas',
];

@Component({
  selector: 'app-registro',
  imports: [FormsModule, RouterLink],
  styleUrl: './registro.page.scss',
  template: `
    <div class="pagina">
      <h1 class="mt">Crear cuenta</h1>
      <p class="suave chico mb">Paso {{ paso() }} de 3 · {{ tituloPaso() }}</p>

      <div class="pasos">
        <div class="paso" [class.activo]="paso() === 1" [class.listo]="paso() > 1"></div>
        <div class="paso" [class.activo]="paso() === 2" [class.listo]="paso() > 2"></div>
        <div class="paso" [class.activo]="paso() === 3"></div>
      </div>

      @if (error()) { <div class="aviso error">{{ error() }}</div> }
      @if (demo()) { <div class="aviso demo">{{ demo() }}</div> }

      <!-- PASO 1: datos -->
      @if (paso() === 1) {
        <div class="tarjeta">
          <h2 class="grande mb">Datos personales</h2>

          <div class="campo">
            <label for="dni">DNI *</label>
            <input id="dni" inputmode="numeric" maxlength="8" [(ngModel)]="f.dni"
                   [class.invalido]="tocado['dni'] && !dniOk" (blur)="validarDniEnServidor()" placeholder="7 u 8 dígitos">
            @if (tocado['dni'] && !dniOk) { <div class="error">DNI inválido</div> }
            @if (dniMensaje()) { <div class="error">{{ dniMensaje() }}</div> }
          </div>

          <div class="fila">
            <div class="campo">
              <label for="nombre">Nombre *</label>
              <input id="nombre" [(ngModel)]="f.nombre" (blur)="tocado['nombre']=true"
                     [class.invalido]="tocado['nombre'] && f.nombre.trim().length < 2">
              @if (tocado['nombre'] && f.nombre.trim().length < 2) { <div class="error">Requerido</div> }
            </div>
            <div class="campo">
              <label for="apellido">Apellido *</label>
              <input id="apellido" [(ngModel)]="f.apellido" (blur)="tocado['apellido']=true"
                     [class.invalido]="tocado['apellido'] && f.apellido.trim().length < 2">
              @if (tocado['apellido'] && f.apellido.trim().length < 2) { <div class="error">Requerido</div> }
            </div>
          </div>

          <div class="campo">
            <label for="fnac">Fecha de nacimiento *</label>
            <input id="fnac" type="date" [(ngModel)]="f.fecha_nacimiento" (blur)="tocado['fnac']=true"
                   [class.invalido]="tocado['fnac'] && !fechaOk">
            @if (tocado['fnac'] && !fechaOk) { <div class="error">Fecha inválida (edad entre 5 y 100 años)</div> }
          </div>

          <h3 class="mt mb">Domicilio</h3>
          <div class="campo">
            <label for="calle">Calle *</label>
            <input id="calle" [(ngModel)]="f.calle" (input)="buscarCalles()" (blur)="tocado['calle']=true"
                   [class.invalido]="tocado['calle'] && f.calle.trim().length < 3" autocomplete="off">
            @if (tocado['calle'] && f.calle.trim().length < 3) { <div class="error">Requerido</div> }
            @if (sugerencias().length) {
              <div class="sugerencias">
                @for (c of sugerencias(); track c.calle) {
                  <button type="button" (click)="elegirCalle(c.calle)">{{ c.calle }}</button>
                }
              </div>
            }
          </div>

          <div class="fila">
            <div class="campo">
              <label for="numero">Número *</label>
              <input id="numero" inputmode="numeric" maxlength="5" [(ngModel)]="f.numero" (blur)="tocado['numero']=true"
                     [class.invalido]="tocado['numero'] && !numeroOk">
              @if (tocado['numero'] && !numeroOk) { <div class="error">Solo números</div> }
            </div>
            <div class="campo">
              <label for="dpto">Departamento</label>
              <input id="dpto" [(ngModel)]="f.departamento" maxlength="20" placeholder="Opcional">
            </div>
          </div>

          <div class="campo">
            <label for="localidad">Localidad *</label>
            <select id="localidad" [(ngModel)]="f.localidad" (blur)="tocado['localidad']=true"
                    [class.invalido]="tocado['localidad'] && !f.localidad">
              <option value="">Elegí una localidad</option>
              @for (l of localidades; track l) { <option [value]="l">{{ l }}</option> }
            </select>
            @if (tocado['localidad'] && !f.localidad) { <div class="error">Requerido</div> }
          </div>

          <h3 class="mt mb">Contacto</h3>
          <div class="campo">
            <label for="celular">Celular primario *</label>
            <input id="celular" type="tel" inputmode="tel" [(ngModel)]="f.celular" (blur)="tocado['celular']=true"
                   [class.invalido]="tocado['celular'] && !celularOk" placeholder="11 2345-6789">
            @if (tocado['celular'] && !celularOk) { <div class="error">Celular inválido (con código de área)</div> }
            <div class="ayuda">Te enviamos un código por SMS para verificarlo.</div>
          </div>

          <div class="campo">
            <label for="email">Email *</label>
            <input id="email" type="email" inputmode="email" [(ngModel)]="f.email" (blur)="tocado['email']=true"
                   [class.invalido]="tocado['email'] && !emailOk">
            @if (tocado['email'] && !emailOk) { <div class="error">Email inválido</div> }
          </div>

          <button class="btn bloque mt" [disabled]="cargando()" (click)="enviarPaso1()">
            @if (cargando()) { <span class="spinner"></span> } @else { Continuar }
          </button>
        </div>
      }

      <!-- PASO 2: verificación SMS -->
      @if (paso() === 2) {
        <div class="tarjeta">
          <h2 class="grande mb">Verificá tu celular</h2>
          <p class="suave chico mb">
            Enviamos un código de 6 dígitos al <strong>{{ celularMostrar() }}</strong>.
          </p>

          <div class="campo">
            <label for="codigo">Código de verificación</label>
            <input id="codigo" inputmode="numeric" maxlength="6" [(ngModel)]="codigo"
                   class="codigo" placeholder="······" (keyup.enter)="verificar()">
          </div>

          <button class="btn bloque" [disabled]="cargando() || codigo.length !== 6" (click)="verificar()">
            @if (cargando()) { <span class="spinner"></span> } @else { Verificar }
          </button>

          <div class="acciones">
            <button class="btn fantasma chico" [disabled]="reenvioEspera() > 0" (click)="reenviar()">
              @if (reenvioEspera() > 0) { Reenviar en {{ reenvioEspera() }}s } @else { Reenviar código }
            </button>
            <button class="btn fantasma chico" (click)="volverPaso1()">Editar datos</button>
          </div>
        </div>
      }

      <!-- PASO 3: clave -->
      @if (paso() === 3) {
        <div class="tarjeta">
          <h2 class="grande mb">Elegí tu clave</h2>
          <p class="suave chico mb">Tu usuario será tu DNI: <strong>{{ dniMostrar() }}</strong></p>

          <div class="campo">
            <label for="clave1">Clave</label>
            <input id="clave1" type="password" [(ngModel)]="clave1" autocomplete="new-password">
            @if (clave1) {
              <div class="fortaleza" [class]="'fortaleza n' + fortaleza()">
                {{ fortalezaTexto() }}
              </div>
            }
          </div>
          <div class="campo">
            <label for="clave2">Repetí la clave</label>
            <input id="clave2" type="password" [(ngModel)]="clave2" (keyup.enter)="crearClave()"
                   [class.invalido]="clave2 && clave1 !== clave2">
            @if (clave2 && clave1 !== clave2) { <div class="error">Las claves no coinciden</div> }
          </div>

          <button class="btn bloque mt" [disabled]="cargando()" (click)="crearClave()">
            @if (cargando()) { <span class="spinner"></span> } @else { Crear cuenta }
          </button>
        </div>
      }

      <p class="centrado suave chico mt">¿Ya tenés cuenta? <a routerLink="/login">Ingresá acá</a></p>
    </div>
  `,
})
export class RegistroPage {
  private api = inject(ApiService);
  private router = inject(Router);
  private estado = inject(RegistroStateService);

  paso = signal(1);
  cargando = signal(false);
  error = signal<string | null>(null);
  demo = signal<string | null>(null);
  dniMensaje = signal<string | null>(null);
  sugerencias = signal<Calle[]>([]);
  reenvioEspera = signal(0);

  localidades = LOCALIDADES;
  tocado: Record<string, boolean> = {};

  f = {
    dni: '', nombre: '', apellido: '', fecha_nacimiento: '',
    calle: '', numero: '', departamento: '', localidad: '', celular: '', email: '',
  };

  codigo = '';
  clave1 = '';
  clave2 = '';
  celularNormalizado = '';
  private timerReenvio: ReturnType<typeof setInterval> | null = null;
  private timerCalles: ReturnType<typeof setTimeout> | null = null;

  get dniOk(): boolean { return /^\d{7,8}$/.test(this.f.dni); }
  get numeroOk(): boolean { return /^[1-9]\d{0,4}$/.test(this.f.numero); }
  get celularOk(): boolean { return this.f.celular.replace(/\D/g, '').length >= 10; }
  get emailOk(): boolean { return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(this.f.email); }
  get fechaOk(): boolean {
    if (!this.f.fecha_nacimiento) return false;
    const fecha = new Date(this.f.fecha_nacimiento);
    if (Number.isNaN(fecha.getTime())) return false;
    const hoy = new Date();
    const edad = hoy.getFullYear() - fecha.getFullYear();
    return edad >= 5 && edad <= 100;
  }

  tituloPaso(): string {
    return ['', 'Datos personales', 'Verificación SMS', 'Elegir clave'][this.paso()];
  }

  celularMostrar(): string {
    const limpio = this.celularNormalizado.replace(/^549/, '');
    return `+54 ${limpio.slice(0, 2)} ${limpio.slice(2, 6)}-${limpio.slice(6)}`;
  }

  dniMostrar(): string { return this.f.dni || this.estado.get()?.dni || ''; }

  async validarDniEnServidor(): Promise<void> {
    this.dniMensaje.set(null);
    if (!this.dniOk) return;
    try {
      const r = await this.api.validarDni(this.f.dni);
      if (r.data && r.data.disponible === false) {
        this.dniMensaje.set(
          r.data.motivo === 'dni_en_base'
            ? 'Este DNI ya figura en el polideportivo. Comunicate con el polideportivo.'
            : 'Este DNI ya tiene una cuenta. Ingresá con tu clave.'
        );
      }
    } catch { /* la validación final la hace el servidor */ }
  }

  buscarCalles(): void {
    if (this.timerCalles) clearTimeout(this.timerCalles);
    this.timerCalles = setTimeout(async () => {
      const q = this.f.calle.trim();
      if (q.length < 3) { this.sugerencias.set([]); return; }
      try {
        this.sugerencias.set(await this.api.buscarCalles(q));
      } catch {
        this.sugerencias.set([]);
      }
    }, 300);
  }

  elegirCalle(calle: string): void {
    this.f.calle = calle;
    this.sugerencias.set([]);
  }

  async enviarPaso1(): Promise<void> {
    this.error.set(null);
    this.tocado = { dni: true, nombre: true, apellido: true, fnac: true, calle: true, numero: true, localidad: true, celular: true, email: true };
    if (!this.dniOk || !this.fechaOk || !this.numeroOk || !this.celularOk || !this.emailOk
      || this.f.nombre.trim().length < 2 || this.f.apellido.trim().length < 2
      || this.f.calle.trim().length < 3 || !this.f.localidad) {
      this.error.set('Revisá los campos marcados.');
      return;
    }

    this.cargando.set(true);
    try {
      const r = await this.api.registroPaso1({
        dni: this.f.dni, nombre: this.f.nombre.trim(), apellido: this.f.apellido.trim(),
        fecha_nacimiento: this.f.fecha_nacimiento, calle: this.f.calle.trim(),
        numero: this.f.numero, departamento: this.f.departamento.trim() || null,
        localidad: this.f.localidad, celular: this.f.celular, email: this.f.email.trim(),
      });
      if (r.status === 200) {
        this.estado.set(this.f);
        this.celularNormalizado = r.data.celular;
        if (r.data.codigo_demo) {
          this.demo.set(`Modo demo: tu código es ${r.data.codigo_demo}`);
          this.codigo = r.data.codigo_demo;
        }
        this.paso.set(2);
        this.iniciarReenvio();
      } else {
        this.error.set(r.error ?? 'No se pudo iniciar el registro');
      }
    } catch (e: unknown) {
      const err = e as { status?: number; error?: { error?: string; data?: { motivo?: string } } };
      if (err?.status === 409) {
        this.error.set(err.error?.error ?? 'No se puede registrar con este DNI');
      } else {
        this.error.set(err?.error?.error ?? 'Error de conexión');
      }
    } finally {
      this.cargando.set(false);
    }
  }

  async verificar(): Promise<void> {
    this.error.set(null);
    this.cargando.set(true);
    try {
      const r = await this.api.registroVerificar(this.f.dni || this.estado.get()?.dni || '', this.codigo);
      if (r.status === 200) {
        this.paso.set(3);
      } else {
        this.error.set(r.error ?? 'Código incorrecto');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }

  async reenviar(): Promise<void> {
    this.error.set(null);
    try {
      const r = await this.api.registroPaso1(this.estado.get() ?? this.f);
      if (r.status === 200 && r.data.codigo_demo) {
        this.demo.set(`Modo demo: tu código es ${r.data.codigo_demo}`);
        this.codigo = r.data.codigo_demo;
      }
      this.iniciarReenvio();
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'No se pudo reenviar');
    }
  }

  volverPaso1(): void {
    this.paso.set(1);
  }

  async crearClave(): Promise<void> {
    this.error.set(null);
    if (this.clave1.length < 8 || !/[A-Za-z]/.test(this.clave1) || !/\d/.test(this.clave1)) {
      this.error.set('La clave debe tener al menos 8 caracteres, una letra y un número.');
      return;
    }
    if (this.clave1 !== this.clave2) {
      this.error.set('Las claves no coinciden.');
      return;
    }

    this.cargando.set(true);
    try {
      const r = await this.api.registroClave(this.f.dni || this.estado.get()?.dni || '', this.clave1, this.clave2);
      if (r.status === 201 || r.status === 200) {
        this.estado.limpiar();
        this.router.navigate(['/login'], { queryParams: { registrado: '1' } });
      } else {
        this.error.set(r.error ?? 'No se pudo crear la cuenta');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }

  fortaleza(): number {
    const c = this.clave1;
    let n = 0;
    if (c.length >= 8) n++;
    if (/[A-Z]/.test(c) && /[a-z]/.test(c)) n++;
    if (/\d/.test(c)) n++;
    if (/[^A-Za-z0-9]/.test(c)) n++;
    return n;
  }

  fortalezaTexto(): string {
    return ['', 'Débil', 'Aceptable', 'Buena', 'Excelente'][this.fortaleza()] ?? '';
  }

  private iniciarReenvio(): void {
    this.reenvioEspera.set(60);
    if (this.timerReenvio) clearInterval(this.timerReenvio);
    this.timerReenvio = setInterval(() => {
      const v = this.reenvioEspera() - 1;
      this.reenvioEspera.set(v);
      if (v <= 0 && this.timerReenvio) clearInterval(this.timerReenvio);
    }, 1000);
  }
}
