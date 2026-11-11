<?php

namespace Tests\Feature;

use App\Models\{Placement, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_archives_without_deleting_history_and_can_restore_safely(): void
    {
        $this->seed();
        $admin=User::where('role','admin')->firstOrFail();
        $teacher=User::create([
            'name'=>'Guru Arsip Uji','nip'=>'197801012010011111','email'=>'guru.arsip@simmag.test',
            'password'=>'RahasiaArsip123!','role'=>'guru','is_active'=>true,
        ]);
        $placement=Placement::firstOrFail();
        $placement->update(['teacher_id'=>$teacher->id]);
        $userCount=User::count();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('Guru Arsip Uji')->assertSee('Arsipkan / Sembunyikan');
        $this->put(route('users.archive',$teacher))->assertRedirect(route('users.index'))->assertSessionHas('success');

        $teacher->refresh();
        $this->assertTrue($teacher->isArchived());
        $this->assertFalse($teacher->is_active);
        $this->assertSame($admin->id,$teacher->archived_by);
        $this->assertSame($userCount,User::count());
        $this->assertSame($teacher->id,$placement->fresh()->teacher_id);
        $this->assertDatabaseHas('audit_logs',['user_id'=>$admin->id,'action'=>'user.archived','subject_id'=>$teacher->id]);

        $this->get(route('users.index'))->assertOk()->assertDontSee('guru.arsip@simmag.test');
        $this->get(route('users.index',['view'=>'archived']))->assertOk()
            ->assertSee('guru.arsip@simmag.test')->assertSee('Tersembunyi &amp; nonaktif',false)->assertSee('Pulihkan');
        $this->put(route('users.toggle',$teacher))->assertStatus(422);
        $this->post(route('users.store'),[
            'name'=>'Duplikat Arsip','email'=>'guru.arsip@simmag.test','password'=>'RahasiaArsip123!','role'=>'guru',
        ])->assertSessionHasErrors('email');

        Auth::logout();
        $this->post(route('login.attempt'),['login'=>$teacher->email,'password'=>'RahasiaArsip123!'])
            ->assertSessionHasErrors('login');

        $this->actingAs($admin)->put(route('users.restore',$teacher))->assertRedirect(route('users.index'))->assertSessionHas('success');
        $teacher->refresh();
        $this->assertFalse($teacher->isArchived());
        $this->assertFalse($teacher->is_active);
        $this->assertNull($teacher->archived_by);
        $this->assertDatabaseHas('audit_logs',['user_id'=>$admin->id,'action'=>'user.restored','subject_id'=>$teacher->id]);
        $this->get(route('users.index'))->assertOk()->assertSee('guru.arsip@simmag.test')->assertSee('Nonaktif');

        Auth::logout();
        $this->post(route('login.attempt'),['login'=>$teacher->email,'password'=>'RahasiaArsip123!'])
            ->assertSessionHasErrors('login');
        $this->actingAs($admin)->put(route('users.toggle',$teacher))->assertSessionHasNoErrors();
        Auth::logout();
        $this->post(route('login.attempt'),['login'=>$teacher->email,'password'=>'RahasiaArsip123!'])
            ->assertRedirect(route('dashboard'));
    }

    public function test_archive_permissions_protect_self_and_coordinator_scope(): void
    {
        $this->seed();
        $admin=User::where('role','admin')->firstOrFail();
        $coordinator=User::where('role','koordinator')->firstOrFail();
        $teacher=User::where('role','guru')->firstOrFail();

        $this->actingAs($admin)->put(route('users.archive',$admin))->assertStatus(422);
        $this->actingAs($coordinator)->put(route('users.archive',$teacher))->assertForbidden();
        $this->put(route('users.restore',$teacher))->assertForbidden();
        $this->assertFalse($teacher->fresh()->isArchived());
    }

    public function test_admin_can_apply_account_actions_with_checkboxes_atomically(): void
    {
        $this->seed();
        $admin=User::where('role','admin')->firstOrFail();
        $teachers=User::where('role','guru')->take(2)->get();
        $ids=$teachers->modelKeys();

        $this->actingAs($admin)->get(route('users.index'))->assertOk()
            ->assertSee('id="user-bulk-form"',false)->assertSee('name="user_ids[]"',false)
            ->assertSee('Arsipkan / Sembunyikan');
        $this->put(route('users.bulk'),['action'=>'deactivate','user_ids'=>$ids])->assertSessionHasNoErrors();
        $this->assertSame(0,User::whereIn('id',$ids)->where('is_active',true)->count());
        $this->assertSame(2,\App\Models\AuditLog::where('action','user.bulk_deactivate')->count());

        $this->put(route('users.bulk'),['action'=>'activate','user_ids'=>$ids])->assertSessionHasNoErrors();
        $this->assertSame(2,User::whereIn('id',$ids)->where('is_active',true)->count());
        $this->put(route('users.bulk'),['action'=>'archive','user_ids'=>$ids])->assertSessionHasNoErrors();
        $this->assertSame(2,User::whereIn('id',$ids)->whereNotNull('archived_at')->where('is_active',false)->count());

        $this->get(route('users.index',['view'=>'archived']))->assertOk()
            ->assertSee('Pulihkan sebagai nonaktif')->assertSee('name="user_ids[]"',false);
        $this->put(route('users.bulk'),['action'=>'restore','user_ids'=>$ids])->assertSessionHasNoErrors();
        $this->assertSame(2,User::whereIn('id',$ids)->whereNull('archived_at')->where('is_active',false)->count());

        $this->put(route('users.bulk'),['action'=>'activate','user_ids'=>[$admin->id,$ids[0]]])->assertSessionHasErrors('user_ids');
        $this->assertFalse(User::findOrFail($ids[0])->is_active);
        $coordinator=User::where('role','koordinator')->firstOrFail();
        $this->actingAs($coordinator)->put(route('users.bulk'),['action'=>'activate','user_ids'=>[$ids[0]]])->assertForbidden();
    }
}
