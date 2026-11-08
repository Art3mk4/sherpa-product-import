import { createReducer, on } from '@ngrx/store';
import { PaginationMeta } from '../../models/paginated';
import { ProductListItem } from '../../models/product';
import { ProductFilter } from '../../models/product-filter';
import { loadProducts, loadProductsFailure, loadProductsSuccess } from './products.actions';

export interface ProductsState {
  items: ProductListItem[];
  meta: PaginationMeta;
  filter: ProductFilter;
  loading: boolean;
  error: string | null;
}

export const initialProductsState: ProductsState = {
  items: [],
  meta: { page: 1, limit: 20, total: 0, totalPages: 1 },
  filter: { page: 1, limit: 20 },
  loading: false,
  error: null,
};

export const productsReducer = createReducer(
  initialProductsState,
  on(loadProducts, (state, { filter }) => ({ ...state, filter, loading: true, error: null })),
  on(loadProductsSuccess, (state, { items, meta }) => ({ ...state, items, meta, loading: false })),
  on(loadProductsFailure, (state, { error }) => ({ ...state, loading: false, error })),
);
