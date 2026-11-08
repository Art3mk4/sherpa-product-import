import { inject } from '@angular/core';
import { CanActivateFn, Router, UrlTree } from '@angular/router';
import { AuthService } from '../services/auth.service';

/**
 * Функциональная форма CanActivate (CanActivateFn) — современный аналог
 * классового CanActivate-гарда в standalone-приложениях.
 */
export const authGuard: CanActivateFn = (
  _route,
  state,
): boolean | UrlTree => {
  const auth = inject(AuthService);

  if (auth.isAuthenticated()) {
    return true;
  }

  return inject(Router).createUrlTree(['/login'], {
    queryParams: { returnUrl: state.url },
  });
};
