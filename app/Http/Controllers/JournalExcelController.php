<?php
namespace App\Http\Controllers;
use App\Models\Placement;
use App\Support\{Access, JournalWorkbook, JournalDateFilter};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class JournalExcelController
{
    public function __invoke(Request $request, Placement $placement, JournalWorkbook $export)
    {
        $placement=Access::placement($request->user(),$placement->id);
        $filters=$request->validate(JournalDateFilter::rules($request));
        $book=$export->build($placement,$filters['date_from'] ?? null,$filters['date_to'] ?? null);
        $file=tmpfile();
        if ($file===false) {
            $book->disconnectWorksheets();
            throw new \RuntimeException('Penyimpanan sementara Excel tidak tersedia.');
        }
        try {
            // Finish the ZIP before sending success headers: failed exports must not become corrupt downloads.
            (new Xlsx($book))->save(stream_get_meta_data($file)['uri']);
            rewind($file);
        } catch (\Throwable $exception) {
            fclose($file);
            throw $exception;
        } finally {
            $book->disconnectWorksheets();
        }
        return response()->streamDownload(function()use($file){
            try { fpassthru($file); } finally { fclose($file); }
        },'jurnal-pkl-'.$placement->id.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'no-store, private']);
    }
}
