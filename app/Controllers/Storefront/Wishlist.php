<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\ProductModel;
use App\Models\WishlistModel;

/**
 * The wishlist, for anyone — signed in or not.
 *
 * It used to live inside the account area, which meant the heart on a product
 * card did nothing at all until you had registered. That is backwards: saving
 * things is exactly what a visitor does BEFORE deciding to make an account, and
 * a collection they have already built is the best reason to make one.
 *
 * A guest's saves are keyed to a long-lived httpOnly cookie (see
 * VisitorService) and are adopted into the account on sign-in.
 */
class Wishlist extends StorefrontController
{
    public function index()
    {
        $customerId = session('customer_id') !== null ? (int) session('customer_id') : null;
        $token      = service('visitor')->peek();

        $ids = array_column(
            model(WishlistModel::class)->forViewer($customerId, $token)
                ->select('product_id')->orderBy('id', 'DESC')->findAll(),
            'product_id'
        );

        $products = $ids === []
            ? []
            : model(ProductModel::class)->withPrimaryImage()->scopeVisible()
                ->whereIn('products.id', $ids)->findAll();

        return $this->page('storefront/wishlist', [
            'products' => $products,
            'isGuest'  => $customerId === null,
        ], ['title' => 'Your wishlist · ' . $this->brand->brandName, 'noindex' => true]);
    }

    public function toggle()
    {
        $productId = (int) $this->request->getPost('product_id');
        $product   = $productId > 0 ? model(ProductModel::class)->find($productId) : null;

        if ($product === null) {
            return redirect()->back()->with('error', 'That product is not available.');
        }

        $customerId = session('customer_id') !== null ? (int) session('customer_id') : null;

        // Mint a token only NOW — when something is actually being saved.
        // Issuing a cookie to every passer-by is rude and pointless.
        $token = $customerId === null ? service('visitor')->token() : null;

        $model    = model(WishlistModel::class);
        $existing = $model->forViewer($customerId, $token)->where('product_id', $productId)->first();

        if ($existing !== null) {
            $model->delete($existing['id']);
            $added = false;
        } else {
            $added = $model->saveFor($customerId, $token, $productId);
        }

        $target = (string) ($this->request->getPost('return_to') ?? '');
        $safe   = $target !== '' && ! preg_match('#^[a-z]+://#i', $target) && ! str_starts_with($target, '//')
            ? site_url(ltrim($target, '/'))
            : site_url('wishlist');

        return redirect()->to($safe)->with(
            'success',
            $added
                ? $product->name . ' saved.' . ($customerId === null ? ' Sign in to keep it on your account.' : '')
                : $product->name . ' removed.'
        );
    }

    /**
     * The same toggle, answered as JSON.
     *
     * The heart on a product card should not reload the page — a listing is
     * something people skim, and losing their scroll position to save one thing
     * is a poor trade. The non-JS form POST is still there and still works;
     * this is the enhanced path.
     *
     * A guest is told to sign in RATHER than silently refused. The item is still
     * saved against their visitor token, so nothing is lost if they do — but the
     * prompt is what turns a saved item into an account.
     */
    public function toggleJson()
    {
        $productId = (int) $this->request->getPost('product_id');
        $product   = $productId > 0 ? model(ProductModel::class)->find($productId) : null;

        if ($product === null) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'That product is not available.']);
        }

        $customerId = session('customer_id') !== null ? (int) session('customer_id') : null;
        $token      = $customerId === null ? service('visitor')->token() : null;

        $model    = model(WishlistModel::class);
        $existing = $model->forViewer($customerId, $token)->where('product_id', $productId)->first();

        if ($existing !== null) {
            $model->delete($existing['id']);
            $saved = false;
        } else {
            $model->saveFor($customerId, $token, $productId);
            $saved = true;
        }

        return $this->response->setJSON([
            'ok'      => true,
            'saved'   => $saved,
            'count'   => $model->forViewer($customerId, $token)->countAllResults(),
            'name'    => $product->name,
            // The panel only appears for a guest who has just SAVED something —
            // prompting on a removal would be nagging.
            'prompt'  => $customerId === null && $saved,
            // CI4 rotates the token on every POST, so the form needs the new
            // one or the SECOND tap is rejected as a forgery.
            'csrf'    => csrf_hash(),
        ]);
    }
}
