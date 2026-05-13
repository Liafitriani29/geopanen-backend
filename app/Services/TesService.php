<?php

namespace App\Services;

class TesService
{
    private function mean(array $data): float
    {
        return count($data) > 0 ? array_sum($data) / count($data) : 0;
    }

    private function getStatus(float $errorPercentage): string
    {
        if ($errorPercentage <= 10) {
            return 'Akurat';
        }

        if ($errorPercentage <= 20) {
            return 'Cukup';
        }

        return 'Perlu Evaluasi';
    }

    public function hitung(array $data, array $options = []): array
    {
        // Parameter disamakan dengan Excel
        $alpha = $options['alpha'] ?? 0.999;
        $beta = $options['beta'] ?? 0.60432745;
        $gamma = $options['gamma'] ?? 0.00100399;
        $seasonLength = $options['seasonLength'] ?? 12;
        $forecastPeriods = $options['forecastPeriods'] ?? 12;

        if (count($data) < $seasonLength * 2) {
            throw new \Exception('Data tidak cukup. Minimal diperlukan 2 tahun data bulanan.');
        }

        usort($data, function ($a, $b) {
            return strtotime($a['periode']) - strtotime($b['periode']);
        });

        $values = array_map(function ($item) {
            return (float) $item['produksi'];
        }, $data);

        $firstSeason = array_slice($values, 0, $seasonLength);
        $secondSeason = array_slice($values, $seasonLength, $seasonLength);

        $level = $this->mean($firstSeason);
        $trend = ($this->mean($secondSeason) - $this->mean($firstSeason)) / $seasonLength;

        $seasonal = [];

        foreach ($firstSeason as $value) {
            $seasonal[] = $value - $level;
        }

        $evaluasi = [];

        for ($t = $seasonLength; $t < count($values); $t++) {
            $seasonalIndex = $t % $seasonLength;
            $currentSeasonal = $seasonal[$seasonalIndex];

            // Forecast additive
            $prediksi = $level + $trend + $currentSeasonal;

            $aktual = $values[$t];
            $selisih = $aktual - $prediksi;

            $deviasi = $aktual != 0 ? ($selisih / $aktual) * 100 : 0;
            $deviasiAbs = abs($deviasi);
            $ape = $aktual != 0 ? abs($selisih / $aktual) * 100 : 0;

            $evaluasi[] = [
                'periode' => $data[$t]['periode'],
                'tahun' => (int) $data[$t]['tahun'],
                'bulan' => (int) $data[$t]['bulan'],
                'aktual' => round($aktual, 2),
                'prediksi' => round($prediksi, 2),
                'selisih' => round($selisih, 2),
                'deviasi' => round($deviasi, 2),
                'deviasiAbs' => round($deviasiAbs, 2),
                'ape' => round($ape, 2),
                'status' => $this->getStatus($ape),
            ];

            $previousLevel = $level;

            $level = $alpha * ($aktual - $currentSeasonal)
                + (1 - $alpha) * ($level + $trend);

            $trend = $beta * ($level - $previousLevel)
                + (1 - $beta) * $trend;

            $seasonal[$seasonalIndex] = $gamma * ($aktual - $level)
                + (1 - $gamma) * $currentSeasonal;
        }

        $prediksiMendatang = [];

        $lastDate = new \DateTime($data[count($data) - 1]['periode']);

        for ($h = 1; $h <= $forecastPeriods; $h++) {
            $seasonalIndex = (count($values) + $h - 1) % $seasonLength;

            $nilaiPrediksi = $level + ($h * $trend) + $seasonal[$seasonalIndex];

            $nextDate = clone $lastDate;
            $nextDate->modify("+{$h} month");

            $prediksiMendatang[] = [
                'periode' => $nextDate->format('Y-m-d'),
                'tahun' => (int) $nextDate->format('Y'),
                'bulan' => (int) $nextDate->format('m'),
                'prediksi' => round($nilaiPrediksi, 2),
            ];
        }

        $mape = count($evaluasi) > 0
            ? array_sum(array_column($evaluasi, 'ape')) / count($evaluasi)
            : 0;

        $rataRataDeviasi = count($evaluasi) > 0
            ? array_sum(array_column($evaluasi, 'deviasiAbs')) / count($evaluasi)
            : 0;

        $estimasiAkurasi = max(0, 100 - $mape);

        return [
            'parameter' => [
                'alpha' => $alpha,
                'beta' => $beta,
                'gamma' => $gamma,
                'seasonLength' => $seasonLength,
                'forecastPeriods' => $forecastPeriods,
            ],
            'ringkasan' => [
                'jumlahDataHistoris' => count($values),
                'jumlahDataEvaluasi' => count($evaluasi),
                'rataRataDeviasi' => round($rataRataDeviasi, 2),
                'mape' => round($mape, 2),
                'estimasiAkurasi' => round($estimasiAkurasi, 2),
            ],
            'evaluasi' => $evaluasi,
            'prediksiMendatang' => $prediksiMendatang,
        ];
    }
}