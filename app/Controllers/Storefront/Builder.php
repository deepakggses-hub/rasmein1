<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Steps 2–4: fill the box, personalise it, review it.
 *
 * Every change is a POST that redirects back, so the page is refresh-safe and
 * works with JavaScript disabled. The Tray re-renders server-side from the
 * authoritative contents — what you see is what the database holds.
 *
 * The box being built IS a cart line (see GiftBoxBuilderService), so nothing
 * needs converting when it is ordered, and it survives a closed browser.
 */
class Builder extends StorefrontController
{
    /** /build — no box chosen yet, so send them to step 1. */
    public function index()
    {
        return redirect()->to(site_url('gift-boxes'));
    }

    /** /build/{slug} — start or resume a box of this design. */
    public function start(string $slug)
    {
        $result = service('builder')->startOrResume($slug);

        if (! $result['ok']) {
            return redirect()->to(site_url('gift-boxes'))->with('error', $result['message']);
        }

        return redirect()->to(site_url('build/box/' . $result['line_id']));
    }

    /** /build/box/{lineId} — the builder itself. */
    public function show(int $lineId): string
    {
        $state = service('builder')->state($lineId);

        // A line id belonging to someone else resolves to null, so this is the
        // access control as well as the not-found case.
        if ($state === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $box = $state['box'];

        return $this->page('storefront/builder', [
            'state'  => $state,
            'crumbs' => [
                ['label' => 'Gift boxes', 'url' => site_url('gift-boxes')],
                ['label' => $box->name, 'url' => null],
            ],
        ], [
            'title'   => 'Filling your ' . $box->name . ' · ' . $this->brand->brandName,
            'noindex' => true,
        ]);
    }

    public function add(int $lineId)
    {
        $productId = (int) $this->request->getPost('product_id');
        $quantity  = (int) ($this->request->getPost('quantity') ?? 1);

        return $this->back($lineId, service('builder')->addProduct($lineId, $productId, $quantity));
    }

    public function setQuantity(int $lineId)
    {
        $productId = (int) $this->request->getPost('product_id');
        $quantity  = (int) $this->request->getPost('quantity');

        return $this->back($lineId, service('builder')->setProductQuantity($lineId, $productId, $quantity));
    }

    public function remove(int $lineId)
    {
        $productId = (int) $this->request->getPost('product_id');

        return $this->back($lineId, service('builder')->removeProduct($lineId, $productId));
    }

    public function clear(int $lineId)
    {
        return $this->back($lineId, service('builder')->clear($lineId));
    }

    public function discard(int $lineId)
    {
        $result = service('builder')->discard($lineId);

        return redirect()->to(site_url('gift-boxes'))
            ->with($result['ok'] ? 'success' : 'error', $result['ok'] ? 'Box discarded.' : $result['message']);
    }

    /** Step 3 — the optional gift message and special request. */
    public function personalise(int $lineId)
    {
        $result = service('builder')->personalise(
            $lineId,
            $this->request->getPost('gift_recipient'),
            $this->request->getPost('gift_message'),
            $this->request->getPost('special_note')
        );

        return $this->back($lineId, $result);
    }

    /** Step 4 — how many of this box, then off to the cart. */
    public function finish(int $lineId)
    {
        $state = service('builder')->state($lineId);

        if ($state === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $quantity = (int) ($this->request->getPost('quantity') ?? 1);

        if ($quantity > 1) {
            service('builder')->setQuantity($lineId, $quantity);
        }

        if (! $state['is_complete']) {
            return redirect()->to(site_url('build/box/' . $lineId))->with(
                'error',
                $state['slots_used'] === 0
                    ? 'Add something to the box first.'
                    : 'This box needs at least ' . $state['min_slots'] . ' compartments filled.'
            );
        }

        return redirect()->to(site_url('cart'))
            ->with('success', $state['box']->name . ' added to your ' . rs_cta_label(null, 'cart') . '.');
    }

    /**
     * @param array{ok: bool, message: string} $result
     */
    private function back(int $lineId, array $result)
    {
        if ($this->request->isAJAX()) {
            return $result['ok']
                ? $this->jsonOk(service('builder')->state($lineId) ?? [], $result['message'])
                : $this->jsonFail($result['message'], 422);
        }

        $redirect = redirect()->to(site_url('build/box/' . $lineId));

        return $result['message'] === ''
            ? $redirect
            : $redirect->with($result['ok'] ? 'success' : 'error', $result['message']);
    }
}
