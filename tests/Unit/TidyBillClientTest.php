<?php

namespace ApexTechnology\TidyBill\Tests\Unit;

use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\InvoiceResult;
use ApexTechnology\TidyBill\DTOs\LineItemData;
use ApexTechnology\TidyBill\DTOs\LineItemResult;
use ApexTechnology\TidyBill\Enums\DraftTieBreak;
use ApexTechnology\TidyBill\Exceptions\MultipleDraftsException;
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
            'data' => [
                'id'          => 10,
                'description' => 'API call',
                'quantity'    => 5,
                'unit_price'  => '1.500000',
                'amount'      => '7.500000',
            ],
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
            $this->json(['data' => ['id' => 10, 'description' => 'A', 'quantity' => 1, 'unit_price' => '1.000000', 'amount' => '1.000000']], 201),
            $this->json(['data' => ['id' => 11, 'description' => 'B', 'quantity' => 2, 'unit_price' => '2.000000', 'amount' => '4.000000']], 201),
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
            'data' => [
                'id'          => 10,
                'description' => 'Updated call',
                'quantity'    => 3,
                'unit_price'  => '2.000000',
                'amount'      => '6.000000',
            ],
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

    private function draft(int $id, string $clientId, string $issueDate): array
    {
        return [
            'id'         => $id,
            'client_id'  => $clientId,
            'status'     => 'draft',
            'issue_date' => $issueDate,
            'currency'   => 'ZAR',
            'total'      => 1000,
            'line_items' => [],
        ];
    }

    #[Test]
    public function find_draft_invoice_returns_null_when_no_invoices(): void
    {
        $this->mockHandler->append($this->json(['data' => []]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertNull($result);
        $this->assertCount(1, $this->history);
        $this->assertSame('GET', $this->history[0]['request']->getMethod());
    }

    #[Test]
    public function find_draft_invoice_returns_null_when_no_match_for_client(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(1, '99', '2026-04-20'),
            $this->draft(2, '77', '2026-05-20'),
        ]]));

        $this->assertNull($this->client->findDraftInvoice('42'));
    }

    #[Test]
    public function find_draft_invoice_returns_single_match(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(7, '42', '2026-05-20'),
        ]]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertInstanceOf(InvoiceResult::class, $result);
        $this->assertSame(7, $result->id);
    }

    #[Test]
    public function find_draft_invoice_with_newest_picks_latest_issue_date(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(1, '42', '2026-04-20'),
            $this->draft(2, '42', '2026-05-20'),
        ]]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertSame(2, $result->id);
        $this->assertSame('2026-05-20', $result->issueDate);
    }

    #[Test]
    public function find_draft_invoice_with_oldest_picks_earliest(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(1, '42', '2026-04-20'),
            $this->draft(2, '42', '2026-05-20'),
        ]]));

        $result = $this->client->findDraftInvoice('42', DraftTieBreak::Oldest);

        $this->assertSame(1, $result->id);
        $this->assertSame('2026-04-20', $result->issueDate);
    }

    #[Test]
    public function find_draft_invoice_with_strict_throws_on_multiple(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(1, '42', '2026-04-20'),
            $this->draft(2, '42', '2026-05-20'),
        ]]));

        try {
            $this->client->findDraftInvoice('42', DraftTieBreak::Strict);
            $this->fail('Expected MultipleDraftsException');
        } catch (MultipleDraftsException $e) {
            $this->assertSame('42', $e->clientId);
            $this->assertSame([1, 2], $e->draftIds);
        }
    }

    #[Test]
    public function find_draft_invoice_newest_breaks_tie_by_id(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(100, '42', '2026-05-20'),
            $this->draft(200, '42', '2026-05-20'),
        ]]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertSame(200, $result->id);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_throws_on_empty_lineitems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lineItems must not be empty');

        $this->client->appendLineItemsToDraftOrCreate('42', []);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_creates_when_no_draft(): void
    {
        $today = (new \DateTimeImmutable())->format('Y-m-d');
        $this->mockHandler->append(
            $this->json(['data' => []]),
            $this->json($this->draft(55, '42', $today), 201),
        );

        $result = $this->client->appendLineItemsToDraftOrCreate('42', [
            new LineItemData(description: 'Scan', quantity: 1, unitPrice: 1.0),
        ]);

        $this->assertSame(55, $result->id);
        $this->assertCount(2, $this->history);

        $createReq = $this->history[1]['request'];
        $body      = json_decode((string) $createReq->getBody(), true);

        $this->assertSame('POST', $createReq->getMethod());
        $this->assertStringContainsString('api/invoices', (string) $createReq->getUri());
        $this->assertSame('42', $body['client_id']);
        $this->assertSame('ZAR', $body['currency']);
        $this->assertSame($today, $body['issue_date']);
        $this->assertCount(1, $body['line_items']);
        $this->assertSame('Scan', $body['line_items'][0]['description']);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_appends_when_draft_exists(): void
    {
        $this->mockHandler->append(
            $this->json(['data' => [$this->draft(999, '42', '2026-05-20')]]),
            $this->json(['data' => ['id' => 10, 'description' => 'Scan', 'quantity' => 1, 'unit_price' => '1.000000', 'amount' => '1.000000']], 201),
            $this->json($this->draft(999, '42', '2026-05-20')),
        );

        $result = $this->client->appendLineItemsToDraftOrCreate('42', [
            new LineItemData(description: 'Scan', quantity: 1, unitPrice: 1.0),
        ]);

        $this->assertSame(999, $result->id);
        $this->assertCount(3, $this->history);

        $this->assertSame('GET', $this->history[0]['request']->getMethod());
        $this->assertStringContainsString('api/invoices', (string) $this->history[0]['request']->getUri());

        $this->assertSame('POST', $this->history[1]['request']->getMethod());
        $this->assertStringContainsString('api/invoices/999/line-items', (string) $this->history[1]['request']->getUri());

        $this->assertSame('GET', $this->history[2]['request']->getMethod());
        $this->assertStringContainsString('api/invoices/999', (string) $this->history[2]['request']->getUri());
    }

    #[Test]
    public function append_line_items_to_draft_or_create_appends_multiple_line_items(): void
    {
        $this->mockHandler->append(
            $this->json(['data' => [$this->draft(999, '42', '2026-05-20')]]),
            $this->json(['data' => ['id' => 10, 'description' => 'A', 'quantity' => 1, 'unit_price' => '1.000000', 'amount' => '1.000000']], 201),
            $this->json(['data' => ['id' => 11, 'description' => 'B', 'quantity' => 2, 'unit_price' => '2.000000', 'amount' => '4.000000']], 201),
            $this->json(['data' => ['id' => 12, 'description' => 'C', 'quantity' => 3, 'unit_price' => '3.000000', 'amount' => '9.000000']], 201),
            $this->json($this->draft(999, '42', '2026-05-20')),
        );

        $result = $this->client->appendLineItemsToDraftOrCreate('42', [
            new LineItemData(description: 'A', quantity: 1, unitPrice: 1.0),
            new LineItemData(description: 'B', quantity: 2, unitPrice: 2.0),
            new LineItemData(description: 'C', quantity: 3, unitPrice: 3.0),
        ]);

        $this->assertSame(999, $result->id);
        $this->assertCount(5, $this->history);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_strict_propagates_multiple_drafts_exception(): void
    {
        $this->mockHandler->append($this->json(['data' => [
            $this->draft(1, '42', '2026-04-20'),
            $this->draft(2, '42', '2026-05-20'),
        ]]));

        try {
            $this->client->appendLineItemsToDraftOrCreate(
                '42',
                [new LineItemData(description: 'Scan', quantity: 1, unitPrice: 1.0)],
                DraftTieBreak::Strict,
            );
            $this->fail('Expected MultipleDraftsException');
        } catch (MultipleDraftsException $e) {
            $this->assertSame('42', $e->clientId);
            $this->assertSame([1, 2], $e->draftIds);
        }

        $this->assertCount(1, $this->history);
    }

    #[Test]
    public function find_draft_invoice_trims_whitespace_when_matching_client_id(): void
    {
        $invoice = $this->draft(7, ' 42 ', '2026-05-20');
        $this->mockHandler->append($this->json(['data' => [$invoice]]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertNotNull($result);
        $this->assertSame(7, $result->id);
    }

    #[Test]
    public function find_draft_invoice_filters_out_non_draft_status_returned_by_server(): void
    {
        $sent = $this->draft(8, '42', '2026-05-20');
        $sent['status'] = 'sent';
        $this->mockHandler->append($this->json(['data' => [$sent]]));

        $result = $this->client->findDraftInvoice('42');

        $this->assertNull($result);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_rejects_associative_array(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lineItems must be a list');

        $this->client->appendLineItemsToDraftOrCreate('42', [
            'a' => new LineItemData(description: 'Scan', quantity: 1, unitPrice: 1.0),
        ]);
    }

    #[Test]
    public function append_line_items_to_draft_or_create_rejects_non_lineitemdata_entries(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('lineItems must contain only LineItemData instances');

        $this->client->appendLineItemsToDraftOrCreate('42', ['not-a-lineitem']);
    }

    #[Test]
    public function client_refuses_to_be_serialized(): void
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('TidyBillClient must not be serialized');

        serialize($this->client);
    }

    #[Test]
    public function decode_handles_empty_response_body(): void
    {
        $this->mockHandler->append(new Response(200, ['Content-Type' => 'application/json'], ''));

        $result = $this->client->getInvoices();

        $this->assertSame([], $result);
    }
}
