import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { Deportista } from '../../core/models';

const LOCALIDADES = [
  'Grand Bourg', 'Ingeniero Adolfo Sourdeaux', 'Ingeniero Pablo Nogues',
  'Los Polvorines', 'Malvinas Argentinas', 'Tortuguitas', 'Villa de Mayo',
  'Tierras Altas',
];

@Component({
  selector: 'app-perfil',
  imports: [FormsModule],
  styleUrl: './perfil.page.scss',
  template: `
    <div class="pagina">
      <h2 class="mt mb">Mi perfil</h2>

      @if (exito()) { <div class="aviso ok">{{ exito() }}</div> }
      @if (error()) { <div class="aviso error">{{ error() }}</div> }

      <!-- Foto -->
      <div class="tarjeta foto-seccion">
        <div class="avatar">
          @if (fotoUrl()) {
            <img [src]="fotoUrl()!" alt="Foto de perfil">
          } @else {
            <div class="sin-foto">📷</div>
          }
        </div>
        <div class="foto-acciones">
          <button class="btn chico" (click)="tomarSelfie()" [disabled]="procesandoFoto()">
            🤳 Tomar selfie
          </button>
          <label class="btn fantasma chico">
            📁 Subir foto
            <input type="file" accept="image/*" (change)="subirArchivo($event)" hidden>
          </label>
        </div>
      </div>

      <!-- Datos -->
      <div class="tarjeta">
        <h3 class="mb">Datos personales</h3>

        <div class="campo">
          <label for="dni">DNI (no se puede modificar)</label>
          <input id="dni" [value]="perfil()?.dni ?? '…'" disabled>
        </div>

        <div class="fila">
          <div class="campo">
            <label for="nombre">Nombre</label>
            <input id="nombre" [(ngModel)]="f.nombre">
          </div>
          <div class="campo">
            <label for="apellido">Apellido</label>
            <input id="apellido" [(ngModel)]="f.apellido">
          </div>
        </div>

        <div class="campo">
          <label for="fnac">Fecha de nacimiento</label>
          <input id="fnac" type="date" [(ngModel)]="f.fecha_nacimiento">
        </div>

        <div class="campo">
          <label for="email">Email</label>
          <input id="email" type="email" [(ngModel)]="f.email">
        </div>

        <h3 class="mt mb">Domicilio</h3>
        <div class="campo">
          <label for="calle">Calle</label>
          <input id="calle" [(ngModel)]="f.calle" autocomplete="off">
        </div>
        <div class="fila">
          <div class="campo">
            <label for="numero">Número</label>
            <input id="numero" inputmode="numeric" [(ngModel)]="f.numero">
          </div>
          <div class="campo">
            <label for="dpto">Departamento</label>
            <input id="dpto" [(ngModel)]="f.departamento" placeholder="Opcional">
          </div>
        </div>
        <div class="campo">
          <label for="localidad">Localidad</label>
          <select id="localidad" [(ngModel)]="f.localidad">
            @for (l of localidades; track l) { <option [value]="l">{{ l }}</option> }
          </select>
        </div>

        <button class="btn bloque mt" [disabled]="cargando()" (click)="guardar()">
          @if (cargando()) { <span class="spinner"></span> } @else { Guardar cambios }
        </button>
      </div>

      <!-- Celular -->
      <div class="tarjeta">
        <h3 class="mb">Celular</h3>
        <p class="chico suave">
          Celular actual: <strong>{{ perfil()?.celular ?? '…' }}</strong>
          @if (perfil()?.celular_verificado) {
            <span class="ok-chip">verificado ✓</span>
          }
        </p>

        @if (celularEnVerificacion()) {
          <div class="aviso demo">Estamos verificando un nuevo celular.</div>
          <div class="campo">
            <label for="cod">Código SMS</label>
            <input id="cod" inputmode="numeric" maxlength="6" [(ngModel)]="codigoCelular" class="codigo">
          </div>
          <div class="fila">
            <button class="btn" [disabled]="cargandoCelular()" (click)="verificarCelular()">Verificar</button>
            <button class="btn fantasma" (click)="cancelarCelular()">Cancelar</button>
          </div>
        } @else {
          <div class="campo">
            <label for="nuevoCel">Nuevo celular</label>
            <input id="nuevoCel" type="tel" inputmode="tel" [(ngModel)]="nuevoCelular" placeholder="11 2345-6789">
            <div class="ayuda">Si lo cambiás, te vamos a pedir verificarlo por SMS.</div>
          </div>
          <button class="btn bloque" [disabled]="cargandoCelular()" (click)="solicitarCelular()">
            Cambiar celular
          </button>
        }
      </div>
    </div>

    <!-- Captura de selfie -->
    @if (mostrarCamara()) {
      <div class="modal-camara">
        <video #video autoplay playsinline muted></video>
        <div class="camara-acciones">
          <button class="btn" (click)="capturar()">📸 Capturar</button>
          <button class="btn fantasma" (click)="cerrarCamara()">Cancelar</button>
        </div>
      </div>
    }
  `,
})
export class PerfilPage {
  private api = inject(ApiService);
  private auth = inject(AuthService);

