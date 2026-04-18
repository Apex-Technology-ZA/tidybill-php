<?php

namespace ApexTechnology\TidyBill\Tests\Unit;

use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\InvoiceResult;
use ApexTechnology\TidyBill\DTOs\LineItemData;
use ApexTechnology\TidyBill\DTOs\LineItemResult;
use ApexTechnology\TidyBill\Exceptions\TidyBillAuthException;
use ApexTechnology\TidyBill\Exceptions\TidyBillException;
use ApexTechnology\TidyBill\Exceptions\TidyBillNotFoundException;
use ApexTechnology\TidyBill\TidyBillClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TidyBillClientTest extends TestCase
{
    private MockHandler $mockHandler;

    /** @var array<int, array{request: \Psr\Http\Message\RequestInterface, response: \Psr\Http\Message\ResponseInterface}> */
    private array $history = [];

    private TidyBillClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockHandler = new MockHandler();
        $this->history     = [];

        $stack = HandlerStack::create($this->mockHandler);
        $stack->push(Middleware::history($this->history));

        $guzzle = new Client([
            'handler' => $stack,
            'headers' => [
                'Authorization' => 'Bearer test-token',
                'X-Company-Id'  => 'test-company',
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);

        $this->client = new TidyBillClient(
            token: 'test-token',
            companyId: 'test-company',
            baseUrl: 'https://tidybill.test',
            httpClient: $guzzle,
        );
    }

    private function json(mixed $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function lastRequest(): \Psr\Http\Message\RequestInterface
    {
        return $this->history[count($this->history) - 1]['request'];
    }

    #[Test]
    public function create_invoice_sends_correct_payload(): void
    {
        $this->mockHandler->append($this->json([
            'id'         => 1,
            'client_id'  => '42',
            'status'     => 'draft',
            'issue_date' => '2026-04-20',
            'currency'   => 'ZAR',
            'total'      => 1000,
            'line_items' => [],
        ], 201));

        $result = $this->client->createInvoice(new CreateInvoiceData(
            clientId: '42',
            issueDate: '2026-04-20',
            lineItems: [
                new LineItemData(description: 'Test', quantity: 10, unitPrice: 1.0),
            ],
        ));

        $this->assertInstanceOf(InvoiceResult::class, $result);
        $this->assertSame(1, $result->id);
        $this->assertSame('draft', $result->status);

        $req  = $this->lastRequest();
        $body = json_decode((string) $req->getBody(), true);

        $this->assertSame('POST', $req->getMethod());
        $this->assertStringContainsString('api/invoices', (string) $req->getUri());
        $this->assertSame('42', $body['client_id']);
        $this->assertSame(100, $body['line_items'][0]['unit_price']);
        $this->assertSame('Bearer test-token', $req->getHeaderLine('Authorization'));
        $this->assertSame('test-company', $req->getHeaderLine('X-Company-Id'));
    }

    #[Test]
    public function get_invoices_with_filters(): void
    {
        $this->mockHandler->append($this->json([
            'data' => [
                [
                    'id'         => 1,
                    'client_id'  => '42',
                    'status'     => 'draft',
                    'issue_date' => '2026-04-20',
                    'currency'   => 'ZAR',
                    'total'      => 1000,
                    'line_items' => [],
                ],
            ],
        ]));

        $results = $this->client->getInvoices(['client_id' => '42', 'status' => 'draft']);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(InvoiceResult::class, $results[0]);
        $this->assertSame('draft', $results[0]->status);

        $req = $this->lastRequest();
        $this->assertStringContainsString('client_id=42', (string) $req->getUri());
        $this->assertStringContainsString('status=draft', (string) $req->getUri());
    }

    #[Test]
    public function get_invoice_by_id(): void
    {
        $this->mockHandler->append($this->json([
            'id'         => 1,
            'client_id'  => '42',
            'status'     => 'draft',
            'issue_date' => '2026-04-20',
            'currency'   => 'ZAR',
            'total'      => 0,
            'line_items' => [],
        ]));

        $result = $this->client->getInvoice(1);

        $this->assertSame(1, $result->id);
        $this->assertStringContainsString('api/invoices/1', (string) $this->lastRequest()->getUri());
    }

    #[Test]
    public function add_line_item_to_invoice(): void
    {
        $this->mockHandler->append($this->json([
            'id'          => 10,
            'description' => 'API call',
            'quantity'    => 5,
            'unit_price'  => 150,
            'total'       => 750,
        ], 201));

        $result = $this->client->addLineItem(1, new LineItemData(
            description: 'API call',
            quantity: 5,
            unitPrice: 1.50,
        ));

        $this->assertInstanceOf(LineItemResult::class, $result);
        $this->assertSame(10, $result->id);
        $this->assertSame(1.50, $result->unitPrice);
    }

    #[Test]
    public function add_line_items_calls_add_line_item_for_each(): void
    {
        $this->mockHandler->append(
            $this->json(['id' => 10, 'description' => 'A', 'quantity' => 1, 'unit_price' => 100, 'total' => 100], 201),
            $this->json(['id' => 11, 'description' => 'B', 'quantity' => 2, 'unit_price' => 200, 'total' => 400], 201),
        );

        $results = $this->client->addLineItems(1, [
            new LineItemData(description: 'A', quantity: 1, unitPrice: 1.0),
            new LineItemData(description: 'B', quantity: 2, unitPrice: 2.0),
        ]);

        $this->assertCount(2, $results);
        $this->assertSame(10, $results[0]->id);
        $this->assertSame(11, $results[1]->id);
    }

    #[Test]
    public function update_line_item_sends_put_to_correct_uri(): void
    {
        $this->mockHandler->append($this->json([
            'id'          => 10,
            'description' => 'Updated call',
            'quantity'    => 3,
            'unit_price'  => 200,
            'total'       => 600,
        ]));

        $result = $this->client->updateLineItem(1, 10, new LineItemData(
            description: 'Updated call',
            quantity: 3,
            unitPrice: 2.00,
        ));

        $this->assertInstanceOf(LineItemResult::class, $result);
        $this->assertSame(10, $result->id);
        $this->assertSame(2.00, $result->unitPrice);

        $req = $this->lastRequest();
        $this->assertSame('PUT', $req->getMethod());
        $this->assertStringContainsString('api/invoices/1/line-items/10', (string) $req->getUri());
    }

    #[Test]
    public function throws_auth_exception_on_401(): void
    {
        $this->mockHandler->append($this->json(['message' => 'Unauthenticated'], 401));

        $this->expectException(TidyBillAuthException::class);

        $this->client->getInvoices();
    }

    #[Test]
    public function throws_auth_exception_on_403(): void
    {
        $this->mockHandler->append($this->json(['message' => 'Forbidden'], 403));

        $this->expectException(TidyBillAuthException::class);

        $this->client->getInvoices();
    }

    #[Test]
    public function throws_not_found_exception_on_404(): void
    {
        $this->mockHandler->append($this->json(['message' => 'Not found'], 404));

        $this->expectException(TidyBillNotFoundException::class);

        $this->client->getInvoice(999);
    }

    #[Test]
    public function throws_base_exception_on_500(): void
    {
        $this->mockHandler->append($this->json(['message' => 'Server error'], 500));

        $this->expectException(TidyBillException::class);

        $this->client->getInvoices();
    }

    #[Test]
    public function get_clients(): void
    {
        $this->mockHandler->append($this->json([
            'data' => [
                ['id' => 1, 'name' => 'Acme Corp', 'email' => 'billing@acme.test'],
            ],
        ]));

        $results = $this->client->getClients();

        $this->assertCount(1, $results);
        $this->assertSame('Acme Corp', $results[0]->name);
    }

    #[Test]
    public function get_client_by_id(): void
    {
        $this->mockHandler->append($this->json([
            'id'    => 42,
            'name'  => 'Acme Corp',
            'email' => 'billing@acme.test',
        ]));

        $result = $this->client->getClient('42');

        $this->assertSame('42', $result->id);
        $this->assertSame('Acme Corp', $result->name);
    }

    #[Test]
    public function update_invoice(): void
    {
        $this->mockHandler->append($this->json([
            'id'         => 1,
            'client_id'  => '42',
            'status'     => 'draft',
            'issue_date' => '2026-04-20',
            'currency'   => 'ZAR',
            'total'      => 500,
            'line_items' => [],
        ]));

        $result = $this->client->updateInvoice(1, ['notes' => 'Updated']);

        $this->assertSame(1, $result->id);

        $req  = $this->lastRequest();
        $body = json_decode((string) $req->getBody(), true);

        $this->assertSame('PUT', $req->getMethod());
        $this->assertSame('Updated', $body['notes']);
    }

    #[Test]
    public function delete_line_item(): void
    {
        $this->mockHandler->append(new Response(204));

        $this->client->deleteLineItem(1, 10);

        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertStringContainsString('api/invoices/1/line-items/10', (string) $this->lastRequest()->getUri());
    }

    #[Test]
    public function default_constructor_creates_guzzle_client(): void
    {
        // Verify that constructing without an explicit httpClient does not throw.
        $client = new TidyBillClient(
            token: 'tok',
            companyId: 'co',
            baseUrl: 'https://tidybill.app',
        );

        $this->assertInstanceOf(TidyBillClient::class, $client);
    }

    #[Test]
    public function rejects_http_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TidyBill base URL must use HTTPS.');

        new TidyBillClient(
            token: 'tok',
            companyId: 'co',
            baseUrl: 'http://tidybill.app',
        );
    }

    #[Test]
    public function rejects_empty_token(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TidyBill token and company ID are required.');

        new TidyBillClient(
            token: '',
            companyId: 'co',
            baseUrl: 'https://tidybill.app',
        );
    }

    #[Test]
    public function rejects_empty_company_id(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('TidyBill token and company ID are required.');

        new TidyBillClient(
            token: 'tok',
            companyId: '',
            baseUrl: 'https://tidybill.app',
        );
    }

    #[Test]
    public function debug_info_masks_token(): void
    {
        $info = $this->client->__debugInfo();

        $this->assertSame('***', $info['token']);
        $this->assertSame('test-company', $info['companyId']);
    }

    #[Test]
    public function throws_exception_on_invalid_json_response(): void
    {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], 'not-valid-json{'));

        $this->expectException(TidyBillException::class);
        $this->expectExceptionMessage('Failed to decode API response:');

        $this->client->getInvoices();
    }
}
