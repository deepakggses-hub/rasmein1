<?php

declare(strict_types=1);

namespace App\Models;

use CodeIgniter\Model;

class WishlistModel extends Model
{
    protected $table         = 'wishlist_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = ['customer_id', 'visitor_token', 'product_id'];

    /*
     * customer_id is permit_empty because a row belongs to EITHER a signed-in
     * customer or a guest's visitor token. Requiring it here was what silently
     * rejected every guest save — the insert returned false and nothing said
     * why, because the model's errors were never read.
     *
     * saveFor() refuses when both are absent, which is the rule that actually
     * matters.
     */
    protected $validationRules = [
        'customer_id'   => 'permit_empty|is_natural_no_zero',
        'visitor_token' => 'permit_empty|exact_length[64]|alpha_numeric',
        'product_id'    => 'required|is_natural_no_zero',
    ];





    /** @return list<int> */
    public function productIds(int $customerId): array
    {
        return array_map(
            static fn (array $r): int => (int) $r['product_id'],
            $this->select('product_id')->where('customer_id', $customerId)->findAll()
        );
    }

    /**
     * Scope a query to whoever is asking — a signed-in customer, or a guest's
     * visitor token.
     *
     * ONE place decides this. Scattering `customer_id ?? token` through the
     * controllers is how a query eventually forgets the token half and shows one
     * visitor another's saved items.
     */
    public function forViewer(?int $customerId, ?string $token): self
    {
        if ($customerId !== null) {
            return $this->where('customer_id', $customerId);
        }

        // No customer and no token: match nothing rather than everything.
        return $this->where('visitor_token', $token ?? '__none__')
            ->where('customer_id', null);
    }

    /**
     * Save a product for the current viewer. Returns false if it was already
     * saved, so the caller can report "removed" versus "added" honestly.
     */
    public function saveFor(?int $customerId, ?string $token, int $productId): bool
    {
        if ($customerId === null && $token === null) {
            return false;
        }

        $existing = $this->forViewer($customerId, $token)
            ->where('product_id', $productId)
            ->countAllResults() > 0;

        if ($existing) {
            return false;
        }

        return (bool) $this->insert([
            'customer_id'   => $customerId,
            'visitor_token' => $customerId === null ? $token : null,
            'product_id'    => $productId,
        ]);
    }

    /**
     * Which of these products are already saved.
     *
     * One query for the whole page. Asking per card is an N+1, and a listing
     * can show fifty cards — the hearts would cost more than the products.
     *
     * @param list<int> $productIds
     *
     * @return list<int>
     */
    public function savedAmong(?int $customerId, ?string $token, array $productIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $productIds))));

        if ($ids === [] || ($customerId === null && $token === null)) {
            return [];
        }

        return array_map('intval', array_column(
            $this->forViewer($customerId, $token)->whereIn('product_id', $ids)
                ->select('product_id')->findAll(),
            'product_id'
        ));
    }
}