  perfil = signal<Deportista | null>(null);
  fotoUrl = signal<string | null>(null);
  cargando = signal(false);
  cargandoCelular = signal(false);
  procesandoFoto = signal(false);
  exito = signal<string | null>(null);
  error = signal<string | null>(null);
  celularEnVerificacion = signal(false);
  mostrarCamara = signal(false);

  localidades = LOCALIDADES;
  f = {
    nombre: '', apellido: '', fecha_nacimiento: '', email: '',
    calle: '', numero: '', departamento: '', localidad: '',
  };
  nuevoCelular = '';
  codigoCelular = '';

  private stream: MediaStream | null = null;

  constructor() {
    void this.cargar();
  }

  private async cargar(): Promise<void> {
    try {
      await this.auth.cargarPerfil();
      const d = this.auth.deportista();
      if (!d) return;
      this.perfil.set(d);
      this.celularEnVerificacion.set(!!d.celular_en_verificacion);
      this.f = {
        nombre: d.nombre, apellido: d.apellido,
        fecha_nacimiento: d.fecha_nacimiento ?? '', email: d.email ?? '',
        calle: d.calle, numero: d.numero, departamento: d.departamento ?? '', localidad: d.localidad,
      };
      if (d.tiene_foto) {
        const blob = await this.api.obtenerFoto();
        if (blob) this.fotoUrl.set(URL.createObjectURL(blob));
      }
    } catch {
      this.error.set('No se pudo cargar el perfil');
    }
  }

