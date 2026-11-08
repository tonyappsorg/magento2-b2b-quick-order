<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Test\Unit\Block\Account;

use Magento\Customer\Block\Account\SortLinkInterface;
use PHPUnit\Framework\TestCase;
use Tonyapps\B2bQuickOrder\Block\Account\QuickOrderLink;

class QuickOrderLinkTest extends TestCase
{
    public function testLinkIsSortedIntoTheCompanySection(): void
    {
        // The account navigation sorts only SortLinkInterface children and puts every other link last.
        self::assertTrue(is_subclass_of(QuickOrderLink::class, SortLinkInterface::class));

        $layout = simplexml_load_file(__DIR__ . '/../../../../view/frontend/layout/customer_account.xml');
        $sortOrder = $layout->xpath('//argument[@name="sortOrder"]');
        // Between the B2b company section delimiters (245 and 234).
        self::assertSame('236', (string) $sortOrder[0]);
    }
}
