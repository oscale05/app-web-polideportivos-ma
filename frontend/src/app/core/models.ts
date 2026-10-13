export interface RespuestaApi<T> {
  status: number;
  data: T;
  error: string | null;
}

export interface Deportista {
  dni: string;
  nombre: string;
  apellido: string;
  fecha_nacimiento: string | null;
  email: string | null;
  calle: string;
  numero: string;
  departamento: string | null;
  localidad: string;
  celular: string;
  celular_verificado: boolean;
  celular_en_verificacion?: boolean;
  tiene_foto: boolean;
}

export interface ResumenAsistencia {
  total: number;
  presentes: number;
  ausentes: number;
  pct: number | null;
}

export interface ActividadInscripta {
  id_categoria: number;
  actividad: string;
  categoria: string;
  dias: string[];
  hora_i: string;
  hora_f: string;
  profesor: string | null;
  polideportivo: string;
  asistencias: ResumenAsistencia;
}

export interface ListadoActividades {
  polideportivo: string;
  actividades: ActividadInscripta[];
}

export interface CategoriaDetalle {
  id_categoria: number;
  actividad: string;
  categoria: string;
  dia?: string;
  dias?: string[];
  hora_i: string;
  hora_f: string;
  profesor: string | null;
  polideportivo: string;
}

export interface AsistenciaFila {
  fecha: string;
  presente: boolean;
  tomada: boolean;
  observacion: string | null;
}

export interface AsistenciasCategoria {
  categoria: CategoriaDetalle;
  historial: AsistenciaFila[];
  resumen: ResumenAsistencia;
}

export interface Calle {
  calle: string;
  codigo: string | null;
}
