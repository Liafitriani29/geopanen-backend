const xlsx = require("xlsx");
const mysql = require("mysql2/promise");
const path = require("path");

const dbConfig = {
  host: "localhost",
  user: "root",
  password: "",
  database: "geopanen_db_ta",
  port: 3306,
};

const bulanMap = {
  jan: 1,
  januari: 1,
  feb: 2,
  februari: 2,
  mar: 3,
  maret: 3,
  apr: 4,
  april: 4,
  mei: 5,
  jun: 6,
  juni: 6,
  jul: 7,
  juli: 7,
  agu: 8,
  ags: 8,
  agustus: 8,
  sep: 9,
  september: 9,
  okt: 10,
  oktober: 10,
  nov: 11,
  november: 11,
  des: 12,
  desember: 12,
};

const fileList = [
  "dataset_bulanan_2021.xlsx",
  "dataset_bulanan_2022.xlsx",
  "dataset_bulanan_sukoharjo_2023.xlsx",
  "dataset_bulanan_2024.xlsx",
];

function ambilNilai(row, namaKolom) {
  const key = Object.keys(row).find(
    (k) => k.toLowerCase().trim() === namaKolom.toLowerCase()
  );

  return key ? row[key] : undefined;
}

function parsingBulan(bulanText) {
  const teks = String(bulanText).trim();

  // Format contoh: 2021-Jan
  if (teks.includes("-")) {
    const [tahunText, bulanNama] = teks.split("-");
    const tahun = parseInt(tahunText);
    const bulan = bulanMap[bulanNama.toLowerCase().trim()];

    return { tahun, bulan };
  }

  throw new Error(`Format bulan tidak dikenali: ${bulanText}`);
}

async function importData() {
  const connection = await mysql.createConnection(dbConfig);

  try {
    for (const fileName of fileList) {
      const filePath = path.join(__dirname, "../dataset", fileName);

      const workbook = xlsx.readFile(filePath);
      const sheetName = workbook.SheetNames[0];
      const sheet = workbook.Sheets[sheetName];
      const rows = xlsx.utils.sheet_to_json(sheet);

      console.log(`Membaca file: ${fileName}`);
      console.log(`Jumlah baris ditemukan: ${rows.length}`);

      for (const row of rows) {
        const bulanText = ambilNilai(row, "bulan");
        const produksiValue = ambilNilai(row, "produksi");

        if (!bulanText || produksiValue === undefined) {
          console.log("Data dilewati karena kolom tidak lengkap:", row);
          continue;
        }

        const { tahun, bulan } = parsingBulan(bulanText);
        const produksi = Number(produksiValue);

        if (!tahun || !bulan || isNaN(produksi)) {
          console.log("Data dilewati karena tidak valid:", row);
          continue;
        }

        const periode = `${tahun}-${String(bulan).padStart(2, "0")}-01`;

        await connection.execute(
          `
          INSERT INTO produksi_bulanan 
          (kabupaten, tahun, bulan, periode, produksi)
          VALUES (?, ?, ?, ?, ?)
          ON DUPLICATE KEY UPDATE
            produksi = VALUES(produksi),
            tahun = VALUES(tahun),
            bulan = VALUES(bulan)
          `,
          ["Sukoharjo", tahun, bulan, periode, produksi]
        );
      }

      console.log(`Berhasil import: ${fileName}`);
    }

    console.log("Semua dataset berhasil dimasukkan ke database.");
  } catch (error) {
    console.error("Gagal import data:", error.message);
  } finally {
    await connection.end();
  }
}

importData();