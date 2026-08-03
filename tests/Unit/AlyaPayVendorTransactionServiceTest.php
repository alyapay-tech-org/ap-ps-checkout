<?php

namespace AlyaPay\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AlyaPayVendorTransactionServiceTest extends TestCase
{
    public function testBuildsRequestPathFromVendorReference(): void
    {
        $client = $this->createMock(\AlyaPayApiClient::class);
        $client->expects($this->once())
            ->method('getWithApiKey')
            ->with('/api/v1/public/partner/transactions/vendor/42')
            ->willReturn(['status' => 'APPROVED', 'id' => 'txn-1']);

        $service = new \AlyaPayVendorTransactionService($client);
        $result = $service->getByVendorReference('42');

        $this->assertSame(['status' => 'APPROVED', 'id' => 'txn-1'], $result);
    }

    public function testUrlEncodesTheVendorReference(): void
    {
        $client = $this->createMock(\AlyaPayApiClient::class);
        $client->expects($this->once())
            ->method('getWithApiKey')
            ->with('/api/v1/public/partner/transactions/vendor/cart%2F1');

        $service = new \AlyaPayVendorTransactionService($client);
        $service->getByVendorReference('cart/1');
    }
}
