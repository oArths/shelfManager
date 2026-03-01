export interface Item {
  product_id: string;
  product_data: string;
  product_name: string;
  product_sku: string;
  quantity: string;
  shelf_id: string;
  added_at: string;
  main_image: string;
  id: string;
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
