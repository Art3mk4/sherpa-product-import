import { Component, OnInit, inject } from '@angular/core';
import { CommonModule } from '@angular/common';
import { ReactiveFormsModule, NonNullableFormBuilder, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { Store } from '@ngrx/store';
import { combineLatest, Observable } from 'rxjs';
import { MatCard, MatCardTitle, MatCardContent } from '@angular/material/card';
import { MatFormField, MatLabel } from '@angular/material/form-field';
import { MatInput } from '@angular/material/input';
import { MatButton } from '@angular/material/button';
import { MatProgressBar } from '@angular/material/progress-bar';
import { MatPaginator, PageEvent } from '@angular/material/paginator';
import { MatTable, MatTableModule } from '@angular/material/table';
import { ProductListItem } from '../../models/product';
import { ProductFilter } from '../../models/product-filter';
import { PaginationMeta } from '../../models/paginated';
import { loadProducts } from '../../store/products/products.actions';
import {
  selectAllProducts,
  selectProductsError,
  selectProductsLoading,
  selectProductsMeta,
} from '../../store/products/products.selectors';

interface ListVm {
  items: ProductListItem[];
  meta: PaginationMeta;
  loading: boolean;
  error: string | null;
}

/**
 * Компонент держит только view-model из store (async pipe, без .subscribe()).
 * Форма фильтров — источник правды для фильтра; страница/лимит приходят из
 * пагинатора и последнего meta.
 */
@Component({
  selector: 'app-product-list',
  imports: [
    CommonModule,
    ReactiveFormsModule,
    RouterLink,
    MatCard,
    MatCardTitle,
    MatCardContent,
    MatFormField,
    MatLabel,
    MatInput,
    MatButton,
    MatProgressBar,
    MatPaginator,
    MatTableModule,
  ],
  templateUrl: './product-list.component.html',
})
export class ProductListComponent implements OnInit {
  private readonly store = inject(Store);
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly router = inject(Router);

  readonly displayedColumns = ['image', 'name', 'externalCode', 'price', 'discount'];

  readonly filterForm = this.fb.group({
    name: [''],
    priceMin: [null as number | null, [Validators.min(0)]],
    priceMax: [null as number | null, [Validators.min(0)]],
  });

  rangeError = false;

  /** Последний выбранный размер страницы — чтобы фильтр не сбрасывал его */
  private lastLimit = 20;

  /** URL картинок, которые не загрузились (внешние источники могут быть недоступны) */
  readonly imgError = new Map<string, boolean>();

  readonly vm$: Observable<ListVm> = combineLatest({
    items: this.store.select(selectAllProducts),
    meta: this.store.select(selectProductsMeta),
    loading: this.store.select(selectProductsLoading),
    error: this.store.select(selectProductsError),
  });

  ngOnInit(): void {
    this.store.dispatch(loadProducts({ filter: { page: 1, limit: 20 } }));
  }

  applyFilter(): void {
    const { name, priceMin, priceMax } = this.filterForm.getRawValue();
    this.rangeError = priceMin != null && priceMax != null && priceMin > priceMax;
    if (this.rangeError) {
      return;
    }

    this.store.dispatch(loadProducts({
      filter: { page: 1, limit: this.lastLimit, name, priceMin, priceMax },
    }));
  }

  resetFilter(): void {
    this.filterForm.reset({ name: '', priceMin: null, priceMax: null });
    this.rangeError = false;
    this.store.dispatch(loadProducts({ filter: { page: 1, limit: this.lastLimit } }));
  }

  onPage(event: PageEvent): void {
    this.lastLimit = event.pageSize;
    const { name, priceMin, priceMax } = this.filterForm.getRawValue();
    const filter: ProductFilter = {
      page: event.pageIndex + 1,
      limit: event.pageSize,
      name,
      priceMin,
      priceMax,
    };

    this.store.dispatch(loadProducts({ filter }));
  }

  openProduct(product: ProductListItem): void {
    void this.router.navigate(['/products', product.id]);
  }
}
