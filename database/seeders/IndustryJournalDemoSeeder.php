<?php
namespace Database\Seeders;
use App\Models\{Company, IndustryMentor, User};
use Illuminate\Database\Seeder;
use RuntimeException;
class IndustryJournalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if(!app()->environment(['local','testing'])) throw new RuntimeException('Demo hanya untuk local/testing.');
        $coordinator=User::where('email','koordinator@simmag.test')->firstOrFail();
        $company=Company::where('name','PT Mitra Teknologi (Demo)')->firstOrFail();
        $mentor=IndustryMentor::firstOrCreate(['name'=>'Dodi (Demo)','company_id'=>$company->id],['created_by'=>$coordinator->id]);
        $ids=\App\Models\Placement::where('company_id',$company->id)
            ->whereHas('roster',fn($q)=>$q->where('notes','like','DATA DEMO FIKTIF:%'))->pluck('id');
        $mentor->placements()->syncWithoutDetaching($ids);
        // Never seed device secrets or pretend that a real industry scan occurred.
    }
}
