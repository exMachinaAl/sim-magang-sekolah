<?php
namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use ZipArchive;

/** Referenced journal photos only; never extracts ZIP paths or writes into live storage. */
class JournalPhotoBackup
{
    private function safePath(string $path): void
    {
        if (!str_starts_with($path,'journals/') || str_contains($path,'\\') || str_contains($path,':')
            || str_contains($path,"\0") || array_intersect(explode('/',$path),['','.', '..'])) {
            throw new RuntimeException('Path foto jurnal tidak aman; backup dihentikan.');
        }
    }

    private function sourceFile(string $root,string $path): string
    {
        $this->safePath($path);
        $base=realpath($root); $file=$base?realpath($base.DIRECTORY_SEPARATOR.$path):false;
        $prefix=$base.DIRECTORY_SEPARATOR;
        $inside=$file && (PHP_OS_FAMILY==='Windows'
            ? strncasecmp($file,$prefix,strlen($prefix))===0 : str_starts_with($file,$prefix));
        if (!$inside || !is_file($file) || !is_readable($file)) throw new RuntimeException('Foto jurnal hilang, tidak terbaca, atau berada di luar storage privat.');
        return $file;
    }

    private function references(Connection $db): array
    {
        return $db->table('journal_attachments')->orderBy('id')->get(['id','path','size'])
            ->map(fn($row)=>['id'=>(int)$row->id,'path'=>$row->path,'size'=>(int)$row->size])->all();
    }

    public function snapshot(Connection $db,string $root): array
    {
        $manifest=[];
        foreach ($this->references($db) as $row) {
            $file=$this->sourceFile($root,$row['path']); clearstatcache(true,$file);
            if ($row['size']<=0 || filesize($file)!==$row['size']) throw new RuntimeException('Ukuran foto jurnal tidak cocok dengan database; periksa lampiran ID '.$row['id'].'.');
            $hash=hash_file('sha256',$file);
            if (!$hash) throw new RuntimeException('Gagal membaca hash foto jurnal.');
            $manifest[]=$row+['sha256'=>$hash];
        }
        return $manifest;
    }

    public function write(array $manifest,string $root,string $zipPath): void
    {
        $zip=new ZipArchive;
        if ($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::EXCL)!==true) throw new RuntimeException('Tidak dapat membuat ZIP foto baru.');
        try {
            if (!$zip->addFromString('manifest.json',json_encode($manifest,JSON_THROW_ON_ERROR))) throw new RuntimeException('Gagal menulis manifest foto.');
            $seen=[];
            foreach ($manifest as $row) {
                if (isset($seen[$row['path']])) continue;
                $seen[$row['path']]=true;
                if (!$zip->addFile($this->sourceFile($root,$row['path']),'files/'.$row['path'])) throw new RuntimeException('Gagal menambahkan foto ke ZIP.');
            }
        } finally { if (!$zip->close()) throw new RuntimeException('ZIP foto tidak selesai ditulis.'); }
    }

    public function verifyRestore(Connection $db,string $zipPath,string $parent,array $manifest): array
    {
        // Create and own a fresh destination; no user-supplied restore directory.
        $base=realpath($parent);
        if (!$base || !is_dir($base)) throw new RuntimeException('Direktori uji foto tidak tersedia.');
        $directory=$base.DIRECTORY_SEPARATOR.'photo-restore-'.bin2hex(random_bytes(16));
        if (!mkdir($directory,0700)) throw new RuntimeException('Tidak dapat membuat direktori uji foto baru.');
        $zip=new ZipArchive; $opened=false;
        try {
            $refs=array_map(fn($row)=>array_intersect_key($row,array_flip(['id','path','size'])),$manifest);
            if ($refs!==$this->references($db)) throw new RuntimeException('Referensi foto hasil pemulihan berbeda dari manifest.');
            if ($zip->open($zipPath,ZipArchive::RDONLY)!==true) throw new RuntimeException('ZIP foto tidak dapat dibuka.');
            $opened=true;
            if ($zip->getFromName('manifest.json')!==json_encode($manifest,JSON_THROW_ON_ERROR)) throw new RuntimeException('Manifest ZIP foto berubah.');
            $seen=[]; $bytes=0;
            foreach ($manifest as $row) {
                $this->safePath($row['path']);
                if (isset($seen[$row['path']])) {
                    if ($seen[$row['path']]!==[$row['size'],$row['sha256']]) throw new RuntimeException('Metadata foto yang sama tidak konsisten.');
                    continue;
                }
                $seen[$row['path']]=[$row['size'],$row['sha256']];
                $target=$directory.DIRECTORY_SEPARATOR.$row['path'];
                if (!is_dir(dirname($target)) && !mkdir(dirname($target),0700,true)) throw new RuntimeException('Gagal membuat folder foto uji.');
                $input=$zip->getStream('files/'.$row['path']);
                if (!is_resource($input)) throw new RuntimeException('Foto tidak ditemukan di ZIP.');
                $output=fopen($target,'xb');
                if (!$output) { fclose($input); throw new RuntimeException('Tidak dapat memulihkan foto uji.'); }
                try { $copied=stream_copy_to_stream($input,$output,$row['size']+1); }
                finally { fclose($input); fclose($output); }
                if ($copied!==$row['size'] || !hash_equals($row['sha256'],hash_file('sha256',$target))) throw new RuntimeException('Ukuran/hash foto hasil pemulihan tidak cocok.');
                $bytes+=$copied;
            }
            if ($zip->numFiles!==count($seen)+1) throw new RuntimeException('ZIP memiliki entri di luar manifest.');
            return ['attachments'=>count($manifest),'unique_files'=>count($seen),'bytes'=>$bytes,'verified'=>true];
        } finally {
            if ($opened) $zip->close();
            // Delete only the random directory just created directly under the verified parent.
            if (realpath(dirname($directory))!==$base || !preg_match('/^photo-restore-[a-f0-9]{32}$/',basename($directory))) throw new RuntimeException('Tujuan pembersihan foto tidak aman.');
            if (!(new Filesystem)->deleteDirectory($directory)) throw new RuntimeException('Folder pemulihan foto uji belum terhapus.');
        }
    }
}
