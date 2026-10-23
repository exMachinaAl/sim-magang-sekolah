<?php
namespace App\Support;

use App\Models\Placement;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\{IOFactory, Spreadsheet};
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\{PageSetup, Worksheet};

class JournalWorkbook
{
    public function build(Placement $placement, ?string $from=null, ?string $to=null): Spreadsheet
    {
        $journals=$placement->journals()->with('industryValidations')->where('status','!=','draft')
            ->when($from,fn($q)=>$q->whereDate('activity_date','>=',$from))->when($to,fn($q)=>$q->whereDate('activity_date','<=',$to))
            ->orderBy('activity_date')->orderBy('id')->get();
        $source=IOFactory::load(resource_path('templates/jurnal-pkl.xlsx'));
        $template=$source->getSheetByName('Jurnal Cetak');
        $book=new Spreadsheet();
        $book->removeSheetByIndex(0);
        $groups=$journals->groupBy(fn($journal)=>$journal->activity_date->copy()->startOfWeek(Carbon::MONDAY)->toDateString());
        if($groups->isEmpty()) $groups=collect([now()->startOfWeek(Carbon::MONDAY)->toDateString()=>collect()]);
        $firstWeek=$placement->roster?->internship_starts_at?->copy()->startOfWeek(Carbon::MONDAY);
        $page=0; $columns=['B-'=>'G','B'=>'H','B+'=>'I','A-'=>'J','A'=>'K'];
        foreach($groups as $week=>$entries) {
            $weekStart=Carbon::parse($week);
            $number=$firstWeek ? (int)floor($firstWeek->diffInDays($weekStart,false)/7)+1 : 1;
            $chunks=$entries->isEmpty()?collect([collect()]):$entries->chunk(6);
            foreach($chunks as $part=>$chunk) {
                $sheet=clone $template;
                $sheet->setTitle('Jurnal '.(++$page),false);
                $source->addSheet($sheet);
                $book->addExternalSheet($sheet);
                // Only the print template is copied; placeholders are never returned.
                foreach($sheet->getRowIterator() as $row) foreach($row->getCellIterator() as $cell) {
                    if(is_string($cell->getValue()) && str_contains($cell->getValue(),'{{')) $cell->setValueExplicit('',DataType::TYPE_STRING);
                }
                $this->text($sheet,'A4','Nama siswa: '.$placement->student->user->name);
                $this->text($sheet,'D4','NIS: '.$placement->student->nis);
                $this->text($sheet,'H4',$entries->isEmpty()?'Minggu: — (tidak ada data)':'Minggu: '.$number.' · lembar '.($part+1).'/'.$chunks->count());
                $this->text($sheet,'A5','Industri: '.$placement->company->name);
                $this->text($sheet,'G5',$entries->isEmpty()?'Filter: '.($from ?? 'awal').' – '.($to ?? 'akhir'):'Periode: '.$weekStart->format('d/m/Y').' – '.$weekStart->copy()->addDays(6)->format('d/m/Y'));
                foreach($chunk->values() as $i=>$journal) {
                    $row=9+$i*2;
                    $this->text($sheet,'B'.$row,$journal->activity_date->locale('id')->translatedFormat('l, d/m/Y')."\n".($journal->work_area ?? ''));
                    $this->text($sheet,'C'.$row,$journal->starts_time ?? '');
                    $this->text($sheet,'D'.$row,$journal->ends_time ?? '');
                    $this->text($sheet,'E'.$row,$journal->activity);
                    $category=$journal->scoreCategory();
                    $notes=$journal->equipment_notes ?? '';
                    if($category==='Di bawah 75') $notes.="\nNilai ".$journal->industry_score.' (di bawah rentang kolom template).';
                    $this->text($sheet,'F'.$row,$notes);
                    if(isset($columns[$category ?? ''])) $sheet->setCellValueExplicit($columns[$category].$row,(float)$journal->industry_score,DataType::TYPE_NUMERIC);
                    $validation=$journal->industryValidations->firstWhere('revision',$journal->revision);
                    $this->text($sheet,'L'.$row,$validation
                        ? 'QR: '.$validation->snapshot['mentor_name']."\n".$validation->scanned_at->format('d/m/Y H:i')
                        : 'Belum divalidasi industri');
                    // Keep the six-entry structure, expand space for longer notes.
                    $height=max(27,min(140,ceil(max(mb_strlen($journal->activity)/48,mb_strlen($notes)/28))*7));
                    $sheet->getRowDimension($row)->setRowHeight($height);
                    $sheet->getRowDimension($row+1)->setRowHeight($height);
                }
                $this->text($sheet,'A21','Total skor / minggu (seluruh lembar, filter terpilih)');
                foreach($columns as $category=>$column) $sheet->setCellValueExplicit($column.'21',
                    round($entries->filter(fn($j)=>$j->scoreCategory()===$category)->sum('industry_score'),2),DataType::TYPE_NUMERIC);
                $this->text($sheet,'A23','Nilai dicatat siswa dari industri; QR mencatat konfirmasi, bukan gambar tanda tangan. Tanpa QR = belum divalidasi. Foto tidak dicetak. Total termasuk nilai belum divalidasi.');
                $sheet->getStyle('A4:L23')->getAlignment()->setWrapText(true);
                $sheet->getRowDimension(23)->setRowHeight(32);
                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)->setFitToHeight(1)->setPrintArea('A1:L23');
            }
        }
        $data=new Worksheet($book,'Data Laravel'); $book->addSheet($data);
        $headers=['tanggal','ruang_kerja','waktu_mulai','waktu_selesai','kegiatan','keterangan','skor_angka','predikat','validasi_industri','pembimbing','waktu_scan','versi','review_sekolah'];
        $data->fromArray($headers);
        foreach($journals->values() as $i=>$journal) {
            $validation=$journal->industryValidations->firstWhere('revision',$journal->revision);
            $values=[$journal->activity_date->toDateString(),$journal->work_area,$journal->starts_time,$journal->ends_time,
                $journal->activity,$journal->equipment_notes,$journal->industry_score,$journal->scoreCategory(),
                $validation?'Tervalidasi':'Belum divalidasi',$validation?->snapshot['mentor_name'] ?? '',
                $validation?->scanned_at->format('Y-m-d H:i:s') ?? '',$journal->revision,
                $journal->reviewed_revision===$journal->revision?'Ditinjau':'Belum ditinjau'];
            foreach($values as $c=>$value) $data->setCellValueExplicit([$c+1,$i+2],$value ?? '',
                $c===6 && $value!==null ? DataType::TYPE_NUMERIC : DataType::TYPE_STRING);
        }
        $data->freezePane('A2'); $data->setAutoFilter($data->calculateWorksheetDimension());
        $book->setActiveSheetIndex(0); $source->disconnectWorksheets();
        return $book;
    }

    private function text(Worksheet $sheet,string $cell,string $value): void
    {
        // User-authored text must never become spreadsheet formulae.
        $sheet->setCellValueExplicit($cell,$value,DataType::TYPE_STRING);
    }
}
