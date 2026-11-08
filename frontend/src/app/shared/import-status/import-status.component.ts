import { Component, input } from '@angular/core';
import { DecimalPipe } from '@angular/common';
import { MatProgressBar } from '@angular/material/progress-bar';
import { MatChip } from '@angular/material/chips';
import {
  MatExpansionPanel,
  MatExpansionPanelHeader,
  MatExpansionPanelTitle,
  MatExpansionPanelDescription,
} from '@angular/material/expansion';
import { RouterLink } from '@angular/router';
import { ImportJob, ImportStatus } from '../../models/import-job';

/** Индикатор прогресса + счётчики + отчёт об ошибках/предупреждениях задачи. */
@Component({
  selector: 'app-import-status',
  imports: [
    DecimalPipe,
    MatProgressBar,
    MatChip,
    MatExpansionPanel,
    MatExpansionPanelHeader,
    MatExpansionPanelTitle,
    MatExpansionPanelDescription,
    RouterLink,
  ],
  templateUrl: './import-status.component.html',
})
export class ImportStatusComponent {
  readonly job = input<ImportJob | null>(null);

  readonly statusLabels: Record<ImportStatus, string> = {
    pending: 'В очереди',
    processing: 'Идёт импорт',
    completed: 'Завершён',
    failed: 'Ошибка',
  };

  readonly statusClasses: Record<ImportStatus, string> = {
    pending: 'status-pending',
    processing: 'status-processing',
    completed: 'status-completed',
    failed: 'status-failed',
  };
}
