import { Routes } from '@angular/router';
import { authGuard, noAuthGuard } from './core/auth.guard';

export const routes: Routes = [
  { path: '', pathMatch: 'full', redirectTo: 'login' },
  {
    path: 'login',
    canActivate: [noAuthGuard],
    loadComponent: () => import('./features/login/login.page').then((m) => m.LoginPage),
  },
  {
    path: 'registro',
    canActivate: [noAuthGuard],
    loadComponent: () => import('./features/registro/registro.page').then((m) => m.RegistroPage),
  },
  {
    path: 'recuperar',
    canActivate: [noAuthGuard],
    loadComponent: () => import('./features/recuperar/recuperar.page').then((m) => m.RecuperarPage),
  },
  {
    path: 'app',
    canActivate: [authGuard],
    loadComponent: () => import('./features/app/shell').then((m) => m.ShellComponent),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'actividades' },
      {
        path: 'actividades',
        loadComponent: () => import('./features/app/actividades.page').then((m) => m.ActividadesPage),
      },
      {
        path: 'actividades/:id/asistencias',
        loadComponent: () => import('./features/app/asistencias.page').then((m) => m.AsistenciasPage),
      },
      {
        path: 'perfil',
        loadComponent: () => import('./features/app/perfil.page').then((m) => m.PerfilPage),
      },
      {
        path: 'carnet',
        loadComponent: () => import('./features/app/carnet.page').then((m) => m.CarnetPage),
      },
    ],
  },
  { path: '**', redirectTo: 'login' },
];
