import { Component, inject, signal } from '@angular/core';
import { HttpErrorResponse } from '@angular/common/http';
import { AsyncPipe } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { MatCard, MatCardTitle, MatCardContent } from '@angular/material/card';
import { MatButton } from '@angular/material/button';
import { Observable, catchError, of, switchMap, tap } from 'rxjs';
import { ImportService } from '../../core/services/import.service';
import { ImportJob } from '../../models/import-job';
import { ImportStatusComponent } from '../../shared/import-status/import-status.component';

const MAX_FILE_SIZE = 10 * 1024 * 1024;   // дублирует серверную проверку, не заменяет её

@Component({
  selector: 'app-import-page',
  imports: [AsyncPipe, FormsModule, MatCard, MatCardTitle, MatCardContent, MatButton, ImportStatusComponent],
  templateUrl: './import-page.component.html',
})
export class ImportPageComponent {
  private readonly importService = inject(ImportService);

  fileName = '';
  fileError: string | null = null;
  uploadError: string | null = null;

  selectedFile: File | null = null;
  job$: Observable<ImportJob | null> | null = null;

  readonly busy = signal(false);

  onFileSelected(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.fileError = null;
    this.uploadError = null;
    this.selectedFile = null;
    this.fileName = '';

    const file = input.files?.[0];
    if (!file) {
      return;
    }

    const extension = file.name.toLowerCase().split('.').pop();
    if (extension !== 'xlsx') {
      this.fileError = 'Допустимо только расширение .xlsx';
      input.value = '';
      return;
    }

    if (file.size > MAX_FILE_SIZE) {
      this.fileError = `Файл больше ${Math.round(MAX_FILE_SIZE / 1024 / 1024)} МБ`;
      input.value = '';
      return;
    }

    this.selectedFile = file;
    this.fileName = file.name;
  }

  startImport(): void {
    if (!this.selectedFile || this.busy()) {
      return;
    }

    this.busy.set(true);
    this.uploadError = null;
    this.job$ = null;

    this.job$ = this.importService.upload(this.selectedFile).pipe(
      switchMap(created => this.importService.poll(created.id)),
      // по завершении задачи снова разблокировать кнопку
      tap(job => {
        if (job.status === 'completed' || job.status === 'failed') {
          this.busy.set(false);
        }
      }),
      catchError((error: unknown) => {
        this.uploadError = error instanceof HttpErrorResponse && error.status === 422
          ? (error.error?.error?.details?.file?.[0] ?? 'Файл не прошёл валидацию')
          : 'Не удалось запустить импорт';
        this.busy.set(false);

        return of(null);
      }),
    );
  }
}
