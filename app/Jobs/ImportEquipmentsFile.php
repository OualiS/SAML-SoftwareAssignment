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
        if (!is_file($this->absolutePath)) {
            throw new RuntimeException("File not found: {$this->absolutePath}");
        }

        $lines = file($this->absolutePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || $lines === []) {
            throw new RuntimeException("Unable to read file: {$this->absolutePath}");
        }

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

        if ($headers === []) {
            throw new RuntimeException('Unable to parse header columns from equipments file.');
        }

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

        // sql query to store data in equipments table
        if ($parsedRows === []) {
            Log::warning('Equipments import produced no parsed rows.', [
                'file' => $this->filename,
            ]);
            return;
        }

        $get = static function (array $row, string $name): ?string {
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
        };

        $getAll = static function (array $row, string $name): array {
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
        };

        $toDate = static function (?string $raw): ?string {
            $raw = trim((string) $raw);
            if ($raw === '' || $raw === '00.00.0000') {
                return null;
            }

            $date = \DateTimeImmutable::createFromFormat('d.m.Y', $raw);
            if ($date === false) {
                return null;
            }

            return $date->format('Y-m-d');
        };

        $toTimestamp = static function (?string $raw): ?string {
            $raw = trim((string) $raw);
            if ($raw === '' || $raw === '00.00.0000') {
                return null;
            }

            $date = \DateTimeImmutable::createFromFormat('d.m.Y', $raw);
            if ($date === false) {
                return null;
            }

            return $date->format('Y-m-d 00:00:00');
        };

        $toFloat = static function (?string $raw): ?float {
            $raw = trim((string) $raw);
            if ($raw === '') {
                return null;
            }

            if (str_contains($raw, ',')) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            }

            return is_numeric($raw) ? (float) $raw : null;
        };

        $toInt = static function (?string $raw): int {
            $raw = trim((string) $raw);
            if ($raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
                return 0;
            }

            return (int) $raw;
        };

        $rowsForUpsert = [];

        foreach ($parsedRows as $row) {
            $equipment = $get($row, 'Equipment');
            if ($equipment === null) {
                continue;
            }

            $material = $get($row, 'Material');
            $oldMaterial = $get($row, 'Old material no.');
            $materialWithoutFet = $oldMaterial ?: (preg_replace('/-FET$/', '', (string) $material) ?: $equipment);

            $stats = $getAll($row, 'Stat');
            $userStatus = $stats[0] ?? '';
            $systemStatus = $stats[1] ?? ($stats[0] ?? '');

            $shortDescription = $get($row, 'Short description');
            $shortDesc = $get($row, 'Short desc.');

            $createdBy = $get($row, 'Created By') ?? '';
            $changedBy = $get($row, 'Changed by') ?? '';
            $workcenter = $get($row, 'WkCtr') ?? $get($row, 'Work ctr') ?? $get($row, 'WorkCtr') ?? '';
            $materialStatus = $get($row, 'MS') ?? '';

            $currentStatus = trim($userStatus . ' ' . $systemStatus);
            if ($currentStatus === '') {
                $currentStatus = (string) ($shortDesc ?? $shortDescription ?? '');
            }

            $rowsForUpsert[] = [
                'Equipment' => $equipment,
                'Material' => $material,
                'MaterialWithoutFet' => $materialWithoutFet,
                'Description' => $get($row, 'Material Description'),
                'IH09Description' => $get($row, 'Description of Technical Object'),
                'Room' => $get($row, 'Room'),
                'Plant' => $get($row, 'Plnt'),
                'Location' => $get($row, 'Location'),
                'Sloc' => $get($row, 'SLoc'),
                'SuperEq' => $get($row, 'Superord.Equipment'),
                'ManufactSerialNumber' => $get($row, 'ManufactSerialNumber') ?? $get($row, 'Serial Number') ?? $equipment,
                'SerNo' => $get($row, 'Serial Number'),
                'UserStatus' => $userStatus,
                'SystemStatus' => $systemStatus,
                'Dimensions' => $get($row, 'Size/dimensions'),
                'CleaningCounter_limit' => 0,
                'CleaningCounter_current' => 0,
                'ToolCompetence' => (string) ($shortDescription ?? $shortDesc ?? ''),
                'NextCertDate' => null,
                'NextCalDate' => null,
                'NextCtrlDate' => null,
                'NEN3140Int' => 0,
                'MaintInt' => $toInt($get($row, 'PP')),
                'NextNEN3140Date' => null,
                'CalInt' => 0,
                'NextMaintDate' => null,
                'CertInt' => 0,
                'CtrlInt' => 0,
                'ExempEndDate' => $toDate($get($row, 'to')),
                'Min_CALD_Date' => $toDate($get($row, 'Valid From')),
                'GrossWeight' => $toFloat($get($row, 'Gross Weight')),
                'current_status' => $currentStatus,
                'needed_time' => null,
                'return_time' => null,
                'workcenter' => $workcenter,
                'material_status' => $materialStatus,
                'StockType' => $get($row, 'S'),
                'SpecialStock' => null,
                'CreatedOn' => $toTimestamp($get($row, 'Created On')),
                'CreatedBy' => $createdBy,
                'ChangedOn' => $toTimestamp($get($row, 'Chngd On')),
                'ChangedBy' => $changedBy,
            ];
        }

        if ($rowsForUpsert === []) {
            Log::warning('Equipments import has no rows to store.', [
                'file' => $this->filename,
            ]);
            return;
        }

        $updateColumns = array_values(array_filter(
            array_keys($rowsForUpsert[0]),
            static fn(string $column): bool => $column !== 'Equipment'
        ));

        DB::transaction(function () use ($rowsForUpsert, $updateColumns): void {
            foreach (array_chunk($rowsForUpsert, 500) as $chunk) {
                DB::table('equipments')->upsert($chunk, ['Equipment'], $updateColumns);
            }
        });

        Cache::put('equipments:last_imported_file', $this->filename);

        Log::info('Equipments import done.', [
            'file' => $this->filename,
            'rows' => count($rowsForUpsert),
        ]);
    }
}
