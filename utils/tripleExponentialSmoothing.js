function mean(arr) {
  return arr.reduce((total, value) => total + value, 0) / arr.length;
}

function getStatus(deviasiAbs) {
  if (deviasiAbs <= 10) return "Akurat";
  if (deviasiAbs <= 20) return "Cukup";
  return "Perlu Evaluasi";
}

export function tripleExponentialSmoothing(data, options = {}) {
  const alpha = options.alpha ?? 0.3;
  const beta = options.beta ?? 0.2;
  const gamma = options.gamma ?? 0.4;
  const seasonLength = options.seasonLength ?? 12;
  const forecastPeriods = options.forecastPeriods ?? 12;

  if (!Array.isArray(data) || data.length < seasonLength * 2) {
    throw new Error("Data tidak cukup. Minimal diperlukan 2 tahun data bulanan.");
  }

  const sortedData = [...data].sort((a, b) => {
    return new Date(a.periode) - new Date(b.periode);
  });

  const values = sortedData.map((item) => Number(item.produksi));

  const firstSeason = values.slice(0, seasonLength);
  const secondSeason = values.slice(seasonLength, seasonLength * 2);

  let level = mean(firstSeason);
  let trend = (mean(secondSeason) - mean(firstSeason)) / seasonLength;

  const seasonal = firstSeason.map((value) => value - level);

  const evaluasi = [];

  for (let t = seasonLength; t < values.length; t++) {
    const seasonalIndex = t % seasonLength;
    const currentSeasonal = seasonal[seasonalIndex];

    const prediksi = level + trend + currentSeasonal;
    const aktual = values[t];

    const selisih = aktual - prediksi;
    const deviasi = (selisih / prediksi) * 100;
    const deviasiAbs = Math.abs(deviasi);

    const ape = aktual !== 0 ? Math.abs(selisih / aktual) * 100 : 0;

    evaluasi.push({
      periode: sortedData[t].periode,
      tahun: sortedData[t].tahun,
      bulan: sortedData[t].bulan,
      aktual: Number(aktual.toFixed(2)),
      prediksi: Number(prediksi.toFixed(2)),
      selisih: Number(selisih.toFixed(2)),
      deviasi: Number(deviasi.toFixed(2)),
      deviasiAbs: Number(deviasiAbs.toFixed(2)),
      ape: Number(ape.toFixed(2)),
      status: getStatus(deviasiAbs),
    });

    const previousLevel = level;

    level =
      alpha * (aktual - currentSeasonal) +
      (1 - alpha) * (level + trend);

    trend =
      beta * (level - previousLevel) +
      (1 - beta) * trend;

    seasonal[seasonalIndex] =
      gamma * (aktual - level) +
      (1 - gamma) * currentSeasonal;
  }

  const prediksiMendatang = [];
  const lastDate = new Date(sortedData[sortedData.length - 1].periode);

  for (let h = 1; h <= forecastPeriods; h++) {
    const seasonalIndex = (values.length + h - 1) % seasonLength;
    const nilaiPrediksi = level + h * trend + seasonal[seasonalIndex];

    const nextDate = new Date(lastDate);
    nextDate.setMonth(lastDate.getMonth() + h);

    prediksiMendatang.push({
      periode: nextDate.toISOString().slice(0, 10),
      tahun: nextDate.getFullYear(),
      bulan: nextDate.getMonth() + 1,
      prediksi: Number(nilaiPrediksi.toFixed(2)),
    });
  }

  const mape =
    evaluasi.reduce((total, item) => total + item.ape, 0) / evaluasi.length;

  const rataRataDeviasi =
    evaluasi.reduce((total, item) => total + item.deviasiAbs, 0) /
    evaluasi.length;

  return {
    parameter: {
      alpha,
      beta,
      gamma,
      seasonLength,
      forecastPeriods,
    },
    ringkasan: {
      jumlahDataHistoris: values.length,
      jumlahDataEvaluasi: evaluasi.length,
      rataRataDeviasi: Number(rataRataDeviasi.toFixed(2)),
      mape: Number(mape.toFixed(2)),
      estimasiAkurasi: Number((100 - mape).toFixed(2)),
    },
    evaluasi,
    prediksiMendatang,
  };
}