<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CertificateApprovalEvent extends Model
{
    protected $fillable = ['certificate_id', 'approver_id', 'version', 'party', 'decision', 'note'];

    public function approver() { return $this->belongsTo(User::class, 'approver_id'); }
}
