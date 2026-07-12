<?php

namespace ApexTechnology\TidyBill;

use ApexTechnology\TidyBill\DTOs\ClientData;
use ApexTechnology\TidyBill\DTOs\ClientEInvoiceSettings;
use ApexTechnology\TidyBill\DTOs\CompanyEInvoiceSettings;
use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\EInvoicePreviewResult;
use ApexTechnology\TidyBill\DTOs\EInvoiceStatus;
use ApexTechnology\TidyBill\DTOs\InvoiceResult;
use ApexTechnology\TidyBill\DTOs\LineItemData;
use ApexTechnology\TidyBill\DTOs\LineItemResult;
use ApexTechnology\TidyBill\Enums\DraftTieBreak;
use ApexTechnology\TidyBill\Exceptions\MultipleDraftsException;
use ApexTechnology\TidyBill\Exceptions\TidyBillAuthException;
use ApexTechnology\TidyBill\Exceptions\TidyBillException;
use ApexTechnology\TidyBill\Exceptions\TidyBillNotFoundException;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;

class TidyBillClient
{
    private ClientInterface $httpClient;

    public function __construct(
        private readonly string $token,
        private readonly string $companyId,
        string $baseUrl = 'https://tidybill.app',
        ?ClientInterface $httpClient = null,
    ) {
        if (empty($token) || empty($companyId)) {
            throw new \InvalidArgumentException('TidyBill token and company ID are required.');
        }

        if (!str_starts_with(rtrim($baseUrl, '/'), 'https://')) {
            throw new \InvalidArgumentException('TidyBill base URL must use HTTPS.');
        }

        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($baseUrl, '/') . '/',
            'headers'  => [
                'Authorization' => "Bearer {$this->token}",
                'X-Company-Id'  => $this->companyId,
                'Accept'        => 'application/json',
                'Content-Type'  => 'application/json',
            ],
        ]);
    }

    public function createInvoice(CreateInvoiceData $data): InvoiceResult
    {
        $response = $this->send('POST', 'api/invoices', ['json' => $data->toArray()]);

        return InvoiceResult::fromResponse($this->decode($response));
    }

    public function getInvoice(int $id): InvoiceResult
    {
        $response = $this->send('GET', "api/invoices/{$id}");

        return InvoiceResult::fromResponse($this->decode($response));
    }

    /**
     * @return InvoiceResult[]
     */
    public function getInvoices(array $filters = []): array
    {
        $options  = $filters ? ['query' => $filters] : [];
        $response = $this->send('GET', 'api/invoices', $options);
        $body     = $this->decode($response);

        return array_map(
            fn (array $invoice) => InvoiceResult::fromResponse($invoice),
            $body['data'] ?? [],
        );
    }

    public function updateInvoice(int $id, array $data): InvoiceResult
    {
        $response = $this->send('PUT', "api/invoices/{$id}", ['json' => $data]);

        return InvoiceResult::fromResponse($this->decode($response));
    }

    public function addLineItem(int $invoiceId, LineItemData $item): LineItemResult
    {
        $response = $this->send('POST', "api/invoices/{$invoiceId}/line-items", ['json' => $item->toArray()]);

        return LineItemResult::fromResponse($this->decode($response));
    }

    /**
     * @param LineItemData[] $items
     * @return LineItemResult[]
     */
    public function addLineItems(int $invoiceId, array $items): array
    {
        return array_map(
            fn (LineItemData $item) => $this->addLineItem($invoiceId, $item),
            $items,
        );
    }

    public function updateLineItem(int $invoiceId, int $lineItemId, LineItemData $item): LineItemResult
    {
        $response = $this->send('PUT', "api/invoices/{$invoiceId}/line-items/{$lineItemId}", ['json' => $item->toArray()]);

        return LineItemResult::fromResponse($this->decode($response));
    }

    public function deleteLineItem(int $invoiceId, int $lineItemId): void
    {
        $this->send('DELETE', "api/invoices/{$invoiceId}/line-items/{$lineItemId}");
    }

    /**
     * @return ClientData[]
     */
    public function getClients(): array
    {
        $response = $this->send('GET', 'api/clients');
        $body     = $this->decode($response);

        return array_map(
            fn (array $client) => ClientData::fromResponse($client),
            $body['data'] ?? [],
        );
    }

    public function getClient(string $id): ClientData
    {
        $response = $this->send('GET', "api/clients/{$id}");

        return ClientData::fromResponse($this->decode($response));
    }

    public function getEInvoiceStatus(int $invoiceId): EInvoiceStatus
    {
        $response = $this->send('GET', "api/invoices/{$invoiceId}/einvoice-status");

        return EInvoiceStatus::fromResponse($this->decode($response));
    }

    public function downloadEInvoiceXml(int $invoiceId): string
    {
        $response = $this->send('GET', "api/invoices/{$invoiceId}/einvoice-xml");

        return (string) $response->getBody();
    }

    public function previewEInvoice(int $invoiceId): EInvoicePreviewResult
    {
        $response = $this->send('POST', "api/invoices/{$invoiceId}/einvoice-preview");

        return EInvoicePreviewResult::fromResponse($this->decode($response));
    }

    public function getCompanyEInvoiceSettings(): CompanyEInvoiceSettings
    {
        $response = $this->send('GET', "api/companies/{$this->companyId}/einvoice-settings");

        return CompanyEInvoiceSettings::fromResponse($this->decode($response));
    }

    public function updateCompanyEInvoiceSettings(array $data): CompanyEInvoiceSettings
    {
        $response = $this->send('PUT', "api/companies/{$this->companyId}/einvoice-settings", ['json' => $data]);

        return CompanyEInvoiceSettings::fromResponse($this->decode($response));
    }

    public function getClientEInvoiceSettings(string $clientId): ClientEInvoiceSettings
    {
        $response = $this->send('GET', "api/clients/{$clientId}/einvoice-settings");

        return ClientEInvoiceSettings::fromResponse($this->decode($response));
    }

    public function updateClientEInvoiceSettings(string $clientId, array $data): ClientEInvoiceSettings
    {
        $response = $this->send('PUT', "api/clients/{$clientId}/einvoice-settings", ['json' => $data]);

        return ClientEInvoiceSettings::fromResponse($this->decode($response));
    }

    public function findDraftInvoice(
        string $clientId,
        DraftTieBreak $tieBreak = DraftTieBreak::Newest,
    ): ?InvoiceResult {
        // TidyBill ignores the client_id and status query filters and returns the full
        // active invoice set (paginated). Fetch every page and filter client-side, else a
        // draft beyond page 1 is missed and appendLineItemsToDraftOrCreate creates a duplicate.
        $target = trim($clientId);
        $drafts = [];
        foreach ($this->fetchActiveInvoicesRaw() as $raw) {
            $invoice = InvoiceResult::fromResponse($raw);
            if (trim($invoice->clientId) === $target && $invoice->status === 'draft') {
                $drafts[] = $invoice;
            }
        }

        return $this->resolveDrafts($drafts, $clientId, $tieBreak);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchActiveInvoicesRaw(): array
    {
        $all  = [];
        $page = 1;
        do {
            $body = $this->decode($this->send('GET', 'api/invoices', ['query' => ['page' => $page]]));
            foreach ($body['data'] ?? [] as $invoice) {
                $all[] = $invoice;
            }
            $lastPage = (int) ($body['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage);

        return $all;
    }

    /**
     * Post line items to a client's draft invoice if one exists, else create a new
     * invoice with them.
     *
     * WARNING: Appending to an existing draft is NOT atomic across multiple line items.
     * If addLineItem fails mid-loop (network error, draft finalised by another process),
     * earlier items are already persisted and a naive retry will duplicate them. Callers
     * with multi-item payloads must either (a) ensure idempotency at their level, or
     * (b) accept that mid-loop failures require manual reconciliation.
     *
     * @param LineItemData[] $lineItems
     */
    public function appendLineItemsToDraftOrCreate(
        string $clientId,
        array $lineItems,
        DraftTieBreak $tieBreak = DraftTieBreak::Newest,
    ): InvoiceResult {
        $this->guardLineItems($lineItems);

        $draft = $this->findDraftInvoice($clientId, $tieBreak);

        if ($draft === null) {
            return $this->createInvoice(new CreateInvoiceData(
                clientId: $clientId,
                issueDate: (new \DateTimeImmutable())->format('Y-m-d'),
                currency: 'ZAR',
                lineItems: $lineItems,
            ));
        }

        $this->addLineItems($draft->id, $lineItems);

        return $this->getInvoice($draft->id);
    }

    /**
     * @param LineItemData[] $lineItems
     */
    private function guardLineItems(array $lineItems): void
    {
        if ($lineItems === []) {
            throw new \InvalidArgumentException('lineItems must not be empty');
        }
        if (!array_is_list($lineItems)) {
            throw new \InvalidArgumentException('lineItems must be a list (zero-indexed array)');
        }
        foreach ($lineItems as $item) {
            if (!$item instanceof LineItemData) {
                throw new \InvalidArgumentException('lineItems must contain only LineItemData instances');
            }
        }
    }

    /**
     * @param InvoiceResult[] $drafts
     */
    private function resolveDrafts(array $drafts, string $clientId, DraftTieBreak $tieBreak): ?InvoiceResult
    {
        if ($drafts === []) {
            return null;
        }

        if (count($drafts) === 1) {
            return $drafts[0];
        }

        if ($tieBreak === DraftTieBreak::Strict) {
            throw new MultipleDraftsException(
                $clientId,
                array_map(fn (InvoiceResult $i) => $i->id, $drafts),
            );
        }

        $comparator = $tieBreak === DraftTieBreak::Newest
            ? fn (InvoiceResult $a, InvoiceResult $b) => [$b->issueDate, $b->id] <=> [$a->issueDate, $a->id]
            : fn (InvoiceResult $a, InvoiceResult $b) => [$a->issueDate, $a->id] <=> [$b->issueDate, $b->id];
        usort($drafts, $comparator);

        return $drafts[0];
    }

    private function send(string $method, string $uri, array $options = []): ResponseInterface
    {
        try {
            return $this->httpClient->request($method, $uri, $options);
        } catch (BadResponseException $e) {
            $this->throwForResponse($e->getResponse());
        }
    }

    public function __debugInfo(): array
    {
        return [
            'companyId' => $this->companyId,
            'token'     => '***',
        ];
    }

    /**
     * Refuse serialization. PHP would otherwise include the bearer token verbatim,
     * which leaks secrets into Laravel queue payloads, session stores, or any sink
     * that persists a serialized job/object. Resolve from the DI container instead.
     */
    public function __serialize(): array
    {
        throw new \LogicException(
            'TidyBillClient must not be serialized. Resolve it from the DI container at handler time.'
        );
    }

    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '' || $body === 'null') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new TidyBillException('Failed to decode API response: ' . json_last_error_msg());
        }

        return $decoded ?? [];
    }

    private function throwForResponse(ResponseInterface $response): never
    {
        $body    = $this->decode($response);
        $status  = $response->getStatusCode();
        $message = $body['message'] ?? "TidyBill API error: {$status}";

        throw match (true) {
            in_array($status, [401, 403]) => new TidyBillAuthException($message, $status, $body),
            $status === 404              => new TidyBillNotFoundException($message, $status, $body),
            default                      => new TidyBillException($message, $status, $body),
        };
    }
}
