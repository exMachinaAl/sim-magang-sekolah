<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEvent extends Model
{
    protected $fillable = ['journal_id', 'actor_id', 'status', 'note'];

    public function journal() { return $this->belongsTo(Journal::class); }
    public function actor() { return $this->belongsTo(User::class, 'actor_id'); }
    public function statusLabel(): string { return Journal::statuses()[$this->status] ?? $this->status; }
}
