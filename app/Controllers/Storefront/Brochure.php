<?php

declare(strict_types=1);

namespace App\Controllers\Storefront;

use App\Models\BrochureModel;
use App\Models\CustomerModel;
use CodeIgniter\HTTP\RedirectResponse;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Downloading a brochure, and the details we ask for first.
 *
 * THE GATE IS REAL
 *
 * The files live under WRITEPATH, so the only way to one is through
 * `download()`. A brochure that is also fetchable at its own URL is not gated
 * at all: the form becomes decoration the moment somebody shares the direct
 * link, and every lead after that is lost.
 *
 * SIGNED IN MEANS NO FORM
 *
 * A customer who has already given us their name, email and phone should not
 * be asked for them again to read a catalogue. Their download is recorded
 * against their account from the profile, which is also more accurate than
 * whatever they would retype. Guests get the form.
 *
 * WHAT COUNTS AS PERMISSION
 *
 * A submitted form grants THAT brochure, in THIS session, once —
 * `session('brochure_ok')`. The download route re-checks it rather than
 * trusting a redirect, so a guessed download URL still gets the form.
 */
class Brochure extends StorefrontController
{
    /** A public form that triggers staff notifications needs a ceiling. */
    private const PER_HOUR = 12;

    /**
     * The form a guest sees. Signed-in customers never reach it.
     */
    public function form(int $id): string|RedirectResponse
    {
        $brochure = $this->live($id);

        if ($brochure === null) {
            return redirect()->to(site_url())->with('error', 'That brochure is no longer available.');
        }

        if ($this->customer() !== null) {
            return redirect()->to(site_url('brochure/' . $id . '/download'));
        }

        return $this->page('storefront/brochure_form', [
            'brochure' => $brochure,
            'backUrl'  => $this->safeReturn(),
        ], [
            'title'       => $brochure['title'],
            'description' => 'Tell us where to send it and the download starts straight away.',
        ]);
    }

    /**
     * Name and phone are required; email is offered and kept when given.
     *
     * One definition, used by the page and by the modal's JSON endpoint. Two
     * copies would drift the first time a rule changed, and the modal would go
     * on accepting something the page refuses.
     */
    private const RULES = [
        'name'  => 'required|min_length[2]|max_length[191]',
        'email' => 'permit_empty|valid_email|max_length[191]',
        'phone' => 'required|min_length[6]|max_length[40]',
        'notes' => 'permit_empty|max_length[2000]',
    ];

    private const MESSAGES = [
        'name'  => ['required' => 'Please tell us your name.'],
        'email' => ['valid_email' => 'That does not look like an email address.'],
        'phone' => ['required' => 'Please give us a phone number.'],
    ];

    /**
     * Take the guest's details, then let them through.
     *
     * The full-page path, used when the script has not run.
     */
    public function submit(int $id): RedirectResponse
    {
        $brochure = $this->live($id);

        if ($brochure === null) {
            return redirect()->to(site_url())->with('error', 'That brochure is no longer available.');
        }

        // A bot filling every field is stopped here rather than by the
        // validator, which would tell it which field it got wrong.
        if ($this->trapped()) {
            return redirect()->to(site_url())->with('success', 'Thank you.');
        }

        if (! $this->withinRate()) {
            return redirect()->back()->withInput()
                ->with('error', 'That is a lot of brochures in one hour. Try again shortly.');
        }

        if (! $this->validate(self::RULES, self::MESSAGES)) {
            return redirect()->back()->withInput()
                ->with('errors', $this->validator->getErrors());
        }

        $this->accept($id, $brochure);

        return redirect()->to(site_url('brochure/' . $id . '/download'));
    }

    /**
     * The same submission, for the modal.
     *
     * Returns where to send the browser rather than the file itself: a fetch
     * cannot hand the user a download, and navigating to an `attachment`
     * response downloads it WITHOUT leaving the page — so the modal can close
     * on a thank-you and the reader stays exactly where they were.
     */
    public function submitJson(int $id): ResponseInterface
    {
        $brochure = $this->live($id);

        /*
         * Every reply carries csrf_hash(). `security.regenerate` rotates the
         * token on each validated POST, so without writing the fresh one back
         * a second attempt in the same modal is rejected as a forgery.
         */
        $fresh = ['csrf' => csrf_hash()];

        if ($brochure === null) {
            return $this->response->setStatusCode(404)->setJSON($fresh + [
                'ok'      => false,
                'message' => 'That brochure is no longer available.',
            ]);
        }

        if ($this->trapped()) {
            // Answered exactly like a success, and nothing is recorded.
            return $this->response->setJSON($fresh + ['ok' => true, 'download' => site_url()]);
        }

        if (! $this->withinRate()) {
            return $this->response->setStatusCode(429)->setJSON($fresh + [
                'ok'      => false,
                'message' => 'That is a lot of brochures in one hour. Try again shortly.',
            ]);
        }

        if (! $this->validate(self::RULES, self::MESSAGES)) {
            return $this->response->setStatusCode(422)->setJSON($fresh + [
                'ok'     => false,
                'errors' => $this->validator->getErrors(),
            ]);
        }

        $this->accept($id, $brochure);

        return $this->response->setJSON($fresh + [
            'ok'       => true,
            'download' => site_url('brochure/' . $id . '/download'),
        ]);
    }

