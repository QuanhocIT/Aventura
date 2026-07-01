import { z } from 'zod';

// Mirrors the server-side rules in App\Http\Requests\Products\StoreProductRequest /
// UpdateProductRequest — keep both in sync when either side's constraints change.
export const productSchema = z.object({
    category_id: z.string().min(1, 'Vui lòng chọn danh mục.'),
    name: z.string().min(1, 'Vui lòng nhập tên sản phẩm.').max(255, 'Tên sản phẩm quá dài.'),
    price: z.coerce.number({ message: 'Giá phải là số.' }).min(0, 'Giá không được âm.'),
    description: z.string().max(2000, 'Mô tả quá dài.').optional().nullable(),
    image: z.instanceof(File).nullable().optional(),
});

export type ProductFormData = z.infer<typeof productSchema>;
