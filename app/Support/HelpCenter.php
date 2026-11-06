<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class HelpCenter
{
    public function page(?string $routeName, User $user): array
    {
        $routeName ??= '';

        foreach ($this->pages() as $pattern => $help) {
            if (Str::is($pattern, $routeName)) {
                return $this->forRole($help, $user);
            }
        }

        return $this->forRole([
            'key' => 'umum',
            'title' => 'Petunjuk halaman',
            'summary' => 'Gunakan halaman ini sesuai hak akses akun Anda. Data yang tidak tersedia untuk role Anda tidak akan ditampilkan.',
            'steps' => [
                'Baca judul dan keterangan halaman sebelum mengisi formulir.',
                'Periksa kembali data sebelum menekan tombol simpan atau proses.',
                'Buka FAQ bila Anda memerlukan alur yang lebih lengkap.',
            ],
        ], $user);
    }

    public function sections(User $user): array
    {
        $common = [
            'title' => 'Dasar penggunaan SIMMAG',
            'description' => 'Informasi yang berlaku untuk seluruh pengguna.',
            'items' => [
                ['question' => 'Bagaimana menemukan petunjuk pada sebuah halaman?', 'answer' => 'Klik tombol bulat bertanda tanya di kanan bawah. Pada komputer, arahkan kursor ke tombol selama sekitar satu detik. Tekan Escape atau klik di luar panel untuk menutupnya.'],
                ['question' => 'Mengapa menu saya berbeda dari pengguna lain?', 'answer' => 'Menu mengikuti role dan ruang lingkup data. Siswa hanya melihat data sendiri, guru melihat siswa bimbingannya, koordinator mengelola proses PKL, dan admin mengelola akun sistem.'],
                ['question' => 'Bagaimana mencari data dengan aman?', 'answer' => 'Gunakan nama, NIS, NIP, tahun, jurusan, atau status pada kotak pencarian yang tersedia. Pastikan filter tahun benar agar data historis tidak tercampur.'],
                ['question' => 'Apa yang dilakukan jika data terlihat salah?', 'answer' => 'Jangan membuat data pengganti untuk menutup kesalahan. Catat nama/NIS dan tahun terkait, lalu laporkan kepada koordinator atau admin sesuai jenis datanya.'],
                ['question' => 'Bagaimana menjaga keamanan akun?', 'answer' => 'Jangan membagikan kata sandi, QR aktif, cookie browser, atau tautan pendaftaran perangkat. Gunakan menu Ubah Kata Sandi dan selalu keluar setelah memakai perangkat bersama.'],
            ],
        ];

        return [$common, $this->roleSection($user->role)];
    }

    public function roleIntroduction(User $user): string
    {
        return match ($user->role) {
            'siswa' => 'Panduan ini memusatkan alur siswa: melihat jadwal, mencatat jurnal, meminta validasi industri, melihat nilai, dan mengunduh sertifikat yang tersedia.',
            'guru' => 'Panduan ini memusatkan akses guru pembimbing yang bersifat pendampingan kasus dan hanya untuk siswa yang ditugaskan.',
            'koordinator' => 'Panduan ini memusatkan kendali operasional koordinator: data siswa, jadwal PKL, penempatan, review, nilai, sertifikat, kiosk, dan audit.',
            'admin' => 'Panduan ini memusatkan administrasi akun dan pemeriksaan sistem. Perubahan proses bisnis PKL tetap menjadi wewenang koordinator.',
            default => 'Panduan disesuaikan dengan hak akses akun yang sedang digunakan.',
        };
    }

    private function forRole(array $help, User $user): array
    {
        $help['role_note'] = $help['roles'][$user->role] ?? match ($user->role) {
            'siswa' => 'Anda hanya dapat membuka dan mengubah data milik sendiri.',
            'guru' => 'Akses guru dibatasi pada siswa yang ditugaskan.',
            'koordinator' => 'Perubahan operasional akan tercatat menggunakan ID akun koordinator yang aktif.',
            'admin' => 'Admin mengelola sistem dan akun; proses bisnis PKL tetap dikendalikan koordinator.',
            default => 'Ikuti hak akses yang tersedia pada akun Anda.',
        };
        unset($help['roles']);

        return $help;
    }

    private function pages(): array
    {
        return [
            'journals.mentors*' => [
                'key' => 'kiosk-industri',
                'title' => 'Pembimbing industri & perangkat kiosk',
                'summary' => 'Daftarkan pembimbing industri, hubungkan siswa, buat QR pendaftaran sekali pakai, dan cabut perangkat yang tidak lagi dipercaya.',
                'steps' => ['Buat atau pilih pembimbing industri.', 'Tetapkan siswa yang benar sebelum membuat QR pendaftaran.', 'Minta pembimbing memindai QR dengan perangkatnya sendiri dan cabut perangkat bila hilang atau berganti pengguna.'],
                'roles' => ['koordinator' => 'QR pendaftaran bukan untuk siswa dan tidak boleh dikirim sebagai tangkapan layar kepada pihak lain.'],
            ],
            'certificate-print-templates.*' => [
                'key' => 'template-sertifikat',
                'title' => 'Template cetak sertifikat',
                'summary' => 'Template mengikat ketentuan nilai, variasi penandatangan, dan tata letak dokumen untuk penerbitan versi berikutnya.',
                'steps' => ['Pilih template penilaian/jurusan yang sesuai.', 'Isi identitas sekolah dan pemetaan nilai tanpa mengambil data siswa dari dropdown massal.', 'Gunakan pratinjau sebelum membuat versi sertifikat. Template lama tidak ditimpa.'],
            ],
            'certificates.prepare' => [
                'key' => 'sertifikat-siapkan',
                'title' => 'Siapkan sertifikat',
                'summary' => 'Lengkapi identitas cetak dan periksa pratinjau sebelum membuat atau merevisi sertifikat.',
                'steps' => ['Pastikan nilai telah disahkan dan jadwal PKL lengkap.', 'Isi data cetak sesuai dokumen sumber industri.', 'Buka pratinjau dua halaman, lalu ajukan. Revisi membuat versi baru dan tidak menimpa arsip lama.'],
            ],
            'certificates.show' => [
                'key' => 'sertifikat-detail',
                'title' => 'Detail & arsip sertifikat',
                'summary' => 'Halaman ini menampilkan versi, persetujuan, arsip PDF/DOCX, dan riwayat distribusi sertifikat.',
                'steps' => ['Periksa nomor serta versi dokumen.', 'Koordinator menyetujui atau mengajukan revisi berdasarkan sumber yang benar.', 'Catat pergerakan fisik sertifikat secara berurutan sampai diserahkan.'],
            ],
            'assessment-templates.*' => [
                'key' => 'template-penilaian',
                'title' => 'Ketentuan penilaian per jurusan',
                'summary' => 'Koordinator membuat template aspek yang berasal dari kebutuhan industri/sekolah dan menggunakannya untuk siswa pada jurusan yang sesuai.',
                'steps' => ['Pilih jurusan dan beri nama versi template.', 'Tandai satu template aktif sebagai default jurusan; template itu dipilih otomatis saat penilaian baru dibuka.', 'Tambahkan aspek angka atau teks beserta bobot dan keterangannya, lalu periksa total bobot. Jangan memakai dropdown nilai siswa.'],
            ],
            'assessments.evidence.*' => [
                'key' => 'bukti-penilaian',
                'title' => 'Bukti nilai industri',
                'summary' => 'Bukti disimpan privat sebagai pendukung input nilai dan tidak mengubah PDF sertifikat yang sudah diterbitkan.',
                'steps' => ['Pastikan bukti berasal dari siswa/industri yang benar.', 'Unggah file dan catatan yang cukup untuk audit.', 'Jangan mengunggah dokumen rahasia yang tidak diperlukan.'],
            ],
            'monitoring.show' => [
                'key' => 'monitoring-kasus',
                'title' => 'Tindak lanjut kasus monitoring',
                'summary' => 'Gunakan riwayat kasus untuk penugasan, tindak lanjut, penutupan, atau pembukaan kembali masalah PKL.',
                'steps' => ['Baca catatan awal dan riwayat sebelum mengubah status.', 'Tetapkan penanggung jawab jika guru perlu dilibatkan.', 'Tulis hasil tindak lanjut faktual; catatan lama tidak ditimpa.'],
            ],
            'students.*' => [
                'key' => 'data-siswa',
                'title' => 'Data siswa',
                'summary' => 'Kelola identitas master siswa dan impor batch. Akun siswa memakai NIS sebagai login dan tanggal lahir sebagai kata sandi awal.',
                'steps' => ['Cari siswa berdasarkan nama/NIS atau buka card jurusan.', 'Gunakan template CSV contoh untuk impor batch.', 'Periksa tanggal lahir dan benturan NIS sebelum membuat akun. Data historis roster tidak boleh ditimpa sembarangan.'],
            ],
            'internships.*' => [
                'key' => 'kontrol-pkl',
                'title' => 'Kontrol PKL',
                'summary' => 'Sumber utama status magang adalah tanggal mulai dan selesai pada roster, bukan konfirmasi manual.',
                'steps' => ['Pilih tahun dan jurusan.', 'Atur tanggal batch lalu centang siswa yang ikut; lepaskan centang untuk pengecualian.', 'Periksa status otomatis PKL/masuk sekolah sebelum melanjutkan ke penempatan.'],
            ],
            'placements.*' => [
                'key' => 'penempatan',
                'title' => 'Penempatan siswa',
                'summary' => 'Hubungkan siswa yang sudah terdaftar pada Kontrol PKL dengan perusahaan dan guru opsional.',
                'steps' => ['Filter tahun/jurusan atau cari nama/NIS.', 'Centang banyak siswa dan lepaskan siswa yang menjadi pengecualian.', 'Periksa perusahaan, tanggal roster, dan guru bila diperlukan sebelum menyimpan.'],
            ],
            'companies.*' => [
                'key' => 'perusahaan',
                'title' => 'Data perusahaan',
                'summary' => 'Simpan identitas mitra yang dipakai pada penempatan, penilaian, dan sertifikat.',
                'steps' => ['Cari nama perusahaan lebih dahulu agar tidak menggandakan data.', 'Gunakan nama resmi dan alamat/kontak yang dapat diverifikasi.', 'Perubahan nama dapat memengaruhi tampilan data aktif; arsip sertifikat terbit tetap beku.'],
            ],
            'periods.*' => [
                'key' => 'tag-pkl',
                'title' => 'Periode / tag PKL',
                'summary' => 'Periode adalah tag tahun ajaran, bukan sumber tanggal serentak seluruh siswa.',
                'steps' => ['Buat tag seperti 2026/2027.', 'Gunakan Kontrol PKL untuk tanggal per jurusan atau per siswa.', 'Arsipkan tag yang tidak aktif tanpa mengubah status siswa secara manual.'],
            ],
            'reports.*' => [
                'key' => 'laporan-audit',
                'title' => 'Laporan & audit',
                'summary' => 'Ekspor data sesuai tahun dan jurusan, termasuk siswa yang belum ditempatkan atau tidak mengikuti PKL.',
                'steps' => ['Pilih tag tahun dan jurusan.', 'Pilih jenis laporan yang diperlukan.', 'Periksa label historis serta simpan hasil ekspor secara privat.'],
            ],
            'users.*' => [
                'key' => 'pengguna-role',
                'title' => 'Pengguna & role',
                'summary' => 'Admin mengelola akun staf, identitas login, role, dan status aktif tanpa mengambil alih proses bisnis PKL.',
                'steps' => ['Cari pengguna sebelum membuat akun baru.', 'Pilih role dengan prinsip akses minimum.', 'Nonaktifkan akun yang tidak digunakan; jangan berbagi kata sandi atau memakai akun bersama.'],
            ],
            'dashboard' => [
                'key' => 'dashboard',
                'title' => 'Dashboard',
                'summary' => 'Dashboard merangkum kondisi PKL berdasarkan tahun dan ruang lingkup role yang sedang login.',
                'steps' => ['Pastikan tag tahun yang dipilih benar.', 'Baca jumlah siswa per fase dan jurusan.', 'Klik tautan ringkasan untuk membuka data rinci, bukan menghitung ulang secara manual.'],
                'roles' => [
                    'siswa' => 'Ringkasan hanya memakai roster dan penempatan Anda sendiri.',
                    'guru' => 'Ringkasan guru dibatasi pada siswa yang ditugaskan.',
                    'koordinator' => 'Gunakan total sekolah untuk menemukan jurusan/fase yang memerlukan perhatian.',
                    'admin' => 'Dashboard admin bersifat pemeriksaan; perubahan operasional dilakukan koordinator.',
                ],
            ],
            'timeline.*' => [
                'key' => 'timeline',
                'title' => 'Timeline PKL',
                'summary' => 'Timeline menampilkan tahapan per siswa tanpa memenuhi halaman dengan seluruh card sekaligus.',
                'steps' => ['Pilih tahun lalu cari nama, NIS, guru, atau jurusan.', 'Buka jurusan/guru kemudian pilih siswa.', 'Status berbasis tanggal dan jurnal dihitung otomatis; hanya tahap manual yang memang menjadi wewenang role dapat diubah.'],
            ],
            'journals.*' => [
                'key' => 'jurnal',
                'title' => 'Jurnal & foto',
                'summary' => 'Jurnal mencatat kegiatan harian, foto opsional, nilai persis dari industri, review sekolah, dan validasi perangkat industri.',
                'steps' => ['Cari/pilih siswa atau penempatan yang benar.', 'Isi kegiatan pada tanggal di dalam jadwal PKL; foto bersifat opsional.', 'Validasi industri berbeda dari review sekolah dan menyimpan waktu scan aktual.'],
                'roles' => [
                    'siswa' => 'Simpan kegiatan sendiri, tunjukkan ringkasan kepada pembimbing industri, lalu tampilkan QR setelah isi dan nilai disepakati.',
                    'guru' => 'Review hanya siswa yang ditugaskan dan digunakan ketika pendampingan kasus diperlukan.',
                    'koordinator' => 'Koordinator memegang kontrol review, ekspor, pembimbing industri, serta audit validasi.',
                    'admin' => 'Akses admin bersifat pemeriksaan; jangan mengubah proses jurnal sebagai pengganti koordinator.',
                ],
            ],
            'monitoring.*' => [
                'key' => 'monitoring',
                'title' => 'Monitoring',
                'summary' => 'Monitoring digunakan untuk mencatat kunjungan atau kasus yang memerlukan tindak lanjut, bukan checklist rutin wajib guru.',
                'steps' => ['Cari siswa berdasarkan nama/NIS dan tahun.', 'Catat keadaan faktual serta tindak lanjut yang diperlukan.', 'Koordinator dapat menugaskan guru secara opsional, menutup, atau membuka kembali kasus dengan riwayat.'],
            ],
            'assessments.*' => [
                'key' => 'penilaian',
                'title' => 'Penilaian',
                'summary' => 'Nilai berasal dari industri, dicatat dan disahkan koordinator berdasarkan template jurusan serta bukti yang tersedia.',
                'steps' => ['Cari siswa menggunakan nama atau NIS.', 'Isi setiap aspek persis sesuai sumber industri; jangan memilih nilai dari daftar siswa massal.', 'Periksa hasil hitung, alasan nilai akhir, bukti, lalu sahkan.'],
                'roles' => [
                    'siswa' => 'Siswa hanya melihat hasil yang sudah tersedia untuk dirinya.',
                    'guru' => 'Guru tidak berwenang memasukkan atau mengesahkan nilai industri.',
                    'koordinator' => 'ID koordinator pencatat dan pengesah disimpan untuk audit.',
                    'admin' => 'Admin memeriksa sistem; pemberian dan input nilai bukan wewenang admin.',
                ],
            ],
            'certificates.*' => [
                'key' => 'sertifikat',
                'title' => 'Sertifikat',
                'summary' => 'Sertifikat memakai nilai yang telah disahkan, arsip PDF per versi, persetujuan koordinator, dan riwayat distribusi.',
                'steps' => ['Cari dengan nama/NIS dan pastikan versi yang dibuka benar.', 'Periksa dua halaman: identitas depan dan daftar nilai belakang.', 'Versi terbit tidak dibangun ulang dari data hidup; koreksi dilakukan melalui revisi resmi.'],
            ],
            'profile*' => [
                'key' => 'keamanan-akun',
                'title' => 'Keamanan akun',
                'summary' => 'Ubah kata sandi awal atau kata sandi yang mungkin sudah diketahui pihak lain.',
                'steps' => ['Masukkan kata sandi saat ini.', 'Gunakan kata sandi baru minimal 10 karakter yang tidak sama dengan NIS/tanggal lahir.', 'Simpan lalu gunakan kata sandi baru pada login berikutnya.'],
            ],
            'guide' => [
                'key' => 'faq',
                'title' => 'FAQ & panduan',
                'summary' => 'Cari pertanyaan atau buka bagian sesuai role. Jawaban di halaman ini melengkapi petunjuk singkat pada setiap menu.',
                'steps' => ['Ketik kata kunci seperti jurnal, nilai, QR, atau sertifikat.', 'Buka pertanyaan yang sesuai.', 'Jika masalah menyangkut data tertentu, catat nama/NIS dan tahun sebelum melapor.'],
            ],
        ];
    }

    private function roleSection(string $role): array
    {
        return match ($role) {
            'siswa' => [
                'title' => 'Panduan siswa',
                'description' => 'Alur dari jadwal PKL sampai sertifikat.',
                'items' => [
                    ['question' => 'Bagaimana mengetahui apakah saya sedang PKL atau sudah masuk sekolah?', 'answer' => 'Status dihitung otomatis dari tanggal mulai dan selesai pada Kontrol PKL. Bila tanggal tidak sesuai, laporkan kepada koordinator; siswa tidak mengonfirmasi status secara manual.'],
                    ['question' => 'Bagaimana mengisi jurnal?', 'answer' => 'Buka Jurnal & Foto, pilih penempatan, isi tanggal, kegiatan, ruang/jam/peralatan, serta nilai industri bila sudah diberikan. Foto opsional dan tanggal harus berada dalam jadwal PKL.'],
                    ['question' => 'Bagaimana validasi jurnal oleh industri?', 'answer' => 'Tunjukkan ringkasan kepada pembimbing. Setelah isi dan nilai cocok, tampilkan QR jurnal agar dipindai dengan perangkat pembimbing yang telah didaftarkan. Scan boleh dilakukan pada hari berbeda dan waktu scan tetap tercatat.'],
                    ['question' => 'Mengapa nilai atau sertifikat belum terlihat?', 'answer' => 'Nilai harus dicatat dan disahkan koordinator terlebih dahulu. Sertifikat juga memerlukan data lengkap, persetujuan, dan proses penerbitan. Hubungi koordinator bila tahap tersebut seharusnya sudah selesai.'],
                ],
            ],
            'guru' => [
                'title' => 'Panduan guru pembimbing',
                'description' => 'Pendampingan bersifat terbatas pada siswa yang ditugaskan dan kasus yang membutuhkan bantuan.',
                'items' => [
                    ['question' => 'Apakah guru wajib memeriksa semua jurnal secara rutin?', 'answer' => 'Tidak. Kontrol utama berada pada koordinator dan kepercayaan data kegiatan berada pada siswa/validasi industri. Guru dapat membantu review atau kasus teknis untuk siswa yang ditugaskan.'],
                    ['question' => 'Mengapa siswa tertentu tidak dapat dibuka?', 'answer' => 'Akses guru dibatasi pada penempatan yang menugaskan guru tersebut. Koordinator perlu memperbaiki penempatan bila tugasnya memang berubah.'],
                    ['question' => 'Apakah guru boleh memasukkan nilai sertifikat?', 'answer' => 'Tidak. Nilai berasal dari industri dan diinput serta disahkan koordinator. Guru hanya melihat informasi sesuai aksesnya.'],
                    ['question' => 'Kapan memakai Monitoring?', 'answer' => 'Gunakan ketika ada kunjungan, kendala teknis, kesalahan, atau kasus yang memerlukan tindak lanjut. Catat fakta dan hasil pendampingan, bukan aktivitas rutin yang tidak terjadi.'],
                ],
            ],
            'koordinator' => [
                'title' => 'Panduan koordinator PKL',
                'description' => 'Kontrol utama operasional dan audit PKL.',
                'items' => [
                    ['question' => 'Apa urutan menyiapkan siswa PKL?', 'answer' => 'Masukkan Data Siswa/roster, buat tag tahun, atur tanggal di Kontrol PKL per jurusan atau siswa, lalu lakukan Penempatan ke perusahaan. Guru bersifat opsional.'],
                    ['question' => 'Bagaimana memakai dua akun koordinator?', 'answer' => 'Setiap koordinator harus memakai akunnya sendiri. Sistem menyimpan ID pencatat/pengubah sehingga jangan berbagi akun meskipun wewenangnya sama.'],
                    ['question' => 'Bagaimana alur nilai sampai sertifikat?', 'answer' => 'Buat template aspek per jurusan, cari siswa dengan nama/NIS, input nilai persis dari industri, unggah bukti bila ada, sahkan nilai, pilih template cetak, pratinjau, ajukan dan setujui sertifikat, lalu catat distribusinya.'],
                    ['question' => 'Bagaimana mendaftarkan perangkat pembimbing industri?', 'answer' => 'Buka Jurnal → Pembimbing Industri & Perangkat QR, hubungkan siswa, lalu buat QR pendaftaran sekali pakai. Pembimbing memindainya dengan perangkat sendiri. Cabut perangkat yang hilang atau tidak lagi digunakan.'],
                    ['question' => 'Kapan memakai batch dan kapan per siswa?', 'answer' => 'Gunakan batch ketika tanggal atau penempatan sama untuk banyak siswa/jurusan. Lepaskan centang untuk pengecualian, kemudian koreksi individual hanya pada siswa yang benar-benar berbeda.'],
                ],
            ],
            'admin' => [
                'title' => 'Panduan admin sistem',
                'description' => 'Administrasi akun dan pemeriksaan teknis.',
                'items' => [
                    ['question' => 'Apa batas admin dibanding koordinator?', 'answer' => 'Admin mengelola akun, role, status aktif, dan pemeriksaan sistem. Jadwal, penempatan, review, nilai, dan penerbitan sertifikat tetap menjadi proses koordinator.'],
                    ['question' => 'Bagaimana membuat akun staf?', 'answer' => 'Buka Pengguna & Role, cari identitas lebih dahulu, lalu buat akun dengan email/NIP unik dan role minimum yang diperlukan. Jangan memakai satu akun untuk beberapa orang.'],
                    ['question' => 'Apa yang dilakukan ketika pengguna tidak lagi bertugas?', 'answer' => 'Nonaktifkan akunnya agar login ditolak. Jangan menghapus atau mengganti identitas historis yang sudah tercatat pada audit.'],
                    ['question' => 'Bagaimana membantu pengguna yang lupa kata sandi?', 'answer' => 'Verifikasi identitas sesuai prosedur sekolah lalu lakukan mekanisme pemulihan yang disediakan. Jangan meminta pengguna mengirimkan kata sandi lama melalui chat.'],
                ],
            ],
            default => [
                'title' => 'Panduan role',
                'description' => 'Gunakan menu yang tersedia pada akun Anda.',
                'items' => [],
            ],
        };
    }
}
