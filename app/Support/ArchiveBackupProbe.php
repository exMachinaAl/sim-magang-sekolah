<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Local-only round trip. Always makes its own fresh dump and isolated destination. */
class ArchiveBackupProbe
{
    public static function assertTarget(string $source,string $target): void
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/',$source) || $source===$target
            || !preg_match('/^simmag_restore_probe_[a-f0-9]{16}$/',$target)) {
            throw new RuntimeException('Nama database sumber/tujuan uji tidak aman.');
        }
    }

    private function fingerprint(Connection $db): array
    {
        $tables=$db->select('SELECT TABLE_NAME AS name, TABLE_TYPE AS type, ENGINE AS engine, TABLE_COLLATION AS collation, AUTO_INCREMENT AS next_id, CREATE_OPTIONS AS options, TABLE_COMMENT AS comment FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? ORDER BY TABLE_NAME',[$db->getDatabaseName()]);
        $result=[];
        foreach ($tables as $table) {
            if ($table->type!=='BASE TABLE' || $table->engine!=='InnoDB' || !preg_match('/^[A-Za-z0-9_]+$/',$table->name)) throw new RuntimeException('Uji ini hanya mendukung tabel aplikasi InnoDB biasa.');
            $name=$table->name;
            $keys=$db->select("SELECT COLUMN_NAME AS name FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = 'PRIMARY' ORDER BY ORDINAL_POSITION",[$db->getDatabaseName(),$name]);
            if (!$keys) throw new RuntimeException('Tabel tanpa primary key tidak didukung: '.$name);
            $query=$db->table($name); foreach ($keys as $key) $query->orderBy($key->name);
            $hash=hash_init('sha256'); $count=0;
            foreach ($query->lazy(100) as $row) {
                // Length prefix makes field/row boundaries unambiguous; no values go into the report.
                foreach ((array)$row as $key=>$value) {
                    $part=$value===null?'N':'V'.(string)$value;
                    hash_update($hash,strlen($key).':'.$key.strlen($part).':'.$part);
                }
                hash_update($hash,"\n"); $count++;
            }
            // SHOW CREATE TABLE may add redundant CHARACTER SET clauses after restore.
            // Compare semantic metadata, including defaults, index prefixes and FK actions.
            $schema=['table'=>(array)$table,
                'columns'=>$db->table('information_schema.COLUMNS')->where('TABLE_SCHEMA',$db->getDatabaseName())->where('TABLE_NAME',$name)
                    ->orderBy('ORDINAL_POSITION')->get(['COLUMN_NAME','ORDINAL_POSITION','COLUMN_DEFAULT','IS_NULLABLE',
                        'COLUMN_TYPE','CHARACTER_SET_NAME','COLLATION_NAME','EXTRA','GENERATION_EXPRESSION','COLUMN_COMMENT'])->all(),
                'indexes'=>[], 'foreign_keys'=>[],
                'checks'=>$db->table('information_schema.TABLE_CONSTRAINTS as t')
                    ->join('information_schema.CHECK_CONSTRAINTS as c',fn($join)=>$join->on('c.CONSTRAINT_SCHEMA','=','t.CONSTRAINT_SCHEMA')->on('c.CONSTRAINT_NAME','=','t.CONSTRAINT_NAME'))
                    ->where('t.TABLE_SCHEMA',$db->getDatabaseName())->where('t.TABLE_NAME',$name)->orderBy('t.CONSTRAINT_NAME')
                    ->get(['t.CONSTRAINT_NAME','c.CHECK_CLAUSE'])->all(),
            ];
            // INFORMATION_SCHEMA may cache AUTO_INCREMENT after inserted rows were
            // deleted. Compare the live counter included in the dump's CREATE TABLE.
            $create=array_values((array)$db->selectOne('SHOW CREATE TABLE `'.$name.'`'))[1];
            preg_match('/\)\s+ENGINE=\S+\s+AUTO_INCREMENT=(\d+)\b/',$create,$counter);
            $hasAutoIncrement=collect($schema['columns'])->contains(fn($column)=>str_contains($column->EXTRA,'auto_increment'));
            $schema['table']['next_id']=$hasAutoIncrement?(int)($counter[1]??1):null;
            foreach ($db->select('SHOW INDEX FROM `'.$name.'`') as $index) {
                $index=(array)$index; unset($index['Table'],$index['Cardinality']);
                $schema['indexes'][]=$index;
            }
            usort($schema['indexes'],fn($a,$b)=>[$a['Key_name'],$a['Seq_in_index']]<=>[$b['Key_name'],$b['Seq_in_index']]);
            foreach ($db->getSchemaBuilder()->getForeignKeys($name) as $foreign) {
                if ($foreign['foreign_schema']!==$db->getDatabaseName()) throw new RuntimeException('Relasi lintas database memerlukan prosedur pemulihan tersendiri.');
                $foreign['foreign_schema']='__self__'; $schema['foreign_keys'][]=$foreign;
            }
            usort($schema['foreign_keys'],fn($a,$b)=>$a['name']<=>$b['name']);
            $result[$name]=['rows'=>$count,'data_sha256'=>hash_final($hash),
                'schema_sha256'=>hash('sha256',json_encode($schema,JSON_THROW_ON_ERROR))];
        }
        return $result;
    }

    private function runProcess(array $args,array $env,?string $input=null): void
    {
        $stream=$input!==null?fopen($input,'rb'):null;
        try {
            $process=new Process($args,null,$env,$stream,120);
            $process->disableOutput();
            if ($process->run()!==0) throw new RuntimeException('Proses MySQL gagal. Periksa binary/izin/koneksi; rincian SQL tidak ditampilkan untuk menjaga data privat.');
        } finally { if (is_resource($stream)) fclose($stream); }
    }

    public function run(string $dumpBinary,string $mysqlBinary): array
    {
        $source=DB::connection();
        if (!app()->environment('local') || $source->getDriverName()!=='mysql') throw new RuntimeException('Uji pemulihan ini hanya untuk lingkungan local dengan MySQL.');
        $config=$source->getConfig(); $sourceName=$source->getDatabaseName();
        $token=bin2hex(random_bytes(8)); $target='simmag_restore_probe_'.$token;
        self::assertTarget($sourceName,$target);
        if (!in_array($config['host'],['127.0.0.1','localhost','::1'],true) || !empty($config['read']) || !empty($config['write'])) {
            throw new RuntimeException('Uji hanya diizinkan pada MySQL lokal tanpa konfigurasi replica.');
        }
        foreach (['TRIGGERS'=>'TRIGGER_SCHEMA','ROUTINES'=>'ROUTINE_SCHEMA','EVENTS'=>'EVENT_SCHEMA'] as $table=>$column) {
            if ($source->table('information_schema.'.$table)->where($column,$sourceName)->exists()) throw new RuntimeException('Database dengan trigger/routine/event khusus memerlukan prosedur pemulihan tersendiri.');
        }
        $directory=storage_path('app/private/backups');
        if (!is_dir($directory) && !mkdir($directory,0700,true)) throw new RuntimeException('Direktori backup tidak dapat dibuat.');
        $file=$directory.'/archive-probe-'.now('Asia/Jakarta')->format('Ymd-His').'-'.$token.'.sql';
        $reportFile=substr($file,0,-4).'.json';
        $report=['started_at'=>now('Asia/Jakarta')->toIso8601String(),'status'=>'failed','scope'=>'local_database_archives',
            'temporary_database'=>$target,'temporary_database_removed'=>false,'backup_file'=>basename($file)];
        $created=false; $failure=null;
        $connectionName='archive_restore_probe_'.$token;
        $args=['--host='.$config['host'],'--port='.($config['port']??3306),'--user='.$config['username'],
            '--default-character-set=utf8mb4'];
        if (!empty($config['unix_socket'])) $args[]='--socket='.$config['unix_socket'];
        $env=['MYSQL_PWD'=>$config['password']??''];
        try {
            $before=$this->fingerprint($source);
            $integrity=app(ArchiveIntegrity::class)->inspect($source);
            $report['source_integrity']=$integrity;
            if ($integrity['failures']) throw new RuntimeException('Arsip sumber gagal pemeriksaan integritas; tidak dinyatakan aman walaupun dapat disalin.');
            $this->runProcess([$dumpBinary,...$args,'--single-transaction','--skip-lock-tables','--skip-add-locks','--no-tablespaces',
                '--set-gtid-purged=OFF','--hex-blob','--result-file='.$file,$sourceName],$env);
            if (!is_file($file) || filesize($file)<100) throw new RuntimeException('Backup kosong.');
            $report['backup_bytes']=filesize($file); $report['backup_sha256']=hash_file('sha256',$file);
            // This tool never accepts an arbitrary SQL file. The fresh dump must remain scoped to its target DB.
            $handle=fopen($file,'rb');
            try {
                while (($line=fgets($handle))!==false) {
                    if (preg_match('/^\s*(?:USE\s|(?:CREATE|DROP|ALTER)\s+DATABASE\b)/i',$line)) throw new RuntimeException('Dump berisi perpindahan database; pemulihan dibatalkan.');
                }
            } finally { fclose($handle); }
            if ($before!==$this->fingerprint($source)) throw new RuntimeException('Data sumber berubah saat backup. Ulangi pada waktu aplikasi tidak sedang dipakai.');
            // No IF NOT EXISTS: a collision fails rather than reusing/deleting someone else's database.
            $source->statement('CREATE DATABASE `'.$target.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            $created=true;
            $this->runProcess([$mysqlBinary,...$args,'--binary-mode','--database='.$target],$env,$file);
            config(['database.connections.'.$connectionName=>array_replace($config,['database'=>$target,'url'=>null])]);
            $restored=DB::connection($connectionName);
            if ($restored->getDatabaseName()!==$target) throw new RuntimeException('Koneksi pemulihan tidak sesuai tujuan.');
            $after=$this->fingerprint($restored);
            if ($before!==$after) {
                $report['differences']=[];
                foreach (array_unique([...array_keys($before),...array_keys($after)]) as $table) {
                    if (($before[$table]??null)!==($after[$table]??null)) $report['differences'][$table]=[
                        'source'=>$before[$table]??null,'restored'=>$after[$table]??null,
                    ];
                }
                throw new RuntimeException('Skema, jumlah atau isi baris hasil pemulihan berbeda dari sumber.');
            }
            $restoredIntegrity=app(ArchiveIntegrity::class)->inspect($restored);
            if ($integrity!==$restoredIntegrity || $restoredIntegrity['failures']) throw new RuntimeException('Pemeriksaan arsip hasil pemulihan gagal.');
            if ($before!==$this->fingerprint($source)) throw new RuntimeException('Data sumber berubah selama pengujian; hasil belum dapat dinyatakan konsisten.');
            $report['tables']=$after;
            $report['restored_integrity']=$restoredIntegrity;
            $report['status']='passed';
        } catch (\Throwable $e) {
            // PDO/process exceptions may contain source values or credentials. Keep the report generic.
            $failure=get_class($e)===RuntimeException::class?$e->getMessage():'Uji gagal pada koneksi atau pemrosesan database; data kerja tidak ditimpa.';
            $report['error']=$failure;
        } finally {
            DB::purge($connectionName);
            if ($created) {
                self::assertTarget($sourceName,$target);
                try {
                    $source->statement('DROP DATABASE `'.$target.'`');
                    $report['temporary_database_removed']=true;
                } catch (\Throwable $e) {
                    $report['status']='failed'; $failure='Database uji belum terhapus: '.$target;
                    $report['cleanup_error']=$failure;
                }
            }
            $report['finished_at']=now('Asia/Jakarta')->toIso8601String();
            if (file_put_contents($reportFile,json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX)===false) {
                throw new RuntimeException('Laporan verifikasi gagal disimpan. Periksa izin direktori backup.');
            }
        }
        if ($failure) throw new RuntimeException($failure.' Laporan: '.$reportFile);
        return $report+['report_file'=>$reportFile,'backup_path'=>$file];
    }
}
