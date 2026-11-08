import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { Paginated } from '../../models/paginated';
import { Product, ProductListItem } from '../../models/product';
import { ProductFilter } from '../../models/product-filter';

@Injectable({ providedIn: 'root' })
export class ProductService {
  constructor(private readonly http: HttpClient) {}

  list(filter: ProductFilter): Observable<Paginated<ProductListItem>> {
    // Параметры — только из непустых значений, иначе backend получит price_min=
    // и ответит 422.
    let params = new HttpParams()
      .set('page', filter.page)
      .set('limit', filter.limit);

    const name = filter.name?.trim();
    if (name) {
      params = params.set('name', name);
    }
    if (filter.priceMin != null) {
      params = params.set('price_min', filter.priceMin);
    }
    if (filter.priceMax != null) {
      params = params.set('price_max', filter.priceMax);
    }

    return this.http.get<Paginated<ProductListItem>>('/api/products', { params });
  }

  get(id: number): Observable<Product> {
    return this.http.get<Product>(`/api/products/${id}`);
  }
}
