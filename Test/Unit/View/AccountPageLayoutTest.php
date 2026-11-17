<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Test\Unit\View;

use PHPUnit\Framework\TestCase;

class AccountPageLayoutTest extends TestCase
{
    public function testQuickOrderPageIsUncachedAndUsesTheAccountLayout(): void
    {
        $layout = simplexml_load_file(__DIR__ . '/../../../view/frontend/layout/b2bquickorder_order_index.xml');

        self::assertSame('customer_account', (string)$layout->update['handle']);
        self::assertSame('false', (string)$layout->xpath('//block[@name="tonyapps.b2b.quickorder.form"]')[0]['cacheable']);
    }

    public function testQuickOrderFormUsesHyvaControlsAndButtons(): void
    {
        $root = dirname(__DIR__, 3);
        $template = file_get_contents($root . '/view/frontend/templates/order/form.phtml');
        self::assertStringContainsString('form-input', $template);
        self::assertStringContainsString('btn-primary', $template);
        self::assertStringContainsString('btn-secondary', $template);
        self::assertStringContainsString('form-input', $template);
        self::assertStringContainsString(
            '@source "../templates/**/*.phtml"',
            file_get_contents($root . '/view/frontend/tailwind/module.css')
        );
    }
}
