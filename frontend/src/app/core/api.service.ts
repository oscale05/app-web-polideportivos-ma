import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { firstValueFrom } from 'rxjs';
import {
  RespuestaApi, Deportista, ListadoActividades, AsistenciasCategoria, Calle,
} from './models';

@Injectable({ providedIn: 'root' })
export class ApiService {
  private http = inject(HttpClient);

  // --- Registro ---
  validarDni(dni: string): Promise<RespuestaApi<{ disponible: boolean; motivo?: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ disponible: boolean; motivo?: string }>>('/api/registro/validar', { dni })
    );
  }

  registroPaso1(datos: Record<string, unknown>): Promise<RespuestaApi<{ etapa: string; celular: string; codigo_demo?: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ etapa: string; celular: string; codigo_demo?: string }>>('/api/registro/paso1', datos)
    );
  }

  registroVerificar(dni: string, codigo: string): Promise<RespuestaApi<{ etapa: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ etapa: string }>>('/api/registro/verificar', { dni, codigo })
    );
  }

  registroClave(dni: string, clave: string, confirmacion: string): Promise<RespuestaApi<{ etapa: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ etapa: string }>>('/api/registro/clave', { dni, clave, clave_confirmacion: confirmacion })
    );
  }

  recuperarSolicitar(dni: string): Promise<RespuestaApi<{ ok: boolean; codigo_demo?: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ ok: boolean; codigo_demo?: string }>>('/api/recuperar/solicitar', { dni })
    );
  }

  recuperarVerificar(dni: string, codigo: string, clave: string, confirmacion: string): Promise<RespuestaApi<{ ok: boolean }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ ok: boolean }>>('/api/recuperar/verificar', { dni, codigo, clave, clave_confirmacion: confirmacion })
    );
  }

  // --- Perfil ---
  guardarPerfil(datos: Record<string, unknown>): Promise<RespuestaApi<Deportista>> {
    return firstValueFrom(this.http.put<RespuestaApi<Deportista>>('/api/me', datos));
  }

  solicitarCelular(celular: string): Promise<RespuestaApi<{ celular_en_verificacion: boolean; codigo_demo?: string }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ celular_en_verificacion: boolean; codigo_demo?: string }>>('/api/me/celular/solicitar', { celular })
    );
  }

  verificarCelular(codigo: string): Promise<RespuestaApi<{ ok: boolean }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ ok: boolean }>>('/api/me/celular/verificar', { codigo })
    );
  }

  cancelarCelular(): Promise<RespuestaApi<{ ok: boolean }>> {
    return firstValueFrom(
      this.http.post<RespuestaApi<{ ok: boolean }>>('/api/me/celular/cancelar', {})
    );
  }

  subirFoto(archivo: File): Promise<RespuestaApi<{ foto: string }>> {
    const form = new FormData();
    form.append('foto', archivo, archivo.name);
    return firstValueFrom(this.http.post<RespuestaApi<{ foto: string }>>('/api/me/foto', form));
  }

  async obtenerFoto(): Promise<Blob | null> {
    try {
      const blob = await firstValueFrom(this.http.get('/api/me/foto', { responseType: 'blob' }));
      return blob as Blob;
    } catch {
      return null;
    }
  }

  // --- Dominio ---
  actividades(): Promise<RespuestaApi<ListadoActividades>> {
    return firstValueFrom(this.http.get<RespuestaApi<ListadoActividades>>('/api/actividades'));
  }

  asistencias(idCategoria: number): Promise<RespuestaApi<AsistenciasCategoria>> {
    return firstValueFrom(
      this.http.get<RespuestaApi<AsistenciasCategoria>>(`/api/actividades/${idCategoria}/asistencias`)
    );
  }

  async carnet(): Promise<Blob> {
    const blob = await firstValueFrom(this.http.get('/api/carnet', { responseType: 'blob' }));
    return blob as Blob;
  }

  async buscarCalles(q: string): Promise<Calle[]> {
    const r = await firstValueFrom(
      this.http.get<RespuestaApi<{ calles: Calle[] }>>('/api/calles', { params: { q } })
    );
    return r.data?.calles ?? [];
  }
}
