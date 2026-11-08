import { Component, computed, inject, input, signal } from '@angular/core';
import { CommonModule } from '@angular/common';
import { RouterLink } from '@angular/router';
import { MatCard, MatCardTitle, MatCardContent } from '@angular/material/card';
import { MatChip, MatChipsModule } from '@angular/material/chips';
import {
  MatExpansionPanel,
  MatExpansionPanelHeader,
  MatExpansionPanelTitle,
  MatExpansionPanelDescription,
} from '@angular/material/expansion';
import { MatButtonModule } from '@angular/material/button';
import { toObservable } from '@angular/core/rxjs-interop';
import { Observable, catchError, of, switchMap } from 'rxjs';
import { ProductService } from '../../core/services/product.service';
import { ImportService } from '../../core/services/import.service';
import { ImportJob } from '../../models/import-job';
import { Product } from '../../models/product';
import { ImportStatusComponent } from '../../shared/import-status/import-status.component';

@Component({
  selector: 'app-product-card',
  imports: [
    CommonModule,
    RouterLink,
    MatCard,
    MatCardTitle,
    MatCardContent,
    MatChip,
    MatChipsModule,
    MatButtonModule,
    MatExpansionPanel,
    MatExpansionPanelHeader,
    MatExpansionPanelTitle,
    MatExpansionPanelDescription,
    ImportStatusComponent,
  ],
  templateUrl: './product-card.component.html',
})
export class ProductCardComponent {
  private readonly productService = inject(ProductService);
  private readonly importService = inject(ImportService);

  /** Привязан из маршрута через withComponentInputBinding */
  readonly id = input.required<string>();

  readonly product$: Observable<Product | null> = toObservable(this.id).pipe(
    switchMap(id => this.productService.get(Number(id))),
    catchError(() => of(null)),
  );

  /** Блок «Последний импорт» — требование ТЗ к карточке */
  readonly lastImport$: Observable<ImportJob | null> = this.importService.latest().pipe(
    switchMap(job => job.status === 'pending' || job.status === 'processing'
      ? this.importService.poll(job.id)
      : of(job)),
    catchError(() => of(null)),   // 404 «импортов не было» — просто скрываем блок
  );

  readonly selectedImage = signal(0);
  readonly imageFailed = signal<Set<string>>(new Set());

  readonly galleryIndex = computed(() => {
    const failed = this.imageFailed();
    return failed.size;
  });

  selectImage(index: number): void {
    this.selectedImage.set(index);
  }

  markFailed(url: string): void {
    this.imageFailed.update(set => new Set(set).add(url));
  }

  isFailed(url: string): boolean {
    return this.imageFailed().has(url);
  }
}
