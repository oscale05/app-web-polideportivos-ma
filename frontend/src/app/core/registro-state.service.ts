import { Injectable } from '@angular/core';

/** Datos del wizard de registro, persistidos para sobrevivir un refresh. */
export interface EstadoRegistro {
  dni: string;
  nombre: string;
  apellido: string;
  fecha_nacimiento: string;
  calle: string;
  numero: string;
  departamento: string;
  localidad: string;
  celular: string;
  celular_normalizado?: string;
  email: string;
}

const CLAVE = 'poli_registro';

@Injectable({ providedIn: 'root' })
export class RegistroStateService {
  private datos: EstadoRegistro | null = this.leer();

  get(): EstadoRegistro | null {
    return this.datos;
  }

  set(datos: EstadoRegistro): void {
    this.datos = datos;
    localStorage.setItem(CLAVE, JSON.stringify(datos));
  }

  setDni(dni: string): void {
    this.datos = { ...(this.datos ?? this.vacio()), dni };
    localStorage.setItem(CLAVE, JSON.stringify(this.datos));
  }

  limpiar(): void {
    this.datos = null;
    localStorage.removeItem(CLAVE);
  }

  private vacio(): EstadoRegistro {
    return {
      dni: '', nombre: '', apellido: '', fecha_nacimiento: '',
      calle: '', numero: '', departamento: '', localidad: '', celular: '', email: '',
    };
  }

  private leer(): EstadoRegistro | null {
    const crudo = localStorage.getItem(CLAVE);
    if (!crudo) return null;
    try {
      return JSON.parse(crudo) as EstadoRegistro;
    } catch {
      return null;
    }
  }
}
