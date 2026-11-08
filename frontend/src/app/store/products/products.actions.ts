import { createAction, props } from '@ngrx/store';
import { PaginationMeta } from '../../models/paginated';
import { ProductListItem } from '../../models/product';
import { ProductFilter } from '../../models/product-filter';

export const loadProducts = createAction(
  '[Products] Load',
  props<{ filter: ProductFilter }>(),
);

export const loadProductsSuccess = createAction(
  '[Products] Load Success',
  props<{ items: ProductListItem[]; meta: PaginationMeta }>(),
);

export const loadProductsFailure = createAction(
  '[Products] Load Failure',
  props<{ error: string }>(),
);
