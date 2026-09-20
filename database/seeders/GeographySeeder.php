<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use JsonException;
use RuntimeException;

class GeographySeeder extends Seeder
{
    public function run(): void
    {
        $base = database_path('../data/geography');
        $stateFile = $base.'/kerala-locations.json';
        $bodiesFile = $base.'/local_bodies.csv';
        $wardsFile = $base.'/wards.csv';

        foreach ([$stateFile, $bodiesFile, $wardsFile] as $f) {
            if (! File::exists($f)) {
                throw new RuntimeException("Kerala geography source file missing: {$f}");
            }
        }

        $stateName = (string) config('jannayaks.geography.current_state_name', 'Keralam');
        $countryCode = (string) config('jannayaks.geography.current_country_code', 'IN');
        $countryName = (string) config('jannayaks.geography.current_country_name', 'India');

        DB::table('geo_states')->insertOrIgnore([
            'code' => $countryCode,
            'name' => $stateName,
            'country_name' => $countryName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('geo_states')
            ->where('code', $countryCode)
            ->where('name', '!=', $stateName)
            ->update([
                'name' => $stateName,
                'updated_at' => now(),
            ]);

        try {
            $state = json_decode(File::get($stateFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("Invalid kerala-locations.json: {$e->getMessage()}");
        }

        if (! isset($state['districts']) || ! is_array($state['districts'])) {
            throw new RuntimeException('kerala-locations.json must contain districts[]');
        }

        $districtIdsBySrc = [];
        foreach ($state['districts'] as $d) {
            $name = trim((string) $d['name']);
            $sourceCode = strtoupper($name);
            DB::table('geo_districts')->insertOrIgnore([
                'state_code' => $countryCode,
                'name' => $name,
                'source_code' => $sourceCode,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $id = DB::table('geo_districts')
                ->where('state_code', $countryCode)
                ->where('source_code', $sourceCode)
                ->value('id');
            if ($id === null) {
                throw new RuntimeException("District {$name} not inserted (source_code {$sourceCode})");
            }
            $districtIdsBySrc[$sourceCode] = (int) $id;
        }
        if (count($districtIdsBySrc) !== 14) {
            throw new RuntimeException('Expected 14 districts in kerala-locations.json, got '.count($districtIdsBySrc));
        }

        $typeMap = [
            'Grama Panchayat' => 'grama_panchayat',
            'Block Panchayat' => 'block_panchayat',
            'District Panchayat' => 'district_panchayat',
            'Municipality' => 'municipality',
            'Municipal Corporation' => 'municipal_corporation',
        ];

        $bodyInserts = [];
        $bodyRows = iterator_to_array($this->readCsvAssoc($bodiesFile));
        foreach ($bodyRows as $row) {
            $srcType = trim((string) ($row['Local Body Type'] ?? ''));
            $bodyCode = trim((string) ($row['Local Body Code'] ?? ''));
            $bodyName = trim((string) ($row['Local Body Name'] ?? ''));
            $districtSrc = strtoupper(trim((string) ($row['District'] ?? '')));

            if ($bodyCode === '' || $bodyName === '' || $districtSrc === '') {
                continue;
            }
            if (! isset($typeMap[$srcType])) {
                throw new RuntimeException("Unknown local body type in CSV: {$srcType} (code {$bodyCode})");
            }
            if (! isset($districtIdsBySrc[$districtSrc])) {
                $matchedSrc = null;
                $nNeedle = self::normalizeKey($districtSrc);
                foreach (array_keys($districtIdsBySrc) as $s) {
                    $n = self::normalizeKey($s);
                    if ($n === $nNeedle) {
                        $matchedSrc = $s;
                        break;
                    }
                    if (
                        ($n === 'KASARAGOD' || $n === 'KASARGOD')
                        && ($nNeedle === 'KASARAGOD' || $nNeedle === 'KASARGOD')
                    ) {
                        $matchedSrc = $s;
                        break;
                    }
                }
                if ($matchedSrc === null) {
                    $debug = [];
                    foreach (array_keys($districtIdsBySrc) as $s) {
                        $debug[] = "{$s}[".self::dumpBytes(self::normalizeKey($s)).']';
                    }
                    throw new RuntimeException(
                        "District '{$districtSrc}'[".self::dumpBytes($nNeedle)."] in CSV row body_code={$bodyCode} not matched. Known: ".implode(' ', $debug)
                    );
                }
                $districtSrc = $matchedSrc;
            }
            $enumType = $typeMap[$srcType];
            $districtId = $districtIdsBySrc[$districtSrc];
            $wardCount = (int) ($row['Ward Count'] ?? 0);

            $bodyInserts[] = [
                'district_id' => $districtId,
                'body_code' => $bodyCode,
                'type' => $enumType,
                'name' => $bodyName,
                'ward_count' => $wardCount,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        if (count($bodyInserts) !== 1200) {
            throw new RuntimeException('Expected 1200 local bodies in CSV, got '.count($bodyInserts));
        }
        foreach (array_chunk($bodyInserts, 500) as $chunk) {
            DB::table('geo_local_bodies')->upsert(
                $chunk,
                ['body_code'],
                ['district_id', 'type', 'name', 'ward_count', 'updated_at']
            );
        }

        $bodyCodeToId = DB::table('geo_local_bodies')
            ->select(['id', 'body_code'])
            ->get()
            ->pluck('id', 'body_code')
            ->all();
        if (count($bodyCodeToId) !== 1200) {
            throw new RuntimeException('After upsert expected 1200 local body rows, got '.count($bodyCodeToId));
        }

        $wardsExisting = DB::table('geo_wards')->pluck('id', 'ward_code')->all();
        $chunkSize = 1000;
        $wardChunks = [];
        $totalInCsv = 0;
        $wardsSeen = [];

        $temporaryWardNameOverrides = [
            'B05049005' => 'A',
            'B05049009' => 'B',
            'B05049010' => 'C',
            'B05049011' => 'D',
            'B05049013' => 'E',
        ];

        foreach ($this->readCsvAssoc($wardsFile) as $row) {
            $bodyCode = trim((string) ($row['Local Body Code'] ?? ''));
            $wardCode = trim((string) ($row['Ward Code'] ?? ''));
            $wardName = trim((string) ($row['Ward Name'] ?? ''));
            if ($bodyCode === '' || $wardCode === '') {
                continue;
            }
            $totalInCsv++;
            if (! isset($bodyCodeToId[$bodyCode])) {
                throw new RuntimeException("Ward {$wardCode} references unknown local body {$bodyCode}");
            }
            if (isset($wardsSeen[$wardCode])) {
                throw new RuntimeException("Duplicate ward_code in source CSV: {$wardCode}");
            }
            $wardsSeen[$wardCode] = true;
            if (isset($wardsExisting[$wardCode])) {
                continue;
            }

            if (isset($temporaryWardNameOverrides[$wardCode])) {
                $wardName = $temporaryWardNameOverrides[$wardCode];
            }

            $m = (int) ($row['Males'] ?? 0);
            $f = (int) ($row['Females'] ?? 0);
            $o = (int) ($row['Others'] ?? 0);
            $t = (int) ($row['Total'] ?? 0);

            $wardChunks[] = [
                'local_body_id' => (int) $bodyCodeToId[$bodyCode],
                'ward_code' => $wardCode,
                'name' => $wardName,
                'pop_male' => $m,
                'pop_female' => $f,
                'pop_other' => $o,
                'pop_total' => $t,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($wardChunks) >= $chunkSize) {
                DB::table('geo_wards')->insert($wardChunks);
                $wardChunks = [];
            }
        }
        if (! empty($wardChunks)) {
            DB::table('geo_wards')->insert($wardChunks);
        }
        if ($totalInCsv !== 23611) {
            throw new RuntimeException("Expected 23,611 wards in source CSV, got {$totalInCsv}");
        }
    }

    private function readCsvAssoc(string $path): iterable
    {
        $f = fopen($path, 'rb');
        if ($f === false) {
            throw new RuntimeException("Cannot open CSV {$path}");
        }
        $headers = fgetcsv($f);
        if ($headers === false) {
            fclose($f);
            throw new RuntimeException("Empty CSV {$path}");
        }
        $headers = array_map(static fn ($v) => trim((string) $v), $headers);

        while (($row = fgetcsv($f)) !== false) {
            if (count($row) === 1 && ($row[0] === null || $row[0] === '')) {
                continue;
            }
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), null);
            } elseif (count($row) > count($headers)) {
                $row = array_slice($row, 0, count($headers));
            }
            $row = array_map(static fn ($v) => is_string($v) ? trim($v) : $v, $row);
            $assoc = [];
            foreach ($headers as $idx => $h) {
                $assoc[$h] = $row[$idx];
            }
            yield $assoc;
        }
        fclose($f);
    }

    private static function normalizeKey(string $k): string
    {
        $k = strtoupper(trim($k));
        $k = strtr($k, [
            "\xE2\x80\x8B" => '',
            "\xC2\xA0" => ' ',
            "\r" => '',
            "\n" => '',
            "\t" => ' ',
        ]);
        $k = preg_replace('/[^A-Z]/', '', $k);

        return $k;
    }

    private static function dumpBytes(string $s): string
    {
        $out = '';
        for ($i = 0, $l = strlen($s); $i < $l; $i++) {
            $out .= sprintf('%02X.', ord($s[$i]));
        }

        return rtrim($out, '.');
    }
}
