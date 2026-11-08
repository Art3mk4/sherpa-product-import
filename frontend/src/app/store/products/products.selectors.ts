import { createFeatureSelector, createSelector } from '@ngrx/store';
import { ProductsState } from './products.reducer';

export const selectProductsState = createFeatureSelector<ProductsState>('products');

export const selectAllProducts = createSelector(
  selectProductsState,
  state => state.items,
);

export const selectProductsLoading = createSelector(
  selectProductsState,
  state => state.loading,
);

export const selectProductsMeta = createSelector(
  selectProductsState,
  state => state.meta,
);

export const selectTotalPages = createSelector(
  selectProductsState,
  state => state.meta.totalPages,
);

export const selectProductsFilter = createSelector(
  selectProductsState,
  state => state.filter,
);

export const selectProductsError = createSelector(
  selectProductsState,
  state => state.error,
);
