<?php

namespace App\Services\Clinic\Settings;

use App\Http\Resources\Clinic\Settings\SyndicatePriceResource;
use App\Models\SyndicatePrice;
use App\Support\ServiceResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;
use ZipArchive;

class SyndicatePriceService
{
    public function index(int $year): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $rows = SyndicatePrice::query()
            ->where('clinic_id', $clinicId)
            ->where('year', $year)
            ->orderBy('category')
            ->orderBy('service_name')
            ->get();

        return ServiceResult::success([
            'year' => $year,
            'is_active_year' => $rows->contains(fn (SyndicatePrice $row) => $row->is_active_year),
            'last_updated' => optional($rows->sortByDesc('updated_at')->first()?->updated_at)->toISOString(),
            'items' => SyndicatePriceResource::collection($rows)->resolve(),
        ], 'Syndicate prices fetched successfully');
    }

    public function store(array $data): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $row = DB::transaction(function () use ($clinicId, $data) {
            if ($data['is_active_year'] ?? false) {
                $this->activateYear($clinicId, (int) $data['year']);
            }

            $payload = [
                'clinic_id' => $clinicId,
                'year' => (int) $data['year'],
                'code' => $data['code'] ?? null,
                'service_name' => $data['service_name'],
                'category' => $data['category'] ?? null,
                'price' => $data['price'],
                'is_active_year' => (bool) ($data['is_active_year'] ?? false),
                'created_by' => auth()->id(),
            ];

            if (empty($data['code'])) {
                return SyndicatePrice::query()->create($payload);
            }

            return SyndicatePrice::query()->updateOrCreate(
                [
                    'clinic_id' => $clinicId,
                    'year' => (int) $data['year'],
                    'code' => $data['code'],
                ],
                [
                    'service_name' => $data['service_name'],
                    'category' => $data['category'] ?? null,
                    'price' => $data['price'],
                    'is_active_year' => (bool) ($data['is_active_year'] ?? false),
                    'created_by' => auth()->id(),
                ]
            );
        });

        return ServiceResult::success((new SyndicatePriceResource($row))->resolve(), 'Syndicate service added successfully', 201);
    }

    public function preview(UploadedFile $file, int $year): array
    {
        $parsed = $this->parseUploadedFile($file);
        if (! $parsed['success']) {
            return $parsed;
        }

        return ServiceResult::success([
            'year' => $year,
            'rows_count' => count($parsed['data']),
            'items' => $parsed['data'],
        ], 'Syndicate prices import preview generated successfully');
    }

    public function confirm(UploadedFile $file, int $year, bool $activeYear = false): array
    {
        $clinicId = $this->currentClinicId();
        if (! $clinicId) {
            return ServiceResult::error('Clinic account is not linked to a clinic.', null, null, 403);
        }

        $parsed = $this->parseUploadedFile($file);
        if (! $parsed['success']) {
            return $parsed;
        }

        DB::transaction(function () use ($activeYear, $clinicId, $parsed, $year) {
            if ($activeYear) {
                $this->activateYear($clinicId, $year);
            }

            foreach ($parsed['data'] as $row) {
                $payload = [
                    'clinic_id' => $clinicId,
                    'year' => $year,
                    'code' => $row['code'],
                    'service_name' => $row['service_name'],
                    'category' => $row['category'],
                    'price' => $row['price'],
                    'is_active_year' => $activeYear,
                    'created_by' => auth()->id(),
                ];

                if ($row['code'] === null) {
                    SyndicatePrice::query()->create($payload);
                    continue;
                }

                SyndicatePrice::query()->updateOrCreate(
                    [
                        'clinic_id' => $clinicId,
                        'year' => $year,
                        'code' => $row['code'],
                    ],
                    [
                        'service_name' => $row['service_name'],
                        'category' => $row['category'],
                        'price' => $row['price'],
                        'is_active_year' => $activeYear,
                        'created_by' => auth()->id(),
                    ]
                );
            }
        });

        return ServiceResult::success([
            'year' => $year,
            'imported_count' => count($parsed['data']),
            'is_active_year' => $activeYear,
        ], 'Syndicate prices imported successfully', 201);
    }

    private function parseUploadedFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'xlsx') {
            return $this->parseXlsx($file);
        }

        return $this->parseDelimitedFile($file);
    }

    private function parseDelimitedFile(UploadedFile $file): array
    {
        $lines = file($file->getRealPath(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (! $lines || count($lines) < 2) {
            return ServiceResult::error('Import file has no data rows.', null, null, 422);
        }

        $delimiter = str_contains($lines[0], "\t") ? "\t" : ',';
        $rows = array_map(fn (string $line) => str_getcsv($line, $delimiter), $lines);

        return $this->mapRows($rows);
    }

    private function parseXlsx(UploadedFile $file): array
    {
        if (! class_exists(ZipArchive::class)) {
            return ServiceResult::error('XLSX import requires the PHP zip extension on the server.', null, ['file' => ['Enable ext-zip or upload CSV/XLS as delimited text.']], 422);
        }

        $zip = new ZipArchive();
        if ($zip->open($file->getRealPath()) !== true) {
            return ServiceResult::error('Unable to open XLSX file.', null, null, 422);
        }

        $sharedStrings = [];
        $shared = $zip->getFromName('xl/sharedStrings.xml');
        if ($shared !== false) {
            $xml = new SimpleXMLElement($shared);
            foreach ($xml->si as $item) {
                $sharedStrings[] = (string) ($item->t ?? $item->r->t ?? '');
            }
        }

        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheet === false) {
            return ServiceResult::error('Unable to find the first worksheet in XLSX file.', null, null, 422);
        }

        $xml = new SimpleXMLElement($sheet);
        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $values = [];
            foreach ($row->c as $cell) {
                $value = (string) $cell->v;
                if ((string) $cell['t'] === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $values[] = $value;
            }
            $rows[] = $values;
        }

        return $this->mapRows($rows);
    }

    private function mapRows(array $rows): array
    {
        $headers = array_map(fn ($header) => strtolower(trim((string) $header)), array_shift($rows));
        $lookup = [
            'service_name' => $this->findHeader($headers, ['service name', 'service_name', 'name']),
            'price' => $this->findHeader($headers, ['price', 'amount']),
            'code' => $this->findHeader($headers, ['service code', 'service_code', 'code']),
            'category' => $this->findHeader($headers, ['category']),
        ];

        if ($lookup['service_name'] === null || $lookup['price'] === null) {
            return ServiceResult::error('Import file must include Service Name and Price columns.', null, null, 422);
        }

        $items = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row[$lookup['service_name']] ?? ''));
            $price = trim((string) ($row[$lookup['price']] ?? ''));
            if ($name === '' && $price === '') {
                continue;
            }
            if ($name === '' || ! is_numeric($price)) {
                return ServiceResult::error('Invalid row in import file.', null, ['row' => ['Row ' . ($index + 2) . ' must include Service Name and numeric Price.']], 422);
            }

            $items[] = [
                'code' => $lookup['code'] !== null ? trim((string) ($row[$lookup['code']] ?? '')) ?: null : null,
                'service_name' => $name,
                'category' => $lookup['category'] !== null ? trim((string) ($row[$lookup['category']] ?? '')) ?: null : null,
                'price' => round((float) $price, 2),
            ];
        }

        return ServiceResult::success($items, 'Import rows parsed successfully');
    }

    private function findHeader(array $headers, array $names): ?int
    {
        foreach ($names as $name) {
            $index = array_search($name, $headers, true);
            if ($index !== false) {
                return $index;
            }
        }

        return null;
    }

    private function activateYear(int $clinicId, int $year): void
    {
        SyndicatePrice::query()->where('clinic_id', $clinicId)->update(['is_active_year' => false]);
        SyndicatePrice::query()->where('clinic_id', $clinicId)->where('year', $year)->update(['is_active_year' => true]);
    }

    private function currentClinicId(): ?int
    {
        return auth()->user()?->clinic_id;
    }
}
