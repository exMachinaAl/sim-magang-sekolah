<?php

// Remove a flag only after the documented integration issue has been fixed and tested.
return [
    'reports' => [
        'routes' => 'reports.*',
        'message' => 'Ekspor laporan masih berbasis siswa yang sudah ditempatkan, belum mencakup seluruh roster sekolah. Fase penempatan sudah mengikuti Kontrol PKL. Untuk rekap seluruh siswa, gunakan Ekspor Hasil di Kontrol PKL. Riwayat audit tetap dapat digunakan.',
    ],
];
