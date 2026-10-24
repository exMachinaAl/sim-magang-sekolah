<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class MonitoringUpdate extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['before_state'=>'array','after_state'=>'array']; }
    public function actor(){return $this->belongsTo(User::class,'actor_id');}
    protected static function booted(): void
    {
        static::updating(fn()=>throw new \LogicException('Riwayat monitoring tidak boleh ditimpa.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat monitoring tidak boleh dihapus.'));
    }
}
