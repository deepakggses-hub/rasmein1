<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CategoryModel;
use App\Models\CollectionModel;

/**
 * The single owner of what lives at the site root.
 *
 * Categories and occasions both want addresses like /teas-infusions and
 * /diwali. Two things claiming the same namespace need ONE authority on
 * whether a name is free — the alternative is a check in each admin
 * controller, and the moment those two copies disagree a shop ends up with a
 * category it can reach and an occasion it cannot, or vice versa, with no
 * error anywhere to explain it.
 *
 * Everything that assigns or resolves a root URL goes through here.
 */
class RootUrlService
{
    /**
     * Segments that can never be claimed, because a route or a real file
     * already answers on them.
     *
     * Parsed out of the routes file with PHP's own tokeniser. The obvious
     * approach — asking the router — returns an EMPTY collection in both CLI
     * and web contexts, so the check silently passed everything and a category
     * called "Cart" saved at /cart. Regex over the source then broke on the
     * quote characters. The tokeniser needs no bootstrapping and cannot be
     * tripped by escaping.
     *
     * Deliberately over-inclusive: a controller name or filter string lands in
     * the list too. That only reserves MORE names, which fails safe.
     *
     * @return list<string>
     */
    public function reserved(): array
    {
        static $reserved = null;

        if ($reserved !== null) {
            return $reserved;
        }

        $found = [
            'admin', 'api', 'assets', 'uploads', 'writable', 'system', 'vendor',
            'index.php', 'favicon.ico', 'robots.txt', 'sitemap.xml',
        ];

        $file = APPPATH . 'Config/Routes.php';

        if (is_file($file)) {
            foreach (token_get_all((string) file_get_contents($file)) as $token) {
                if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                    continue;
                }

                $literal = trim($token[1], '"\'');
                $first   = explode('/', trim($literal, '/'))[0] ?? '';

                if ($first !== '' && preg_match('/^[a-z0-9][a-z0-9-]*$/', $first) === 1) {
                    $found[] = $first;
                }
            }
        } else {
            log_message('critical', 'Routes file missing; root URL collision checks are degraded.');
        }

        foreach ((array) glob(FCPATH . '*') as $entry) {
            $name = basename((string) $entry);

            if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) === 1) {
                $found[] = $name;
            }
        }

        return $reserved = array_values(array_unique($found));
    }

    /**
     * Why this root slug cannot be used, or null when it is free.
     *
     * @param string   $kind   'category' or 'occasion' — the thing asking
     * @param int|null $exceptId Ignore this row, so saving something under its
     *                           own existing slug is not a clash with itself
     */
    public function whyUnavailable(string $slug, string $kind, ?int $exceptId = null): ?string
    {
        $slug = trim($slug, '/');

        if ($slug === '') {
            return 'That produces an empty web address.';
        }

        if (in_array($slug, $this->reserved(), true)) {
            return '"' . $slug . '" is already used by a page on the site.';
        }

        // A top-level category holds the first segment of its path.
        $category = model(CategoryModel::class)
            ->where('path', $slug)
            ->where('parent_id', null);

        if ($kind === 'category' && $exceptId !== null) {
            $category->where('id !=', $exceptId);
        }

        if ($category->countAllResults() > 0) {
            return 'The category "' . $slug . '" already sits at that address.';
        }

        $occasion = model(CollectionModel::class)
            ->where('slug', $slug)
            ->where('type', 'occasion');

        if ($kind === 'occasion' && $exceptId !== null) {
            $occasion->where('id !=', $exceptId);
        }

        if ($occasion->countAllResults() > 0) {
            return 'The occasion "' . $slug . '" already sits at that address.';
        }

        return null;
    }

    /**
     * What lives at a root path, if anything.
     *
     * Categories are tried first because their paths can be several segments
     * deep, and an occasion is always a single segment — so a multi-segment
     * path can only ever be a category, and a single segment is unambiguous
     * once whyUnavailable() has kept them from colliding.
     *
     * NOTE the two shapes: CategoryModel returns entities, CollectionModel
     * returns arrays. Rather than paper over that here, each caller handles the
     * shape it asked for — pretending they are the same is how a TypeError
     * reaches a customer.
     *
     * @return array{kind: string, entity: object|array<string, mixed>}|null
     */
    public function resolve(string $path): ?array
    {
        $path = trim($path, '/');

        if ($path === '') {
            return null;
        }

        $category = model(CategoryModel::class)->findByPath($path);

        if ($category !== null) {
            return ['kind' => 'category', 'entity' => $category];
        }

        // Only a single segment can be an occasion.
        if (! str_contains($path, '/')) {
            $occasion = model(CollectionModel::class)
                ->where('slug', $path)
                ->where('type', 'occasion')
                ->where('is_active', 1)
                ->first();

            if ($occasion !== null && $this->isRunning($occasion)) {
                return ['kind' => 'occasion', 'entity' => $occasion];
            }
        }

        return null;
    }

    /**
     * Is an occasion inside its date window?
     *
     * A blank window means always. An occasion that has not started, or has
     * finished, is treated as absent rather than shown — a Diwali page in March
     * is worse than a 404.
     *
     * @param array<string, mixed> $occasion CollectionModel returns arrays.
     */
    public function isRunning(array $occasion): bool
    {
        $now = time();

        $starts = $occasion['starts_at'] ?? null;
        $ends   = $occasion['ends_at'] ?? null;

        if ($starts !== null && strtotime((string) $starts) > $now) {
            return false;
        }

        return ! ($ends !== null && strtotime((string) $ends) < $now);
    }
}
