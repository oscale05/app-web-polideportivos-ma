import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-login',
  imports: [FormsModule, RouterLink],
  styleUrl: './login.page.scss',
  template: `
    <div class="pagina">
      <div class="cabecera">
        <div class="escudo">🏟️</div>
        <h1>Polideportivos</h1>
        <p class="suave">Municipio Malvinas Argentinas · Palmeras</p>
      </div>

      @if (exito()) {
        <div class="aviso ok">{{ exito() }}</div>
      }
      @if (error()) {
        <div class="aviso error">{{ error() }}</div>
      }

      <div class="tarjeta">
        <h2 class="grande mb">Ingresar</h2>
        <div class="campo">
          <label for="dni">DNI</label>
          <input id="dni" type="text" inputmode="numeric" autocomplete="username"
                 [(ngModel)]="dni" placeholder="Sin puntos ni espacios" maxlength="8">
        </div>
        <div class="campo">
          <label for="clave">Clave</label>
          <input id="clave" [type]="verClave ? 'text' : 'password'" autocomplete="current-password"
                 [(ngModel)]="clave" (keyup.enter)="ingresar()">
          <button type="button" class="link" (click)="verClave = !verClave">
            {{ verClave ? 'Ocultar' : 'Mostrar' }} clave
          </button>
        </div>
        <button class="btn bloque" [disabled]="cargando()" (click)="ingresar()">
          @if (cargando()) { <span class="spinner"></span> } @else { Ingresar }
        </button>
        <div class="enlaces">
          <a routerLink="/recuperar">Olvidé mi clave</a>
          <a routerLink="/registro">Crear cuenta</a>
        </div>
      </div>
    </div>
  `,
})
export class LoginPage {
  private auth = inject(AuthService);
  private router = inject(Router);

  dni = '';
  clave = '';
  verClave = false;
  cargando = signal(false);
  error = signal<string | null>(null);
  exito = signal<string | null>(null);

  constructor() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('registrado') === '1') {
      this.exito.set('Registro completado. Ingresá con tu DNI y tu clave.');
    }
  }

  async ingresar(): Promise<void> {
    this.error.set(null);
    if (!/^\d{7,8}$/.test(this.dni)) {
      this.error.set('Ingresá un DNI válido (7 u 8 dígitos).');
      return;
    }
    if (!this.clave) {
      this.error.set('Ingresá tu clave.');
      return;
    }

    this.cargando.set(true);
    try {
      const r = await this.auth.login(this.dni, this.clave);
      if (r.status === 200) {
        this.router.navigate(['/app/actividades']);
      } else {
        this.error.set(r.error ?? 'No se pudo iniciar sesión');
      }
    } catch (e: unknown) {
      this.error.set(this.mensajeError(e));
    } finally {
      this.cargando.set(false);
    }
  }

  private mensajeError(e: unknown): string {
    const err = e as { error?: { error?: string } };
    return err?.error?.error ?? 'Error de conexión. Intentá nuevamente.';
  }
}
