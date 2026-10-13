import { Injectable, inject, signal } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import { RespuestaApi, Deportista } from './models';

const CLAVE_TOKEN = 'poli_token';
const CLAVE_DNI = 'poli_dni';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private http = inject(HttpClient);
  readonly token = signal<string | null>(localStorage.getItem(CLAVE_TOKEN));
  readonly deportista = signal<Deportista | null>(null);

  get autenticado(): boolean {
    return this.token() !== null;
  }

  async login(dni: string, clave: string): Promise<RespuestaApi<{ token: string; deportista: Deportista }>> {
    const r = await firstValueFrom(
      this.http.post<RespuestaApi<{ token: string; deportista: Deportista }>>('/api/login', { dni, clave })
    );
    if (r.status === 200 && r.data?.token) {
      this.establecerSesion(r.data.token, r.data.deportista);
    }
    return r;
  }

  async logout(): Promise<void> {
    try {
      await firstValueFrom(this.http.post<RespuestaApi<unknown>>('/api/logout', {}));
    } catch {
      // El logout local se hace igualmente.
    }
    this.token.set(null);
    this.deportista.set(null);
    localStorage.removeItem(CLAVE_TOKEN);
    localStorage.removeItem(CLAVE_DNI);
  }

  async cargarPerfil(): Promise<void> {
    const r = await firstValueFrom(
      this.http.get<RespuestaApi<Deportista>>('/api/me')
    );
    if (r.status === 200) {
      this.deportista.set(r.data);
    }
  }

  private establecerSesion(token: string, deportista: Deportista): void {
    this.token.set(token);
    this.deportista.set(deportista);
    localStorage.setItem(CLAVE_TOKEN, token);
    localStorage.setItem(CLAVE_DNI, deportista.dni);
  }
}
