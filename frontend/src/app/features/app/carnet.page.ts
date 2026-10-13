import { Component, inject, signal } from '@angular/core';
import { ApiService } from '../../core/api.service';

@Component({
  selector: 'app-carnet',
  styleUrl: './carnet.page.scss',
  template: `
    <div class="pagina">
      <h2 class="mt mb">Mi carnet</h2>
      <p class="suave chico mb">
        Mostrá este QR en el polideportivo. Contiene tu nombre, DNI y tus actividades con
        sus días y horarios.
      </p>

      @if (error()) { <div class="aviso error">{{ error() }}</div> }

      <div class="tarjeta centrado">
        @if (cargando()) {
          <div class="spinner"></div>
          <p class="suave chico">Generando carnet…</p>
        } @else if (carnetUrl()) {
          <img class="carnet" [src]="carnetUrl()!" alt="Carnet de acreditación con QR">
          <div class="botones">
            <button class="btn" (click)="descargar()">⬇️ Descargar</button>
            <button class="btn secundario" (click)="compartir()" [disabled]="!soportaCompartir">
              📤 Compartir
            </button>
          </div>
        }
      </div>
    </div>
  `,
})
export class CarnetPage {
  private api = inject(ApiService);

  carnetUrl = signal<string | null>(null);
  cargando = signal(true);
  error = signal<string | null>(null);
  private blob: Blob | null = null;

  get soportaCompartir(): boolean {
    return typeof navigator !== 'undefined' && 'share' in navigator;
  }

  constructor() {
    void this.cargar();
  }

  private async cargar(): Promise<void> {
    this.cargando.set(true);
    this.error.set(null);
    try {
      this.blob = await this.api.carnet();
      if (this.carnetUrl()) URL.revokeObjectURL(this.carnetUrl()!);
      this.carnetUrl.set(URL.createObjectURL(this.blob));
    } catch {
      this.error.set('No se pudo generar el carnet. Intentá nuevamente.');
    } finally {
      this.cargando.set(false);
    }
  }

  descargar(): void {
    if (!this.blob) return;
    const a = document.createElement('a');
    a.href = URL.createObjectURL(this.blob);
    a.download = 'carnet-polideportivos.jpg';
    a.click();
    URL.revokeObjectURL(a.href);
  }

  async compartir(): Promise<void> {
    if (!this.blob || !this.soportaCompartir) return;
    try {
      const archivo = new File([this.blob], 'carnet-polideportivos.jpg', { type: 'image/jpeg' });
      if (navigator.canShare?.({ files: [archivo] })) {
        await navigator.share({ files: [archivo], title: 'Mi carnet Polideportivos' });
      } else {
        await navigator.share({ title: 'Mi carnet Polideportivos', text: 'Carnet de acreditación' });
      }
    } catch {
      // El usuario canceló el compartir: no es un error.
    }
  }
}
