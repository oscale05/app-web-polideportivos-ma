import { Component, inject, signal } from '@angular/core';
import { Router, RouterLink } from '@angular/router';
import { FormsModule } from '@angular/forms';
import { ApiService } from '../../core/api.service';

@Component({
  selector: 'app-recuperar',
  imports: [FormsModule, RouterLink],
  styleUrl: './recuperar.page.scss',
  template: `
    <div class="pagina">
      <h1 class="mt">Recuperar clave</h1>

      @if (paso() === 1) {
        <p class="suave chico mb">Ingresá tu DNI y te enviamos un código por SMS para elegir una clave nueva.</p>
        <div class="tarjeta">
          <div class="campo">
            <label for="dni">DNI</label>
            <input id="dni" inputmode="numeric" maxlength="8" [(ngModel)]="dni">
          </div>
          <button class="btn bloque" [disabled]="cargando()" (click)="solicitar()">
            @if (cargando()) { <span class="spinner"></span> } @else { Enviar código }
          </button>
        </div>
      } @else if (paso() === 2) {
        <p class="suave chico mb">Ingresá el código que recibiste y tu nueva clave.</p>
        <div class="tarjeta">
          @if (demo()) { <div class="aviso demo">{{ demo() }}</div> }
          <div class="campo">
            <label for="codigo">Código</label>
            <input id="codigo" inputmode="numeric" maxlength="6" [(ngModel)]="codigo" class="codigo">
          </div>
          <div class="campo">
            <label for="clave1">Clave nueva</label>
            <input id="clave1" type="password" [(ngModel)]="clave1">
          </div>
          <div class="campo">
            <label for="clave2">Repetí la clave</label>
            <input id="clave2" type="password" [(ngModel)]="clave2">
          </div>
          <button class="btn bloque" [disabled]="cargando()" (click)="confirmar()">
            @if (cargando()) { <span class="spinner"></span> } @else { Guardar clave }
          </button>
        </div>
      } @else {
        <div class="aviso ok">Clave actualizada. Ya podés ingresar.</div>
        <button class="btn bloque" routerLink="/login">Ir a ingresar</button>
      }

      @if (error()) { <div class="aviso error mt">{{ error() }}</div> }

      <p class="centrado suave chico mt"><a routerLink="/login">Volver a ingresar</a></p>
    </div>
  `,
})
export class RecuperarPage {
  private api = inject(ApiService);
  private router = inject(Router);

  paso = signal(1);
  cargando = signal(false);
  error = signal<string | null>(null);
  demo = signal<string | null>(null);

  dni = '';
  codigo = '';
  clave1 = '';
  clave2 = '';

  async solicitar(): Promise<void> {
    this.error.set(null);
    if (!/^\d{7,8}$/.test(this.dni)) {
      this.error.set('Ingresá un DNI válido.');
      return;
    }
    this.cargando.set(true);
    try {
      const r = await this.api.recuperarSolicitar(this.dni);
      if (r.status === 200) {
        if (r.data.codigo_demo) {
          this.demo.set(`Modo demo: tu código es ${r.data.codigo_demo}`);
          this.codigo = r.data.codigo_demo;
        }
        this.paso.set(2);
      } else {
        this.error.set(r.error ?? 'No se pudo solicitar el código');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }

  async confirmar(): Promise<void> {
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
      const r = await this.api.recuperarVerificar(this.dni, this.codigo, this.clave1, this.clave2);
      if (r.status === 200) {
        this.paso.set(3);
      } else {
        this.error.set(r.error ?? 'Código incorrecto o vencido');
      }
    } catch (e: unknown) {
      const err = e as { error?: { error?: string } };
      this.error.set(err?.error?.error ?? 'Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }
}
