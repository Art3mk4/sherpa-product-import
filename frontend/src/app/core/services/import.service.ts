import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, shareReplay, switchMap, takeWhile, timer } from 'rxjs';
import { ImportJob, ImportJobCreated } from '../../models/import-job';

@Injectable({ providedIn: 'root' })
export class ImportService {
  private readonly pollIntervalMs = 1000;

  constructor(private readonly http: HttpClient) {}

  upload(file: File): Observable<ImportJobCreated> {
    const formData = new FormData();
    formData.append('file', file, file.name);

    return this.http.post<ImportJobCreated>('/api/imports', formData);
  }

  get(id: string): Observable<ImportJob> {
    return this.http.get<ImportJob>(`/api/imports/${id}`);
  }

  /** 404 «импортов ещё не было» фронт корректно игнорирует — обрабатывает вызывающий. */
  latest(): Observable<ImportJob> {
    return this.http.get<ImportJob>('/api/imports/latest');
  }

  /** Опрос статуса задачи до терминального статуса включительно. */
  poll(id: string): Observable<ImportJob> {
    return timer(0, this.pollIntervalMs).pipe(
      switchMap(() => this.get(id)),
      // inclusive: финальный completed/failed обязан дойти до UI
      takeWhile(job => job.status === 'pending' || job.status === 'processing', true),
      shareReplay({ bufferSize: 1, refCount: true }),
    );
  }
}
