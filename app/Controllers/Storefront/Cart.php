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

        // Only ever redirect within this site — a posted URL is not trusted.
        $safe = $target !== '' && ! preg_match('#^[a-z]+://#i', $target) && ! str_starts_with($target, '//')
            ? site_url(ltrim($target, '/'))
            : site_url('cart');

        return redirect()->to($safe)->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
