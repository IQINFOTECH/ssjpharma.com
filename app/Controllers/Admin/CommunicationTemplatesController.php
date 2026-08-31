<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\EmailQueueRepository;
use App\Repositories\EmailTemplateRepository;
use App\Repositories\WhatsappTemplateRepository;
use App\Services\SettingsService;
use App\Support\TemplateRenderer;

/**
 * CMS-managed email + WhatsApp templates (Phase 5, §7-9, §20). Rendering is pure
 * placeholder substitution (never PHP/JS/SQL). Preview uses clearly-labelled DEMO
 * values and never sends. A test send goes ONLY to the acting admin's own address
 * (no arbitrary recipients). All actions gated by communications.* permissions.
 */
final class CommunicationTemplatesController extends AdminController
{
    private function emailTpls(): EmailTemplateRepository { return $this->container->get(EmailTemplateRepository::class); }
    private function waTpls(): WhatsappTemplateRepository { return $this->container->get(WhatsappTemplateRepository::class); }
    private function renderer(): TemplateRenderer { return $this->container->get(TemplateRenderer::class); }

    public function index(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        return $this->adminView('admin.templates.index', [
            'title'      => 'Templates',
            'emailTemplates' => $this->emailTpls()->allOrdered(),
            'waTemplates'    => $this->waTpls()->allOrdered(),
            'canTest'    => $this->can('communications.send_test'),
        ], 'templates');
    }

    // --- Email templates -----------------------------------------------------

