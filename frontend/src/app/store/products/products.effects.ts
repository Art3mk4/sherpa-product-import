import { Injectable, inject } from '@angular/core';
import { Actions, createEffect, ofType } from '@ngrx/effects';
import { catchError, map, of, switchMap } from 'rxjs';
import { ProductService } from '../../core/services/product.service';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

@Injectable()
export class ProductsEffects {
  private readonly actions$ = inject(Actions);
  private readonly productService = inject(ProductService);

  load$ = createEffect(() =>
    this.actions$.pipe(
      ofType(loadProducts),
      // switchMap отменяет устаревший запрос при быстром вводе в поиск
      switchMap(({ filter }) =>
        this.productService.list(filter).pipe(
          map(result => loadProductsSuccess({ items: result.data, meta: result.meta })),
          // catchError строго ВНУТРИ switchMap — иначе после первой ошибки effect умирает
          catchError(error => {
            const message = error?.error?.error?.message ?? 'Не удалось загрузить товары';
            return of(loadProductsFailure({ error: message }));
          }),
        ),
      ),
    ),
  );
}
