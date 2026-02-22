<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class ImportEquipmentsFile implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1200;
    public int $uniqueFor = 3600;

    public function __construct(
        public string $absolutePath,
        public string $filename
    ) {
    }

    public function uniqueId(): string
    {
        return $this->filename;
    }

    public function handle(): void
    {
        $lines = $this->readLinesOrFail($this->absolutePath);
        $headers = self::extractHeaders($lines);

        if ($headers === []) {
            throw new RuntimeException('Unable to parse header columns from equipments file.');
        }

        $parsedRows = self::parseRows($lines, $headers);

        if ($parsedRows === []) {
            Log::warning('Equipments import produced no parsed rows.', [
                'file' => $this->filename,
            ]);
            return;
        }

        $rowsForUpsert = self::buildRowsForUpsert($parsedRows);

        if ($rowsForUpsert === []) {
            Log::warning('Equipments import has no rows to store.', [
                'file' => $this->filename,
            ]);
            return;
        }

        $this->upsertRows($rowsForUpsert);

        Cache::put('equipments:last_imported_file', $this->filename);

        Log::info('Equipments import done.', [
            'file' => $this->filename,
            'rows' => count($rowsForUpsert),
        ]);
    }

    private function readLinesOrFail(string $absolutePath): array
    {
        if (!is_file($absolutePath)) {
            throw new RuntimeException("File not found: {$absolutePath}");
        }

        $lines = file($absolutePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            throw new RuntimeException("Unable to read file: {$absolutePath}");
        }

        return $lines;
    }

    public static function extractHeaders(array &$lines): array
    {
        $headers = [];

        for ($headerLineIndex = 0; $headerLineIndex < count($lines); $headerLineIndex += 3) {
            $line1 = $lines[$headerLineIndex] ?? null;
            $line2 = $lines[$headerLineIndex + 1] ?? null;
            $line3 = $lines[$headerLineIndex + 2] ?? null;

            if (!$line1 || !$line2 || !$line3) {
                continue;
            }

            if (str_starts_with($line1, '|-')) { // detect header
                $lastHeaderNameEndIndex = 0;

                for ($j = 1; $j < 4; $j++) {
                    $lines[$headerLineIndex + $j] = substr($lines[$headerLineIndex + $j], 1, -6);
                }

                $fullHeaderLine = $lines[$headerLineIndex + 1] . $lines[$headerLineIndex + 2] . $lines[$headerLineIndex + 3];
                for ($k = 0; $k < strlen($fullHeaderLine); $k++) {
                    if ($k > 0 && $k < (strlen($fullHeaderLine) - 1)) {
                        if ($fullHeaderLine[$k] == ' ' && $fullHeaderLine[$k - 1] == ' ' && $fullHeaderLine[$k + 1] != ' ') {
                            $length = $k - $lastHeaderNameEndIndex;
                            $headerColumnName = trim(substr($fullHeaderLine, $lastHeaderNameEndIndex, $length));
                            $headerColumnSize = $k - $lastHeaderNameEndIndex;

                            if ($headerColumnName === '') {
                                $lastHeaderNameEndIndex = $k;
                                continue;
                            }

                            if (array_key_exists($headerColumnName, $headers)) {
                                $headers[$headerColumnName . '#' . $headerColumnSize] = $headerColumnSize;
                            } else {
                                $headers[$headerColumnName] = $headerColumnSize;
                            }

                            $lastHeaderNameEndIndex = $k;
                        }
                    }
                }

                break;
            }
        }

        return $headers;
    }

    public static function parseRows(array $lines, array $headers): array
    {
        $parsedRows = [];

        for ($contentLine = 5; $contentLine < count($lines); $contentLine += 3) {
            $contentLine1 = preg_replace('/^\||\|$/', '', $lines[$contentLine] ?? null);
            $contentLine2 = preg_replace('/^\||\|$/', '', $lines[$contentLine + 1] ?? null);
            $contentLine3 = preg_replace('/^\||\|$/', '', $lines[$contentLine + 2] ?? null);

            if (!$contentLine1 || !$contentLine2 || !$contentLine3) {
                continue;
            }

            if (
                str_starts_with(trim($contentLine1), '-') ||
                str_starts_with(trim($contentLine2), '-') ||
                str_starts_with(trim($contentLine3), '-')
            ) {
                continue;
            }

            $fullContentLine = $contentLine1 . $contentLine2 . $contentLine3;
            $previousHeaderValue = 0;
            $row = [];

            foreach ($headers as $headerKey => $headerValue) {
                $contentValue = substr($fullContentLine, $previousHeaderValue, $headerValue);
                $row[$headerKey] = trim((string) $contentValue);
                $previousHeaderValue += $headerValue;
            }

            if ($row !== []) {
                $parsedRows[] = $row;
            }
        }

        return $parsedRows;
    }

    public static function buildRowsForUpsert(array $parsedRows): array
    {
        $rowsForUpsert = [];

        foreach ($parsedRows as $row) {
            $equipment = self::getFirstValue($row, 'Equipment');
            if ($equipment === null) {
                continue;
            }

            $material = self::getFirstValue($row, 'Material');
            $oldMaterial = self::getFirstValue($row, 'Old material no.');
            $materialWithoutFet = $oldMaterial ?: (preg_replace('/-FET$/', '', (string) $material) ?: $equipment);

            $stats = self::getAllValues($row, 'Stat');
            $userStatus = $stats[0] ?? '';
            $systemStatus = $stats[1] ?? ($stats[0] ?? '');

            $shortDescription = self::getFirstValue($row, 'Short description');
            $shortDesc = self::getFirstValue($row, 'Short desc.');

            $createdBy = self::getFirstValue($row, 'Created By') ?? '';
            $changedBy = self::getFirstValue($row, 'Changed by') ?? '';
            $workcenter = self::getFirstValue($row, 'WkCtr') ?? self::getFirstValue($row, 'Work ctr') ?? self::getFirstValue($row, 'WorkCtr') ?? '';
            $materialStatus = self::getFirstValue($row, 'MS') ?? '';

            $currentStatus = trim($userStatus . ' ' . $systemStatus);
            if ($currentStatus === '') {
                $currentStatus = (string) ($shortDesc ?? $shortDescription ?? '');
            }

            $rowsForUpsert[] = [
                'Equipment' => $equipment,
                'Material' => $material,
                'MaterialWithoutFet' => $materialWithoutFet,
                'Description' => self::getFirstValue($row, 'Material Description'),
                'IH09Description' => self::getFirstValue($row, 'Description of Technical Object'),
                'Room' => self::getFirstValue($row, 'Room'),
                'Plant' => self::getFirstValue($row, 'Plnt'),
                'Location' => self::getFirstValue($row, 'Location'),
                'Sloc' => self::getFirstValue($row, 'SLoc'),
                'SuperEq' => self::getFirstValue($row, 'Superord.Equipment'),
                'ManufactSerialNumber' => self::getFirstValue($row, 'ManufactSerialNumber') ?? self::getFirstValue($row, 'Serial Number') ?? $equipment,
                'SerNo' => self::getFirstValue($row, 'Serial Number'),
                'UserStatus' => $userStatus,
                'SystemStatus' => $systemStatus,
                'Dimensions' => self::getFirstValue($row, 'Size/dimensions'),
                'CleaningCounter_limit' => 0,
                'CleaningCounter_current' => 0,
                'ToolCompetence' => (string) ($shortDescription ?? $shortDesc ?? ''),
                'NextCertDate' => null,
                'NextCalDate' => null,
                'NextCtrlDate' => null,
                'NEN3140Int' => 0,
                'MaintInt' => self::toInt(self::getFirstValue($row, 'PP')),
                'NextNEN3140Date' => null,
                'CalInt' => 0,
                'NextMaintDate' => null,
                'CertInt' => 0,
                'CtrlInt' => 0,
                'ExempEndDate' => self::toDate(self::getFirstValue($row, 'to')),
                'Min_CALD_Date' => self::toDate(self::getFirstValue($row, 'Valid From')),
                'GrossWeight' => self::toFloat(self::getFirstValue($row, 'Gross Weight')),
                'current_status' => $currentStatus,
                'needed_time' => null,
                'return_time' => null,
                'workcenter' => $workcenter,
                'material_status' => $materialStatus,
                'StockType' => self::getFirstValue($row, 'S'),
                'SpecialStock' => null,
                'CreatedOn' => self::toTimestamp(self::getFirstValue($row, 'Created On')),
                'CreatedBy' => $createdBy,
                'ChangedOn' => self::toTimestamp(self::getFirstValue($row, 'Chngd On')),
                'ChangedBy' => $changedBy,
            ];
        }

        return $rowsForUpsert;
    }

    public static function getFirstValue(array $row, string $name): ?string
    {
        foreach ($row as $key => $value) {
            $key = (string) $key;
            if ($key === $name || str_starts_with($key, $name . '#')) {
                $clean = trim((string) $value);
                if ($clean !== '') {
                    return $clean;
                }
            }
        }

        return null;
    }

    public static function getAllValues(array $row, string $name): array
    {
        $values = [];
        foreach ($row as $key => $value) {
            $key = (string) $key;
            if ($key === $name || str_starts_with($key, $name . '#')) {
                $clean = trim((string) $value);
                if ($clean !== '') {
                    $values[] = $clean;
                }
            }
        }

        return $values;
    }

    public static function toDate(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00.00.0000') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('d.m.Y', $raw);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d');
    }

    public static function toTimestamp(?string $raw): ?string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || $raw === '00.00.0000') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('d.m.Y', $raw);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d 00:00:00');
    }

    public static function toFloat(?string $raw): ?float
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        }

        return is_numeric($raw) ? (float) $raw : null;
    }

    public static function toInt(?string $raw): int
    {
        $raw = trim((string) $raw);
        if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
            return 0;
        }

        return (int) $raw;
    }

    private function upsertRows(array $rowsForUpsert): void
    {
        $updateColumns = array_values(array_filter(
            array_keys($rowsForUpsert[0]),
            static fn(string $column): bool => $column !== 'Equipment'
        ));

        DB::transaction(function () use ($rowsForUpsert, $updateColumns): void {
            foreach (array_chunk($rowsForUpsert, 500) as $chunk) {
                DB::table('equipments')->upsert($chunk, ['Equipment'], $updateColumns);
            }
        });
    }
}
