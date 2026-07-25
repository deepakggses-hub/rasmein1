<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\ProductImageModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Products extends StorefrontController
{
    public function show(string $slug): string
    {
        $model   = model(ProductModel::class);
        $product = $model->findVisibleBySlug($slug);

        if ($product === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $category = $product->category_id !== null
            ? model(\App\Models\CategoryModel::class)->find($product->category_id)
            : null;

        $crumbs = [['label' => 'Shop', 'url' => site_url('shop')]];

        if ($category !== null) {
            $crumbs[] = ['label' => $category->name, 'url' => $category->url()];
        }

        $crumbs[] = ['label' => $product->name, 'url' => null];

        return $this->page('storefront/product', [
            'product'  => $product,
            'images'   => model(ProductImageModel::class)->forProduct($product->id),
            'category' => $category,
            'related'  => $model->related($product, 4),
            'crumbs'   => $crumbs,
        ], [
            'title'       => ($product->meta_title ?: $product->name) . ' · ' . $this->brand->brandName,
            'description' => rs_excerpt($product->meta_description ?: $product->short_description, 155),
            'image'       => $product->imageUrl(),
        ]);
    }
}
