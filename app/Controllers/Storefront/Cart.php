<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

/**
 * The cart — or, in Enquire mode, the enquiry list. Same rows, same page,
 * different vocabulary and a different button at the end.
 *
 * Every mutation is a POST and every POST carries CSRF (applied globally by
 * HTTP method in Config\Filters). Each one redirects back rather than
 * rendering, so a refresh cannot resubmit.
 */
class Cart extends StorefrontController
{
    public function show(): string
    {
        $snapshot = service('cart')->snapshot();

        return $this->page('storefront/cart', [
            'snapshot' => $snapshot,
            'crumbs'   => [['label' => rs_cta_label(null, 'cart'), 'url' => null]],
        ], [
            'title'   => rs_cta_label(null, 'cart') . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    public function add()
    {
        // The chosen colour rides along with the line, so the order records it.
        $chosen = service('cart')->describeChoices(
            (int) $this->request->getPost('product_id'),
            (array) $this->request->getPost('attr')
        );

        service('cart')->pendingChoices = $chosen;

        // The chosen variant, validated against the product — a posted id must
        // not be able to attach someone else's variant to this line.
        $variantId = (int) $this->request->getPost('variant_id');

        service('cart')->pendingVariant = $variantId > 0
            && model(\App\Models\ProductVariantModel::class)
                ->where('id', $variantId)
                ->where('product_id', (int) $this->request->getPost('product_id'))
                ->countAllResults() > 0
            ? $variantId
            : null;

        $productId = (int) $this->request->getPost('product_id');
        $quantity  = (int) ($this->request->getPost('quantity') ?? 1);

        if ($productId <= 0) {
            return $this->back(['ok' => false, 'message' => 'Nothing to add.']);
        }

        return $this->back(service('cart')->addProduct($productId, $quantity));
    }

    public function update()
    {
        $lineId   = (int) $this->request->getPost('line_id');
        $quantity = (int) $this->request->getPost('quantity');

        if ($lineId <= 0) {
            return $this->back(['ok' => false, 'message' => 'That item could not be found.']);
        }

        return $this->back(service('cart')->updateQuantity($lineId, $quantity));
    }

    public function remove()
    {
        $lineId = (int) $this->request->getPost('line_id');

        if ($lineId <= 0) {
            return $this->back(['ok' => false, 'message' => 'That item could not be found.']);
        }

        return $this->back(service('cart')->removeLine($lineId));
    }

    public function applyCoupon()
    {
        $code = (string) ($this->request->getPost('code') ?? '');

        return $this->back(service('cart')->applyCoupon($code));
    }

    public function removeCoupon()
    {
        service('cart')->removeCoupon();

        return $this->back(['ok' => true, 'message' => 'Coupon removed.']);
    }

    /**
     * Redirect back to wherever the action came from, carrying a flash message.
     * Returns JSON instead when the caller asked for it.
     *
     * @param array{ok: bool, message: string} $result
     */
    private function back(array $result)
    {
        if ($this->request->isAJAX()) {
            return $result['ok']
                ? $this->jsonOk(['count' => service('cart')->itemCount()], $result['message'])
                : $this->jsonFail($result['message'], 422);
        }

        $target = (string) ($this->request->getPost('return_to') ?? '');

        /*
         * "Buy now" skips the basket. It is a separate submit button on the same
         * form rather than a second form, so the quantity the person chose
         * carries over — and it must actually behave differently, or the button
         * is a lie.
         */
        if ($this->request->getPost('checkout') !== null) {
            $target = 'checkout';
        }

        // Only ever redirect within this site — a posted URL is not trusted.
        $safe = $target !== '' && ! preg_match('#^[a-z]+://#i', $target) && ! str_starts_with($target, '//')
            ? site_url(ltrim($target, '/'))
            : site_url('cart');

        return redirect()->to($safe)->with($result['ok'] ? 'success' : 'error', $result['message']);
    }

    /**
     * Add or set a quantity, answered as JSON.
     *
     * The card's stepper needs to change a quantity without losing the page.
     * `quantity` is the ABSOLUTE number wanted, not a delta — a delta sent twice
     * because a tap was slow gives two increments, and the customer ends up with
     * three of something they wanted one of.
     */
    public function addJson()
    {
        /*
         * The chosen variant, validated against the product.
         *
         * This is the path the product page actually posts to, and it was
         * dropping the variant entirely — so 25 of 29 cart lines carried NULL
         * and every one of them was priced from the base product.
         */
        $variantId = (int) $this->request->getPost('variant_id');

        if ($variantId > 0 && model(\App\Models\ProductVariantModel::class)
            ->where('id', $variantId)
            ->where('product_id', (int) $this->request->getPost('product_id'))
            ->where('is_active', 1)
            ->countAllResults() === 0) {
            // A posted id that does not belong to this product is ignored
            // rather than trusted.
            $variantId = 0;
        }

        $productId = (int) $this->request->getPost('product_id');
        $quantity  = (int) $this->request->getPost('quantity');

        $product = $productId > 0 ? model(\App\Models\ProductModel::class)->find($productId) : null;

        if ($product === null || ! $product->inStock()) {
            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => 'That is not available right now.', 'csrf' => csrf_hash()]);
        }

        try {
            if ($quantity < 1) {
                service('cart')->setProductQuantity($productId, 0, $variantId ?: null);
                $quantity = 0;
            } else {
                $result = service('cart')->setProductQuantity($productId, $quantity, $variantId ?: null);

                if (($result['ok'] ?? true) === false) {
                    return $this->response->setStatusCode(422)->setJSON([
                        'ok' => false, 'error' => $result['error'] ?? 'That could not be added.',
                        'csrf' => csrf_hash(),
                    ]);
                }

                // The service may cap it — stock, or the per-basket limit — so
                // report what actually landed rather than what was asked for.
                $quantity = service('cart')->quantityOf($productId);
            }
        } catch (\Throwable $e) {
            log_message('error', 'Cart JSON failed: {m}', ['m' => $e->getMessage()]);

            return $this->response->setStatusCode(422)
                ->setJSON(['ok' => false, 'error' => 'That could not be added.', 'csrf' => csrf_hash()]);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'quantity' => $quantity,
            'count'    => service('cart')->itemCount(),
            'name'     => $product->name,
            // Rotated on every POST — the page needs the new one or the next
            // tap is rejected as a forgery.
            'csrf'     => csrf_hash(),
        ]);
    }

    /**
     * The drawer's contents, rendered server-side.
     *
     * A fragment, not JSON: the totals come from PricingService, and rebuilding
     * coupon and shipping arithmetic in the browser is a second implementation
     * that eventually disagrees with the first.
     */
    public function drawer()
    {
        return $this->response->setBody(
            view('partials/cart_drawer', ['snapshot' => service('cart')->snapshot()])
        );
    }
}
