# apextechnology/tidybill

A framework-agnostic PHP SDK for the [TidyBill](https://tidybill.app) invoicing API. Provides a typed client with DTOs for creating invoices, managing line items, and fetching clients. Works in any PHP project; Laravel users get an optional service provider via auto-discovery.

## Requirements

- PHP ^8.2
- `ext-json`
- `guzzlehttp/guzzle` ^7.0

## Installation

```bash
composer require apextechnology/tidybill
```

## Plain PHP usage

```php
use ApexTechnology\TidyBill\TidyBillClient;

$client = new TidyBillClient(
    token: 'your-api-token',
    companyId: 'your-company-id',
    baseUrl: 'https://tidybill.app', // optional, this is the default
);
```

You can also pass a custom `GuzzleHttp\ClientInterface` as the fourth argument for testing or advanced configuration:

```php
use GuzzleHttp\Client;

$client = new TidyBillClient(
    token: 'your-api-token',
    companyId: 'your-company-id',
    httpClient: new Client(['timeout' => 10]),
);
```

## Laravel usage

Laravel's package auto-discovery registers the service provider automatically. Publish the config file:

```bash
php artisan vendor:publish --tag=tidybill-config
```

This creates `config/tidybill.php`. Set the following environment variables:

```env
TIDYBILL_TOKEN=your-api-token
TIDYBILL_COMPANY_ID=your-company-id
TIDYBILL_BASE_URL=https://tidybill.app
```

Resolve the client from the container:

```php
use ApexTechnology\TidyBill\TidyBillClient;

$client = app(TidyBillClient::class);
```

Or inject it via the constructor in any Laravel service or controller.

## Usage

### Creating an invoice

```php
use ApexTechnology\TidyBill\DTOs\CreateInvoiceData;
use ApexTechnology\TidyBill\DTOs\LineItemData;

$invoice = $client->createInvoice(new CreateInvoiceData(
    clientId: '42',
    issueDate: '2026-05-01',
    currency: 'ZAR',
    notes: 'Net 30',
    lineItems: [
        new LineItemData(description: 'API usage', quantity: 100, unitPrice: 1.50),
        new LineItemData(description: 'Support hours', quantity: 2, unitPrice: 350.00),
    ],
));

echo $invoice->id;     // int
echo $invoice->status; // 'draft'
echo $invoice->total;  // float (in the currency's major unit)
```

### Adding line items to an existing invoice

```php
use ApexTechnology\TidyBill\DTOs\LineItemData;

// Single item
$lineItem = $client->addLineItem(invoiceId: $invoice->id, item: new LineItemData(
    description: 'Extra usage',
    quantity: 50,
    unitPrice: 0.80,
));

// Multiple items (sequential API calls)
$results = $client->addLineItems(invoiceId: $invoice->id, items: [
    new LineItemData(description: 'Item A', quantity: 1, unitPrice: 100.00),
    new LineItemData(description: 'Item B', quantity: 3, unitPrice: 25.00),
]);
```

### Fetching invoices

```php
// All invoices (with optional filters)
$invoices = $client->getInvoices(['status' => 'draft', 'client_id' => '42']);

// Single invoice
$invoice = $client->getInvoice(id: 123);

// Update an invoice
$updated = $client->updateInvoice(id: 123, data: ['notes' => 'Updated terms']);
```

### Managing line items

```php
// Update a line item
$client->updateLineItem(invoiceId: 123, lineItemId: 10, item: new LineItemData(
    description: 'Revised description',
    quantity: 5,
    unitPrice: 2.00,
));

// Delete a line item
$client->deleteLineItem(invoiceId: 123, lineItemId: 10);
```

### Fetching clients

```php
// All clients
$clients = $client->getClients();

// Single client
$clientData = $client->getClient(id: '42');

echo $clientData->name;  // string
echo $clientData->email; // ?string
echo $clientData->id;    // string
```

### E-invoicing

TidyBill generates and validates structured e-invoice documents from your invoices: ZUGFeRD / Factur-X (EN 16931) and UBL Peppol BIS Billing 3.0. These methods drive that generation and validation via the TidyBill API. They do **not** transmit invoices on the Peppol network; TidyBill produces the compliant document and you retrieve it.

```php
use ApexTechnology\TidyBill\Enums\EInvoiceFormat;

// Status of e-invoice generation for an invoice
$status = $client->getEInvoiceStatus(invoiceId: 123);
echo $status->enabled ? 'on' : 'off';        // bool
echo $status->format?->value;                // ?EInvoiceFormat ('zugferd_en16931' | 'ubl_peppol_bis3')
echo $status->generatedAt ?? 'not generated'; // ?string
$status->warnings;                            // array
$status->hasXml;                              // bool

// Download the generated XML (raw application/xml body).
// Throws TidyBillNotFoundException if no XML has been generated.
$xml = $client->downloadEInvoiceXml(invoiceId: 123); // string

// Validate an invoice against the target format without generating
$preview = $client->previewEInvoice(invoiceId: 123);
$preview->valid;  // bool
$preview->issues; // array of issue objects

// Company-level settings (uses the client's configured company ID)
$company = $client->getCompanyEInvoiceSettings();
echo $company->format->value; // EInvoiceFormat

$client->updateCompanyEInvoiceSettings([
    'enabled' => true,
    'format'  => EInvoiceFormat::ZugferdEn16931->value,
    'vat_id'  => 'GB123456789',
    'electronic_address_scheme' => '0088',
    'electronic_address'        => '7300010000001',
    'tax_registration_number'   => 'TRN-1',
]);

// Client-level settings (enabled = null inherits the company setting)
$clientSettings = $client->getClientEInvoiceSettings(clientId: '42');

$client->updateClientEInvoiceSettings('42', [
    'enabled'                 => true,
    'vat_id'                  => 'GB999',
    'buyer_reference_default' => 'PO-1234',
    'electronic_address_scheme' => '0088',
    'electronic_address'        => '5790000435975',
    'tax_registration_number'   => 'TRN-9',
]);
```

`EInvoiceFormat` cases: `EInvoiceFormat::ZugferdEn16931` (`'zugferd_en16931'`) and `EInvoiceFormat::UblPeppolBis3` (`'ubl_peppol_bis3'`).

### Error handling

```php
use ApexTechnology\TidyBill\Exceptions\TidyBillAuthException;
use ApexTechnology\TidyBill\Exceptions\TidyBillNotFoundException;
use ApexTechnology\TidyBill\Exceptions\TidyBillException;

try {
    $invoice = $client->getInvoice(999);
} catch (TidyBillNotFoundException $e) {
    // 404: invoice does not exist
    echo $e->getMessage();
    echo $e->statusCode;
} catch (TidyBillAuthException $e) {
    // 401 / 403: invalid token or insufficient permissions
} catch (TidyBillException $e) {
    // Any other API error (5xx, etc.)
    // $e->responseBody contains the raw response array
}
```

## DTO reference

### `CreateInvoiceData`

| Property | Type | Default | Description |
|---|---|---|---|
| `clientId` | `string` | required | TidyBill client ID |
| `issueDate` | `string` | required | ISO 8601 date (e.g. `2026-05-01`) |
| `currency` | `string` | `'ZAR'` | ISO 4217 currency code |
| `notes` | `?string` | `null` | Optional invoice notes |
| `lineItems` | `LineItemData[]` | `[]` | Line items to include on creation |

### `LineItemData`

| Property | Type | Description |
|---|---|---|
| `description` | `string` | Line item description |
| `quantity` | `int` | Number of units |
| `unitPrice` | `float` | Price per unit in the invoice currency (e.g. `1.50` for R1.50) |

> Unit prices are converted to integer cents before being sent to the API.

### `InvoiceResult`

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Invoice ID |
| `clientId` | `string` | Client ID |
| `status` | `string` | Invoice status |
| `issueDate` | `string` | Issue date |
| `currency` | `string` | Currency code |
| `total` | `float` | Invoice total |
| `lineItems` | `array` | Raw line items from the API |
| `raw` | `array` | Full raw response |

### `LineItemResult`

| Property | Type | Description |
|---|---|---|
| `id` | `int` | Line item ID |
| `description` | `string` | Description |
| `quantity` | `int` | Quantity |
| `unitPrice` | `float` | Unit price in currency's major unit |
| `total` | `float` | Line item total |

### `ClientData`

| Property | Type | Description |
|---|---|---|
| `id` | `string` | Client ID |
| `name` | `string` | Client name |
| `email` | `?string` | Client email |
| `raw` | `array` | Full raw response |

## License

MIT
