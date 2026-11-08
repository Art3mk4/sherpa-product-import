import { ProductAttribute } from './product-attribute';
import { ProductImage } from './product-image';

export interface ProductListItem {
  id: number;
  externalCode: string;
  name: string;
  price: number;
  discount: number | null;
  image: string | null;
}

export interface Product extends Omit<ProductListItem, 'image'> {
  description: string | null;
  createdAt: string;
  updatedAt: string;
  attributes: ProductAttribute[];
  images: ProductImage[];
}