    // =================================================================

    /** The honeypot. A person never sees the field; a bot fills everything. */
    private function trapped(): bool
    {
        return trim((string) $this->request->getPost('website')) !== '';
    }

    /** A public form that raises staff notifications needs a ceiling. */
    private function withinRate(): bool
    {
        return service('throttler')
            ->check(md5('brochure-' . $this->request->getIPAddress()), self::PER_HOUR, HOUR) !== false;
    }

    /** Grant the download and record who asked. */
    private function accept(int $id, array $brochure): void
    {
        $this->grant($id);

        $email = trim((string) $this->request->getPost('email'));

        service('brochures')->recordLead($brochure, [
            'customer_id' => null,
            'name'        => trim((string) $this->request->getPost('name')),
            // NULL, not '': "not given" is the fact, and only one spelling of
            // it should reach the column.
            'email'       => $email !== '' ? $email : null,
            'phone'       => trim((string) $this->request->getPost('phone')),
            'notes'       => trim((string) $this->request->getPost('notes')) ?: null,
            'source_type' => $this->request->getPost('source_type') ?: null,
            'source_id'   => (int) $this->request->getPost('source_id') ?: null,
            'source_url'  => mb_substr((string) $this->request->getPost('source_url'), 0, 255) ?: null,
        ]);
    }

    /**
     * Stream the file.
     *
     * A signed-in customer is recorded here, because this is the only point
     * their download actually happens — there is no form for them to submit.
     */
    public function download(int $id): ResponseInterface|RedirectResponse
    {
        $brochure = $this->live($id);

        if ($brochure === null) {
            return redirect()->to(site_url())->with('error', 'That brochure is no longer available.');
        }

        $customer = $this->customer();

        if ($customer === null && ! $this->granted($id)) {
            // Not a refusal — they simply have not told us who they are yet.
            return redirect()->to(site_url('brochure/' . $id));
        }

        $full = service('brochures')->fullPath($brochure);

        if ($full === null) {
            log_message('error', 'Brochure {id} has no file at {p}', [
                'id' => $id, 'p' => $brochure['path'],
            ]);

            return redirect()->to(site_url())
                ->with('error', 'That brochure could not be found. Please let us know.');
        }

        if ($customer !== null) {
            service('brochures')->recordLead($brochure, [
                'customer_id' => (int) $customer['id'],
                'name'        => (string) $customer['name'],
                'email'       => (string) $customer['email'],
                'phone'       => (string) ($customer['phone'] ?? ''),
                'notes'       => null,
                'source_type' => $this->request->getGet('from') ?: null,
                'source_id'   => (int) $this->request->getGet('from_id') ?: null,
                'source_url'  => mb_substr((string) $this->request->getServer('HTTP_REFERER'), 0, 255) ?: null,
            ]);
        }

        /*
         * The permission is spent. Without this a guest's session grants the
         * same brochure for as long as it lives, and repeat downloads stop
         * producing the lead that justifies the gate.
         */
        $this->revoke($id);

        return $this->response->download($full, null)
            ->setFileName(service('brochures')->safeDownloadName((string) $brochure['filename']));
    }

    // =================================================================

    /** @return array<string, mixed>|null */
    private function live(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }

        return model(BrochureModel::class)->where('is_active', 1)->find($id);
    }

    /**
     * The signed-in customer, or null.
     *
     * Scoped by session, never by anything posted — the same rule the whole of
     * AccountArea follows.
     *
     * @return array<string, mixed>|null
     */
    private function customer(): ?array
    {
        $id = (int) (session('customer_id') ?? 0);

        if ($id < 1) {
            return null;
        }

        $row = model(CustomerModel::class)->find($id);

        return $row === null ? null : (array) $row;
    }

    private function grant(int $id): void
    {
        $ok      = (array) (session('brochure_ok') ?? []);
        $ok[$id] = true;
        session()->set('brochure_ok', $ok);
    }

    private function granted(int $id): bool
    {
        $ok = (array) (session('brochure_ok') ?? []);

        return ! empty($ok[$id]);
    }

    private function revoke(int $id): void
    {
        $ok = (array) (session('brochure_ok') ?? []);
        unset($ok[$id]);
        session()->set('brochure_ok', $ok);
    }

    /**
     * Where "back" goes after the form.
     *
     * Only a path on this site. A referer is attacker-controllable, and an
     * open redirect out of a form the shop links to everywhere is a phishing
     * primitive — the same reason banner links refuse a scheme.
     */
    private function safeReturn(): string
    {
        $referer = (string) $this->request->getServer('HTTP_REFERER');

        if ($referer === '') {
            return site_url();
        }

        $host = parse_url($referer, PHP_URL_HOST);
        $here = parse_url(site_url(), PHP_URL_HOST);

        return $host !== null && $host === $here ? $referer : site_url();
    }
}
