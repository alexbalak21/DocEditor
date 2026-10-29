<?php

namespace App\Services;

use App\Models\Document;
use App\Repositories\DocumentRepository;
use App\Repositories\CustomerRepository;
use App\Core\Database;

class DocumentService
{
    public function __construct(
        private DocumentRepository $docs     = new DocumentRepository(),
        private CustomerRepository $customers = new CustomerRepository(),
    ) {}

    public function list(string $search = '', string $type = '', string $status = '', string $from = '', string $to = ''): array
    {
        return array_map(
            fn(Document $d) => $d->toSummary(),
            $this->docs->findAll($search, $type, $status, $from, $to)
        );
    }

    public function get(int $id): ?Document
    {
        return $this->docs->findById($id);
    }

    public function create(array $data): int
    {
        $doc = new Document(
            id:       null,
            type:     strtoupper(trim($data['type']              ?? 'INVOICE')),
            number:   trim($data['number']                       ?? ''),
            date:     self::validDate($data['date'] ?? null) ?? date('Y-m-d'),
            status:   'draft',
            customer: trim($data['customer']['name']             ?? ''),
            data:     $data,
        );
        $id = $this->docs->create($doc);
        $this->upsertCustomer($data['customer'] ?? []);
        return $id;
    }

    public function update(int $id, array $data, string $status = 'draft'): void
    {
        $doc = new Document(
            id:       $id,
            type:     strtoupper(trim($data['type']              ?? 'INVOICE')),
            number:   trim($data['number']                       ?? ''),
            date:     self::validDate($data['date'] ?? null) ?? date('Y-m-d'),
            status:   $status,
            customer: trim($data['customer']['name']             ?? ''),
            data:     $data,
        );
        $this->docs->update($id, $doc);
        $this->upsertCustomer($data['customer'] ?? []);
    }