  async guardar(): Promise<void> {
    this.exito.set(null);
    this.error.set(null);
    if (this.f.nombre.trim().length < 2 || this.f.apellido.trim().length < 2) {
      this.error.set('Nombre y apellido son obligatorios.');
      return;
    }
    this.cargando.set(true);
    try {
      const r = await this.api.guardarPerfil({
        nombre: this.f.nombre.trim(), apellido: this.f.apellido.trim(),
        fecha_nacimiento: this.f.fecha_nacimiento || null,
        email: this.f.email.trim() || null,
        calle: this.f.calle.trim(), numero: this.f.numero,
        departamento: this.f.departamento.trim() || null,
        localidad: this.f.localidad,
      });
      if (r.status === 200) {
        this.perfil.set(r.data);
        this.auth.deportista.set(r.data);
        this.exito.set('Datos guardados.');
      } else {
        this.error.set(r.error ?? 'No se pudieron guardar los datos');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }

  async solicitarCelular(): Promise<void> {
    this.error.set(null);
    if (this.nuevoCelular.replace(/\D/g, '').length < 10) {
      this.error.set('Ingresá un celular válido con código de área.');
      return;
    }
    this.cargandoCelular.set(true);
    try {
      const r = await this.api.solicitarCelular(this.nuevoCelular);
      if (r.status === 200) {
        this.celularEnVerificacion.set(true);
        if (r.data.codigo_demo) {
          this.codigoCelular = r.data.codigo_demo;
          this.exito.set(`Modo demo: tu código es ${r.data.codigo_demo}`);
        } else {
          this.exito.set('Enviamos un código al nuevo celular.');
        }
      } else {
        this.error.set(r.error ?? 'No se pudo solicitar');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargandoCelular.set(false);
    }
  }

  async verificarCelular(): Promise<void> {
    this.error.set(null);
    if (!/^\d{6}$/.test(this.codigoCelular)) {
      this.error.set('Ingresá el código de 6 dígitos.');
      return;
    }
    this.cargandoCelular.set(true);
    try {
      const r = await this.api.verificarCelular(this.codigoCelular);
      if (r.status === 200) {
        this.celularEnVerificacion.set(false);
        this.nuevoCelular = '';
        this.exito.set('¡Celular verificado!');
        await this.cargar();
      } else {
        this.error.set(r.error ?? 'Código incorrecto');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargandoCelular.set(false);
    }
  }

  async cancelarCelular(): Promise<void> {
    await this.api.cancelarCelular();
    this.celularEnVerificacion.set(false);
  }

  async subirArchivo(event: Event): Promise<void> {
    const input = event.target as HTMLInputElement;
    const archivo = input.files?.[0];
    if (!archivo) return;
    await this.guardarFoto(archivo);
    input.value = '';
  }

  async tomarSelfie(): Promise<void> {
    this.error.set(null);
    try {
      this.stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: 'user', width: { ideal: 720 }, height: { ideal: 720 } },
        audio: false,
      });
      this.mostrarCamara.set(true);
      setTimeout(() => {
        const video = document.querySelector('video');
        if (video && this.stream) video.srcObject = this.stream;
      }, 50);
    } catch {
      this.error.set('No se pudo acceder a la cámara. Verificá el permiso o subí una foto desde el archivo.');
    }
  }

  capturar(): void {
    const video = document.querySelector('video') as HTMLVideoElement | null;
    if (!video || !this.stream) return;
    const canvas = document.createElement('canvas');
    const lado = Math.min(video.videoWidth, video.videoHeight) || 480;
    canvas.width = lado;
    canvas.height = lado;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    // Espejo para que se vea natural.
    ctx.translate(lado, 0);
    ctx.scale(-1, 1);
    ctx.drawImage(video, (video.videoWidth - lado) / 2, 0, lado, lado, 0, 0, lado, lado);
    this.cerrarCamara();
    canvas.toBlob((blob) => {
      if (blob) void this.guardarFoto(new File([blob], 'selfie.jpg', { type: 'image/jpeg' }));
    }, 'image/jpeg', 0.9);
  }

  cerrarCamara(): void {
    if (this.stream) {
      this.stream.getTracks().forEach((t) => t.stop());
      this.stream = null;
    }
    this.mostrarCamara.set(false);
  }

  private async guardarFoto(archivo: File): Promise<void> {
    if (archivo.size > 5 * 1024 * 1024) {
      this.error.set('La imagen no puede superar 5 MB.');
      return;
    }
    this.procesandoFoto.set(true);
    this.error.set(null);
    try {
      const r = await this.api.subirFoto(archivo);
      if (r.status === 200) {
        const blob = await this.api.obtenerFoto();
        if (blob) {
          if (this.fotoUrl()) URL.revokeObjectURL(this.fotoUrl()!);
          this.fotoUrl.set(URL.createObjectURL(blob));
        }
        this.exito.set('Foto de perfil actualizada.');
        const d = this.auth.deportista();
        if (d) this.auth.deportista.set({ ...d, tiene_foto: true });
      } else {
        this.error.set(r.error ?? 'No se pudo subir la foto');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error al subir la foto');
    } finally {
      this.procesandoFoto.set(false);
    }
  }
}
