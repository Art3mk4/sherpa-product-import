import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { MatSnackBar } from '@angular/material/snack-bar';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../services/auth.service';

/**
 * Глобальная обработка ошибок: 401 → logout + редирект на /login,
 * 429 и 5xx → снекбар. 422 и 404 глобально НЕ показываем — их обрабатывают
 * компоненты (валидация, «не найдено»).
 */
export const errorInterceptor: HttpInterceptorFn = (req, next) => {
  const auth = inject(AuthService);
  const router = inject(Router);
  const snackBar = inject(MatSnackBar);

  return next(req).pipe(
    catchError((error: unknown) => {
      if (!(error instanceof HttpErrorResponse)) {
        return throwError(() => error);
      }

      if (error.status === 401 && !req.url.includes('/api/auth/login')) {
        auth.logout();
        router.navigate(['/login'], {
          queryParams: { returnUrl: router.url },
        });
        return throwError(() => error);
      }

      if (error.status === 429) {
        const retryAfter = Number(error.headers.get('Retry-After') ?? '60');
        snackBar.open(
          `Слишком много запросов, повторите через ${retryAfter} с`,
          'Закрыть',
          { duration: 6000 },
        );
        return throwError(() => error);
      }

      if (error.status === 0 || error.status >= 500) {
        snackBar.open('Ошибка сервера. Попробуйте позже.', 'Закрыть', { duration: 6000 });
        return throwError(() => error);
      }

      return throwError(() => error);
    }),
  );
};