    private static function validDate(mixed $v): ?string
    {
        if (!is_string($v) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})/', trim($v), $m)) return null;
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? "$m[1]-$m[2]-$m[3]" : null;
    }

    private static function validDateTime(mixed $v): ?string
    {
        if (!is_string($v) || trim($v) === '') return null;
        $t = strtotime(trim($v));
        return $t === false ? null : date('Y-m-d H:i:s', $t);
    }

    /** Everything, in the file format used by Export / Import. */
    public function exportAll(): array
    {
        $documents = array_map(fn(Document $d) => [
            'id' => $d->id, 'status' => $d->status,
            'created_at' => $d->createdAt, 'updated_at' => $d->updatedAt,
            'data' => $d->data,
        ], $this->docs->findAllFull());

        return [
            'format' => 'doceditor-export', 'version' => 1,
            'exported_at' => date('c'), 'count' => count($documents),
            'documents' => $documents,
        ];
    }

    /**
     * Bulk import (documents exported from the old version, or from this one).
     * Each entry is {data, status?, created_at?, updated_at?} or a bare document object.
     * A document whose type + number already exist is skipped, so re-running is safe.
     * Customers are added to the customer list only when missing (existing ones are not overwritten).
     *
     * @return array{imported:int, skipped:int, failed:int, errors:string[]}
     */
    public function importMany(array $entries): array
    {
        $res = ['imported' => 0, 'skipped' => 0, 'failed' => 0, 'errors' => []];

        foreach ($entries as $i => $entry) {
            $label = '#' . ($i + 1);
            try {
                if (!is_array($entry)) throw new \InvalidArgumentException('not an object');
                $data = isset($entry['data']) && is_array($entry['data']) ? $entry['data'] : $entry;
                if (!isset($data['items']) && !isset($data['customer']) && !isset($data['type'])) {
                    throw new \InvalidArgumentException('not a document');
                }
                $type   = strtoupper(trim((string)($data['type'] ?? 'INVOICE'))) ?: 'INVOICE';
                $number = trim((string)($data['number'] ?? ''));
                $label  = $number !== '' ? "$type $number" : $label;
                $data['type'] = $type;

                // old documents had a single service_date -> new start/end fields
                if (!empty($data['service_date']) && empty($data['service_date_start'])) {
                    $data['service_date_start'] = $data['service_date'];
                }
                unset($data['service_date']);
                $data += ['service_date_start' => '', 'service_date_end' => '', 'po_number' => ''];

                if ($number !== '' && $this->docs->findIdByTypeAndNumber($type, $number) !== null) {
                    $res['skipped']++;
                    continue;
                }

                $created = self::validDateTime($entry['created_at'] ?? null) ?? date('Y-m-d H:i:s');
                $updated = self::validDateTime($entry['updated_at'] ?? null) ?? $created;
                $status  = in_array($entry['status'] ?? '', ['draft', 'final', 'paid'], true) ? $entry['status'] : 'draft';
                $date    = self::validDate($data['date'] ?? null) ?? substr($created, 0, 10);
                $data['date'] = $date;
                $cust    = is_array($data['customer'] ?? null) ? $data['customer'] : [];

                $this->docs->createWithDates(new Document(
                    id: null, type: $type, number: $number, date: $date, status: $status,
                    customer: trim((string)($cust['name'] ?? '')), data: $data,
                    createdAt: $created, updatedAt: $updated,
                ));
                $this->createCustomerIfMissing($cust);
                $res['imported']++;
            } catch (\Throwable $e) {
                $res['failed']++;
                $res['errors'][] = "$label: " . $e->getMessage();
            }
        }
        return $res;
    }

    private function createCustomerIfMissing(array $cust): void
    {
        $name = trim((string)($cust['name'] ?? ''));
        if ($name === '' || $this->customers->findByName($name)) return;
        $this->customers->create(new \App\Models\Customer(
            id: null, name: $name,
            address: trim((string)($cust['address'] ?? '')), city: trim((string)($cust['city'] ?? '')),
            contact: trim((string)($cust['contact'] ?? '')), phone: trim((string)($cust['phone'] ?? '')),
            vat: trim((string)($cust['vat'] ?? '')),
        ));
    }

    public function delete(int $id): void
    {
        $this->docs->delete($id);
    }

    public function duplicate(int $id): int
    {
        return $this->docs->duplicate($id);
    }

    /**
     * Turn a quote into a new draft invoice (the quote itself is left untouched).
     * Customer, items, VAT, currency, notes, bank details and the PO number are carried over;
     * the invoice gets its own number and today's date, and quote_ref points back to the quote.
     * If an invoice already references this quote and $force is false, nothing is created and
     * a RuntimeException('already_converted:INV-1,INV-2') is thrown so the UI can ask first.
     *
     * @return array{id:int, number:string}
     */
    public function convertQuoteToInvoice(int $id, bool $force = false): array
    {
        $quote = $this->docs->findById($id);
        if (!$quote) throw new \InvalidArgumentException('Document not found');
        if ($quote->type !== 'QUOTE') throw new \InvalidArgumentException('Only a quote can be converted to an invoice');

        if (!$force && $quote->number !== '') {
            $existing = $this->docs->findInvoiceNumbersByQuoteRef($quote->number);
            if ($existing) throw new \RuntimeException('already_converted:' . implode(',', $existing));
        }

        $number = $this->nextNumber('INVOICE')['number'];
        $today  = date('Y-m-d');

        $data = $quote->data;
        $data['type']             = 'INVOICE';
        $data['number']           = $number;
        $data['date']             = $today;
        $data['due_date']         = '';
        $data['quote_ref']        = $quote->number;
        $data['po_number']        = (string) ($data['po_number'] ?? '');
        $data['tracking']         = '';
        $data['amount_paid']      = 0;
        $data['show_amount_paid'] = false;

        $newId = $this->docs->create(new Document(
            id: null, type: 'INVOICE', number: $number, date: $today, status: 'draft',
            customer: $quote->customer, data: $data,
        ));
        return ['id' => $newId, 'number' => $number];
    }

    public function defaultTemplate(string $type = 'INVOICE'): array
    {
        return [
            'type'         => $type,
            'number'       => '',
            'date'         => date('Y-m-d'),
            'due_date'     => '',
            'service_date_start' => '',
            'service_date_end'   => '',
            'po_number'          => '',
            'quote_ref'    => '',
            'tracking'     => '',
            'issuer' => [
                'name'        => 'NOVOCIB SAS',
                'address'     => 'BD de Chatillon, Quai Jean Voisin',
                'city'        => '62200 Boulogne-sur-Mer — France',
                'email'       => 'lbalakireva@novocib.com',
                'legal'       => "SAS, société par actions simplifiée — Share capital: 260 158,00 €\nEORI# FR48237937700047\nVAT# FR90 482 379 377",
                'logo_base64' => '',
            ],
            'customer' => [
                'name' => '', 'address' => '', 'city' => '',
                'contact' => '', 'phone' => '', 'vat' => '',
            ],
            'currency'        => 'EUR',
            'currency_symbol' => '€',
            'items'           => [],
            'vat_rate'        => 0,
            'amount_paid'     => 0,
            'show_amount_paid'=> false,
            'balance_label'   => 'TOTAL DUE',
            'vat_mention'     => '',
            'notes'           => '',
            'terms'           => 'PAYMENT IMMEDIATE UPON RECEIPT, by credit card or wire transfer.',
            'bank' => [
                'label'        => 'Bank Details EUR - Banque Populaire, France',
                'beneficiary'  => 'SAS NOVOCIB',
                'bank_name'    => 'BANQUE POPULAIRE AUVERGNE RHÔNE ALPES (BPAURA)',
                'bank_address' => '215 Avenue Jean Jaurès, 69007 Lyon, France',
                'iban'         => 'FR76 1680 7004 0081 0876 0421 151',
                'bic'          => 'CCBPFRPPGRE',
            ],
            'footer_thanks'  => 'Thank you for your business!',
            'footer_contact' => "If you have any questions, please contact us.\n• lbalakireva@novocib.com",
        ];
    }

    // ── Document numbering ────────────────────────────────────

    private static array $prefixMap = [
        'INVOICE'        => 'INV',
        'QUOTE'          => 'QUO',
        'CREDIT NOTE'    => 'CRN',
        'OTHER'          => 'DOC',
        'PROFORMA'       => 'PRO',
        'DELIVERY NOTE'  => 'DLV',
    ];

    public function typeToPrefix(string $type): string
    {
        $type = strtoupper(trim($type));
        if (isset(self::$prefixMap[$type])) return self::$prefixMap[$type];
        $clean = preg_replace('/[^A-Z]/', '', $type);
        return $clean !== '' ? substr($clean, 0, 3) : 'DOC';
    }

    public function nextNumber(string $type): array
    {
        $prefix  = $this->typeToPrefix($type);
        $dateKey = date('Ymd');
        $db      = Database::getInstance();

        // Atomic increment: LAST_INSERT_ID(expr) hands back this connection's own new counter value,
        // so two simultaneous requests can never receive the same number.
        $db->prepare('
            INSERT INTO document_numbers (prefix, date_key, counter)
            VALUES (?, ?, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE counter = LAST_INSERT_ID(counter + 1)
        ')->execute([$prefix, $dateKey]);

        $counter = (int) $db->query('SELECT LAST_INSERT_ID()')->fetchColumn();

        return ['number' => "$prefix-$dateKey-$counter", 'prefix' => $prefix, 'counter' => $counter];
    }

    public function peekNumber(string $type): array
    {
        $prefix  = $this->typeToPrefix($type);
        $dateKey = date('Ymd');
        $db      = Database::getInstance();

        $stmt = $db->prepare('SELECT counter FROM document_numbers WHERE prefix = ? AND date_key = ?');
        $stmt->execute([$prefix, $dateKey]);
        $current = $stmt->fetchColumn();
        $counter = $current !== false ? (int)$current + 1 : 1;

        return ['number' => "$prefix-$dateKey-$counter", 'prefix' => $prefix, 'counter' => $counter];
    }

    // ── Auto-upsert customer from document ────────────────────
    private function upsertCustomer(array $cust): void
    {
        $name = trim($cust['name'] ?? '');
        if ($name === '') return;

        $existing = $this->customers->findByName($name);
        $model    = new \App\Models\Customer(
            id:      null,
            name:    $name,
            address: trim($cust['address'] ?? ''),
            city:    trim($cust['city']    ?? ''),
            contact: trim($cust['contact'] ?? ''),
            phone:   trim($cust['phone']   ?? ''),
            vat:     trim($cust['vat']     ?? ''),
        );

        if ($existing) {
            $this->customers->update($existing->id, $model);
        } else {
            $this->customers->create($model);
        }
    }
}
