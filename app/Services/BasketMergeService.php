<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CartModel;
use App\Models\WishlistModel;

/**
 * Moves a guest's basket and wishlist into their account when they sign in.
 *
 * WHY THIS EXISTS
 *
 * Someone browses, adds three hampers, then registers at checkout. If signing
 * in swapped their basket for an empty one, they would have to start again —
 * and most would not. The basket has to follow them.
 *
 * WHAT "MERGE" MEANS HERE
 *
 *  - Wishlist: a union. A saved item is a bookmark; having it twice is
 *    meaningless, and losing one because it was saved on another device is
 *    worse than keeping both.
 *  - Cart: quantities are REPLACED, not added. If the account already had two
 *    of something and the guest basket has one, the answer is one — the number
 *    the person last chose while looking at the product. Adding them would
 *    silently make it three, which nobody asked for.
 *  - Lines only in the account are kept. Signing in on a new device should not
 *    empty a basket built on another.
 */
class BasketMergeService
{
    /**
     * @return array{cart: int, wishlist: int} How many lines moved
     */
    public function adopt(int $customerId): array
    {
        $token = service('visitor')->peek();

        $moved = [
            'cart'     => $this->mergeCart($customerId, $token),
            'wishlist' => $this->mergeWishlist($customerId, $token),
        ];

        /*
         * A fresh token afterwards. The guest rows now belong to the account, so
         * the old one points at nothing — and rotating means a token that leaked
         * (a shared computer, a copied cookie) cannot be replayed to read what
         * the account does next.
         */
        if ($token !== null) {
            service('visitor')->rotate();
        }

        return $moved;
    }

    // -----------------------------------------------------------------

    private function mergeCart(int $customerId, ?string $token): int
    {
        $model = model(CartModel::class);
        $db    = db_connect();

        // The guest cart: whichever this browser was using, by session or token.
        $guest = $this->guestCart($token);

        if ($guest === null) {
            return 0;
        }

        $owned = $model->findActiveForCustomer($customerId);

        // No account cart yet — the guest cart simply becomes theirs.
        if ($owned === null) {
            $model->update((int) $guest['id'], [
                'id'            => (int) $guest['id'],
                'customer_id'   => $customerId,
                'visitor_token' => null,
            ]);

            return (int) $db->table('cart_items')->where('cart_id', $guest['id'])->countAllResults();
        }

        if ((int) $owned['id'] === (int) $guest['id']) {
            return 0;
        }

        $moved = 0;

        foreach ($db->table('cart_items')->where('cart_id', $guest['id'])->get()->getResultArray() as $line) {
            $match = $db->table('cart_items')
                ->where('cart_id', $owned['id'])
                ->where('product_id', $line['product_id'])
                // A configured gift box is not the same line as a loose product
                // of the same id, so the box has to match too.
                ->where('gift_box_id', $line['gift_box_id'])
                ->get()->getRowArray();

            if ($match === null) {
                unset($line['id']);
                $line['cart_id'] = $owned['id'];
                $db->table('cart_items')->insert($line);
            } else {
                // Replace, don't add — see the note at the top of this class.
                $db->table('cart_items')->where('id', $match['id'])->update([
                    'quantity'   => $line['quantity'],
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            $moved++;
        }

        // The guest cart is emptied and closed rather than deleted, so an order
        // that referenced it still resolves.
        $db->table('cart_items')->where('cart_id', $guest['id'])->delete();
        $model->update((int) $guest['id'], ['id' => (int) $guest['id'], 'status' => 'merged']);

        session()->set('cart_uuid', $owned['uuid']);

        return $moved;
    }

    private function mergeWishlist(int $customerId, ?string $token): int
    {
        if ($token === null) {
            return 0;
        }

        $db    = db_connect();
        $moved = 0;

        foreach ($db->table('wishlist_items')
            ->where('visitor_token', $token)
            ->where('customer_id', null)
            ->get()->getResultArray() as $row) {
            $exists = $db->table('wishlist_items')
                ->where('customer_id', $customerId)
                ->where('product_id', $row['product_id'])
                ->countAllResults() > 0;

            if ($exists) {
                // Already saved on the account — drop the guest copy rather than
                // trip the unique index.
                $db->table('wishlist_items')->where('id', $row['id'])->delete();

                continue;
            }

            $db->table('wishlist_items')->where('id', $row['id'])->update([
                'customer_id'   => $customerId,
                'visitor_token' => null,
            ]);

            $moved++;
        }

        return $moved;
    }

    /** @return array<string, mixed>|null */
    private function guestCart(?string $token): ?array
    {
        $model = model(CartModel::class);
        $uuid  = session()->get('cart_uuid');

        if ($uuid !== null) {
            $cart = $model->findActiveByUuid((string) $uuid);

            // Only if it is genuinely unclaimed. A cart already belonging to
            // someone must never be adopted by whoever holds the session.
            if ($cart !== null && $cart['customer_id'] === null) {
                return $cart;
            }
        }

        if ($token === null) {
            return null;
        }

        return $model->where('visitor_token', $token)
            ->where('customer_id', null)
            ->where('status', 'active')
            ->orderBy('id', 'DESC')
            ->first();
    }
}
