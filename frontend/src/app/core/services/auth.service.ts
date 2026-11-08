import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, tap } from 'rxjs';

interface LoginResponse {
  token: string;
  expiresIn: number;
}

interface JwtPayload {
  sub: string;
  iat: number;
  exp: number;
}

/** Только сервисы работают с HttpClient — не компоненты. */
@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly storageKey = 'import_app_token';

  constructor(private readonly http: HttpClient) {}

  login(email: string, password: string): Observable<LoginResponse> {
    return this.http.post<LoginResponse>('/api/auth/login', { email, password })
      .pipe(tap(response => this.saveToken(response.token)));
  }

  logout(): void {
    try {
      localStorage.removeItem(this.storageKey);
    } catch {
      // localStorage может быть недоступен (приватный режим) — не падаем
    }
  }

  token(): string | null {
    try {
      return localStorage.getItem(this.storageKey);
    } catch {
      return null;
    }
  }

  /** Проверка без похода на сервер: токен есть и exp не истёк. */
  isAuthenticated(): boolean {
    const token = this.token();
    if (token === null) {
      return false;
    }

    try {
      const payload = JSON.parse(atob(token.split('.')[1])) as JwtPayload;
      return typeof payload.exp === 'number' && payload.exp * 1000 > Date.now();
    } catch {
      return false;
    }
  }

  private saveToken(token: string): void {
    try {
      localStorage.setItem(this.storageKey, token);
    } catch {
      // см. logout()
    }
  }
}