    public function editEmail(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        $tpl = $this->emailTpls()->findById((int) $request->route('id'));
        if ($tpl === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.templates.edit_email', [
            'title'       => 'Edit template',
            'tpl'         => $tpl,
            'placeholders' => $this->placeholderList(),
            'canTest'     => $this->can('communications.send_test'),
        ], 'templates');
    }

    public function updateEmail(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        $id = (int) $request->route('id');
        if ($this->emailTpls()->findById($id) === null) {
            throw new HttpException(404);
        }
        $this->emailTpls()->update($id, [
            'name'      => mb_substr(trim((string) $request->input('name', '')), 0, 120),
            'subject'   => mb_substr(trim((string) $request->input('subject', '')), 0, 255),
            'body_html' => (string) $request->input('body_html', ''),
            'body_text' => (string) $request->input('body_text', ''),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ], $this->currentUserId());
        $this->audit('COMM_TEMPLATE_UPDATED', ['entity_type' => 'email_template', 'entity_id' => $id]);
        $this->flash('success', 'Template saved.');
        return Response::redirect('/admin/communications/email-templates/' . $id . '/edit');
    }

    /** Render the POSTed template body with DEMO values into a sandboxed preview. */
    public function previewEmail(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        $subject = $this->renderer()->renderSubject((string) $request->input('subject', ''), $this->demoContext());
        $html = $this->renderer()->render((string) $request->input('body_html', ''), $this->demoContext(), true, ['followups.rows']);
        return $this->adminView('admin.templates.preview', [
            'title'   => 'Preview',
            'subject' => $subject,
            'html'    => $html,
        ], 'templates');
    }

    /** Queue a test email to the ACTING ADMIN only (never an arbitrary recipient). */
    public function testEmail(Request $request): Response
    {
        $this->requirePermission('communications.send_test');
        $id = (int) $request->route('id');
        $tpl = $this->emailTpls()->findById($id);
        if ($tpl === null) {
            throw new HttpException(404);
        }
        $me = $this->auth()->user() ?? [];
        $to = (string) ($me['email'] ?? '');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $this->flash('error', 'Your account has no valid email for a test send.');
            return Response::redirect('/admin/communications/email-templates/' . $id . '/edit');
        }
        $rendered = $this->renderer()->renderTemplate($tpl, $this->demoContext(), ['followups.rows']);
        /** @var EmailQueueRepository $queue */
        $queue = $this->container->get(EmailQueueRepository::class);
        $queue->enqueue([
            'lead_id'         => null,
            'template_key'    => (string) $tpl['key'],
            'recipient_email' => $to,
            'recipient_name'  => (string) ($me['name'] ?? ''),
            'subject'         => '[TEST] ' . mb_substr($rendered['subject'], 0, 240),
            'body_html'       => $rendered['html'],
            'body_text'       => $rendered['text'],
        ]);
        $this->audit('COMM_TEST_SENT', ['entity_type' => 'email_template', 'entity_id' => $id, 'meta' => ['to' => $to]]);
        $this->flash('success', 'Test email queued to ' . $to . ' (delivered on the next queue run).');
        return Response::redirect('/admin/communications/email-templates/' . $id . '/edit');
    }

    // --- WhatsApp templates --------------------------------------------------

    public function editWhatsapp(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        $tpl = $this->waTpls()->findById((int) $request->route('id'));
        if ($tpl === null) {
            throw new HttpException(404);
        }
        return $this->adminView('admin.templates.edit_whatsapp', [
            'title'   => 'Edit WhatsApp template',
            'tpl'     => $tpl,
            'preview' => $this->waPreviewLink((string) $tpl['message']),
        ], 'templates');
    }

    public function updateWhatsapp(Request $request): Response
    {
        $this->requirePermission('communications.manage_templates');
        $id = (int) $request->route('id');
        if ($this->waTpls()->findById($id) === null) {
            throw new HttpException(404);
        }
        $this->waTpls()->update($id, [
            'name'      => mb_substr(trim((string) $request->input('name', '')), 0, 120),
            'message'   => mb_substr(trim((string) $request->input('message', '')), 0, 1000),
            'is_active' => $request->input('is_active') ? 1 : 0,
        ], $this->currentUserId());
        $this->audit('COMM_TEMPLATE_UPDATED', ['entity_type' => 'whatsapp_template', 'entity_id' => $id]);
        $this->flash('success', 'WhatsApp template saved.');
        return Response::redirect('/admin/communications/whatsapp-templates/' . $id . '/edit');
    }

    // --- helpers -------------------------------------------------------------

    private function waPreviewLink(string $message): string
    {
        /** @var SettingsService $settings */
        $settings = $this->container->get(SettingsService::class);
        $rendered = $this->renderer()->render($message, [
            'product.name' => 'DEMO PRODUCT',
            'product.url'  => $settings->websiteUrl() . '/products/demo-product',
            'site.name'    => $settings->websiteName(),
        ], false);
        $number = $settings->whatsappNumber();
        if ($number === '') {
            return '';
        }
        return 'https://wa.me/' . $number . '?text=' . rawurlencode($rendered);
    }

    /** @return array<int,string> */
    private function placeholderList(): array
    {
        return [
            '{{lead.name}}', '{{lead.company}}', '{{lead.email}}', '{{lead.phone}}', '{{lead.whatsapp}}',
            '{{lead.country}}', '{{lead.city}}', '{{lead.product_name}}', '{{lead.enquiry_type}}',
            '{{lead.message}}', '{{lead.requirement}}', '{{lead.source}}', '{{lead.status}}',
            '{{lead.priority}}', '{{lead.follow_up_date}}', '{{lead.reference}}', '{{lead.landing_page}}',
            '{{lead.utm_source}}', '{{lead.utm_medium}}', '{{lead.utm_campaign}}', '{{lead.url}}',
            '{{site.name}}', '{{site.url}}',
            '{{assignee.name}}', '{{followups.count}}', '{{followups.rows}}', '{{followups.rows_text}}',
        ];
    }

    private function demoContext(): array
    {
        /** @var SettingsService $settings */
        $settings = $this->container->get(SettingsService::class);
        $url = $settings->websiteUrl();
        return [
            'lead.name' => 'TEST LEAD 001', 'lead.company' => 'TEST COMPANY',
            'lead.email' => 'test.lead@example.test', 'lead.phone' => '+91 90000 00000',
            'lead.whatsapp' => '+91 90000 00000', 'lead.country' => 'India', 'lead.city' => 'Mumbai',
            'lead.product_name' => 'DEMO PRODUCT', 'lead.enquiry_type' => 'product',
            'lead.message' => 'This is a demo enquiry message (preview only).',
            'lead.requirement' => '100 units (demo)', 'lead.source' => 'Contact Form',
            'lead.status' => 'New', 'lead.priority' => 'Medium', 'lead.follow_up_date' => date('Y-m-d'),
            'lead.reference' => 'SSJ-DEMO01', 'lead.landing_page' => '/contact-us',
            'lead.utm_source' => 'demo', 'lead.utm_medium' => 'email', 'lead.utm_campaign' => 'preview',
            'lead.url' => $url . '/admin/leads/0',
            'site.name' => $settings->websiteName(), 'site.url' => $url,
            'assignee.name' => 'TEST USER', 'followups.count' => '1',
            'followups.rows' => '<table cellpadding="6"><tr><td>TEST LEAD 001</td><td>Medium</td><td>' . date('Y-m-d') . '</td></tr></table>',
            'followups.rows_text' => '- TEST LEAD 001 (SSJ-DEMO01), Medium, due ' . date('Y-m-d'),
        ];
    }
}
