<?php

namespace Tests\Feature;

use App\Models\{Company, Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoordinatorTeacherManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_can_create_and_manage_only_teacher_accounts(): void
    {
        $this->seed();
        $coordinator=User::where('email','koordinator@simmag.test')->firstOrFail();
        $admin=User::where('role','admin')->firstOrFail();

        $this->actingAs($coordinator)->get(route('users.index'))
            ->assertOk()->assertSee('Guru Pendamping Sekolah')
            ->assertSee('name="role" value="guru"',false)
            ->assertDontSee($admin->email);

        $this->post(route('users.store'),[
            'name'=>'Guru Pendamping Baru',
            'nip'=>'198001012010011001',
            'email'=>'guru.pendamping.baru@simmag.test',
            'password'=>'RahasiaGuru123!',
            'role'=>'guru',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $teacher=User::where('email','guru.pendamping.baru@simmag.test')->firstOrFail();
        $this->assertSame('guru',$teacher->role);
        $this->assertTrue($teacher->is_active);
        $this->assertDatabaseHas('audit_logs',[
            'user_id'=>$coordinator->id,'action'=>'user.created','subject_id'=>$teacher->id,
        ]);

        $this->post(route('users.store'),[
            'name'=>'Koordinator Tidak Sah','email'=>'koord.tidak.sah@simmag.test',
            'password'=>'RahasiaKoord123!','role'=>'koordinator',
        ])->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users',['email'=>'koord.tidak.sah@simmag.test']);

        $this->put(route('users.identity',$admin),['nip'=>'999'])->assertForbidden();
        $this->put(route('users.toggle',$admin))->assertForbidden();
        $this->put(route('users.identity',$teacher),['nip'=>'198001012010011002'])
            ->assertSessionHasNoErrors();
        $this->put(route('users.toggle',$teacher))->assertSessionHasNoErrors();
        $this->assertFalse($teacher->fresh()->is_active);
    }

    public function test_teacher_can_be_assigned_after_student_has_journals_without_changing_documents(): void
    {
        $this->seed();
        $coordinator=User::where('email','koordinator@simmag.test')->firstOrFail();
        $teacher=User::create([
            'name'=>'Guru Penugasan Baru','nip'=>'197912122009011003',
            'email'=>'guru.penugasan@simmag.test','password'=>'RahasiaGuru123!',
            'role'=>'guru','is_active'=>true,
        ]);
        $placement=Placement::whereHas('journals')->with(['journals','student.user'])->firstOrFail();
        $journalIds=$placement->journals->modelKeys();
        $companyId=$placement->company_id;

        $this->actingAs($coordinator)->get(route('placements.index'))
            ->assertOk()->assertSee('Guru Penugasan Baru')->assertSee('Buat akun guru pendamping');
        $this->put(route('placements.update',$placement),[
            'company_id'=>$companyId,'teacher_id'=>$teacher->id,
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $placement->refresh();
        $this->assertSame($teacher->id,$placement->teacher_id);
        $this->assertSame($companyId,$placement->company_id);
        $this->assertEqualsCanonicalizing($journalIds,$placement->journals()->pluck('id')->all());
        $this->assertDatabaseHas('audit_logs',[
            'user_id'=>$coordinator->id,'action'=>'placement.updated','subject_id'=>$placement->id,
        ]);

        $this->actingAs($placement->student->user)->get(route('timeline.index'))
            ->assertOk()->assertSee('Guru Penugasan Baru');

        $otherCompany=Company::create(['name'=>'Industri Pengganti Tidak Sah']);
        $this->actingAs($coordinator)->put(route('placements.update',$placement),[
            'company_id'=>$otherCompany->id,'teacher_id'=>$teacher->id,
        ])->assertSessionHasErrors('company_id');
        $this->assertSame($companyId,$placement->fresh()->company_id);
    }

    public function test_teacher_cannot_open_teacher_account_management(): void
    {
        $teacher=User::create([
            'name'=>'Guru Biasa','email'=>'guru.biasa@simmag.test',
            'password'=>'RahasiaGuru123!','role'=>'guru','is_active'=>true,
        ]);

        $this->actingAs($teacher)->get(route('users.index'))->assertForbidden();
        $this->post(route('users.store'),[
            'name'=>'Guru Buatan Guru','email'=>'guru.buatan@simmag.test',
            'password'=>'RahasiaGuru123!','role'=>'guru',
        ])->assertForbidden();
    }
}
