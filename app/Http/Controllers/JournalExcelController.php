<?php
namespace App\Http\Controllers;
use App\Models\Placement;
use App\Support\{Access, JournalWorkbook};
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class JournalExcelController
{
    public function __invoke(Request $request, Placement $placement, JournalWorkbook $export)
    {
        $placement=Access::placement($request->user(),$placement->id);
        $filters=$request->validate(['date_from'=>'nullable|date_format:Y-m-d','date_to'=>'nullable|date_format:Y-m-d|after_or_equal:date_from']);
        $book=$export->build($placement,$filters['date_from'] ?? null,$filters['date_to'] ?? null);
        return response()->streamDownload(function()use($book){
            try { (new Xlsx($book))->save('php://output'); } finally { $book->disconnectWorksheets(); }
        },'jurnal-pkl-'.$placement->id.'.xlsx',['Content-Type'=>'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','Cache-Control'=>'no-store, private']);
    }
}
