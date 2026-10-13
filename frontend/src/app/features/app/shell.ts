import { Component, inject } from '@angular/core';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService } from '../../core/auth.service';

@Component({
  selector: 'app-shell',
  imports: [RouterOutlet, RouterLink, RouterLinkActive],
  styleUrl: './shell.scss',
  template: `
    <div class="shell">
      <header class="marca">
        <div>
          <h1>Polideportivos</h1>
          <div class="sub">{{ nombreDeportista() }}</div>
        </div>
        <button class="salir" type="button" (click)="salir()" title="Cerrar sesión">Salir</button>
      </header>

      <router-outlet />

      <nav class="nav-inferior">
        <a routerLink="/app/actividades" routerLinkActive="activo">
          <span class="ico">🏃</span> Actividades
        </a>
        <a routerLink="/app/carnet" routerLinkActive="activo">
          <span class="ico">🎟️</span> Carnet
        </a>
        <a routerLink="/app/perfil" routerLinkActive="activo">
          <span class="ico">👤</span> Perfil
        </a>
      </nav>
    </div>
  `,
})
export class ShellComponent {
  private auth = inject(AuthService);

  nombreDeportista(): string {
    const d = this.auth.deportista();
    return d ? `${d.nombre} ${d.apellido}` : ' deportista';
  }

  async salir(): Promise<void> {
    await this.auth.logout();
    window.location.href = '/login';
  }
}
