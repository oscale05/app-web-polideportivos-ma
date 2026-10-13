import { Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ApiService } from '../../core/api.service';
import { AuthService } from '../../core/auth.service';
import { ActividadInscripta } from '../../core/models';

@Component({
  selector: 'app-actividades',
  imports: [RouterLink],
  styleUrl: './actividades.page.scss',
  template: `
    <div class="pagina">
      <h2 class="mt mb">Mis actividades</h2>

      @if (cargando()) {
        <div class="centrado"><div class="spinner"></div><p class="suave chico">Cargando actividades…</p></div>
      } @else if (error()) {
        <div class="aviso error">{{ error() }}</div>
      } @else if (lista().length === 0) {
        <div class="tarjeta centrado">
          <p class="grande">Sin inscripciones</p>
          <p class="suave chico">Todavía no figurás en ninguna actividad. Comunicate con el polideportivo.</p>
        </div>
      } @else {
        <p class="suave chico mb">Polideportivo <strong>{{ polideportivo() }}</strong></p>
        @for (a of lista(); track a.id_categoria) {
          <article class="tarjeta actividad">
            <div class="encabezado">
              <h3 class="grande">{{ a.actividad }}</h3>
              <span class="chip">{{ a.categoria }}</span>
            </div>
            <ul class="datos">
              <li>📅 {{ a.dias.join(', ') }}</li>
              <li>🕒 {{ a.hora_i }} – {{ a.hora_f }}</li>
              @if (a.profesor) { <li>🧑‍🏫 {{ a.profesor }}</li> }
              <li>🏟️ {{ a.polideportivo }}</li>
            </ul>
            @if (a.asistencias.total > 0) {
              <div class="asistencia-barra">
                <div class="relleno" [style.width.%]="a.asistencias.pct ?? 0"></div>
              </div>
              <p class="chico suave">
                Asistencia: <strong>{{ a.asistencias.pct }}%</strong>
                ({{ a.asistencias.presentes }}/{{ a.asistencias.total }})
              </p>
            } @else {
              <p class="chico suave">Sin asistencias registradas todavía.</p>
            }
            <a class="btn secundario chico" [routerLink]="['/app/actividades', a.id_categoria, 'asistencias']">
              Ver asistencias
            </a>
          </article>
        }
      }
    </div>
  `,
})
export class ActividadesPage {
  private api = inject(ApiService);
  private auth = inject(AuthService);

  lista = signal<ActividadInscripta[]>([]);
  polideportivo = signal('Palmeras');
  cargando = signal(true);
  error = signal<string | null>(null);

  constructor() {
    void this.cargar();
  }

  private async cargar(): Promise<void> {
    this.cargando.set(true);
    this.error.set(null);
    try {
      const r = await this.api.actividades();
      if (r.status === 200) {
        this.lista.set(r.data.actividades);
        this.polideportivo.set(r.data.polideportivo);
        const d = r.data.actividades[0];
        if (d && !this.auth.deportista()) {
          void this.auth.cargarPerfil();
        }
      } else if (r.status === 401) {
        window.location.href = '/login';
      } else {
        this.error.set(r.error ?? 'No se pudieron cargar las actividades');
      }
    } catch (e: unknown) {
      const err = e as { status?: number };
      if (err?.status === 401) {
        window.location.href = '/login';
        return;
      }
      this.error.set('Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }
}
