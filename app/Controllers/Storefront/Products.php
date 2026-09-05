<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\ProductImageModel;
use App\Models\ProductModel;
use CodeIgniter\Exceptions\PageNotFoundException;

class Products extends StorefrontController
{
    public function show(string $slug, string $variantKey = ''): string
    {
        // $variantKey comes from the URL — product/{slug}/{key} — so a link to
        // one colour is shareable and the back button works.

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

        $variantModel = model(\App\Models\ProductVariantModel::class);
        $matrix       = $variantModel->matrixFor((int) $product->id);

        $chosen = $variantKey !== ''
            ? $variantModel->byKey((int) $product->id, $variantKey)
            : null;

        /*
         * An unknown key is not a 404. Links get shared after a variant is
         * retired, and the piece still exists — showing it with the default
         * selected is better than a dead end.
         */
        $chosen ??= $variantModel->pick($matrix['variants']);

        return $this->page('storefront/product', [
            'variants'      => $matrix['variants'],
            'variantGroups' => $matrix['groups'],
            'variant'       => $chosen,
            'chosen'        => $chosen === null ? null : $variantModel->resolve($chosen, $product),
            // Grouped by attribute, so the page reads "Colour: silver, gold"
            // rather than a flat list of unrelated words.
            'attributes' => model(\App\Models\AttributeValueModel::class)->grouped((int) $product->id),
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
