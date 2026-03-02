export interface Item {
  id: number;
  added_at: string;
  product_data: {
    id: number;
    name: string;
    sku: string;
    price: number;
    stock: number | null;
    image?: string;
    main_image?: string;
  };
  product_id: number;
  quantity: number;
  shelf_id: number;
}

export interface Local {
  id: number;
  name: string;
  description: string | null;
  item_count: number;
  created_at: string;
  updated_at: string;
  created_by: number;
}
