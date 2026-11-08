export interface ProductFilter {
  page: number;
  limit: number;
  name?: string;
  priceMin?: number | null;
  priceMax?: number | null;
}
