import { Routes } from '@angular/router';
import { authGuard } from './core/guards/auth.guard';
import { ShellComponent } from './layout/shell.component';

/**
 * Standalone-компоненты грузятся лениво через loadComponent (lazy loading
 * без NgModules). Требование ТЗ «CanActivate» — функциональный authGuard.
 */
export const routes: Routes = [
  { path: 'login', loadComponent: () => import('./features/auth/login.component').then(m => m.LoginComponent) },
  {
    path: '',
    component: ShellComponent,
    canActivate: [authGuard],
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'products' },
      { path: 'import', loadComponent: () => import('./features/import/import-page.component').then(m => m.ImportPageComponent) },
      { path: 'products', loadComponent: () => import('./features/products/product-list.component').then(m => m.ProductListComponent) },
      { path: 'products/:id', loadComponent: () => import('./features/products/product-card.component').then(m => m.ProductCardComponent) },
    ],
  },
  { path: '**', redirectTo: '' },
];
