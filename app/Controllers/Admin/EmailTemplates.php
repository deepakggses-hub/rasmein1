<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Models\EmailTemplateModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Editing the wording of every email the system sends.
 *
 * Two things keep this safe. The body is sanitised on save by the model, using
 * the same allowlist as the CMS pages — a template becomes an email in
 * somebody's inbox, so a script tag matters as much there as on a page. And the
 * renderer only substitutes the tokens a template DECLARES, escaping each
 * value, so editing wording can never turn into executing anything.
 *
 * `template_key` is not editable: the code sends by key, and renaming one would
 * silently stop that email going out.
 */
class EmailTemplates extends AdminController
{
    public function index()
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        return $this->adminPage('admin/templates/index', [
            'grouped' => model(EmailTemplateModel::class)->grouped(),
        ], 'Email templates');
    }

    public function edit(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $template = model(EmailTemplateModel::class)->find($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->adminPage('admin/templates/form', [
            'template'     => $template,
            'placeholders' => model(EmailTemplateModel::class)->placeholdersFor($template),
            'preview'      => $this->buildPreview($template),
        ], 'Edit ' . $template['name']);
    }

    public function update(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $model    = model(EmailTemplateModel::class);
        $template = $model->find($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $raw = (string) $this->request->getPost('body_html');

        $payload = [
            'id'        => $id,
            // Deliberately absent: template_key. The code sends by key.
            'name'      => trim((string) $this->request->getPost('name')),
            'subject'   => trim((string) $this->request->getPost('subject')),
            'body_html' => $raw,
            'is_active' => $this->request->getPost('is_active') !== null ? 1 : 0,
        ];

        if ($model->update($id, $payload) === false) {
            return redirect()->back()->withInput()->with('errors', $model->errors());
        }

        $stored = $model->find($id);

        // Warn about tokens that will render as nothing, rather than letting a
        // customer receive an email with a hole in it.
        $declared = array_keys($model->placeholdersFor($stored));
        $used     = [];
        preg_match_all('/\{\{([a-z0-9_]+)\}\}/i', $payload['subject'] . ' ' . $raw, $used);

        $always  = ['brand_name', 'brand_tagline', 'support_email', 'support_phone', 'site_url', 'year'];
        $unknown = array_values(array_unique(array_diff($used[1] ?? [], $declared, $always)));

        service('audit')->log('updated', 'content', 'email_template', $id, $stored['template_key']);

        $message = 'Template saved.';

        if ($unknown !== []) {
            $message .= ' Note: {{' . implode('}}, {{', $unknown) . '}} '
                . (count($unknown) === 1 ? 'is not' : 'are not')
                . ' available in this template and will render as nothing.';
        }

        if ($raw !== '' && strlen((string) $stored['body_html']) < strlen($raw) * 0.9) {
            $message .= ' Some markup was removed — only basic formatting is allowed.';
        }

        return redirect()->to(site_url('admin/email-templates/' . $id . '/edit'))
            ->with($unknown === [] ? 'success' : 'error', $message);
    }

    /**
     * Send this template to the signed-in administrator, filled with sample
     * values. Only ever to your own address — a test-send box accepting an
     * arbitrary recipient is an open relay with extra steps.
     */
    public function test(int $id)
    {
        if ($denied = $this->deny('content.manage')) {
            return $denied;
        }

        $template = model(EmailTemplateModel::class)->find($id);

        if ($template === null) {
            throw PageNotFoundException::forPageNotFound();
        }

        $to = (string) ($this->admin['email'] ?? '');

        if ($to === '') {
            return redirect()->back()->with('error', 'Your account has no email address.');
        }

        if (service('throttler')->check('mailtest_' . (int) session('admin_id'), 5, MINUTE) === false) {
            return redirect()->back()->with('error', 'Too many test sends. Wait a moment.');
        }

        $rendered = service('mail')->render($template, $this->sampleData($template));

        try {
            service('mail')->deliver($to, '[TEST] ' . $rendered['subject'], $rendered['body']);
        } catch (\Throwable $e) {
            log_message('error', 'Test send failed: {msg}', ['msg' => $e->getMessage()]);

            return redirect()->back()->with(
                'error',
                'The mail server would not accept it. Check the SMTP settings in .env — the '
                    . 'reason is in the log.'
            );
        }

        service('audit')->log('test_sent', 'content', 'email_template', $id, $template['template_key']);

        return redirect()->back()->with('success', 'Test sent to ' . $to . '.');
    }

    /** @param array<string, mixed> $template @return array<string, string> */
    private function buildPreview(array $template): array
    {
        $rendered = service('mail')->render($template, $this->sampleData($template));

        return [
            'subject' => $rendered['subject'],
            'html'    => service('mail')->wrap($rendered['body']),
            'text'    => service('mail')->toPlainText($rendered['body']),
        ];
    }

    /**
     * Plausible values for every token this template declares, so the preview
     * reads like a real email rather than a form full of braces.
     *
     * @param array<string, mixed> $template
     *
     * @return array<string, string>
     */
    private function sampleData(array $template): array
    {
        $samples = [
            'order_ref'       => 'RSM-2026-000142',
            'customer_name'   => 'Asha Patel',
            'customer_email'  => 'asha@example.com',
            'customer_phone'  => '+91 98765 43210',
            'order_total'     => rs_money(2489),
            'order_subtotal'  => rs_money(2410),
            'order_status'    => 'confirmed',
            'placed_at'       => date('j M Y'),
            'order_url'       => site_url('order/sample-preview'),
            'admin_url'       => site_url('admin/orders/1'),
            'ship_name'       => 'Asha Patel',
            'ship_address'    => '14 Malviya Nagar, Jaipur, Rajasthan, 302017',
            'courier_name'    => 'Delhivery',
            'tracking_number' => 'DLV8842019773',
            'tracking_url'    => 'https://www.delhivery.com/track/package/DLV8842019773',
            'reset_url'       => site_url('account/reset/sample-token'),
            'expiry_hours'    => '1',
        ];

        $out = [];

        foreach (array_keys(model(EmailTemplateModel::class)->placeholdersFor($template)) as $token) {
            $out[$token] = $samples[$token] ?? '[' . $token . ']';
        }

        return $out;
    }
}
