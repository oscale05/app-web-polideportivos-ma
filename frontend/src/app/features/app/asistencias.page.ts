import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { ApiService } from '../../core/api.service';
import { AsistenciasCategoria } from '../../core/models';

@Component({
  selector: 'app-asistencias',
  imports: [RouterLink],
  styleUrl: './asistencias.page.scss',
  template: `
    <div class="pagina">
      <a routerLink="/app/actividades" class="volver">← Volver a actividades</a>

      @if (cargando()) {
        <div class="centrado"><div class="spinner"></div></div>
      } @else if (error()) {
        <div class="aviso error">{{ error() }}</div>
      } @else if (data()) {
        <div class="tarjeta">
          <h2 class="grande">{{ data()!.categoria.actividad }}</h2>
          <p class="suave">{{ data()!.categoria.categoria }} · {{ data()!.categoria.polideportivo }}</p>
          <p class="chico suave">
            📅 {{ data()!.categoria.dia ?? data()!.categoria.dias?.join(', ') }} ·
            🕒 {{ data()!.categoria.hora_i }}–{{ data()!.categoria.hora_f }}
          </p>

          @if (data()!.resumen.total > 0) {
            <div class="resumen">
              <div class="circulo">
                <span>{{ data()!.resumen.pct }}%</span>
              </div>
              <div>
                <p class="grande">{{ data()!.resumen.presentes }} presentes</p>
                <p class="chico suave">de {{ data()!.resumen.total }} clases tomadas</p>
              </div>
            </div>
          } @else {
            <p class="suave chico">Todavía no hay asistencias tomadas en esta categoría.</p>
          }
        </div>

        @if (data()!.historial.length) {
          <h3 class="mb">Historial</h3>
          <div class="tarjeta lista">
            @for (h of data()!.historial; track h.fecha) {
              <div class="fila-asistencia">
                <span class="fecha">{{ formatearFecha(h.fecha) }}</span>
                @if (!h.tomada) {
                  <span class="estado pendiente">Sin tomar</span>
                } @else if (h.presente) {
                  <span class="estado presente">Presente</span>
                } @else {
                  <span class="estado ausente">Ausente</span>
                }
              </div>
            }
          </div>
        }
      }
    </div>
  `,
})
export class AsistenciasPage {
  private api = inject(ApiService);
  private route = inject(ActivatedRoute);

  data = signal<AsistenciasCategoria | null>(null);
  cargando = signal(true);
  error = signal<string | null>(null);

  constructor() {
    void this.cargar();
  }

  private async cargar(): Promise<void> {
    const id = Number(this.route.snapshot.paramMap.get('id'));
    if (!id) {
      this.error.set('Categoría inválida');
      this.cargando.set(false);
      return;
    }
    try {
      const r = await this.api.asistencias(id);
      if (r.status === 200) {
        this.data.set(r.data);
      } else {
        this.error.set(r.error ?? 'No se pudo cargar');
      }
    } catch {
      this.error.set('Error de conexión');
    } finally {
      this.cargando.set(false);
    }
  }

  formatearFecha(iso: string): string {
    const [a, m, d] = iso.split('-');
    return `${d}/${m}/${a}`;
  }
}
