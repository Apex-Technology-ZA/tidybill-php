<?php

namespace ApexTechnology\TidyBill;

use ApexTechnology\TidyBill\DTOs\ClientData;
use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\InvoiceResult;
use ApexTechnology\TidyBill\DTOs\LineItemData;
use ApexTechnology\TidyBill\DTOs\LineItemResult;
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
        private readonly string $baseUrl = 'https://tidybill.app',
        ?ClientInterface $httpClient = null,
    ) {
        $this->httpClient = $httpClient ?? new Client([
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
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

    public function deleteLineItem(int $invoiceId, int $lineItemId): bool
    {
        $this->send('DELETE', "api/invoices/{$invoiceId}/line-items/{$lineItemId}");

        return true;
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

    private function send(string $method, string $uri, array $options = []): ResponseInterface
    {
        $options['headers'] = array_merge([
            'Authorization' => "Bearer {$this->token}",
            'X-Company-Id'  => $this->companyId,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ], $options['headers'] ?? []);

        try {
            return $this->httpClient->request($method, $uri, $options);
        } catch (BadResponseException $e) {
            $this->throwForResponse($e->getResponse());
        }
    }

    private function decode(ResponseInterface $response): array
    {
        $body = (string) $response->getBody();

        if ($body === '' || $body === 'null') {
            return [];
        }

        return json_decode($body, true) ?? [];
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
