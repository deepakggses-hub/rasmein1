<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * A coupon applied to a cart belongs on the cart, not in the session.
 *
 * The cart itself is database-backed precisely so it survives a browser
 * restart; keeping the applied code in the session would mean the basket
 * persisted while the discount silently vanished.
 *
 * Only the CODE is stored. The discount value is never persisted here and is
 * recomputed from the coupons table at checkout, so an expired or exhausted
 * coupon cannot ride along on an old cart.
 */
class AddCouponToCarts extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('carts', [
            'coupon_code' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
                'null'       => true,
                'after'      => 'currency',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('carts', 'coupon_code');
    }
}
