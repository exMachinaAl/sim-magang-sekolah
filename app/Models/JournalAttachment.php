<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class JournalAttachment extends Model {
 protected $fillable=['journal_id','path','original_name','mime','size'];
 public function journal(){return $this->belongsTo(Journal::class);}
}
